<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');

// Role enforcement: Admin only
$admin = requireRole(['admin'], true);

// Parameter normalization (support both camelCase and snake_case)
$tab = $_GET['tab'] ?? 'duty'; // 'duty' or 'sessions'
$startDate = $_GET['startDate'] ?? ($_GET['start_date'] ?? null);
$endDate = $_GET['endDate'] ?? ($_GET['end_date'] ?? null);
$role = $_GET['role'] ?? ($_GET['user_type'] ?? 'all');
$deptId = isset($_GET['department_id']) && $_GET['department_id'] !== '' ? intval($_GET['department_id']) : null;
$userId = isset($_GET['user_id']) && $_GET['user_id'] !== '' ? intval($_GET['user_id']) : null;
$status = $_GET['status'] ?? 'all'; // 'all', 'on_duty', 'completed', 'auto_closed'
$search = isset($_GET['search']) ? trim($_GET['search']) : null;
$page = max(1, intval($_GET['page'] ?? 1));
$limit = min(100, max(1, intval($_GET['limit'] ?? 25)));
$offset = ($page - 1) * $limit;

function formatDur($sec) {
    if ($sec < 0) $sec = 0;
    $hrs = floor($sec / 3600);
    $mins = floor(($sec % 3600) / 60);
    $secs = $sec % 60;
    return sprintf('%02dh %02dm %02ds', $hrs, $mins, $secs);
}

