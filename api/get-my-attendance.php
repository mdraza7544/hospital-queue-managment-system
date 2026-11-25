<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');

$user = requireRole(['staff', 'doctor'], true);
$userType = $user['user_type'];
$userId = $user['user_id'];

// 1. Auto-close any unclosed check-in from prior days lazily
$autoCloseStmt = $conn->prepare("
    UPDATE attendance 
    SET auto_closed = 1, 
        check_out_at = COALESCE(
            (SELECT last_seen_at FROM user_sessions WHERE user_type = ? AND user_id = ? AND DATE(login_at) = attendance.work_date ORDER BY id DESC LIMIT 1),
            CONCAT(work_date, ' 18:00:00')
        )
    WHERE user_type = ? AND user_id = ? AND work_date < CURDATE() AND check_out_at IS NULL
");
$autoCloseStmt->bind_param("sisi", $userType, $userId, $userType, $userId);
$autoCloseStmt->execute();

// 2. Check current open record
$openStmt = $conn->prepare("
    SELECT id, check_in_at, work_date 
    FROM attendance 
    WHERE user_type = ? AND user_id = ? AND check_out_at IS NULL 
    ORDER BY id DESC LIMIT 1
");
$openStmt->bind_param("si", $userType, $userId);
$openStmt->execute();
$openRecord = $openStmt->get_result()->fetch_assoc();

$isOnDuty = !empty($openRecord);
$serverNow = date('Y-m-d H:i:s');
$checkInAt = $openRecord['check_in_at'] ?? null;
$elapsedSeconds = 0;
if ($isOnDuty && $checkInAt) {
    $elapsedSeconds = max(0, time() - strtotime($checkInAt));
}

// 3. Compute Worked Totals (Today, This Week, This Month)
// Completed duty time
$totalsStmt = $conn->prepare("
    SELECT 
        COALESCE(SUM(CASE WHEN work_date = CURDATE() THEN TIMESTAMPDIFF(SECOND, check_in_at, check_out_at) ELSE 0 END), 0) AS today_closed_seconds,
        COALESCE(SUM(CASE WHEN YEARWEEK(work_date, 1) = YEARWEEK(CURDATE(), 1) THEN TIMESTAMPDIFF(SECOND, check_in_at, check_out_at) ELSE 0 END), 0) AS week_closed_seconds,
        COALESCE(SUM(CASE WHEN MONTH(work_date) = MONTH(CURDATE()) AND YEAR(work_date) = YEAR(CURDATE()) THEN TIMESTAMPDIFF(SECOND, check_in_at, check_out_at) ELSE 0 END), 0) AS month_closed_seconds
    FROM attendance
    WHERE user_type = ? AND user_id = ? AND check_out_at IS NOT NULL
");
$totalsStmt->bind_param("si", $userType, $userId);
$totalsStmt->execute();
$totalsRow = $totalsStmt->get_result()->fetch_assoc();

$todaySeconds = intval($totalsRow['today_closed_seconds'] ?? 0) + ($isOnDuty ? $elapsedSeconds : 0);
$weekSeconds = intval($totalsRow['week_closed_seconds'] ?? 0) + ($isOnDuty ? $elapsedSeconds : 0);
$monthSeconds = intval($totalsRow['month_closed_seconds'] ?? 0) + ($isOnDuty ? $elapsedSeconds : 0);

function formatSeconds($sec) {
    $hrs = floor($sec / 3600);
    $mins = floor(($sec % 3600) / 60);
    return sprintf('%02dh %02dm', $hrs, $mins);
}

// 4. Fetch Paginated Personal Log
$startDate = $_GET['startDate'] ?? ($_GET['start_date'] ?? null);
$endDate = $_GET['endDate'] ?? ($_GET['end_date'] ?? null);
$page = max(1, intval($_GET['page'] ?? 1));
$limit = min(100, max(1, intval($_GET['limit'] ?? 20)));
$offset = ($page - 1) * $limit;

$logQuery = "
    SELECT id, work_date, check_in_at, check_out_at, auto_closed,
           TIMESTAMPDIFF(SECOND, check_in_at, COALESCE(check_out_at, NOW())) AS duration_seconds
    FROM attendance
    WHERE user_type = ? AND user_id = ?
";
$params = [$userType, $userId];
$types = "si";

if (!empty($startDate)) {
    $logQuery .= " AND work_date >= ?";
    $params[] = $startDate;
    $types .= "s";
}
if (!empty($endDate)) {
    $logQuery .= " AND work_date <= ?";
    $params[] = $endDate;
    $types .= "s";
}

// Count total
$countQuery = "SELECT COUNT(*) AS total FROM ($logQuery) AS t";
$countStmt = $conn->prepare($countQuery);
$countStmt->bind_param($types, ...$params);
$countStmt->execute();
$totalLogs = intval($countStmt->get_result()->fetch_assoc()['total'] ?? 0);

// Paginated query
$logQuery .= " ORDER BY id DESC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;
$types .= "ii";

$stmt = $conn->prepare($logQuery);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$res = $stmt->get_result();

$records = [];
while ($r = $res->fetch_assoc()) {
    $durSec = intval($r['duration_seconds']);
    $isOpen = empty($r['check_out_at']);
    $statusText = $isOpen ? 'On Duty' : ($r['auto_closed'] ? 'Auto-Closed' : 'Completed');

    $records[] = [
        'id' => intval($r['id']),
        'work_date' => $r['work_date'],
        'check_in_at' => $r['check_in_at'],
        'check_out_at' => $r['check_out_at'],
        'is_open' => $isOpen,
        'auto_closed' => intval($r['auto_closed']) === 1,
        'status' => $statusText,
        'duration_seconds' => $durSec,
        'duration_formatted' => formatSeconds($durSec)
    ];
}

sendJsonResponse([
    'success' => true,
    'server_now' => $serverNow,
    'is_on_duty' => $isOnDuty,
    'check_in_at' => $checkInAt,
    'elapsed_seconds' => $elapsedSeconds,
    'today_seconds' => $todaySeconds,
    'today_formatted' => formatSeconds($todaySeconds),
    'week_seconds' => $weekSeconds,
    'week_formatted' => formatSeconds($weekSeconds),
    'month_seconds' => $monthSeconds,
    'month_formatted' => formatSeconds($monthSeconds),
    'totals' => [
        'today_seconds' => $todaySeconds,
        'today_formatted' => formatSeconds($todaySeconds),
        'week_seconds' => $weekSeconds,
        'week_formatted' => formatSeconds($weekSeconds),
        'month_seconds' => $monthSeconds,
        'month_formatted' => formatSeconds($monthSeconds)
    ],
    'records' => $records,
    'total' => $totalLogs,
    'page' => $page,
    'limit' => $limit
]);
?>
