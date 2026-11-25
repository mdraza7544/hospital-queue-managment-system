<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');

// 1. Role enforcement: Staff only
$user = requireRole(['staff'], true);

// Function to generate Ticket Number (Department or Priority prefix + 3 digits)
function generateTicketNumber($conn, $prefix) {
    $pattern = $prefix . '-%';
    $stmt = $conn->prepare("
        SELECT ticket_number 
        FROM queue_tickets 
        WHERE ticket_number LIKE ? 
        ORDER BY id DESC 
        LIMIT 1
    ");
    $stmt->bind_param("s", $pattern);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $lastNumberString = substr($row['ticket_number'], strlen($prefix) + 1);
        $nextNum = intval($lastNumberString) + 1;
    } else {
        $nextNum = 1;
    }
    
    return $prefix . '-' . str_pad($nextNum, 3, '0', STR_PAD_LEFT);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);

    $staffId = $user['user_id'];
    $deptId = $user['department_id'];

    if (!$deptId || $deptId <= 0) {
        sendJsonResponse(['success' => false, 'message' => 'Staff member is not assigned to a valid department'], 400);
    }

    $patientName = trim($data['patient_name'] ?? '');
    $gender = trim($data['gender'] ?? '');
    $weightRaw = $data['weight_kg'] ?? null;
    $bp = trim($data['bp'] ?? '');
    $appointmentNumber = trim($data['appointment_number'] ?? '');
    $ageRaw = isset($data['age']) && $data['age'] !== '' ? $data['age'] : null;
    $doctorIdRaw = $data['doctor_id'] ?? null;
    $isPriority = !empty($data['is_priority']);

    // Mandatory Patient Name
    if ($patientName === '') {
        sendJsonResponse(['success' => false, 'message' => 'Patient name is required'], 400);
    }

    // Mandatory Gender
    if (!in_array($gender, ['Male', 'Female', 'Other'], true)) {
        sendJsonResponse(['success' => false, 'message' => 'Valid gender is required (Male, Female, Other)'], 400);
    }

    // Mandatory Weight
    if ($weightRaw === null || !is_numeric($weightRaw)) {
        sendJsonResponse(['success' => false, 'message' => 'Weight (kg) is required and must be numeric'], 400);
    }
    $weightKg = floatval($weightRaw);
    if ($weightKg <= 0 || $weightKg >= 500) {
        sendJsonResponse(['success' => false, 'message' => 'Weight must be greater than 0 and less than 500 kg'], 400);
    }

    // Mandatory Blood Pressure
    if (!preg_match('/^(\d{2,3})\/(\d{2,3})$/', $bp, $bpMatches)) {
        sendJsonResponse(['success' => false, 'message' => 'Blood pressure must be in format like 120/80'], 400);
    }
    $systolic = intval($bpMatches[1]);
    $diastolic = intval($bpMatches[2]);
    if ($systolic <= $diastolic) {
        sendJsonResponse(['success' => false, 'message' => 'Systolic pressure must be greater than diastolic pressure'], 400);
    }

    // Mandatory Appointment Number
    if ($appointmentNumber === '') {
        sendJsonResponse(['success' => false, 'message' => 'Appointment number is required'], 400);
    }

    // Mandatory Doctor Selection for Staff Check-in
    if (empty($doctorIdRaw) || !is_numeric($doctorIdRaw)) {
        sendJsonResponse(['success' => false, 'message' => 'Attending doctor selection is required'], 400);
    }
    $doctorId = intval($doctorIdRaw);

    // Verify doctor belongs to this department and is active
    $docStmt = $conn->prepare("SELECT id, full_name FROM doctors WHERE id = ? AND department_id = ? AND is_active = 1");
    $docStmt->bind_param("ii", $doctorId, $deptId);
    $docStmt->execute();
    $docResult = $docStmt->get_result();
    if (!$docResult->fetch_assoc()) {
        sendJsonResponse(['success' => false, 'message' => 'Selected doctor is invalid, inactive, or not in your department'], 400);
    }

    // Optional Age
    $age = null;
    if ($ageRaw !== null) {
        if (!is_numeric($ageRaw)) {
            sendJsonResponse(['success' => false, 'message' => 'Age must be a valid number'], 400);
        }
        $age = intval($ageRaw);
        if ($age < 0 || $age > 120) {
            sendJsonResponse(['success' => false, 'message' => 'Age must be between 0 and 120'], 400);
        }
    }

    // Same-day duplicate appointment number check
    $dupStmt = $conn->prepare("
        SELECT id FROM queue_tickets 
        WHERE appointment_number = ? 
          AND DATE(created_at) = CURDATE() 
          AND status != 'referred' 
          AND referred_from_ticket_id IS NULL 
        LIMIT 1
    ");
    $dupStmt->bind_param("s", $appointmentNumber);
    $dupStmt->execute();
    if ($dupStmt->get_result()->fetch_assoc()) {
        sendJsonResponse(['success' => false, 'message' => 'A ticket with this appointment number already exists today'], 400);
    }

    // Get department details
    $deptStmt = $conn->prepare("SELECT name, prefix, window_number FROM departments WHERE id = ?");
    $deptStmt->bind_param("i", $deptId);
    $deptStmt->execute();
    $deptRow = $deptStmt->get_result()->fetch_assoc();
    if (!$deptRow) {
        sendJsonResponse(['success' => false, 'message' => 'Department not found'], 404);
    }

    // Priority uses 'P', otherwise department prefix
    $prefix = $isPriority ? 'P' : $deptRow['prefix'];
    $ticketNumber = generateTicketNumber($conn, $prefix);

    // Insert new ticket assigned directly to chosen doctor and checked in by staff
    $insertStmt = $conn->prepare("
        INSERT INTO queue_tickets 
        (ticket_number, patient_name, appointment_number, age, gender, weight_kg, bp, department_id, doctor_id, checked_in_by_staff_id, is_priority, status) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'waiting')
    ");

    $priorityInt = $isPriority ? 1 : 0;
    $insertStmt->bind_param(
        "sssisdsiiii",
        $ticketNumber,
        $patientName,
        $appointmentNumber,
        $age,
        $gender,
        $weightKg,
        $bp,
        $deptId,
        $doctorId,
        $staffId,
        $priorityInt
    );

    if ($insertStmt->execute()) {
        sendJsonResponse([
            'success' => true,
            'ticket_id' => $conn->insert_id,
            'ticket_number' => $ticketNumber,
            'department' => $deptRow['name'],
            'window_number' => $deptRow['window_number'],
            'message' => 'Patient checked in successfully'
        ]);
    } else {
        sendJsonResponse(['success' => false, 'message' => 'Failed to create queue ticket: ' . $conn->error], 500);
    }

} else {
    sendJsonResponse(['success' => false, 'message' => 'Invalid request method'], 405);
}
?>