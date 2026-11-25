<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');

// Role enforcement: Admin only
$admin = requireRole(['admin'], true);

// 1. Overall System Summary
$summaryQuery = "
    SELECT 
        (SELECT COUNT(*) FROM doctors WHERE is_active = 1 AND is_deleted = 0) AS total_active_doctors,
        (SELECT COUNT(*) FROM staff WHERE is_active = 1 AND is_deleted = 0 AND role = 'staff') AS total_active_staff,
        (SELECT COUNT(DISTINCT user_id, user_type) FROM user_sessions WHERE logout_at IS NULL AND last_seen_at >= (NOW() - INTERVAL 90 SECOND)) AS total_online_users,
        (SELECT COUNT(DISTINCT user_id, user_type) FROM attendance WHERE check_out_at IS NULL) AS total_on_duty_users,
        (SELECT COUNT(*) FROM queue_tickets WHERE DATE(created_at) = CURDATE()) AS checked_in_today,
        (SELECT COUNT(*) FROM queue_tickets WHERE status = 'waiting') AS waiting_now,
        (SELECT COUNT(*) FROM queue_tickets WHERE status = 'serving') AS serving_now,
        (SELECT COUNT(*) FROM queue_tickets WHERE status = 'completed' AND DATE(completed_at) = CURDATE()) AS completed_today,
        (SELECT COUNT(*) FROM queue_tickets WHERE status = 'referred' AND DATE(completed_at) = CURDATE()) AS referred_today,
        (SELECT COUNT(*) FROM queue_tickets WHERE status = 'no-show' AND DATE(completed_at) = CURDATE()) AS noshow_today
";
$summaryRes = $conn->query($summaryQuery);
$metrics = $summaryRes->fetch_assoc();

// 2. Department Breakdown
$deptBreakdownQuery = "
    SELECT 
        d.id, 
        d.name, 
        d.window_number,
        (SELECT COUNT(*) FROM doctors doc WHERE doc.department_id = d.id AND doc.is_active = 1 AND doc.is_deleted = 0) AS doctor_count,
        (SELECT COUNT(*) FROM staff s WHERE s.department_id = d.id AND s.is_active = 1 AND s.is_deleted = 0 AND s.role = 'staff') AS staff_count,
        (SELECT COUNT(*) FROM queue_tickets qt WHERE qt.department_id = d.id AND qt.status = 'waiting') AS waiting_count,
        (SELECT COUNT(*) FROM queue_tickets qt WHERE qt.department_id = d.id AND qt.status = 'serving') AS serving_count,
        (SELECT COUNT(*) FROM queue_tickets qt WHERE qt.department_id = d.id AND qt.status = 'completed' AND DATE(qt.completed_at) = CURDATE()) AS completed_today
    FROM departments d
    WHERE d.name != 'Admin'
    ORDER BY d.window_number ASC
";
$deptRes = $conn->query($deptBreakdownQuery);
$departments = [];
while ($dRow = $deptRes->fetch_assoc()) {
    $departments[] = $dRow;
}

// 3. User Attendance & Real-Time Status Roster
// Doctors roster with latest session & duty status
$doctorsRosterQuery = "
    SELECT 
        doc.id,
        doc.full_name,
        doc.username,
        doc.specialization,
        doc.room_label,
        d.name AS department_name,
        'doctor' AS user_type,
        doc.is_active,
        s.login_at,
        s.logout_at,
        s.last_seen_at,
        CASE 
            WHEN s.logout_at IS NULL AND s.last_seen_at >= (NOW() - INTERVAL 90 SECOND) THEN 1 
            ELSE 0 
        END AS is_online,
        CASE 
            WHEN att.id IS NOT NULL THEN 1 
            ELSE 0 
        END AS is_on_duty
    FROM doctors doc
    LEFT JOIN departments d ON doc.department_id = d.id
    LEFT JOIN (
        SELECT us1.*
        FROM user_sessions us1
        INNER JOIN (
            SELECT user_type, user_id, MAX(login_at) AS max_login
            FROM user_sessions
            WHERE user_type = 'doctor'
            GROUP BY user_type, user_id
        ) us2 ON us1.user_type = us2.user_type AND us1.user_id = us2.user_id AND us1.login_at = us2.max_login
    ) s ON s.user_id = doc.id
    LEFT JOIN attendance att ON att.user_type = 'doctor' AND att.user_id = doc.id AND att.check_out_at IS NULL
    WHERE doc.is_deleted = 0
    ORDER BY is_online DESC, doc.full_name ASC
";
$docRosterRes = $conn->query($doctorsRosterQuery);
$doctorsRoster = [];
while ($dr = $docRosterRes->fetch_assoc()) {
    $doctorsRoster[] = $dr;
}

// Staff roster with latest session & duty status
$staffRosterQuery = "
    SELECT 
        st.id,
        st.full_name,
        st.username,
        st.role,
        d.name AS department_name,
        'staff' AS user_type,
        st.is_active,
        s.login_at,
        s.logout_at,
        s.last_seen_at,
        CASE 
            WHEN s.logout_at IS NULL AND s.last_seen_at >= (NOW() - INTERVAL 90 SECOND) THEN 1 
            ELSE 0 
        END AS is_online,
        CASE 
            WHEN att.id IS NOT NULL THEN 1 
            ELSE 0 
        END AS is_on_duty
    FROM staff st
    LEFT JOIN departments d ON st.department_id = d.id
    LEFT JOIN (
        SELECT us1.*
        FROM user_sessions us1
        INNER JOIN (
            SELECT user_type, user_id, MAX(login_at) AS max_login
            FROM user_sessions
            WHERE user_type = 'staff'
            GROUP BY user_type, user_id
        ) us2 ON us1.user_type = us2.user_type AND us1.user_id = us2.user_id AND us1.login_at = us2.max_login
    ) s ON s.user_id = st.id
    LEFT JOIN attendance att ON att.user_type = 'staff' AND att.user_id = st.id AND att.check_out_at IS NULL
    WHERE st.is_deleted = 0
    ORDER BY is_online DESC, st.full_name ASC
";
$staffRosterRes = $conn->query($staffRosterQuery);
$staffRoster = [];
while ($sr = $staffRosterRes->fetch_assoc()) {
    $staffRoster[] = $sr;
}

sendJsonResponse([
    'success' => true,
    'metrics' => $metrics,
    'departments' => $departments,
    'roster' => [
        'doctors' => $doctorsRoster,
        'staff' => $staffRoster
    ]
]);
?>