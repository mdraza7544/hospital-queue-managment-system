<?php
// Comprehensive Automated Acceptance Test Suite for Hospital Queueing System
// Covers All Acceptance Tests (1 to 19): Roles, RBAC, Queue, Attendance, Deletion, and PMA

$baseUrl = 'http://127.0.0.1/STI_Queueing-System';
$tmpDir = __DIR__ . '/test_tmp';
if (!is_dir($tmpDir)) mkdir($tmpDir);

$adminCookie = $tmpDir . '/admin_cookie.txt';
$docCookie = $tmpDir . '/doc_cookie.txt';
$staffCookie = $tmpDir . '/staff_cookie.txt';

@unlink($adminCookie);
@unlink($docCookie);
@unlink($staffCookie);

function doCurl($url, $method = 'GET', $data = null, $cookieFile = null) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    if ($cookieFile) {
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    }

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($data !== null) {
            $payload = is_array($data) ? json_encode($data) : $data;
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        }
    }

    $body = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return [
        'code' => $httpCode,
        'body' => $body,
        'json' => json_decode($body, true)
    ];
}

$results = [];

function assertTest($name, $condition, $details = '') {
    global $results;
    if ($condition) {
        $results[] = ['name' => $name, 'status' => 'PASS', 'details' => $details];
        echo "[PASS] $name" . ($details ? " ($details)" : "") . "\n";
    } else {
        $results[] = ['name' => $name, 'status' => 'FAIL', 'details' => $details];
        echo "[FAIL] $name: $details\n";
    }
}

echo "=== STARTING COMPREHENSIVE ACCEPTANCE TESTS ===\n\n";

// =========================================================================
// TEST 1: Admin Login
// =========================================================================
$res1 = doCurl("$baseUrl/api/login.php", 'POST', ['username' => 'admin', 'password' => 'admin123'], $adminCookie);
assertTest("1. Admin Login", 
    $res1['code'] === 200 && ($res1['json']['role'] ?? '') === 'admin' && strpos($res1['json']['redirect'] ?? '', 'admin-dashboard') !== false,
    "HTTP {$res1['code']}, Role: " . ($res1['json']['role'] ?? 'none')
);

// =========================================================================
// TEST 2: Admin Dashboard Overview API (with roster duty status)
// =========================================================================
$res2 = doCurl("$baseUrl/api/get-admin-overview.php", 'GET', null, $adminCookie);
$hasRoster = isset($res2['json']['roster']['doctors']) && isset($res2['json']['roster']['staff']);
assertTest("2. Admin Overview API",
    $res2['code'] === 200 && isset($res2['json']['metrics']) && isset($res2['json']['departments']) && $hasRoster,
    "HTTP {$res2['code']}, Doctors: " . ($res2['json']['metrics']['total_active_doctors'] ?? 0)
);

// =========================================================================
// TEST 3: Admin Doctor CRUD (Create, Edit, Toggle, List)
// =========================================================================
$docUsername = 'dr.accept_' . time();
$res3 = doCurl("$baseUrl/api/manage-doctors.php", 'POST', [
    'action' => 'create',
    'full_name' => 'Dr. Stephen Acceptance',
    'specialization' => 'Cardiologist',
    'department_id' => 1,
    'room_label' => 'Clinic 101',
    'username' => $docUsername,
    'password' => 'password123'
], $adminCookie);

$createdDocId = $res3['json']['doctor_id'] ?? null;
assertTest("3. Admin Create Doctor",
    $res3['code'] === 200 && ($res3['json']['success'] ?? false) === true && $createdDocId > 0,
    "DocID: $createdDocId"
);

// Edit Doctor
$res3Edit = doCurl("$baseUrl/api/manage-doctors.php", 'POST', [
    'action' => 'edit',
    'id' => $createdDocId,
    'full_name' => 'Dr. Stephen Acceptance Jr',
    'specialization' => 'Senior Cardiologist',
    'department_id' => 1,
    'room_label' => 'Clinic 102',
    'username' => $docUsername
], $adminCookie);
assertTest("3b. Admin Edit Doctor",
    $res3Edit['code'] === 200 && ($res3Edit['json']['success'] ?? false) === true,
    "Edit success: " . ($res3Edit['json']['success'] ? 'true' : 'false')
);

