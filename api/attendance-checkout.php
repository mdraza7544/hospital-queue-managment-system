<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');

// Only staff and doctor can record duty attendance
$user = requireRole(['staff', 'doctor'], true);
$userType = $user['user_type'];
$userId = $user['user_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse(['success' => false, 'message' => 'Invalid request method'], 405);
}

$conn->begin_transaction();

try {
    // Find active open attendance record
    $stmt = $conn->prepare("
        SELECT id, check_in_at 
        FROM attendance 
        WHERE user_type = ? AND user_id = ? AND check_out_at IS NULL 
        ORDER BY id DESC LIMIT 1 FOR UPDATE
    ");
    $stmt->bind_param("si", $userType, $userId);
    $stmt->execute();
    $active = $stmt->get_result()->fetch_assoc();

    if (!$active) {
        $conn->rollback();
        sendJsonResponse([
            'success' => false,
            'message' => 'You are not currently checked in / not On Duty.'
        ], 400);
    }

    $attendanceId = $active['id'];
    $checkInTime = strtotime($active['check_in_at']);
    $now = time();
    $durationSeconds = max(0, $now - $checkInTime);

    $update = $conn->prepare("UPDATE attendance SET check_out_at = NOW() WHERE id = ?");
    $update->bind_param("i", $attendanceId);
    $update->execute();

    $conn->commit();

    $hrs = floor($durationSeconds / 3600);
    $mins = floor(($durationSeconds % 3600) / 60);

    $durationFormatted = sprintf('%02dh %02dm', $hrs, $mins);

    sendJsonResponse([
        'success' => true,
        'message' => "Checked out successfully. Shift ended (Duty duration: {$hrs}h {$mins}m).",
        'check_out_at' => date('Y-m-d H:i:s'),
        'duration_seconds' => $durationSeconds,
        'duration_formatted' => $durationFormatted,
        'server_now' => date('Y-m-d H:i:s'),
        'is_on_duty' => false,
        'attendance' => [
            'id' => $attendanceId,
            'status' => 'completed',
            'duration_formatted' => $durationFormatted
        ]
    ]);

} catch (Exception $e) {
    $conn->rollback();
    sendJsonResponse([
        'success' => false,
        'message' => 'Failed to record check-out: ' . $e->getMessage()
    ], 500);
}
?>
