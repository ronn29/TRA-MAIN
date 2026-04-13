<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

require_once '../db/dbconn.php';
require_once __DIR__ . '/includes/consultation_functions.php';

ensureConsultationTables($conn);
purgePastSchedules($conn);

$action = $_POST['action'] ?? $_GET['action'] ?? null;

function jsonResponse($data, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($data);
    exit;
}

switch ($action) {
    case 'get_availability':
        $start = $_GET['start_date'] ?? date('Y-m-d');
        $end = $_GET['end_date'] ?? date('Y-m-d', strtotime('+30 days'));
        $rows = getAvailabilityWindow($conn, $start, $end);
        jsonResponse(['data' => $rows]);
        break;

    case 'save_availability':
        $date = $_POST['date'] ?? '';
        $limit = isset($_POST['daily_limit']) ? (int)$_POST['daily_limit'] : 0;
        $morning = isset($_POST['morning_enabled']) ? (bool)$_POST['morning_enabled'] : false;
        $afternoon = isset($_POST['afternoon_enabled']) ? (bool)$_POST['afternoon_enabled'] : false;

        if (!$date) {
            jsonResponse(['error' => 'Date is required'], 400);
        }

        $ok = upsertAvailability($conn, $date, $morning, $afternoon, $limit);
        if ($ok) {
            $updated = getAvailabilityWindow($conn, $date, $date);
            jsonResponse(['success' => true, 'data' => $updated[0] ?? null]);
        }
        jsonResponse(['error' => 'Failed to save availability'], 500);
        break;

case 'get_bookings':
case 'get_schedule':
    $start = $_GET['start_date'] ?? date('Y-m-d');
    $end = $_GET['end_date'] ?? date('Y-m-d', strtotime('+30 days'));
    $rows = getBookingsByDate($conn, $start, $end);
    jsonResponse(['data' => $rows]);
    break;

case 'update_status':
case 'update_schedule_status':
        $bookingId = isset($_POST['booking_id']) ? intval($_POST['booking_id']) : 0;
        $status = $_POST['status'] ?? '';
        if (!$bookingId || !in_array($status, ['completed', 'cancelled', 'booked'], true)) {
            jsonResponse(['error' => 'Invalid request'], 400);
        }
        $stmt = mysqli_prepare($conn, "UPDATE consultation_bookings SET status = ? WHERE booking_id = ?");
        mysqli_stmt_bind_param($stmt, "si", $status, $bookingId);
        if (mysqli_stmt_execute($stmt)) {
            jsonResponse(['success' => true]);
        } else {
            jsonResponse(['error' => 'Failed to update status'], 500);
        }
        break;

    default:
        jsonResponse(['error' => 'Unknown action'], 400);
}

