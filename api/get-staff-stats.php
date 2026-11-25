<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');

// Role enforcement: Staff only
$staff = requireRole(['staff'], true);
$staffId = $staff['user_id'];

$startDate = $_GET['start_date'] ?? null;
$endDate = $_GET['end_date'] ?? null;

// Base aggregate query
$query = "
    SELECT 
        COUNT(CASE WHEN DATE(created_at) = CURDATE() THEN 1 END) AS count_today,
        COUNT(CASE WHEN YEARWEEK(created_at, 1) = YEARWEEK(CURDATE(), 1) THEN 1 END) AS count_week,
        COUNT(CASE WHEN MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE()) THEN 1 END) AS count_month,
        COUNT(*) AS count_total
    FROM queue_tickets
    WHERE checked_in_by_staff_id = ?
";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $staffId);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();

// Optional custom date range filter count
$customCount = null;
if (!empty($startDate) || !empty($endDate)) {
    $rangeQuery = "SELECT COUNT(*) AS range_count FROM queue_tickets WHERE checked_in_by_staff_id = ?";
    $params = [$staffId];
    $types = "i";

    if (!empty($startDate)) {
        $rangeQuery .= " AND DATE(created_at) >= ?";
        $params[] = $startDate;
        $types .= "s";
    }
    if (!empty($endDate)) {
        $rangeQuery .= " AND DATE(created_at) <= ?";
        $params[] = $endDate;
        $types .= "s";
    }

    $rangeStmt = $conn->prepare($rangeQuery);
    $rangeStmt->bind_param($types, ...$params);
    $rangeStmt->execute();
    $rangeRes = $rangeStmt->get_result()->fetch_assoc();
    $customCount = intval($rangeRes['range_count'] ?? 0);
}

sendJsonResponse([
    'success' => true,
    'counts' => [
        'today' => intval($stats['count_today'] ?? 0),
        'week' => intval($stats['count_week'] ?? 0),
        'month' => intval($stats['count_month'] ?? 0),
        'total' => intval($stats['count_total'] ?? 0),
        'custom_range' => $customCount
    ],
    'stats' => [
        'today_count' => intval($stats['count_today'] ?? 0),
        'week_count' => intval($stats['count_week'] ?? 0),
        'month_count' => intval($stats['count_month'] ?? 0),
        'total_count' => intval($stats['count_total'] ?? 0),
        'custom_range_count' => $customCount
    ]
]);
?>