// =========================================================================
// TEST 4: Admin Staff CRUD (Create, Edit, List)
// =========================================================================
$staffUsername = 'staff.accept_' . time();
$res4 = doCurl("$baseUrl/api/manage-staff.php", 'POST', [
    'action' => 'create',
    'username' => $staffUsername,
    'password' => 'password123',
    'full_name' => 'Cardio Receptionist Acceptance',
    'department_id' => 1
], $adminCookie);

$createdStaffId = $res4['json']['staff_id'] ?? null;
assertTest("4. Admin Create Staff",
    $res4['code'] === 200 && ($res4['json']['success'] ?? false) === true && $createdStaffId > 0,
    "StaffID: $createdStaffId"
);

// Edit Staff
$res4Edit = doCurl("$baseUrl/api/manage-staff.php", 'POST', [
    'action' => 'edit',
    'id' => $createdStaffId,
    'full_name' => 'Senior Receptionist Acceptance',
    'department_id' => 1
], $adminCookie);
assertTest("4b. Admin Edit Staff",
    $res4Edit['code'] === 200 && ($res4Edit['json']['success'] ?? false) === true,
    "Edit success: " . ($res4Edit['json']['success'] ? 'true' : 'false')
);

// =========================================================================
// TEST 5: Doctor Login
// =========================================================================
$res5 = doCurl("$baseUrl/api/login.php", 'POST', ['username' => $docUsername, 'password' => 'password123'], $docCookie);
assertTest("5. Doctor Login",
    $res5['code'] === 200 && ($res5['json']['role'] ?? '') === 'doctor' && strpos($res5['json']['redirect'] ?? '', 'doctor-dashboard') !== false,
    "Role: " . ($res5['json']['role'] ?? 'none')
);

// =========================================================================
// TEST 6: Staff Login
// =========================================================================
$res6 = doCurl("$baseUrl/api/login.php", 'POST', ['username' => $staffUsername, 'password' => 'password123'], $staffCookie);
assertTest("6. Staff Login",
    $res6['code'] === 200 && ($res6['json']['role'] ?? '') === 'staff' && strpos($res6['json']['redirect'] ?? '', 'staff-dashboard') !== false,
    "Role: " . ($res6['json']['role'] ?? 'none')
);

// =========================================================================
// TEST 7: Staff Check-in Patient (Doctor Mandatory)
// =========================================================================
$apptNo = 'APPT-' . rand(10000, 99999);
$res7 = doCurl("$baseUrl/api/checkin-patient.php", 'POST', [
    'department_id' => 1,
    'patient_name' => 'Jane Acceptance Patient',
    'appointment_number' => $apptNo,
    'gender' => 'Female',
    'age' => 45,
    'weight_kg' => 62.5,
    'bp' => '120/80',
    'doctor_id' => $createdDocId,
    'is_priority' => 1
], $staffCookie);

$createdTicketNo = $res7['json']['ticket_number'] ?? null;
assertTest("7. Staff Check-in Patient (Doctor Required)",
    $res7['code'] === 200 && ($res7['json']['success'] ?? false) === true && !empty($createdTicketNo),
    "Ticket: $createdTicketNo"
);

// Staff History Stats Check (Counts only)
$res7Stats = doCurl("$baseUrl/api/get-staff-stats.php", 'GET', null, $staffCookie);
assertTest("7b. Staff Stats (Count Only)",
    $res7Stats['code'] === 200 && ($res7Stats['json']['stats']['today_count'] ?? 0) >= 1,
    "Today count: " . ($res7Stats['json']['stats']['today_count'] ?? 0)
);

// =========================================================================
// TEST 8: Server-Side RBAC Enforcement (401 vs 403)
// =========================================================================
// 8a. Unauthenticated calls return 401
$res8Unauth1 = doCurl("$baseUrl/api/checkin-patient.php", 'POST', ['department_id' => 1]); // No cookie
$res8Unauth2 = doCurl("$baseUrl/api/call-next.php", 'POST', []); // No cookie
$res8Unauth3 = doCurl("$baseUrl/api/get-admin-overview.php", 'GET'); // No cookie
assertTest("8a. Unauthenticated Requests Return 401 UNAUTHENTICATED",
    $res8Unauth1['code'] === 401 && $res8Unauth2['code'] === 401 && $res8Unauth3['code'] === 401,
    "Checkin: {$res8Unauth1['code']}, Call: {$res8Unauth2['code']}, Overview: {$res8Unauth3['code']}"
);

