<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');

// Role enforcement: Admin only
$admin = requireRole(['admin'], true);

$method = $_SERVER['REQUEST_METHOD'];

// GET: Retrieve staff members
if ($method === 'GET') {
    $deptFilter = isset($_GET['department_id']) && $_GET['department_id'] !== '' ? intval($_GET['department_id']) : null;
    $search = isset($_GET['search']) ? trim($_GET['search']) : null;
    $status = isset($_GET['status']) ? trim($_GET['status']) : null;

    $query = "
        SELECT 
            s.id, 
            s.username, 
            s.full_name, 
            s.role, 
            s.is_active, 
            s.department_id, 
            s.last_login_at, 
            s.created_at,
            d.name AS department_name
        FROM staff s
        LEFT JOIN departments d ON s.department_id = d.id
        WHERE s.is_deleted = 0
    ";

    $params = [];
    $types = "";

    if ($deptFilter) {
        $query .= " AND s.department_id = ?";
        $params[] = $deptFilter;
        $types .= "i";
    }

    if ($status === 'active') {
        $query .= " AND s.is_active = 1";
    } elseif ($status === 'inactive') {
        $query .= " AND s.is_active = 0";
    }

    if ($search) {
        $query .= " AND (s.username LIKE ? OR s.full_name LIKE ? OR d.name LIKE ?)";
        $searchPattern = "%{$search}%";
        $params[] = $searchPattern;
        $params[] = $searchPattern;
        $params[] = $searchPattern;
        $types .= "sss";
    }

    $query .= " ORDER BY s.role ASC, s.full_name ASC";

    $stmt = $conn->prepare($query);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    $staffList = [];
    while ($row = $result->fetch_assoc()) {
        $staffList[] = [
            'id' => intval($row['id']),
            'username' => $row['username'],
            'full_name' => $row['full_name'],
            'role' => $row['role'],
            'is_active' => intval($row['is_active']),
            'department_id' => intval($row['department_id']),
            'department_name' => $row['department_name'] ?? 'General',
            'last_login_at' => $row['last_login_at'],
            'created_at' => $row['created_at']
        ];
    }

    sendJsonResponse(['success' => true, 'staff' => $staffList, 'total' => count($staffList)]);
}

