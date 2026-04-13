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

$start = $_GET['start_date'] ?? date('Y-m-d');
$end = $_GET['end_date'] ?? date('Y-m-d', strtotime('+30 days'));

$rows = getAvailabilityWindow($conn, $start, $end);

echo json_encode(['data' => $rows]);

