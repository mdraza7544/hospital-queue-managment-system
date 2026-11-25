<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');

// Role enforcement: Admin only
$admin = requireRole(['admin'], true);

$method = $_SERVER['REQUEST_METHOD'];

// GET: Retrieve doctor list
if ($method === 'GET') {
    $deptFilter = isset($_GET['department_id']) && $_GET['department_id'] !== '' ? intval($_GET['department_id']) : null;
    $search = isset($_GET['search']) ? trim($_GET['search']) : null;
    $status = isset($_GET['status']) ? trim($_GET['status']) : null; // 'active', 'inactive', 'all'

    $query = "
        SELECT 
            doc.id, 
            doc.full_name, 
            doc.specialization, 
            doc.username, 
            doc.room_label, 
            doc.department_id, 
            doc.is_active, 
            doc.last_login_at,
            doc.created_at,
            d.name AS department_name
        FROM doctors doc
        LEFT JOIN departments d ON doc.department_id = d.id
        WHERE doc.is_deleted = 0
    ";

    $params = [];
    $types = "";

    if ($deptFilter) {
        $query .= " AND doc.department_id = ?";
        $params[] = $deptFilter;
        $types .= "i";
    }

    if ($status === 'active') {
        $query .= " AND doc.is_active = 1";
    } elseif ($status === 'inactive') {
        $query .= " AND doc.is_active = 0";
    }

    if ($search) {
        $query .= " AND (doc.full_name LIKE ? OR doc.username LIKE ? OR doc.specialization LIKE ? OR doc.room_label LIKE ?)";
        $searchPattern = "%{$search}%";
        $params[] = $searchPattern;
        $params[] = $searchPattern;
        $params[] = $searchPattern;
        $params[] = $searchPattern;
        $types .= "ssss";
    }

    $query .= " ORDER BY doc.full_name ASC";

    $stmt = $conn->prepare($query);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    $doctors = [];
    while ($row = $result->fetch_assoc()) {
        $doctors[] = [
            'id' => intval($row['id']),
            'full_name' => $row['full_name'],
            'specialization' => $row['specialization'],
            'username' => $row['username'],
            'room_label' => $row['room_label'] ?? '',
            'department_id' => intval($row['department_id']),
            'department_name' => $row['department_name'] ?? 'N/A',
            'is_active' => intval($row['is_active']),
            'last_login_at' => $row['last_login_at'],
            'created_at' => $row['created_at']
        ];
    }

    sendJsonResponse(['success' => true, 'doctors' => $doctors, 'total' => count($doctors)]);
}

