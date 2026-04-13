<?php

function ensureConsultationTables($conn): void
{
    $availabilitySql = "
        CREATE TABLE IF NOT EXISTS consultation_availability (
            id INT AUTO_INCREMENT PRIMARY KEY,
            available_date DATE NOT NULL UNIQUE,
            morning_enabled TINYINT(1) NOT NULL DEFAULT 1,
            afternoon_enabled TINYINT(1) NOT NULL DEFAULT 1,
            daily_limit INT NOT NULL DEFAULT 50,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB";

    $bookingSql = "
        CREATE TABLE IF NOT EXISTS consultation_bookings (
            booking_id INT AUTO_INCREMENT PRIMARY KEY,
            student_school_id VARCHAR(50) NOT NULL,
            student_user_id INT NULL,
            assessment_id INT NULL,
            scheduled_date DATE NOT NULL,
            session ENUM('morning','afternoon') NOT NULL,
            status ENUM('booked','cancelled','completed') NOT NULL DEFAULT 'booked',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_date (scheduled_date),
            INDEX idx_student (student_school_id),
            CONSTRAINT fk_consult_booking_user FOREIGN KEY (student_user_id) REFERENCES user_tbl(user_id) ON DELETE SET NULL
        ) ENGINE=InnoDB";

    mysqli_query($conn, $availabilitySql);
    mysqli_query($conn, $bookingSql);

    mysqli_query(
        $conn,
        "ALTER TABLE consultation_bookings 
            MODIFY status ENUM('booked','cancelled','completed') NOT NULL DEFAULT 'booked'"
    );
}

function purgePastSchedules($conn): void
{
    $today = date('Y-m-d');
    mysqli_query($conn, "DELETE FROM consultation_bookings WHERE scheduled_date < '$today'");
}

function upsertAvailability($conn, string $date, bool $morning, bool $afternoon, int $limit): bool
{
    $sql = "
        INSERT INTO consultation_availability (available_date, morning_enabled, afternoon_enabled, daily_limit)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            morning_enabled = VALUES(morning_enabled),
            afternoon_enabled = VALUES(afternoon_enabled),
            daily_limit = VALUES(daily_limit),
            updated_at = CURRENT_TIMESTAMP";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "siii", $date, $morning, $afternoon, $limit);
    return mysqli_stmt_execute($stmt);
}

function getAvailabilityWindow($conn, string $startDate, string $endDate): array
{
    $sql = "
        SELECT
            a.available_date,
            a.morning_enabled,
            a.afternoon_enabled,
            a.daily_limit,
            COALESCE(SUM(CASE WHEN b.status = 'booked' THEN 1 ELSE 0 END), 0) AS total_booked,
            COALESCE(SUM(CASE WHEN b.status = 'booked' AND b.session = 'morning' THEN 1 ELSE 0 END), 0) AS morning_booked,
            COALESCE(SUM(CASE WHEN b.status = 'booked' AND b.session = 'afternoon' THEN 1 ELSE 0 END), 0) AS afternoon_booked
        FROM consultation_availability a
        LEFT JOIN consultation_bookings b
            ON a.available_date = b.scheduled_date
        WHERE a.available_date BETWEEN ? AND ?
        GROUP BY a.available_date, a.morning_enabled, a.afternoon_enabled, a.daily_limit
        ORDER BY a.available_date ASC";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "ss", $startDate, $endDate);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    return mysqli_fetch_all($result, MYSQLI_ASSOC);
}

function getBookingsByDate($conn, string $startDate, string $endDate): array
{
    $sql = "
        SELECT
            b.booking_id,
            b.student_school_id,
            b.assessment_id,
            b.scheduled_date,
            b.session,
            b.status,
            b.created_at,
            s.first_name,
            s.last_name,
            s.program_id,
            p.program_name
        FROM consultation_bookings b
        LEFT JOIN student_tbl s ON b.student_school_id = s.school_id
        LEFT JOIN program_tbl p ON s.program_id = p.program_id
        WHERE b.scheduled_date BETWEEN ? AND ?
        ORDER BY b.scheduled_date ASC, b.session ASC, b.created_at ASC";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "ss", $startDate, $endDate);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    return mysqli_fetch_all($result, MYSQLI_ASSOC);
}

function findNextAvailableDate($conn, string $session, string $startDate, int $daysLookahead = 60): ?string
{
    $session = ($session === 'afternoon') ? 'afternoon' : 'morning';
    for ($i = 0; $i <= $daysLookahead; $i++) {
        $candidate = date('Y-m-d', strtotime($startDate . " +{$i} day"));

        $availSql = "
            SELECT available_date, morning_enabled, afternoon_enabled, daily_limit
            FROM consultation_availability
            WHERE available_date = ?";
        $availStmt = mysqli_prepare($conn, $availSql);
        mysqli_stmt_bind_param($availStmt, "s", $candidate);
        mysqli_stmt_execute($availStmt);
        $availRes = mysqli_stmt_get_result($availStmt);
        $availability = mysqli_fetch_assoc($availRes);

        if (!$availability) {
            continue;
        }

        if ($session === 'morning' && !$availability['morning_enabled']) {
            continue;
        }
        if ($session === 'afternoon' && !$availability['afternoon_enabled']) {
            continue;
        }

        $countSql = "
            SELECT COUNT(*) AS total
            FROM consultation_bookings
            WHERE scheduled_date = ? AND status = 'booked'";
        $countStmt = mysqli_prepare($conn, $countSql);
        mysqli_stmt_bind_param($countStmt, "s", $candidate);
        mysqli_stmt_execute($countStmt);
        $countRes = mysqli_stmt_get_result($countStmt);
        $count = mysqli_fetch_assoc($countRes)['total'] ?? 0;

        $limit = (int)$availability['daily_limit'];
        if ($limit <= 0 || $count < $limit) {
            return $candidate;
        }
    }

    return null;
}

function bookConsultationSlot($conn, string $studentSchoolId, ?int $studentUserId, ?int $assessmentId, string $session): array
{
    ensureConsultationTables($conn);
    $session = ($session === 'afternoon') ? 'afternoon' : 'morning';
    $today = date('Y-m-d');

    $completedSql = "
        SELECT booking_id, scheduled_date, session
        FROM consultation_bookings
        WHERE student_school_id = ?
          AND status = 'completed'
        LIMIT 1";
    $completedStmt = mysqli_prepare($conn, $completedSql);
    mysqli_stmt_bind_param($completedStmt, "s", $studentSchoolId);
    mysqli_stmt_execute($completedStmt);
    $completedRes = mysqli_stmt_get_result($completedStmt);
    if ($completed = mysqli_fetch_assoc($completedRes)) {
        return [
            'success' => false,
            'message' => "You already completed a consultation on {$completed['scheduled_date']} ({$completed['session']}).",
            'scheduled_date' => $completed['scheduled_date'],
            'session' => $completed['session']
        ];
    }

    $dupSql = "
        SELECT booking_id, scheduled_date, session
        FROM consultation_bookings
        WHERE student_school_id = ? AND status = 'booked' AND scheduled_date >= ?
        ORDER BY scheduled_date ASC
        LIMIT 1";
    $dupStmt = mysqli_prepare($conn, $dupSql);
    mysqli_stmt_bind_param($dupStmt, "ss", $studentSchoolId, $today);
    mysqli_stmt_execute($dupStmt);
    $dupRes = mysqli_stmt_get_result($dupStmt);
    if ($existing = mysqli_fetch_assoc($dupRes)) {
        return [
            'success' => false,
            'message' => "You already have a consultation booked on {$existing['scheduled_date']} ({$existing['session']}).",
            'scheduled_date' => $existing['scheduled_date'],
            'session' => $existing['session']
        ];
    }

    $lookahead = 60;
    for ($i = 0; $i <= $lookahead; $i++) {
        $candidate = date('Y-m-d', strtotime($today . " +{$i} day"));

        mysqli_begin_transaction($conn);

        $lockSql = "
            SELECT available_date, morning_enabled, afternoon_enabled, daily_limit
            FROM consultation_availability
            WHERE available_date = ?
            FOR UPDATE";
        $lockStmt = mysqli_prepare($conn, $lockSql);
        mysqli_stmt_bind_param($lockStmt, "s", $candidate);
        mysqli_stmt_execute($lockStmt);
        $lockRes = mysqli_stmt_get_result($lockStmt);
        $availability = mysqli_fetch_assoc($lockRes);

        if (!$availability) {
            mysqli_rollback($conn);
            continue;
        }

        $sessionEnabled = ($session === 'morning')
            ? (bool)$availability['morning_enabled']
            : (bool)$availability['afternoon_enabled'];

        if (!$sessionEnabled || (int)$availability['daily_limit'] <= 0) {
            mysqli_rollback($conn);
            continue;
        }

        $countSql = "
            SELECT COUNT(*) AS total
            FROM consultation_bookings
            WHERE scheduled_date = ? AND status = 'booked'
            FOR UPDATE";
        $countStmt = mysqli_prepare($conn, $countSql);
        mysqli_stmt_bind_param($countStmt, "s", $candidate);
        mysqli_stmt_execute($countStmt);
        $countRes = mysqli_stmt_get_result($countStmt);
        $count = mysqli_fetch_assoc($countRes)['total'] ?? 0;

        $limit = (int)$availability['daily_limit'];
        if ($limit > 0 && $count >= $limit) {
            mysqli_rollback($conn);
            continue;
        }

        $insertSql = "
            INSERT INTO consultation_bookings
                (student_school_id, student_user_id, assessment_id, scheduled_date, session, status)
            VALUES (?, ?, ?, ?, ?, 'booked')";
        $insertStmt = mysqli_prepare($conn, $insertSql);
        mysqli_stmt_bind_param(
            $insertStmt,
            "siiss",
            $studentSchoolId,
            $studentUserId,
            $assessmentId,
            $candidate,
            $session
        );

        if (mysqli_stmt_execute($insertStmt)) {
            mysqli_commit($conn);
            return [
                'success' => true,
                'scheduled_date' => $candidate,
                'session' => $session,
                'message' => ($count + 1) . " / {$availability['daily_limit']} slots booked for {$candidate}."
            ];
        }

        mysqli_rollback($conn);
    }

    return [
        'success' => false,
        'message' => 'No available consultation slots in the next 60 days. Please try again later.'
    ];
}
