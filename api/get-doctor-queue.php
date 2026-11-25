<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');

// Role enforcement: Doctor only
$doctor = requireRole(['doctor'], true);
$doctorId = $doctor['user_id'];

// 1. Get currently serving ticket for this doctor
$servingStmt = $conn->prepare("
    SELECT 
        id, ticket_number, patient_name, appointment_number, age, gender, weight_kg, bp, is_priority, status, called_at, notes
    FROM queue_tickets
    WHERE doctor_id = ? AND status = 'serving'
    LIMIT 1
");
$servingStmt->bind_param("i", $doctorId);
$servingStmt->execute();
$servingTicket = $servingStmt->get_result()->fetch_assoc();

// 2. Get waiting queue for this doctor
$waitingStmt = $conn->prepare("
    SELECT 
        id, ticket_number, patient_name, appointment_number, age, gender, weight_kg, bp, is_priority, status, created_at
    FROM queue_tickets
    WHERE doctor_id = ? AND status = 'waiting'
    ORDER BY is_priority DESC, created_at ASC
");
$waitingStmt->bind_param("i", $doctorId);
$waitingStmt->execute();
$waitingResult = $waitingStmt->get_result();

$waitingList = [];
while ($row = $waitingResult->fetch_assoc()) {
    $waitingList[] = [
        'id' => intval($row['id']),
        'ticket_number' => $row['ticket_number'],
        'patient_name' => $row['patient_name'],
        'appointment_number' => $row['appointment_number'],
        'age' => $row['age'] !== null ? intval($row['age']) : null,
        'gender' => $row['gender'],
        'weight_kg' => $row['weight_kg'],
        'bp' => $row['bp'],
        'is_priority' => intval($row['is_priority']),
        'status' => $row['status'],
        'created_at' => $row['created_at']
    ];
}

sendJsonResponse([
    'success' => true,
    'serving' => $servingTicket,
    'waiting' => $waitingList,
    'waiting_count' => count($waitingList)
]);
?>