<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');

$user = getCurrentUser();
$role = $user ? $user['role'] : null;
$userId = $user ? $user['user_id'] : null;

$query = "
    SELECT 
        qt.id,
        qt.ticket_number,
        qt.department_id,
        d.name AS department_name,
        d.prefix,
        d.window_number,
        qt.doctor_id,
        doc.full_name AS doctor_name,
        doc.room_label,
        qt.is_priority,
        qt.status,
        qt.created_at,
        qt.called_at,
        -- Patient details (only included for authorized doctor for their own tickets)
        qt.patient_name,
        qt.appointment_number,
        qt.age,
        qt.gender,
        qt.weight_kg,
        qt.bp,
        qt.notes
    FROM queue_tickets qt
    JOIN departments d ON qt.department_id = d.id
    LEFT JOIN doctors doc ON qt.doctor_id = doc.id
    WHERE qt.status IN ('waiting', 'serving')
    ORDER BY 
        d.id ASC,
        qt.status = 'serving' DESC,
        qt.is_priority DESC,
        qt.created_at ASC
";

$result = $conn->query($query);

if (!$result) {
    sendJsonResponse(['success' => false, 'message' => 'Database error: ' . $conn->error], 500);
}

$tickets = [];
while ($row = $result->fetch_assoc()) {
    $item = [
        'id' => intval($row['id']),
        'ticket_number' => $row['ticket_number'],
        'department_id' => intval($row['department_id']),
        'department_name' => $row['department_name'],
        'window_number' => intval($row['window_number']),
        'is_priority' => intval($row['is_priority']),
        'status' => $row['status'],
        'doctor_id' => $row['doctor_id'] !== null ? intval($row['doctor_id']) : null,
        'room_label' => $row['room_label'] ?? ('Window ' . $row['window_number'])
    ];

    // If a logged-in doctor is inspecting their own ticket, include patient details
    if ($role === 'doctor' && intval($row['doctor_id']) === $userId) {
        $item['patient_name'] = $row['patient_name'];
        $item['appointment_number'] = $row['appointment_number'];
        $item['age'] = $row['age'] !== null ? intval($row['age']) : null;
        $item['gender'] = $row['gender'];
        $item['weight_kg'] = $row['weight_kg'];
        $item['bp'] = $row['bp'];
        $item['notes'] = $row['notes'];
    }

    $tickets[] = $item;
}

sendJsonResponse([
    'success' => true,
    'tickets' => $tickets,
    'count' => count($tickets)
]);
?>