// 8b. Mismatched role calls return 403
$res8Role1 = doCurl("$baseUrl/api/call-next.php", 'POST', [], $staffCookie); // Staff calling queue
$res8Role2 = doCurl("$baseUrl/api/checkin-patient.php", 'POST', ['department_id' => 1], $adminCookie); // Admin checkin
$res8Role3 = doCurl("$baseUrl/api/call-next.php", 'POST', [], $adminCookie); // Admin call queue
$res8Role4 = doCurl("$baseUrl/api/checkin-patient.php", 'POST', ['department_id' => 1], $docCookie); // Doctor checkin
$res8Role5 = doCurl("$baseUrl/api/get-queue-history.php", 'GET', null, $staffCookie); // Staff patient history
assertTest("8b. Mismatched Roles Return 403 FORBIDDEN_ROLE",
    $res8Role1['code'] === 403 && $res8Role2['code'] === 403 && $res8Role3['code'] === 403 && $res8Role4['code'] === 403 && $res8Role5['code'] === 403,
    "Staff call: {$res8Role1['code']}, Admin checkin: {$res8Role2['code']}, Doctor checkin: {$res8Role4['code']}"
);

// =========================================================================
// TEST 9: Doctor Queue & Call Next
// =========================================================================
$res9a = doCurl("$baseUrl/api/get-doctor-queue.php", 'GET', null, $docCookie);
$waitingList = $res9a['json']['waiting'] ?? [];
$hasWaitingTicket = false;
foreach ($waitingList as $w) {
    if ($w['ticket_number'] === $createdTicketNo) {
        $hasWaitingTicket = true;
        break;
    }
}
assertTest("9a. Doctor sees checked-in patient in queue",
    $res9a['code'] === 200 && $hasWaitingTicket,
    "Ticket $createdTicketNo in waiting list"
);

$res9b = doCurl("$baseUrl/api/call-next.php", 'POST', [], $docCookie);
$calledTicketId = $res9b['json']['ticket']['id'] ?? null;
assertTest("9b. Doctor Call Next",
    $res9b['code'] === 200 && ($res9b['json']['ticket']['ticket_number'] ?? '') === $createdTicketNo,
    "Called Ticket: " . ($res9b['json']['ticket']['ticket_number'] ?? 'none')
);

// =========================================================================
// TEST 10: Monitor Queue Sanitization
// =========================================================================
$res10 = doCurl("$baseUrl/api/get-queue.php", 'GET');
$publicTickets = $res10['json']['tickets'] ?? [];
$monitorServingTicket = null;
foreach ($publicTickets as $pt) {
    if ($pt['ticket_number'] === $createdTicketNo) {
        $monitorServingTicket = $pt;
        break;
    }
}
$isSanitized = $monitorServingTicket && !isset($monitorServingTicket['bp']) && !isset($monitorServingTicket['weight_kg']) && isset($monitorServingTicket['room_label']);
assertTest("10. Monitor Queue Data Sanitized & Room Label Included",
    $res10['code'] === 200 && $isSanitized,
    "Room: " . ($monitorServingTicket['room_label'] ?? 'none') . ", Status: " . ($monitorServingTicket['status'] ?? 'none')
);

// =========================================================================
// TEST 11: Doctor Refer Patient
// =========================================================================
$res11 = doCurl("$baseUrl/api/refer-patient.php", 'POST', [
    'ticket_id' => $calledTicketId,
    'target_department_id' => 2, // Neurology
    'notes' => 'Patient needs neurology evaluation',
    'is_priority' => 1
], $docCookie);

assertTest("11. Doctor Refer Patient",
    $res11['code'] === 200 && ($res11['json']['success'] ?? false) === true && ($res11['json']['ticket']['status'] ?? '') === 'referred',
    "Status: " . ($res11['json']['ticket']['status'] ?? 'none')
);

