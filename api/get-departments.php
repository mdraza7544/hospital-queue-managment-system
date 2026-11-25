<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');

// Retrieve all departments
$stmt = $conn->prepare("SELECT id, name, prefix, window_number FROM departments ORDER BY window_number ASC, name ASC");
$stmt->execute();
$result = $stmt->get_result();

$departments = [];
while ($row = $result->fetch_assoc()) {
    $departments[] = [
        'id' => intval($row['id']),
        'name' => $row['name'],
        'prefix' => $row['prefix'],
        'window_number' => intval($row['window_number'])
    ];
}

sendJsonResponse([
    'success' => true,
    'departments' => $departments
]);
?>