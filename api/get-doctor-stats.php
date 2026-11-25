<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');

// Role enforcement: Doctor only
$doctor = requireRole(['doctor'], true);
$doctorId = $doctor['user_id'];

// Aggregate consultation stats for this doctor
$statsQuery = "
    SELECT 
        COUNT(CASE WHEN DATE(completed_at) = CURDATE() AND status = 'completed' THEN 1 END) AS completed_today,
        COUNT(CASE WHEN DATE(completed_at) = CURDATE() AND status = 'referred' THEN 1 END) AS referred_today,
        COUNT(CASE WHEN DATE(completed_at) = CURDATE() AND status = 'no-show' THEN 1 END) AS noshow_today,
        COUNT(CASE WHEN DATE(completed_at) = CURDATE() AND status = 'cancelled' THEN 1 END) AS cancelled_today,
        COUNT(CASE WHEN DATE(completed_at) = CURDATE() THEN 1 END) AS total_today,

        COUNT(CASE WHEN YEARWEEK(completed_at, 1) = YEARWEEK(CURDATE(), 1) AND status = 'completed' THEN 1 END) AS completed_week,
        COUNT(CASE WHEN YEARWEEK(completed_at, 1) = YEARWEEK(CURDATE(), 1) AND status = 'referred' THEN 1 END) AS referred_week,
        COUNT(CASE WHEN YEARWEEK(completed_at, 1) = YEARWEEK(CURDATE(), 1) THEN 1 END) AS total_week,

        COUNT(CASE WHEN MONTH(completed_at) = MONTH(CURDATE()) AND YEAR(completed_at) = YEAR(CURDATE()) AND status = 'completed' THEN 1 END) AS completed_month,
        COUNT(CASE WHEN MONTH(completed_at) = MONTH(CURDATE()) AND YEAR(completed_at) = YEAR(CURDATE()) AND status = 'referred' THEN 1 END) AS referred_month,
        COUNT(CASE WHEN MONTH(completed_at) = MONTH(CURDATE()) AND YEAR(completed_at) = YEAR(CURDATE()) THEN 1 END) AS total_month,

        COUNT(CASE WHEN status = 'completed' THEN 1 END) AS completed_total,
        COUNT(CASE WHEN status = 'referred' THEN 1 END) AS referred_total,
        COUNT(*) AS total_all_time
    FROM queue_tickets
    WHERE doctor_id = ? AND status IN ('completed', 'referred', 'no-show', 'cancelled')
";

$stmt = $conn->prepare($statsQuery);
$stmt->bind_param("i", $doctorId);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();

sendJsonResponse([
    'success' => true,
    'stats' => $stats
]);
?>