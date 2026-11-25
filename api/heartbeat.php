<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');

// Re-validate against database
$user = getCurrentUser(true);

if (!$user || !isLoggedIn()) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'code' => 'UNAUTHENTICATED',
        'message' => 'Session expired or account no longer active. Please log in again.'
    ]);
    exit;
}

$sessionLogId = $user['session_log_id'];

if ($sessionLogId) {
    $stmt = $conn->prepare("UPDATE user_sessions SET last_seen_at = NOW() WHERE id = ? AND logout_at IS NULL");
    $stmt->bind_param("i", $sessionLogId);
    $stmt->execute();
}

// Lazy cleanup: close any abandoned sessions inactive for > 10 minutes using their last_seen_at
$conn->query("
    UPDATE user_sessions 
    SET logout_at = last_seen_at 
    WHERE logout_at IS NULL AND last_seen_at < (NOW() - INTERVAL 10 MINUTE)
");

// Check if user has an open duty record (if attendance table exists)
$isOnDuty = false;
$checkDuty = @$conn->prepare("SELECT id FROM attendance WHERE user_type = ? AND user_id = ? AND check_out_at IS NULL LIMIT 1");
if ($checkDuty) {
    $checkDuty->bind_param("si", $user['user_type'], $user['user_id']);
    $checkDuty->execute();
    if ($checkDuty->get_result()->fetch_assoc()) {
        $isOnDuty = true;
    }
}

sendJsonResponse([
    'success' => true,
    'timestamp' => date('Y-m-d H:i:s'),
    'user_id' => $user['user_id'],
    'role' => $user['role'],
    'user_type' => $user['user_type'],
    'is_on_duty' => $isOnDuty
]);
?>
