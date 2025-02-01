<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/auth_middleware.php';

// Ensure user is admin
requireAdmin();

// Set JSON content type
header('Content-Type: application/json');

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['report_id']) || !isset($input['action'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

$report_id = $input['report_id'];
$action = $input['action'];
$admin_id = $_SESSION['user_id'];

try {
    $conn->begin_transaction();

    // Get report details first
    $stmt = $conn->prepare("SELECT * FROM reports WHERE id = ?");
    $stmt->bind_param("i", $report_id);
    $stmt->execute();
    $report = $stmt->get_result()->fetch_assoc();

    if (!$report) {
        throw new Exception("Report not found");
    }

    switch ($action) {
        case 'resolve':
            // Update report status
            $stmt = $conn->prepare("
                UPDATE reports 
                SET status = 'resolved', 
                    resolved_by = ?, 
                    resolved_at = CURRENT_TIMESTAMP 
                WHERE id = ?
            ");
            $stmt->bind_param("ii", $admin_id, $report_id);
            $stmt->execute();
            break;

        case 'dismiss':
            // Update report status
            $stmt = $conn->prepare("
                UPDATE reports 
                SET status = 'dismissed', 
                    resolved_by = ?, 
                    resolved_at = CURRENT_TIMESTAMP 
                WHERE id = ?
            ");
            $stmt->bind_param("ii", $admin_id, $report_id);
            $stmt->execute();
            break;

        default:
            throw new Exception("Invalid action");
    }

    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Report handled successfully']);

} catch (Exception $e) {
    $conn->rollback();
    error_log("Error handling report: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}