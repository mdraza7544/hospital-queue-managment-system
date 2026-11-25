<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');

// Role enforcement: Doctor only
$doctor = requireRole(['doctor'], true);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);

    $doctorId = $doctor['user_id'];
    $ticketId = isset($data['ticket_id']) ? intval($data['ticket_id']) : null;

    if (!$ticketId || $ticketId <= 0) {
        sendJsonResponse(['success' => false, 'message' => 'Valid Ticket ID required'], 400);
    }

    $stmt = $conn->prepare("
        UPDATE queue_tickets 
        SET status = 'waiting', called_at = NULL 
        WHERE id = ? AND doctor_id = ?
    ");
    $stmt->bind_param("ii", $ticketId, $doctorId);

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            sendJsonResponse(['success' => true, 'message' => 'Ticket recalled successfully']);
        } else {
            sendJsonResponse(['success' => false, 'message' => 'Ticket not found or does not belong to your queue'], 404);
        }
    } else {
        sendJsonResponse(['success' => false, 'message' => 'Failed to recall ticket: ' . $conn->error], 500);
    }

} else {
    sendJsonResponse(['success' => false, 'message' => 'Invalid request method'], 405);
}
?>