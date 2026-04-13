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

$session = $_POST['session'] ?? '';
$assessmentId = isset($_POST['assessment_id']) ? intval($_POST['assessment_id']) : null;

if (!in_array($session, ['morning', 'afternoon'], true)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid session']);
    exit;
}

$studentSchoolId = $_SESSION['school_id'];
$studentUserId = $_SESSION['user_id'] ?? null;

$result = bookConsultationSlot($conn, $studentSchoolId, $studentUserId, $assessmentId, $session);

if ($result['success'] ?? false) {
    echo json_encode($result);
} else {
    http_response_code(400);
    echo json_encode($result);
}

