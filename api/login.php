<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);

    $username = trim($data['username'] ?? '');
    $password = $data['password'] ?? '';

    if ($username === '' || $password === '') {
        sendJsonResponse(['success' => false, 'message' => 'Username and password are required'], 400);
    }

    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

    // 1. Check in staff table (for admin and staff roles)
    $stmt = $conn->prepare("
        SELECT s.id, s.username, s.password, s.full_name, s.role, s.is_active, s.department_id, d.name AS department_name
        FROM staff s
        LEFT JOIN departments d ON s.department_id = d.id
        WHERE s.username = ? AND s.is_deleted = 0
    ");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $staffResult = $stmt->get_result();

    if ($user = $staffResult->fetch_assoc()) {
        if (intval($user['is_active']) !== 1) {
            sendJsonResponse(['success' => false, 'message' => 'Account has been deactivated. Please contact administrator.'], 401);
        }

        $isValid = password_verify($password, $user['password']);

        // Default seed fallback if password needs rehash
        if (!$isValid && $user['username'] === 'admin' && $password === 'admin123') {
            $newHash = password_hash($password, PASSWORD_DEFAULT);
            $update = $conn->prepare("UPDATE staff SET password = ? WHERE id = ?");
            $update->bind_param("si", $newHash, $user['id']);
            $update->execute();
            $isValid = true;
        }

        if ($isValid) {
            session_regenerate_id(true);

            // Update last login
            $updateLogin = $conn->prepare("UPDATE staff SET last_login_at = NOW() WHERE id = ?");
            $updateLogin->bind_param("i", $user['id']);
            $updateLogin->execute();

            // Insert into user_sessions (Attendance)
            $sessionStmt = $conn->prepare("
                INSERT INTO user_sessions (user_type, user_id, login_at, last_seen_at, ip_address) 
                VALUES ('staff', ?, NOW(), NOW(), ?)
            ");
            $sessionStmt->bind_param("is", $user['id'], $ipAddress);
            $sessionStmt->execute();
            $sessionLogId = $conn->insert_id;

            // Set session variables
            $_SESSION['user_type'] = 'staff';
            $_SESSION['user_id'] = intval($user['id']);
            $_SESSION['staff_id'] = intval($user['id']);
            $_SESSION['username'] = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['staff_name'] = $user['full_name'];
            $_SESSION['role'] = $user['role']; // 'admin' or 'staff'
            $_SESSION['department_id'] = $user['department_id'] ? intval($user['department_id']) : null;
            $_SESSION['department_name'] = $user['department_name'] ?? '';
            $_SESSION['session_log_id'] = $sessionLogId;

            $redirectUrl = ($user['role'] === 'admin') 
                ? '/STI_Queueing-System/admin-dashboard' 
                : '/STI_Queueing-System/staff-dashboard';

            echo json_encode([
                'success' => true,
                'role' => $user['role'],
                'redirect' => $redirectUrl,
                'user' => [
                    'id' => $user['id'],
                    'name' => $user['full_name'],
                    'role' => $user['role'],
                    'department' => $user['department_name']
                ]
            ]);
            exit;
        } else {
            sendJsonResponse(['success' => false, 'message' => 'Invalid username or password'], 401);
        }
    }

    // 2. Check in doctors table
    $docStmt = $conn->prepare("
        SELECT doc.id, doc.full_name, doc.specialization, doc.username, doc.password, doc.room_label, doc.department_id, doc.is_active, d.name AS department_name
        FROM doctors doc
        LEFT JOIN departments d ON doc.department_id = d.id
        WHERE doc.username = ? AND doc.is_deleted = 0
    ");
    $docStmt->bind_param("s", $username);
    $docStmt->execute();
    $docResult = $docStmt->get_result();

    if ($doctor = $docResult->fetch_assoc()) {
        if (intval($doctor['is_active']) !== 1) {
            sendJsonResponse(['success' => false, 'message' => 'Doctor account is deactivated. Please contact administrator.'], 401);
        }

        if (empty($doctor['password']) || !password_verify($password, $doctor['password'])) {
            sendJsonResponse(['success' => false, 'message' => 'Invalid username or password'], 401);
        }

        session_regenerate_id(true);

        // Update last login
        $updateLogin = $conn->prepare("UPDATE doctors SET last_login_at = NOW() WHERE id = ?");
        $updateLogin->bind_param("i", $doctor['id']);
        $updateLogin->execute();

        // Insert into user_sessions
        $sessionStmt = $conn->prepare("
            INSERT INTO user_sessions (user_type, user_id, login_at, last_seen_at, ip_address) 
            VALUES ('doctor', ?, NOW(), NOW(), ?)
        ");
        $sessionStmt->bind_param("is", $doctor['id'], $ipAddress);
        $sessionStmt->execute();
        $sessionLogId = $conn->insert_id;

        // Set session
        $_SESSION['user_type'] = 'doctor';
        $_SESSION['user_id'] = intval($doctor['id']);
        $_SESSION['doctor_id'] = intval($doctor['id']);
        $_SESSION['username'] = $doctor['username'];
        $_SESSION['full_name'] = $doctor['full_name'];
        $_SESSION['doctor_name'] = $doctor['full_name'];
        $_SESSION['role'] = 'doctor';
        $_SESSION['department_id'] = intval($doctor['department_id']);
        $_SESSION['department_name'] = $doctor['department_name'] ?? '';
        $_SESSION['room_label'] = $doctor['room_label'] ?? '';
        $_SESSION['session_log_id'] = $sessionLogId;

        $redirectUrl = '/STI_Queueing-System/doctor-dashboard';

        echo json_encode([
            'success' => true,
            'role' => 'doctor',
            'redirect' => $redirectUrl,
            'user' => [
                'id' => $doctor['id'],
                'name' => $doctor['full_name'],
                'role' => 'doctor',
                'specialization' => $doctor['specialization'],
                'department' => $doctor['department_name'],
                'room_label' => $doctor['room_label']
            ]
        ]);
        exit;
    }

    sendJsonResponse(['success' => false, 'message' => 'Invalid username or password'], 401);

} else {
    sendJsonResponse(['success' => false, 'message' => 'Invalid request method'], 405);
}
?>