if ($tab === 'sessions') {
    // -------------------------------------------------------------
    // SUB-TAB 2: Login Sessions (user_sessions table)
    // -------------------------------------------------------------
    $query = "
        SELECT 
            us.id,
            us.user_type,
            us.user_id,
            us.login_at,
            us.logout_at,
            us.last_seen_at,
            us.ip_address,
            TIMESTAMPDIFF(SECOND, us.login_at, COALESCE(us.logout_at, NOW())) AS duration_seconds,
            CASE WHEN us.user_type = 'doctor' THEN doc.full_name ELSE st.full_name END AS full_name,
            CASE WHEN us.user_type = 'doctor' THEN doc.username ELSE st.username END AS username,
            CASE WHEN us.user_type = 'doctor' THEN doc.role ELSE st.role END AS user_role,
            CASE WHEN us.user_type = 'doctor' THEN dd.name ELSE sd.name END AS department_name,
            CASE WHEN us.user_type = 'doctor' THEN doc.department_id ELSE st.department_id END AS department_id,
            CASE WHEN us.logout_at IS NULL AND us.last_seen_at >= (NOW() - INTERVAL 90 SECOND) THEN 1 ELSE 0 END AS is_online
        FROM user_sessions us
        LEFT JOIN (SELECT id, full_name, username, 'doctor' AS role, department_id FROM doctors) doc ON us.user_type = 'doctor' AND us.user_id = doc.id
        LEFT JOIN departments dd ON doc.department_id = dd.id
        LEFT JOIN staff st ON us.user_type = 'staff' AND us.user_id = st.id
        LEFT JOIN departments sd ON st.department_id = sd.id
        WHERE 1=1
    ";

    $params = [];
    $types = "";

    if (!empty($role) && $role !== 'all') {
        if ($role === 'doctor') {
            $query .= " AND us.user_type = 'doctor'";
        } elseif ($role === 'staff') {
            $query .= " AND us.user_type = 'staff' AND (st.role = 'staff' OR st.role IS NULL)";
        } elseif ($role === 'admin') {
            $query .= " AND us.user_type = 'staff' AND st.role = 'admin'";
        }
    }

    if (!empty($startDate)) {
        $query .= " AND DATE(us.login_at) >= ?";
        $params[] = $startDate;
        $types .= "s";
    }

    if (!empty($endDate)) {
        $query .= " AND DATE(us.login_at) <= ?";
        $params[] = $endDate;
        $types .= "s";
    }

    if ($deptId) {
        $query .= " AND (dd.id = ? OR sd.id = ?)";
        $params[] = $deptId;
        $params[] = $deptId;
        $types .= "ii";
    }

    if ($search) {
        $query .= " AND (doc.full_name LIKE ? OR doc.username LIKE ? OR st.full_name LIKE ? OR st.username LIKE ?)";
        $sTerm = "%$search%";
        $params[] = $sTerm;
        $params[] = $sTerm;
        $params[] = $sTerm;
        $params[] = $sTerm;
        $types .= "ssss";
    }

    // Count
    $countStmt = $conn->prepare("SELECT COUNT(*) AS total FROM ($query) AS t");
    if (!empty($params)) $countStmt->bind_param($types, ...$params);
    $countStmt->execute();
    $total = intval($countStmt->get_result()->fetch_assoc()['total'] ?? 0);

    // Paginated
    $query .= " ORDER BY us.id DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    $types .= "ii";

    $stmt = $conn->prepare($query);
    if (!empty($params)) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();

    $records = [];
    while ($row = $res->fetch_assoc()) {
        $durSec = intval($row['duration_seconds']);
        $records[] = [
            'id' => intval($row['id']),
            'user_type' => $row['user_type'],
            'role' => $row['user_role'] ?? $row['user_type'],
            'user_id' => intval($row['user_id']),
            'full_name' => $row['full_name'] ?? 'N/A',
            'username' => $row['username'] ?? 'N/A',
            'department_name' => $row['department_name'] ?? 'N/A',
            'login_at' => $row['login_at'],
            'logout_at' => $row['logout_at'],
            'duration_formatted' => empty($row['logout_at']) ? 'Active Session' : formatDur($durSec),
            'is_online' => intval($row['is_online']) === 1,
            'status' => empty($row['logout_at']) ? 'Active Session' : 'Logged Out'
        ];
    }

    sendJsonResponse([
        'success' => true,
        'tab' => 'sessions',
        'records' => $records,
        'logs' => $records,
        'total' => $total,
        'page' => $page,
        'limit' => $limit
    ]);

} else {
    // -------------------------------------------------------------
    // SUB-TAB 1: Primary Duty Attendance (attendance table)
    // -------------------------------------------------------------
    $query = "
        SELECT 
            a.id,
            a.user_type,
            a.user_id,
            a.work_date,
            a.check_in_at,
            a.check_out_at,
            a.auto_closed,
            TIMESTAMPDIFF(SECOND, a.check_in_at, COALESCE(a.check_out_at, NOW())) AS duration_seconds,
            CASE WHEN a.user_type = 'doctor' THEN doc.full_name ELSE st.full_name END AS full_name,
            CASE WHEN a.user_type = 'doctor' THEN doc.username ELSE st.username END AS username,
            CASE WHEN a.user_type = 'doctor' THEN 'doctor' ELSE st.role END AS user_role,
            CASE WHEN a.user_type = 'doctor' THEN dd.name ELSE sd.name END AS department_name,
            CASE WHEN a.user_type = 'doctor' THEN dd.id ELSE sd.id END AS department_id
        FROM attendance a
        LEFT JOIN doctors doc ON a.user_type = 'doctor' AND a.user_id = doc.id
        LEFT JOIN departments dd ON doc.department_id = dd.id
        LEFT JOIN staff st ON a.user_type = 'staff' AND a.user_id = st.id
        LEFT JOIN departments sd ON st.department_id = sd.id
        WHERE 1=1
    ";

    $params = [];
    $types = "";

    if (!empty($role) && $role !== 'all') {
        if ($role === 'doctor') {
            $query .= " AND a.user_type = 'doctor'";
        } elseif ($role === 'staff') {
            $query .= " AND a.user_type = 'staff' AND (st.role = 'staff' OR st.role IS NULL)";
        }
    }

    if (!empty($startDate)) {
        $query .= " AND a.work_date >= ?";
        $params[] = $startDate;
        $types .= "s";
    }

    if (!empty($endDate)) {
        $query .= " AND a.work_date <= ?";
        $params[] = $endDate;
        $types .= "s";
    }

    if ($deptId) {
        $query .= " AND (dd.id = ? OR sd.id = ?)";
        $params[] = $deptId;
        $params[] = $deptId;
        $types .= "ii";
    }

    if ($userId) {
        $query .= " AND a.user_id = ?";
        $params[] = $userId;
        $types .= "i";
    }

    if (!empty($status) && $status !== 'all') {
        if ($status === 'on_duty') {
            $query .= " AND a.check_out_at IS NULL";
        } elseif ($status === 'completed') {
            $query .= " AND a.check_out_at IS NOT NULL AND a.auto_closed = 0";
        } elseif ($status === 'auto_closed') {
            $query .= " AND a.auto_closed = 1";
        }
    }

    if ($search) {
        $query .= " AND (doc.full_name LIKE ? OR doc.username LIKE ? OR st.full_name LIKE ? OR st.username LIKE ? OR dd.name LIKE ? OR sd.name LIKE ?)";
        $sTerm = "%$search%";
        $params[] = $sTerm;
        $params[] = $sTerm;
        $params[] = $sTerm;
        $params[] = $sTerm;
        $params[] = $sTerm;
        $params[] = $sTerm;
        $types .= "ssssss";
    }

    // Count
    $countStmt = $conn->prepare("SELECT COUNT(*) AS total FROM ($query) AS t");
    if (!empty($params)) $countStmt->bind_param($types, ...$params);
    $countStmt->execute();
    $total = intval($countStmt->get_result()->fetch_assoc()['total'] ?? 0);

    // Paginated
    $query .= " ORDER BY a.id DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    $types .= "ii";

    $stmt = $conn->prepare($query);
    if (!empty($params)) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();

    $records = [];
    while ($row = $res->fetch_assoc()) {
        $durSec = intval($row['duration_seconds']);
        $isOpen = empty($row['check_out_at']);
        $statusText = $isOpen ? 'On Duty' : ($row['auto_closed'] ? 'Auto-Closed' : 'Completed');

        $records[] = [
            'id' => intval($row['id']),
            'user_type' => $row['user_type'],
            'role' => $row['user_role'] ?? $row['user_type'],
            'user_id' => intval($row['user_id']),
            'full_name' => $row['full_name'] ?? 'N/A',
            'username' => $row['username'] ?? 'N/A',
            'department_name' => $row['department_name'] ?? 'N/A',
            'work_date' => $row['work_date'],
            'check_in_at' => $row['check_in_at'],
            'check_out_at' => $row['check_out_at'],
            'duration_formatted' => formatDur($durSec),
            'auto_closed' => intval($row['auto_closed']) === 1,
            'is_on_duty' => $isOpen,
            'status' => $statusText
        ];
    }

    sendJsonResponse([
        'success' => true,
        'tab' => 'duty',
        'records' => $records,
        'logs' => $records,
        'total' => $total,
        'page' => $page,
        'limit' => $limit
    ]);
}
?>
