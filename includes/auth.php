<?php
// Centralized Role-Based Authentication & Session Helper
require_once __DIR__ . '/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Enforce no-cache on dynamic and authenticated endpoints
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

/**
 * Get current authenticated user details from session with optional DB re-validation
 * @param bool $validateWithDb Re-verifies user still exists and is active in database
 */
function getCurrentUser($validateWithDb = false) {
    if (!isset($_SESSION['user_id']) && !isset($_SESSION['staff_id']) && !isset($_SESSION['doctor_id'])) {
        return null;
    }

    $userType = $_SESSION['user_type'] ?? 'staff';
    $userId = intval($_SESSION['user_id'] ?? ($_SESSION['staff_id'] ?? ($_SESSION['doctor_id'] ?? 0)));

    if ($userId <= 0) {
        return null;
    }

    // Re-validate against database if requested
    if ($validateWithDb) {
        global $conn;
        if ($conn && !$conn->connect_error) {
            if ($userType === 'doctor') {
                $check = $conn->prepare("SELECT id, is_active FROM doctors WHERE id = ?");
                $check->bind_param("i", $userId);
                $check->execute();
                $row = $check->get_result()->fetch_assoc();
                if (!$row || intval($row['is_active']) !== 1) {
                    session_unset();
                    return null;
                }
            } else {
                $check = $conn->prepare("SELECT id, is_active, role FROM staff WHERE id = ?");
                $check->bind_param("i", $userId);
                $check->execute();
                $row = $check->get_result()->fetch_assoc();
                if (!$row || intval($row['is_active']) !== 1) {
                    session_unset();
                    return null;
                }
                // Keep session role in sync with DB
                $_SESSION['role'] = $row['role'];
            }
        }
    }

    return [
        'user_type' => $userType,
        'user_id' => $userId,
        'username' => $_SESSION['username'] ?? '',
        'full_name' => $_SESSION['full_name'] ?? ($_SESSION['staff_name'] ?? ($_SESSION['doctor_name'] ?? '')),
        'role' => $_SESSION['role'] ?? 'staff',
        'department_id' => isset($_SESSION['department_id']) ? intval($_SESSION['department_id']) : null,
        'department_name' => $_SESSION['department_name'] ?? '',
        'room_label' => $_SESSION['room_label'] ?? '',
        'session_log_id' => isset($_SESSION['session_log_id']) ? intval($_SESSION['session_log_id']) : null
    ];
}

/**
 * Check if a user is logged in
 */
function isLoggedIn($validateWithDb = false) {
    $user = getCurrentUser($validateWithDb);
    return $user !== null && $user['user_id'] > 0;
}

/**
 * Check if the authenticated user has one of the allowed roles
 * @param array|string $allowedRoles e.g. ['admin'], ['staff'], ['doctor']
 */
function hasRole($allowedRoles) {
    $user = getCurrentUser();
    if (!$user) {
        return false;
    }

    if (is_string($allowedRoles)) {
        $allowedRoles = [$allowedRoles];
    }

    return in_array($user['role'], $allowedRoles, true);
}

/**
 * Enforce role authorization with strict 401 (unauthenticated) vs 403 (unauthorized role)
 * @param array|string $allowedRoles
 * @param bool $isApi If true, outputs JSON error; if false, redirects to appropriate page
 */
function requireRole($allowedRoles, $isApi = true) {
    if (is_string($allowedRoles)) {
        $allowedRoles = [$allowedRoles];
    }

    // Always re-validate active user against database
    $user = getCurrentUser(true);

    // 1. Not authenticated -> 401 Unauthorized
    if (!$user || !isLoggedIn()) {
        if ($isApi) {
            header('Content-Type: application/json');
            http_response_code(401);
            echo json_encode([
                'success' => false,
                'code' => 'UNAUTHENTICATED',
                'message' => 'Session expired or not logged in. Please log in again.'
            ]);
            exit;
        } else {
            header('Location: /STI_Queueing-System/staff-login');
            exit;
        }
    }

    // 2. Authenticated but wrong role -> 403 Forbidden
    if (!hasRole($allowedRoles)) {
        if ($isApi) {
            header('Content-Type: application/json');
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'code' => 'FORBIDDEN_ROLE',
                'message' => 'Access denied: Your account role does not have permission for this action.',
                'current_role' => $user['role']
            ]);
            exit;
        } else {
            // Redirect web page to the user's correct dashboard
            $targetUrl = '/STI_Queueing-System/staff-login';
            if ($user['role'] === 'admin') {
                $targetUrl = '/STI_Queueing-System/admin-dashboard';
            } elseif ($user['role'] === 'doctor') {
                $targetUrl = '/STI_Queueing-System/doctor-dashboard';
            } elseif ($user['role'] === 'staff') {
                $targetUrl = '/STI_Queueing-System/staff-dashboard';
            }
            header("Location: $targetUrl");
            exit;
        }
    }

    return $user;
}

/**
 * Standardized JSON response helper
 */
function sendJsonResponse($data, $statusCode = 200) {
    header('Content-Type: application/json');
    http_response_code($statusCode);
    echo json_encode($data);
    exit;
}
?>
