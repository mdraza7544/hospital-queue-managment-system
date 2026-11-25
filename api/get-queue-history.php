<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');

// Role enforcement: Admin or Doctor only (Staff view counts via get-staff-stats.php)
$user = requireRole(['admin', 'doctor'], true);
$role = $user['role'];

// Filter parameters
$startDate = $_GET['start_date'] ?? null;
$endDate = $_GET['end_date'] ?? null;
$status = $_GET['status'] ?? null;
$searchQuery = isset($_GET['search']) ? trim($_GET['search']) : null;
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 50;
$offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;

$query = "
    SELECT 
        qt.id,
        qt.ticket_number,
        qt.patient_name,
        qt.appointment_number,
        qt.age,
        qt.gender,
        qt.weight_kg,
        qt.bp,
        qt.department_id,
        qt.doctor_id,
        qt.status,
        qt.created_at AS date_issued,
        qt.called_at AS time_called,
        qt.completed_at AS time_completed,
        qt.notes,
        qt.referral_notes,
        d.name AS department,
        d.window_number,
        s.full_name AS staff_name,
        doc.full_name AS doctor_name,
        doc.room_label,
        rd.name AS referred_to_department,
        rdoc.full_name AS referred_to_doctor
    FROM queue_tickets qt
    LEFT JOIN departments d ON qt.department_id = d.id
    LEFT JOIN staff s ON qt.staff_id = s.id
    LEFT JOIN doctors doc ON qt.doctor_id = doc.id
    LEFT JOIN departments rd ON qt.referred_to_department_id = rd.id
    LEFT JOIN doctors rdoc ON qt.referred_to_doctor_id = rdoc.id
    WHERE 1=1
";

$params = [];
$types = "";

// Role-based data scoping
if ($role === 'doctor') {
    // Doctors only view tickets assigned to themselves
    $query .= " AND qt.doctor_id = ?";
    $params[] = $user['user_id'];
    $types .= "i";
} elseif ($role === 'admin') {
    // Admin can optionally filter by department and doctor
    $deptFilter = isset($_GET['department_id']) && $_GET['department_id'] !== '' ? intval($_GET['department_id']) : null;
    $docFilter = isset($_GET['doctor_id']) && $_GET['doctor_id'] !== '' ? intval($_GET['doctor_id']) : null;

    if ($deptFilter) {
        $query .= " AND qt.department_id = ?";
        $params[] = $deptFilter;
        $types .= "i";
    }

    if ($docFilter) {
        $query .= " AND qt.doctor_id = ?";
        $params[] = $docFilter;
        $types .= "i";
    }
}

// Date filters
if (!empty($startDate)) {
    $query .= " AND DATE(qt.created_at) >= ?";
    $params[] = $startDate;
    $types .= "s";
}

if (!empty($endDate)) {
    $query .= " AND DATE(qt.created_at) <= ?";
    $params[] = $endDate;
    $types .= "s";
}

// Status filter
if (!empty($status) && $status !== 'all') {
    $query .= " AND qt.status = ?";
    $params[] = $status;
    $types .= "s";
}

// Search filter: Patient name, appointment number, ticket number
if (!empty($searchQuery)) {
    $query .= " AND (qt.ticket_number LIKE ? OR qt.patient_name LIKE ? OR qt.appointment_number LIKE ?)";
    $searchParam = "%{$searchQuery}%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
    $types .= "sss";
}

// Count total
$countQuery = "SELECT COUNT(*) as total FROM (" . $query . ") as filtered";
$countStmt = $conn->prepare($countQuery);
if (!empty($params)) {
    $countStmt->bind_param($types, ...$params);
}
$countStmt->execute();
$totalResult = $countStmt->get_result();
$totalRow = $totalResult->fetch_assoc();
$total = intval($totalRow['total'] ?? 0);

// Order and pagination
$query .= " ORDER BY qt.created_at DESC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;
$types .= "ii";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

if ($stmt->execute()) {
    $result = $stmt->get_result();
    $history = [];

    while ($row = $result->fetch_assoc()) {
        $referredTo = $row['referred_to_department'] 
            ? $row['referred_to_department'] . ($row['referred_to_doctor'] ? ' (' . $row['referred_to_doctor'] . ')' : '') 
            : ($row['referred_to_doctor'] ?? null);

        $history[] = [
            'id' => intval($row['id']),
            'queue_number' => $row['ticket_number'],
            'ticket_number' => $row['ticket_number'],
            'patient_name' => $row['patient_name'],
            'appointment_number' => $row['appointment_number'],
            'age' => $row['age'] !== null ? intval($row['age']) : null,
            'gender' => $row['gender'],
            'weight_kg' => $row['weight_kg'],
            'bp' => $row['bp'],
            'department_id' => $row['department_id'] !== null ? intval($row['department_id']) : null,
            'department' => $row['department'],
            'department_name' => $row['department'],
            'doctor_id' => $row['doctor_id'] !== null ? intval($row['doctor_id']) : null,
            'window_number' => $row['window_number'],
            'date_issued' => $row['date_issued'],
            'created_at' => $row['date_issued'],
            'time_called' => $row['time_called'],
            'called_at' => $row['time_called'],
            'time_completed' => $row['time_completed'],
            'completed_at' => $row['time_completed'],
            'status' => $row['status'],
            'staff_name' => $row['staff_name'] ?? 'N/A',
            'doctor_name' => $row['doctor_name'] ?? 'N/A',
            'room_label' => $row['room_label'] ?? '',
            'referred_to' => $referredTo,
            'referred_to_department_name' => $row['referred_to_department'],
            'referred_to_doctor_name' => $row['referred_to_doctor'],
            'notes' => $row['notes'],
            'referral_notes' => $row['referral_notes']
        ];
    }

    sendJsonResponse([
        'success' => true,
        'history' => $history,
        'total' => $total,
        'limit' => $limit,
        'offset' => $offset
    ]);
} else {
    sendJsonResponse(['success' => false, 'message' => 'Failed to fetch queue history: ' . $conn->error], 500);
}
?>