// =========================================================================
// TEST 12: Doctor History Scoped to Doctor
// =========================================================================
$res12 = doCurl("$baseUrl/api/get-queue-history.php", 'GET', null, $docCookie);
$docHistory = $res12['json']['history'] ?? [];
$allMatchDoctor = true;
foreach ($docHistory as $h) {
    if (intval($h['doctor_id']) !== intval($createdDocId)) {
        $allMatchDoctor = false;
        break;
    }
}
assertTest("12. Doctor Consultation History Scoped to Doctor",
    $res12['code'] === 200 && count($docHistory) > 0 && $allMatchDoctor,
    "Records: " . count($docHistory)
);

// =========================================================================
// TEST 13: Duty Attendance - Self Check-In (Staff & Doctor)
// =========================================================================
$res13StaffIn = doCurl("$baseUrl/api/attendance-checkin.php", 'POST', [], $staffCookie);
assertTest("13a. Staff Duty Attendance Check-In",
    $res13StaffIn['code'] === 200 && ($res13StaffIn['json']['success'] ?? false) === true && ($res13StaffIn['json']['is_on_duty'] ?? false) === true,
    "HTTP {$res13StaffIn['code']}, is_on_duty: " . (($res13StaffIn['json']['is_on_duty'] ?? false) ? 'true' : 'false')
);

$res13DocIn = doCurl("$baseUrl/api/attendance-checkin.php", 'POST', [], $docCookie);
assertTest("13b. Doctor Duty Attendance Check-In",
    $res13DocIn['code'] === 200 && ($res13DocIn['json']['success'] ?? false) === true && ($res13DocIn['json']['is_on_duty'] ?? false) === true,
    "HTTP {$res13DocIn['code']}, is_on_duty: " . (($res13DocIn['json']['is_on_duty'] ?? false) ? 'true' : 'false')
);

// =========================================================================
// TEST 14: Duty Attendance - Double Check-In Rejection (409 Conflict)
// =========================================================================
$res14DoubleStaff = doCurl("$baseUrl/api/attendance-checkin.php", 'POST', [], $staffCookie);
$res14DoubleDoc = doCurl("$baseUrl/api/attendance-checkin.php", 'POST', [], $docCookie);
assertTest("14. Double Check-In Blocked by DB Constraint (409 Conflict)",
    $res14DoubleStaff['code'] === 409 && $res14DoubleDoc['code'] === 409,
    "Staff HTTP: {$res14DoubleStaff['code']}, Doc HTTP: {$res14DoubleDoc['code']}"
);

// =========================================================================
// TEST 15: Duty Attendance - Running Timer & Personal Summary API
// =========================================================================
$res15MyAtt = doCurl("$baseUrl/api/get-my-attendance.php", 'GET', null, $staffCookie);
assertTest("15. Personal Attendance Status & Summary API",
    $res15MyAtt['code'] === 200 && ($res15MyAtt['json']['is_on_duty'] ?? false) === true && isset($res15MyAtt['json']['today_seconds']),
    "On Duty: " . ($res15MyAtt['json']['is_on_duty'] ? 'yes' : 'no') . ", Server Time: " . ($res15MyAtt['json']['server_now'] ?? '')
);

// =========================================================================
// TEST 16: Duty Attendance - Self Check-Out
// =========================================================================
$res16StaffOut = doCurl("$baseUrl/api/attendance-checkout.php", 'POST', [], $staffCookie);
$res16DocOut = doCurl("$baseUrl/api/attendance-checkout.php", 'POST', [], $docCookie);
assertTest("16. Duty Attendance Check-Out",
    $res16StaffOut['code'] === 200 && ($res16StaffOut['json']['success'] ?? false) === true &&
    $res16DocOut['code'] === 200 && ($res16DocOut['json']['success'] ?? false) === true,
    "Staff Dur: " . ($res16StaffOut['json']['attendance']['duration_formatted'] ?? '') . ", Doc Dur: " . ($res16DocOut['json']['attendance']['duration_formatted'] ?? '')
);

// Check-out again when not on duty -> 400
$res16DoubleOut = doCurl("$baseUrl/api/attendance-checkout.php", 'POST', [], $staffCookie);
assertTest("16b. Check-Out when not on duty returns 400",
    $res16DoubleOut['code'] === 400,
    "HTTP {$res16DoubleOut['code']}"
);

