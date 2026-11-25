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

// Transaction with row locking to prevent double check-in
$conn->begin_transaction();

try {
    // 1. Auto-close any forgotten check-in from prior days first
    $autoCloseStmt = $conn->prepare("
        UPDATE attendance 
        SET auto_closed = 1, 
            check_out_at = COALESCE(
                (SELECT last_seen_at FROM user_sessions WHERE user_type = ? AND user_id = ? AND DATE(login_at) = attendance.work_date ORDER BY id DESC LIMIT 1),
                CONCAT(work_date, ' 18:00:00')
            )
        WHERE user_type = ? AND user_id = ? AND work_date < CURDATE() AND check_out_at IS NULL
    ");
    $autoCloseStmt->bind_param("sisi", $userType, $userId, $userType, $userId);
    $autoCloseStmt->execute();

    // 2. Check if user already has an open check-in
    $checkStmt = $conn->prepare("
        SELECT id, check_in_at 
        FROM attendance 
        WHERE user_type = ? AND user_id = ? AND check_out_at IS NULL 
        LIMIT 1 FOR UPDATE
    ");
    $checkStmt->bind_param("si", $userType, $userId);
    $checkStmt->execute();
    $existing = $checkStmt->get_result()->fetch_assoc();

    if ($existing) {
        $conn->rollback();
        sendJsonResponse([
            'success' => false,
            'message' => 'You are already On Duty (checked in at ' . date('h:i A', strtotime($existing['check_in_at'])) . ').'
        ], 409);
    }

    // 3. Insert new duty attendance record
    $insertStmt = $conn->prepare("
        INSERT INTO attendance (user_type, user_id, work_date, check_in_at) 
        VALUES (?, ?, CURDATE(), NOW())
    ");
    $insertStmt->bind_param("si", $userType, $userId);
    $insertStmt->execute();
    $attendanceId = $conn->insert_id;

    $conn->commit();

    sendJsonResponse([
        'success' => true,
        'message' => 'Checked in successfully. You are now marked On Duty.',
        'attendance_id' => $attendanceId,
        'check_in_at' => date('Y-m-d H:i:s'),
        'server_now' => date('Y-m-d H:i:s'),
        'is_on_duty' => true,
        'attendance' => [
            'id' => $attendanceId,
            'status' => 'on_duty',
            'check_in_at' => date('Y-m-d H:i:s')
        ]
    ]);

} catch (Exception $e) {
    $conn->rollback();
    sendJsonResponse([
        'success' => false,
        'message' => 'Failed to record check-in: ' . $e->getMessage()
    ], 500);
}
?>
