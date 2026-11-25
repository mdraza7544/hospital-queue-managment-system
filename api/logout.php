<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';

$sessionLogId = $_SESSION['session_log_id'] ?? null;

if ($sessionLogId && isset($conn)) {
    $stmt = $conn->prepare("UPDATE user_sessions SET logout_at = NOW(), last_seen_at = NOW() WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $sessionLogId);
        $stmt->execute();
    }
}

$_SESSION = [];
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
session_destroy();

// Redirect to login page
header('Location: /STI_Queueing-System/staff-login');
exit;
?>