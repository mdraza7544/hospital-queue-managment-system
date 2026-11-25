<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');

// Role enforcement: Doctor only
$doctor = requireRole(['doctor'], true);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);

    $doctorId = $doctor['user_id'];
    $currentDeptId = $doctor['department_id'];

    $ticketId = isset($data['ticket_id']) ? intval($data['ticket_id']) : 0;
    $referType = trim($data['refer_type'] ?? 'department'); // 'doctor' or 'department'
    $targetDeptId = isset($data['target_department_id']) && $data['target_department_id'] !== '' ? intval($data['target_department_id']) : null;
    $targetDoctorId = isset($data['target_doctor_id']) && $data['target_doctor_id'] !== '' ? intval($data['target_doctor_id']) : null;
    $notes = isset($data['notes']) ? trim($data['notes']) : null;

    if ($ticketId <= 0) {
        sendJsonResponse(['success' => false, 'message' => 'Valid Ticket ID required'], 400);
    }

    // Verify ticket belongs to this doctor and is currently serving
    $checkStmt = $conn->prepare("
        SELECT id, ticket_number, patient_name, appointment_number, department_id, doctor_id
        FROM queue_tickets
        WHERE id = ? AND doctor_id = ? AND status = 'serving'
    ");
    $checkStmt->bind_param("ii", $ticketId, $doctorId);
    $checkStmt->execute();
    $currentTicket = $checkStmt->get_result()->fetch_assoc();

    if (!$currentTicket) {
        sendJsonResponse(['success' => false, 'message' => 'No actively serving ticket found in your queue to refer'], 404);
    }

    $finalTargetDeptId = null;
    $finalTargetDoctorId = null;

    if ($referType === 'doctor') {
        // Referral within same department
        $finalTargetDeptId = $currentDeptId;

        if (!$targetDoctorId) {
            sendJsonResponse(['success' => false, 'message' => 'Target doctor is required for doctor referral'], 400);
        }

        if ($targetDoctorId === $doctorId) {
            sendJsonResponse(['success' => false, 'message' => 'Cannot refer patient to yourself'], 400);
        }

        $docCheck = $conn->prepare("SELECT id, full_name FROM doctors WHERE id = ? AND department_id = ? AND is_active = 1");
        $docCheck->bind_param("ii", $targetDoctorId, $currentDeptId);
        $docCheck->execute();
        $targetDocRow = $docCheck->get_result()->fetch_assoc();
        if (!$targetDocRow) {
            sendJsonResponse(['success' => false, 'message' => 'Target doctor not found or inactive in your department'], 400);
        }
        $finalTargetDoctorId = $targetDoctorId;

    } else {
        // Referral to another department
        if (!$targetDeptId) {
            sendJsonResponse(['success' => false, 'message' => 'Target department is required'], 400);
        }

        if ($targetDeptId === $currentDeptId) {
            sendJsonResponse(['success' => false, 'message' => 'Target department cannot be your current department. Choose another department or select doctor referral.'], 400);
        }

        $deptCheck = $conn->prepare("SELECT id, name FROM departments WHERE id = ?");
        $deptCheck->bind_param("i", $targetDeptId);
        $deptCheck->execute();
        $targetDeptRow = $deptCheck->get_result()->fetch_assoc();
        if (!$targetDeptRow) {
            sendJsonResponse(['success' => false, 'message' => 'Target department not found'], 404);
        }
        $finalTargetDeptId = $targetDeptId;

        // If target doctor is specified for target department
        if ($targetDoctorId) {
            $docCheck = $conn->prepare("SELECT id, full_name FROM doctors WHERE id = ? AND department_id = ? AND is_active = 1");
            $docCheck->bind_param("ii", $targetDoctorId, $targetDeptId);
            $docCheck->execute();
            if (!$docCheck->get_result()->fetch_assoc()) {
                sendJsonResponse(['success' => false, 'message' => 'Selected doctor not found or inactive in target department'], 400);
            }
            $finalTargetDoctorId = $targetDoctorId;
        }
    }

    // Update ticket to 'referred' (NO new ticket is auto-created; patient checks in at target counter)
    $updateStmt = $conn->prepare("
        UPDATE queue_tickets 
        SET status = 'referred', 
            completed_at = NOW(), 
            referred_at = NOW(),
            referred_by_doctor_id = ?,
            referred_to_department_id = ?, 
            referred_to_doctor_id = ?, 
            referral_notes = ? 
        WHERE id = ? AND doctor_id = ? AND status = 'serving'
    ");
    $updateStmt->bind_param("iiisii", $doctorId, $finalTargetDeptId, $finalTargetDoctorId, $notes, $ticketId, $doctorId);

    if ($updateStmt->execute() && $updateStmt->affected_rows > 0) {
        sendJsonResponse([
            'success' => true,
            'message' => 'Patient referred successfully. The patient can now proceed to the target counter to check in.',
            'status' => 'referred',
            'ticket' => [
                'id' => $ticketId,
                'status' => 'referred'
            ]
        ]);
    } else {
        sendJsonResponse(['success' => false, 'message' => 'Failed to record referral: ' . $conn->error], 500);
    }

} else {
    sendJsonResponse(['success' => false, 'message' => 'Invalid request method'], 405);
}
?>