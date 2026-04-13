<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'student') {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

require_once '../db/dbconn.php';
require_once __DIR__ . '/../admin/includes/consultation_functions.php';

ensureConsultationTables($conn);

$schoolId = $_SESSION['school_id'];
$today = date('Y-m-d');

$activeSql = "
    SELECT booking_id, scheduled_date, session
    FROM consultation_bookings
    WHERE student_school_id = ? AND status = 'booked' AND scheduled_date >= ?
    ORDER BY scheduled_date ASC
    LIMIT 1";
$activeStmt = mysqli_prepare($conn, $activeSql);
mysqli_stmt_bind_param($activeStmt, "ss", $schoolId, $today);
mysqli_stmt_execute($activeStmt);
$activeRes = mysqli_stmt_get_result($activeStmt);
$active = mysqli_fetch_assoc($activeRes) ?: null;

$options = [];
foreach (['morning', 'afternoon'] as $slot) {
    $nextDate = findNextAvailableDate($conn, $slot, $today, 60);
    $options[$slot] = [
        'next_available_date' => $nextDate,
        'enabled' => $nextDate !== null
    ];
}

echo json_encode([
    'active_booking' => $active,
    'options' => $options
]);