// =========================================================================
// TEST 17: Admin Attendance View & Subtabs (Duty vs Sessions) & Filters
// =========================================================================
// 17a. Subtab 1: Duty logs
$res17Duty = doCurl("$baseUrl/api/get-attendance.php?tab=duty&role=all", 'GET', null, $adminCookie);
assertTest("17a. Admin Attendance Duty Logs Subtab",
    $res17Duty['code'] === 200 && ($res17Duty['json']['tab'] ?? '') === 'duty' && is_array($res17Duty['json']['records']),
    "Duty records: " . count($res17Duty['json']['records'] ?? [])
);

// 17b. Subtab 2: Session logs
$res17Sessions = doCurl("$baseUrl/api/get-attendance.php?tab=sessions&role=all", 'GET', null, $adminCookie);
assertTest("17b. Admin Attendance Sessions Audit Subtab",
    $res17Sessions['code'] === 200 && ($res17Sessions['json']['tab'] ?? '') === 'sessions' && is_array($res17Sessions['json']['records']),
    "Session records: " . count($res17Sessions['json']['records'] ?? [])
);

// 17c. Filter by Role & Search
$res17Filter = doCurl("$baseUrl/api/get-attendance.php?tab=duty&role=staff&search=" . urlencode($staffUsername), 'GET', null, $adminCookie);
$foundStaffRecord = false;
foreach ($res17Filter['json']['records'] ?? [] as $rec) {
    if (($rec['username'] ?? '') === $staffUsername) {
        $foundStaffRecord = true;
        break;
    }
}
assertTest("17c. Admin Attendance Filter by Role & Search Username",
    $res17Filter['code'] === 200 && $foundStaffRecord,
    "Found created staff record: " . ($foundStaffRecord ? 'yes' : 'no')
);

// =========================================================================
// TEST 18: Admin Deletion of Staff & Doctor (Soft Delete & Inactive Checks)
// =========================================================================
// 18a. Self-deletion protection for Admin
$res18SelfDel = doCurl("$baseUrl/api/manage-staff.php", 'POST', [
    'action' => 'delete',
    'id' => 1 // admin
], $adminCookie);
assertTest("18a. Self-deletion of Admin blocked",
    $res18SelfDel['code'] === 400 && strpos($res18SelfDel['json']['message'] ?? '', 'Cannot delete') !== false,
    "Msg: " . ($res18SelfDel['json']['message'] ?? '')
);

// 18b. Soft Delete Staff
$res18DelStaff = doCurl("$baseUrl/api/manage-staff.php", 'POST', [
    'action' => 'delete',
    'id' => $createdStaffId
], $adminCookie);
assertTest("18b. Admin Soft Delete Staff",
    $res18DelStaff['code'] === 200 && ($res18DelStaff['json']['success'] ?? false) === true,
    "Msg: " . ($res18DelStaff['json']['message'] ?? '')
);

// Deleted staff cannot log in
$res18StaffLoginAfterDel = doCurl("$baseUrl/api/login.php", 'POST', ['username' => $staffUsername, 'password' => 'password123']);
assertTest("18c. Deleted Staff Login Rejected (401)",
    $res18StaffLoginAfterDel['code'] === 401,
    "HTTP {$res18StaffLoginAfterDel['code']}"
);

// 18d. Doctor with Active Ticket Deletion Blocked
$docBusyUser = 'dr.busy_' . time();
$resDocBusy = doCurl("$baseUrl/api/manage-doctors.php", 'POST', [
    'action' => 'create',
    'full_name' => 'Dr. Busy Acceptance',
    'specialization' => 'Surgeon',
    'department_id' => 1,
    'room_label' => 'Clinic 103',
    'username' => $docBusyUser,
    'password' => 'password123'
], $adminCookie);
$busyDocId = $resDocBusy['json']['doctor_id'] ?? 0;

// Re-login an active staff to create ticket for this doctor
$tempStaffUser = 'staff.busy_' . time();
$resTempStaff = doCurl("$baseUrl/api/manage-staff.php", 'POST', [
    'action' => 'create',
    'username' => $tempStaffUser,
    'password' => 'password123',
    'full_name' => 'Temp Staff Busy',
    'department_id' => 1
], $adminCookie);
$tempStaffCookie = $tmpDir . '/temp_staff_cookie.txt';
doCurl("$baseUrl/api/login.php", 'POST', ['username' => $tempStaffUser, 'password' => 'password123'], $tempStaffCookie);