// POST: Create, Edit, or Toggle Staff
if ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $action = trim($data['action'] ?? 'create');

    // 1. CREATE STAFF
    if ($action === 'create') {
        $fullName = trim($data['full_name'] ?? '');
        $username = strtolower(trim($data['username'] ?? ''));
        $password = $data['password'] ?? '';
        $role = trim($data['role'] ?? 'staff');
        $departmentId = isset($data['department_id']) ? intval($data['department_id']) : 0;

        if (empty($fullName) || empty($username) || empty($password) || $departmentId <= 0) {
            sendJsonResponse(['success' => false, 'message' => 'All required fields must be filled'], 400);
        }

        if (!in_array($role, ['admin', 'staff'], true)) {
            $role = 'staff';
        }

        if (strlen($password) < 8) {
            sendJsonResponse(['success' => false, 'message' => 'Password must be at least 8 characters long'], 400);
        }

        if (preg_match('/\s/', $username)) {
            sendJsonResponse(['success' => false, 'message' => 'Username cannot contain whitespace'], 400);
        }

        // Check uniqueness in staff and doctors
        $checkStaff = $conn->prepare("SELECT id FROM staff WHERE username = ?");
        $checkStaff->bind_param("s", $username);
        $checkStaff->execute();
        if ($checkStaff->get_result()->fetch_assoc()) {
            sendJsonResponse(['success' => false, 'message' => 'Username is already taken by a staff member'], 400);
        }

        $checkDoc = $conn->prepare("SELECT id FROM doctors WHERE username = ?");
        $checkDoc->bind_param("s", $username);
        $checkDoc->execute();
        if ($checkDoc->get_result()->fetch_assoc()) {
            sendJsonResponse(['success' => false, 'message' => 'Username is already in use by a doctor'], 400);
        }

        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

        $stmt = $conn->prepare("
            INSERT INTO staff (full_name, username, password, role, is_active, department_id) 
            VALUES (?, ?, ?, ?, 1, ?)
        ");
        $stmt->bind_param("ssssi", $fullName, $username, $hashedPassword, $role, $departmentId);

        if ($stmt->execute()) {
            sendJsonResponse(['success' => true, 'staff_id' => $conn->insert_id, 'message' => 'Staff account created successfully']);
        } else {
            sendJsonResponse(['success' => false, 'message' => 'Failed to create staff account: ' . $conn->error], 500);
        }
    }

    // 2. EDIT STAFF
    elseif ($action === 'edit') {
        $id = isset($data['id']) ? intval($data['id']) : 0;
        $fullName = trim($data['full_name'] ?? '');
        $username = strtolower(trim($data['username'] ?? ''));
        $role = trim($data['role'] ?? 'staff');
        $departmentId = isset($data['department_id']) ? intval($data['department_id']) : 0;
        $newPassword = $data['new_password'] ?? ($data['password'] ?? '');

        if ($id <= 0 || empty($fullName) || $departmentId <= 0) {
            sendJsonResponse(['success' => false, 'message' => 'All required fields must be filled'], 400);
        }

        if (empty($username)) {
            $existingUserStmt = $conn->prepare("SELECT username FROM staff WHERE id = ?");
            $existingUserStmt->bind_param("i", $id);
            $existingUserStmt->execute();
            $existingRow = $existingUserStmt->get_result()->fetch_assoc();
            if ($existingRow) $username = $existingRow['username'];
        }

        if (empty($username)) {
            sendJsonResponse(['success' => false, 'message' => 'Username is required'], 400);
        }

        if (!in_array($role, ['admin', 'staff'], true)) {
            $role = 'staff';
        }

        // Check username uniqueness
        $checkStaff = $conn->prepare("SELECT id FROM staff WHERE username = ? AND id != ?");
        $checkStaff->bind_param("si", $username, $id);
        $checkStaff->execute();
        if ($checkStaff->get_result()->fetch_assoc()) {
            sendJsonResponse(['success' => false, 'message' => 'Username is already in use'], 400);
        }

        $checkDoc = $conn->prepare("SELECT id FROM doctors WHERE username = ?");
        $checkDoc->bind_param("s", $username);
        $checkDoc->execute();
        if ($checkDoc->get_result()->fetch_assoc()) {
            sendJsonResponse(['success' => false, 'message' => 'Username is already in use by a doctor'], 400);
        }

        if (!empty($newPassword)) {
            if (strlen($newPassword) < 8) {
                sendJsonResponse(['success' => false, 'message' => 'Password must be at least 8 characters long'], 400);
            }
            $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);
            $stmt = $conn->prepare("
                UPDATE staff 
                SET full_name = ?, username = ?, password = ?, role = ?, department_id = ?
                WHERE id = ?
            ");
            $stmt->bind_param("ssssii", $fullName, $username, $hashedPassword, $role, $departmentId, $id);
        } else {
            $stmt = $conn->prepare("
                UPDATE staff 
                SET full_name = ?, username = ?, role = ?, department_id = ?
                WHERE id = ?
            ");
            $stmt->bind_param("sssii", $fullName, $username, $role, $departmentId, $id);
        }

        if ($stmt->execute()) {
            sendJsonResponse(['success' => true, 'message' => 'Staff profile updated successfully']);
        } else {
            sendJsonResponse(['success' => false, 'message' => 'Failed to update staff account: ' . $conn->error], 500);
        }
    }

    // 3. TOGGLE ACTIVE / DEACTIVATE
    elseif ($action === 'toggle_active') {
        $id = isset($data['id']) ? intval($data['id']) : 0;
        $isActive = !empty($data['is_active']) ? 1 : 0;

        if ($id <= 0) {
            sendJsonResponse(['success' => false, 'message' => 'Valid staff ID required'], 400);
        }

        // Prevent self-deactivation by current admin
        if ($id === $admin['user_id']) {
            sendJsonResponse(['success' => false, 'message' => 'Cannot deactivate your own administrator account'], 400);
        }

        $stmt = $conn->prepare("UPDATE staff SET is_active = ? WHERE id = ?");
        $stmt->bind_param("ii", $isActive, $id);

        if ($stmt->execute()) {
            $statusText = $isActive ? 'activated' : 'deactivated';
            sendJsonResponse(['success' => true, 'message' => "Staff member successfully {$statusText}"]);
        } else {
            sendJsonResponse(['success' => false, 'message' => 'Failed to update staff status: ' . $conn->error], 500);
        }
    }

    // 4. DELETE STAFF (ADMIN ONLY)
    elseif ($action === 'delete') {
        $id = isset($data['id']) ? intval($data['id']) : 0;
        if ($id <= 0) {
            sendJsonResponse(['success' => false, 'message' => 'Valid staff ID required'], 400);
        }

        // Prevent self-deletion
        if ($id === $admin['user_id']) {
            sendJsonResponse(['success' => false, 'message' => 'Cannot delete your own administrator account'], 400);
        }

        $targetStmt = $conn->prepare("SELECT id, username, role FROM staff WHERE id = ? AND is_deleted = 0");
        $targetStmt->bind_param("i", $id);
        $targetStmt->execute();
        $targetStaff = $targetStmt->get_result()->fetch_assoc();

        if (!$targetStaff) {
            sendJsonResponse(['success' => false, 'message' => 'Staff member not found or already deleted'], 404);
        }

        // Prevent deleting the last remaining admin
        if ($targetStaff['role'] === 'admin') {
            $adminCountRes = $conn->query("SELECT COUNT(*) AS active_admins FROM staff WHERE role = 'admin' AND is_active = 1 AND is_deleted = 0");
            $activeAdmins = intval($adminCountRes->fetch_assoc()['active_admins'] ?? 0);
            if ($activeAdmins <= 1) {
                sendJsonResponse(['success' => false, 'message' => 'Cannot delete the last remaining active administrator account'], 400);
            }
        }

        $conn->begin_transaction();
        try {
            $delStmt = $conn->prepare("UPDATE staff SET is_deleted = 1, is_active = 0, deleted_at = NOW() WHERE id = ?");
            $delStmt->bind_param("i", $id);
            $delStmt->execute();

            $termSessions = $conn->prepare("UPDATE user_sessions SET logout_at = NOW() WHERE user_type = 'staff' AND user_id = ? AND logout_at IS NULL");
            $termSessions->bind_param("i", $id);
            $termSessions->execute();

            $closeAtt = $conn->prepare("UPDATE attendance SET check_out_at = NOW(), auto_closed = 1 WHERE user_type = 'staff' AND user_id = ? AND check_out_at IS NULL");
            $closeAtt->bind_param("i", $id);
            $closeAtt->execute();

            $conn->commit();
            sendJsonResponse(['success' => true, 'message' => "Staff member @{$targetStaff['username']} successfully deleted"]);
        } catch (Exception $e) {
            $conn->rollback();
            sendJsonResponse(['success' => false, 'message' => 'Failed to delete staff member: ' . $e->getMessage()], 500);
        }
    }

    sendJsonResponse(['success' => false, 'message' => 'Invalid action specified'], 400);
}

sendJsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
?>