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
    $status = trim($data['status'] ?? 'completed');
    $notes = isset($data['notes']) ? trim($data['notes']) : null;

    if (!$ticketId || $ticketId <= 0) {
        sendJsonResponse(['success' => false, 'message' => 'Valid Ticket ID required'], 400);
    }

    if (!in_array($status, ['completed', 'no-show', 'cancelled'], true)) {
        sendJsonResponse(['success' => false, 'message' => 'Invalid status (allowed: completed, no-show, cancelled)'], 400);
    }

    // Verify ownership: ticket must belong to this doctor
    $checkStmt = $conn->prepare("SELECT id, notes FROM queue_tickets WHERE id = ? AND doctor_id = ?");
    $checkStmt->bind_param("ii", $ticketId, $doctorId);
    $checkStmt->execute();
    $existing = $checkStmt->get_result()->fetch_assoc();

    if (!$existing) {
        sendJsonResponse(['success' => false, 'message' => 'Ticket not found or does not belong to your queue'], 404);
    }

    $finalNotes = ($notes !== null && $notes !== '') ? $notes : $existing['notes'];

    $stmt = $conn->prepare("
        UPDATE queue_tickets 
        SET status = ?, completed_at = NOW(), notes = ? 
        WHERE id = ? AND doctor_id = ?
    ");
    $stmt->bind_param("ssii", $status, $finalNotes, $ticketId, $doctorId);

    if ($stmt->execute()) {
        sendJsonResponse(['success' => true, 'message' => 'Ticket updated successfully']);
    } else {
        sendJsonResponse(['success' => false, 'message' => 'Failed to update ticket: ' . $conn->error], 500);
    }

} else {
    sendJsonResponse(['success' => false, 'message' => 'Invalid request method'], 405);
}
?>