// Check in ticket for Dr Busy
$resBusyCheckin = doCurl("$baseUrl/api/checkin-patient.php", 'POST', [
    'department_id' => 1,
    'patient_name' => 'Active Queue Patient',
    'appointment_number' => 'APPT-BUSY-' . rand(1000, 9999),
    'gender' => 'Male',
    'weight_kg' => 70,
    'bp' => '120/80',
    'doctor_id' => $busyDocId
], $tempStaffCookie);
$busyTicketId = $resBusyCheckin['json']['ticket_id'] ?? null;

// Attempt to delete Dr. Busy while ticket is waiting -> MUST FAIL (400)
$resDelBusyFail = doCurl("$baseUrl/api/manage-doctors.php", 'POST', [
    'action' => 'delete',
    'id' => $busyDocId
], $adminCookie);
assertTest("18d. Deletion of Doctor with Active/Waiting Tickets Blocked (400)",
    $resDelBusyFail['code'] === 400 && strpos($resDelBusyFail['json']['message'] ?? '', 'active/waiting') !== false,
    "Msg: " . ($resDelBusyFail['json']['message'] ?? '')
);

// Cancel the ticket using doctor cookie
$busyDocCookie = $tmpDir . '/busy_doc_cookie.txt';
doCurl("$baseUrl/api/login.php", 'POST', ['username' => $docBusyUser, 'password' => 'password123'], $busyDocCookie);
doCurl("$baseUrl/api/complete-ticket.php", 'POST', ['ticket_id' => $busyTicketId, 'status' => 'cancelled'], $busyDocCookie);

// Now delete Dr. Busy -> MUST SUCCEED (200)
$resDelBusySuccess = doCurl("$baseUrl/api/manage-doctors.php", 'POST', [
    'action' => 'delete',
    'id' => $busyDocId
], $adminCookie);
assertTest("18e. Deletion of Doctor after tickets resolved succeeds",
    $resDelBusySuccess['code'] === 200 && ($resDelBusySuccess['json']['success'] ?? false) === true,
    "Msg: " . ($resDelBusySuccess['json']['message'] ?? '')
);

// 18f. Soft Delete Original Doctor
$res18DelDoc = doCurl("$baseUrl/api/manage-doctors.php", 'POST', [
    'action' => 'delete',
    'id' => $createdDocId
], $adminCookie);
assertTest("18f. Admin Soft Delete Doctor",
    $res18DelDoc['code'] === 200 && ($res18DelDoc['json']['success'] ?? false) === true,
    "Msg: " . ($res18DelDoc['json']['message'] ?? '')
);

// Deleted doctor cannot log in
$res18DocLoginAfterDel = doCurl("$baseUrl/api/login.php", 'POST', ['username' => $docUsername, 'password' => 'password123']);
assertTest("18g. Deleted Doctor Login Rejected (401)",
    $res18DocLoginAfterDel['code'] === 401,
    "HTTP {$res18DocLoginAfterDel['code']}"
);

// =========================================================================
// TEST 19: phpMyAdmin Connectivity on Port 3307
// =========================================================================
$res19PMA = doCurl("http://127.0.0.1/phpmyadmin/index.php", 'GET');
$pmaAccessible = ($res19PMA['code'] === 200) && (strpos($res19PMA['body'], 'phpMyAdmin') !== false) && (strpos($res19PMA['body'], 'Access denied') === false);
assertTest("19. phpMyAdmin Accessible without 'Access denied' on Port 3307",
    $pmaAccessible,
    "HTTP: {$res19PMA['code']}, Contains phpMyAdmin: " . (strpos($res19PMA['body'], 'phpMyAdmin') !== false ? 'yes' : 'no')
);

// Cleanup
@unlink($adminCookie);
@unlink($docCookie);
@unlink($staffCookie);
@rmdir($tmpDir);

$totalTests = count($results);
$passedTests = count(array_filter($results, fn($r) => $r['status'] === 'PASS'));

echo "\n============================================\n";
echo "ACCEPTANCE TEST SUMMARY: $passedTests / $totalTests PASSED\n";
echo "============================================\n";

if ($passedTests === $totalTests) {
    exit(0);
} else {
    exit(1);
}
