<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');

$deptIdParam = isset($_GET['department_id']) && $_GET['department_id'] !== '' ? $_GET['department_id'] : null;

if ($deptIdParam !== null) {
    if ($deptIdParam === 'all') {
        $stmt = $conn->prepare("
            SELECT doc.id, doc.full_name, doc.specialization, doc.department_id, doc.room_label, d.name AS department_name 
            FROM doctors doc
            LEFT JOIN departments d ON doc.department_id = d.id
            WHERE doc.is_active = 1 AND doc.is_deleted = 0
            ORDER BY doc.full_name ASC
        ");
    } else {
        $deptId = intval($deptIdParam);
        $stmt = $conn->prepare("
            SELECT doc.id, doc.full_name, doc.specialization, doc.department_id, doc.room_label, d.name AS department_name 
            FROM doctors doc
            LEFT JOIN departments d ON doc.department_id = d.id
            WHERE doc.is_active = 1 AND doc.is_deleted = 0 AND doc.department_id = ? 
            ORDER BY doc.full_name ASC
        ");
        $stmt->bind_param("i", $deptId);
    }
} else {
    // If user is logged in, default to their own department if staff or doctor
    $user = getCurrentUser();
    if ($user && !empty($user['department_id'])) {
        $deptId = intval($user['department_id']);
        $stmt = $conn->prepare("
            SELECT doc.id, doc.full_name, doc.specialization, doc.department_id, doc.room_label, d.name AS department_name 
            FROM doctors doc
            LEFT JOIN departments d ON doc.department_id = d.id
            WHERE doc.is_active = 1 AND doc.is_deleted = 0 AND doc.department_id = ? 
            ORDER BY doc.full_name ASC
        ");
        $stmt->bind_param("i", $deptId);
    } else {
        $stmt = $conn->prepare("
            SELECT doc.id, doc.full_name, doc.specialization, doc.department_id, doc.room_label, d.name AS department_name 
            FROM doctors doc
            LEFT JOIN departments d ON doc.department_id = d.id
            WHERE doc.is_active = 1 AND doc.is_deleted = 0
            ORDER BY doc.full_name ASC
        ");
    }
}

$stmt->execute();
$result = $stmt->get_result();

$doctors = [];
while ($row = $result->fetch_assoc()) {
    $doctors[] = [
        'id' => intval($row['id']),
        'full_name' => $row['full_name'],
        'specialization' => $row['specialization'],
        'department_id' => intval($row['department_id']),
        'department_name' => $row['department_name'] ?? '',
        'room_label' => $row['room_label'] ?? ''
    ];
}

sendJsonResponse([
    'success' => true,
    'doctors' => $doctors
]);
?>