// POST: Create, Edit, or Toggle Doctor
if ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $action = trim($data['action'] ?? 'create');

    // 1. CREATE DOCTOR
    if ($action === 'create') {
        $fullName = trim($data['full_name'] ?? '');
        $username = strtolower(trim($data['username'] ?? ''));
        $password = $data['password'] ?? '';
        $specialization = trim($data['specialization'] ?? '');
        $departmentId = isset($data['department_id']) ? intval($data['department_id']) : 0;
        $roomLabel = trim($data['room_label'] ?? '');

        if (empty($fullName) || empty($username) || empty($password) || empty($specialization) || $departmentId <= 0) {
            sendJsonResponse(['success' => false, 'message' => 'All required fields must be filled'], 400);
        }

        if (strlen($password) < 8) {
            sendJsonResponse(['success' => false, 'message' => 'Password must be at least 8 characters long'], 400);
        }

        if (preg_match('/\s/', $username)) {
            sendJsonResponse(['success' => false, 'message' => 'Username cannot contain whitespace'], 400);
        }

        // Check uniqueness in doctors and staff
        $checkDoc = $conn->prepare("SELECT id FROM doctors WHERE username = ?");
        $checkDoc->bind_param("s", $username);
        $checkDoc->execute();
        if ($checkDoc->get_result()->fetch_assoc()) {
            sendJsonResponse(['success' => false, 'message' => 'Username is already taken by another doctor'], 400);
        }

        $checkStaff = $conn->prepare("SELECT id FROM staff WHERE username = ?");
        $checkStaff->bind_param("s", $username);
        $checkStaff->execute();
        if ($checkStaff->get_result()->fetch_assoc()) {
            sendJsonResponse(['success' => false, 'message' => 'Username is already in use by a staff member'], 400);
        }

        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

        $stmt = $conn->prepare("
            INSERT INTO doctors (full_name, specialization, username, password, room_label, department_id, is_active) 
            VALUES (?, ?, ?, ?, ?, ?, 1)
        ");
        $stmt->bind_param("sssssi", $fullName, $specialization, $username, $hashedPassword, $roomLabel, $departmentId);

        if ($stmt->execute()) {
            sendJsonResponse(['success' => true, 'doctor_id' => $conn->insert_id, 'message' => 'Doctor account created successfully']);
        } else {
            sendJsonResponse(['success' => false, 'message' => 'Failed to create doctor: ' . $conn->error], 500);
        }
    }

    // 2. EDIT DOCTOR
    elseif ($action === 'edit') {
        $id = isset($data['id']) ? intval($data['id']) : 0;
        $fullName = trim($data['full_name'] ?? '');
        $username = strtolower(trim($data['username'] ?? ''));
        $specialization = trim($data['specialization'] ?? '');
        $departmentId = isset($data['department_id']) ? intval($data['department_id']) : 0;
        $roomLabel = trim($data['room_label'] ?? '');
        $newPassword = $data['new_password'] ?? '';

        if ($id <= 0 || empty($fullName) || empty($username) || empty($specialization) || $departmentId <= 0) {
            sendJsonResponse(['success' => false, 'message' => 'All required fields must be filled'], 400);
        }

        // Check username uniqueness
        $checkDoc = $conn->prepare("SELECT id FROM doctors WHERE username = ? AND id != ?");
        $checkDoc->bind_param("si", $username, $id);
        $checkDoc->execute();
        if ($checkDoc->get_result()->fetch_assoc()) {
            sendJsonResponse(['success' => false, 'message' => 'Username is already in use'], 400);
        }

        $checkStaff = $conn->prepare("SELECT id FROM staff WHERE username = ?");
        $checkStaff->bind_param("s", $username);
        $checkStaff->execute();
        if ($checkStaff->get_result()->fetch_assoc()) {
            sendJsonResponse(['success' => false, 'message' => 'Username is already in use by a staff member'], 400);
        }

        if (!empty($newPassword)) {
            if (strlen($newPassword) < 8) {
                sendJsonResponse(['success' => false, 'message' => 'Password must be at least 8 characters long'], 400);
            }
            $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);
            $stmt = $conn->prepare("
                UPDATE doctors 
                SET full_name = ?, specialization = ?, username = ?, password = ?, room_label = ?, department_id = ?
                WHERE id = ?
            ");
            $stmt->bind_param("sssssii", $fullName, $specialization, $username, $hashedPassword, $roomLabel, $departmentId, $id);
        } else {
            $stmt = $conn->prepare("
                UPDATE doctors 
                SET full_name = ?, specialization = ?, username = ?, room_label = ?, department_id = ?
                WHERE id = ?
            ");
            $stmt->bind_param("ssssii", $fullName, $specialization, $username, $roomLabel, $departmentId, $id);
        }

        if ($stmt->execute()) {
            sendJsonResponse(['success' => true, 'message' => 'Doctor profile updated successfully']);
        } else {
            sendJsonResponse(['success' => false, 'message' => 'Failed to update doctor: ' . $conn->error], 500);
        }
    }

    // 3. TOGGLE ACTIVE / DEACTIVATE
    elseif ($action === 'toggle_active') {
        $id = isset($data['id']) ? intval($data['id']) : 0;
        $isActive = !empty($data['is_active']) ? 1 : 0;

        if ($id <= 0) {
            sendJsonResponse(['success' => false, 'message' => 'Valid doctor ID required'], 400);
        }

        $stmt = $conn->prepare("UPDATE doctors SET is_active = ? WHERE id = ?");
        $stmt->bind_param("ii", $isActive, $id);

        if ($stmt->execute()) {
            $statusText = $isActive ? 'activated' : 'deactivated';
            sendJsonResponse(['success' => true, 'message' => "Doctor successfully {$statusText}"]);
        } else {
            sendJsonResponse(['success' => false, 'message' => 'Failed to update doctor status: ' . $conn->error], 500);
        }
    }

    // 4. DELETE DOCTOR (ADMIN ONLY)
    elseif ($action === 'delete') {
        $id = isset($data['id']) ? intval($data['id']) : 0;
        if ($id <= 0) {
            sendJsonResponse(['success' => false, 'message' => 'Valid doctor ID required'], 400);
        }

        $docStmt = $conn->prepare("SELECT id, full_name, username FROM doctors WHERE id = ? AND is_deleted = 0");
        $docStmt->bind_param("i", $id);
        $docStmt->execute();
        $targetDoc = $docStmt->get_result()->fetch_assoc();

        if (!$targetDoc) {
            sendJsonResponse(['success' => false, 'message' => 'Doctor not found or already deleted'], 404);
        }

        // Safety rule: Doctor with waiting or serving tickets CANNOT be deleted
        $ticketCheck = $conn->prepare("
            SELECT COUNT(*) AS active_count 
            FROM queue_tickets 
            WHERE doctor_id = ? AND status IN ('waiting', 'serving')
        ");
        $ticketCheck->bind_param("i", $id);
        $ticketCheck->execute();
        $activeTickets = intval($ticketCheck->get_result()->fetch_assoc()['active_count'] ?? 0);

        if ($activeTickets > 0) {
            sendJsonResponse([
                'success' => false,
                'message' => "Cannot delete Dr. {$targetDoc['full_name']}: Doctor currently has {$activeTickets} active/waiting patient(s) in queue. Please resolve or reassign tickets first."
            ], 400);
        }

        $conn->begin_transaction();
        try {
            $delStmt = $conn->prepare("UPDATE doctors SET is_deleted = 1, is_active = 0, deleted_at = NOW() WHERE id = ?");
            $delStmt->bind_param("i", $id);
            $delStmt->execute();

            $termSessions = $conn->prepare("UPDATE user_sessions SET logout_at = NOW() WHERE user_type = 'doctor' AND user_id = ? AND logout_at IS NULL");
            $termSessions->bind_param("i", $id);
            $termSessions->execute();

            $closeAtt = $conn->prepare("UPDATE attendance SET check_out_at = NOW(), auto_closed = 1 WHERE user_type = 'doctor' AND user_id = ? AND check_out_at IS NULL");
            $closeAtt->bind_param("i", $id);
            $closeAtt->execute();

            $conn->commit();
            sendJsonResponse(['success' => true, 'message' => "Doctor Dr. {$targetDoc['full_name']} successfully deleted"]);
        } catch (Exception $e) {
            $conn->rollback();
            sendJsonResponse(['success' => false, 'message' => 'Failed to delete doctor: ' . $e->getMessage()], 500);
        }
    }

    sendJsonResponse(['success' => false, 'message' => 'Invalid action specified'], 400);
}

sendJsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
?>