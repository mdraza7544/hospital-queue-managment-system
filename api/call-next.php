<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');

// Role enforcement: Doctor only
$doctor = requireRole(['doctor'], true);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $doctorId = $doctor['user_id'];
    $departmentId = $doctor['department_id'];

    if (!$doctorId || $doctorId <= 0) {
        sendJsonResponse(['success' => false, 'message' => 'Invalid doctor session'], 400);
    }

    // Begin database transaction with row locking to prevent race conditions
    $conn->begin_transaction();

    try {
        // STEP 1: Auto-complete currently serving ticket for this specific doctor
        $completeStmt = $conn->prepare("
            UPDATE queue_tickets 
            SET status = 'completed', completed_at = NOW() 
            WHERE doctor_id = ? AND status = 'serving'
        ");
        $completeStmt->bind_param("i", $doctorId);
        $completeStmt->execute();

        // STEP 2: Lock and select the next waiting ticket for this doctor (Priority first, then FIFO)
        $selectStmt = $conn->prepare("
            SELECT 
                qt.id, 
                qt.ticket_number, 
                qt.patient_name, 
                qt.appointment_number, 
                qt.age, 
                qt.gender, 
                qt.weight_kg, 
                qt.bp, 
                qt.is_priority, 
                qt.doctor_id,
                doc.full_name AS doctor_name,
                doc.room_label
            FROM queue_tickets qt
            LEFT JOIN doctors doc ON qt.doctor_id = doc.id
            WHERE qt.doctor_id = ? AND qt.status = 'waiting'
            ORDER BY qt.is_priority DESC, qt.created_at ASC
            LIMIT 1
            FOR UPDATE
        ");
        $selectStmt->bind_param("i", $doctorId);
        $selectStmt->execute();
        $result = $selectStmt->get_result();

        if ($ticket = $result->fetch_assoc()) {
            // STEP 3: Update selected ticket to 'serving'
            $updateStmt = $conn->prepare("
                UPDATE queue_tickets 
                SET status = 'serving', called_at = NOW() 
                WHERE id = ?
            ");
            $updateStmt->bind_param("i", $ticket['id']);
            $updateStmt->execute();

            $conn->commit();

            sendJsonResponse([
                'success' => true,
                'ticket' => $ticket
            ]);
        } else {
            $conn->rollback();
            sendJsonResponse(['success' => false, 'message' => 'No tickets in waiting queue']);
        }

    } catch (Exception $e) {
        $conn->rollback();
        sendJsonResponse(['success' => false, 'message' => 'Error calling next ticket: ' . $e->getMessage()], 500);
    }

} else {
    sendJsonResponse(['success' => false, 'message' => 'Invalid request method'], 405);
}
?>