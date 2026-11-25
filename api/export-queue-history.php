<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';

// Role enforcement: Admin or Doctor only
$user = requireRole(['admin', 'doctor'], false);
$role = $user['role'];

// Get export format and filters
$format = $_GET['format'] ?? 'csv'; // csv or pdf
$startDate = $_GET['start_date'] ?? null;
$endDate = $_GET['end_date'] ?? null;
$status = $_GET['status'] ?? null;
$searchQuery = isset($_GET['search']) ? trim($_GET['search']) : null;
$deptFilter = isset($_GET['department_id']) && $_GET['department_id'] !== '' ? intval($_GET['department_id']) : null;
$docFilter = isset($_GET['doctor_id']) && $_GET['doctor_id'] !== '' ? intval($_GET['doctor_id']) : null;

// Build query
$query = "
    SELECT 
        qt.ticket_number,
        qt.patient_name,
        qt.appointment_number,
        qt.age,
        qt.gender,
        qt.weight_kg,
        qt.bp,
        qt.status,
        qt.created_at,
        qt.called_at,
        qt.completed_at,
        d.name as department,
        d.window_number,
        s.full_name as staff_name,
        doc.full_name as doctor_name,
        doc.room_label,
        rd.name as referred_to_department,
        rdoc.full_name as referred_to_doctor
    FROM queue_tickets qt
    LEFT JOIN departments d ON qt.department_id = d.id
    LEFT JOIN staff s ON qt.staff_id = s.id
    LEFT JOIN doctors doc ON qt.doctor_id = doc.id
    LEFT JOIN departments rd ON qt.referred_to_department_id = rd.id
    LEFT JOIN doctors rdoc ON qt.referred_to_doctor_id = rdoc.id
    WHERE 1=1
";

$params = [];
$types = "";

// Scoping
if ($role === 'doctor') {
    $query .= " AND qt.doctor_id = ?";
    $params[] = $user['user_id'];
    $types .= "i";
} elseif ($role === 'admin') {
    if ($deptFilter) {
        $query .= " AND qt.department_id = ?";
        $params[] = $deptFilter;
        $types .= "i";
    }
    if ($docFilter) {
        $query .= " AND qt.doctor_id = ?";
        $params[] = $docFilter;
        $types .= "i";
    }
}

// Apply filters
if (!empty($startDate)) {
    $query .= " AND DATE(qt.created_at) >= ?";
    $params[] = $startDate;
    $types .= "s";
}

if (!empty($endDate)) {
    $query .= " AND DATE(qt.created_at) <= ?";
    $params[] = $endDate;
    $types .= "s";
}

if (!empty($status) && $status !== 'all') {
    $query .= " AND qt.status = ?";
    $params[] = $status;
    $types .= "s";
}

if (!empty($searchQuery)) {
    $query .= " AND (qt.ticket_number LIKE ? OR qt.patient_name LIKE ? OR qt.appointment_number LIKE ?)";
    $searchParam = "%{$searchQuery}%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
    $types .= "sss";
}

$query .= " ORDER BY qt.created_at DESC";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// Export as CSV
if ($format === 'csv') {
    $scopeName = ($role === 'doctor') ? "doctor_{$user['username']}" : "hospital_all";
    $filename = "queue_history_{$scopeName}_" . date('Y-m-d_His') . ".csv";
    
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    
    fputcsv($output, [
        'Queue Number',
        'Patient Name',
        'Appointment #',
        'Age',
        'Gender',
        'Weight (kg)',
        'Blood Pressure',
        'Doctor',
        'Room / Window',
        'Department',
        'Date Issued',
        'Time Called',
        'Time Completed',
        'Status',
        'Staff',
        'Referred To'
    ]);
    
    while ($row = $result->fetch_assoc()) {
        $referredTo = $row['referred_to_department'] 
            ? $row['referred_to_department'] . ($row['referred_to_doctor'] ? ' (' . $row['referred_to_doctor'] . ')' : '') 
            : ($row['referred_to_doctor'] ?? 'N/A');

        fputcsv($output, [
            $row['ticket_number'],
            $row['patient_name'],
            $row['appointment_number'] ?? 'N/A',
            $row['age'] ?? 'N/A',
            $row['gender'] ?? 'N/A',
            $row['weight_kg'] ?? 'N/A',
            $row['bp'] ?? 'N/A',
            $row['doctor_name'] ?? 'N/A',
            $row['room_label'] ?? "Window {$row['window_number']}",
            $row['department'],
            $row['created_at'],
            $row['called_at'] ?? 'N/A',
            $row['completed_at'] ?? 'N/A',
            strtoupper($row['status']),
            $row['staff_name'] ?? 'N/A',
            $referredTo
        ]);
    }
    
    fclose($output);
    exit;
}

// Export as Printable HTML / PDF
if ($format === 'pdf') {
    $titleScope = ($role === 'doctor') ? "Dr. {$user['full_name']} Queue History" : "Hospital Master Queue History";
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title><?php echo htmlspecialchars($titleScope); ?></title>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; color: #2c3e50; }
            h1 { color: #1a5276; border-bottom: 2px solid #2980b9; padding-bottom: 8px; margin-bottom: 6px; }
            .info { margin-bottom: 15px; color: #555; font-size: 13px; }
            table { width: 100%; border-collapse: collapse; margin-top: 15px; }
            th, td { border: 1px solid #ddd; padding: 7px; text-align: left; font-size: 11px; }
            th { background-color: #2980b9; color: white; }
            tr:nth-child(even) { background-color: #f8f9fa; }
            .status { padding: 3px 6px; border-radius: 4px; font-weight: bold; text-transform: uppercase; font-size: 10px; }
            .status-completed { background: #d4edda; color: #155724; }
            .status-cancelled { background: #f8d7da; color: #721c24; }
            .status-no-show { background: #fff3cd; color: #856404; }
            .status-serving { background: #cfe2ff; color: #084298; }
            .status-referred { background: #e2d9f3; color: #59359a; }
            @media print { body { margin: 0; } button { display: none; } }
        </style>
    </head>
    <body>
        <h1><?php echo htmlspecialchars($titleScope); ?></h1>
        <div class="info">
            <p><strong>Generated:</strong> <?php echo date('F d, Y h:i A'); ?></p>
            <?php if ($startDate || $endDate): ?>
                <p><strong>Date Range:</strong> 
                    <?php echo $startDate ? date('M d, Y', strtotime($startDate)) : 'Beginning'; ?> - 
                    <?php echo $endDate ? date('M d, Y', strtotime($endDate)) : 'Present'; ?>
                </p>
            <?php endif; ?>
        </div>
        
        <button onclick="window.print()" style="padding: 8px 16px; background: #2980b9; color: white; border: none; border-radius: 4px; cursor: pointer; margin-bottom: 10px;">
            🖨️ Print / Save as PDF
        </button>
        
        <table>
            <thead>
                <tr>
                    <th>Queue #</th>
                    <th>Patient Name</th>
                    <th>Appt #</th>
                    <th>Age / Gender</th>
                    <th>BP / Weight</th>
                    <th>Doctor</th>
                    <th>Dept / Room</th>
                    <th>Date Issued</th>
                    <th>Called</th>
                    <th>Completed</th>
                    <th>Status</th>
                    <th>Referred To</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $result->fetch_assoc()): 
                    $referredTo = $row['referred_to_department'] 
                        ? $row['referred_to_department'] . ($row['referred_to_doctor'] ? ' (' . $row['referred_to_doctor'] . ')' : '') 
                        : ($row['referred_to_doctor'] ?? 'N/A');
                ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($row['ticket_number']); ?></strong></td>
                    <td><?php echo htmlspecialchars($row['patient_name']); ?></td>
                    <td><?php echo htmlspecialchars($row['appointment_number'] ?? 'N/A'); ?></td>
                    <td><?php echo ($row['age'] !== null ? $row['age'] . 'y' : 'N/A') . ' / ' . ($row['gender'] ?? 'N/A'); ?></td>
                    <td><?php echo ($row['bp'] ?? 'N/A') . ' / ' . ($row['weight_kg'] ? $row['weight_kg'] . 'kg' : 'N/A'); ?></td>
                    <td><?php echo htmlspecialchars($row['doctor_name'] ?? 'N/A'); ?></td>
                    <td><?php echo htmlspecialchars($row['department'] . ' (' . ($row['room_label'] ?: 'W' . $row['window_number']) . ')'); ?></td>
                    <td><?php echo date('M d, Y h:i A', strtotime($row['created_at'])); ?></td>
                    <td><?php echo $row['called_at'] ? date('h:i A', strtotime($row['called_at'])) : 'N/A'; ?></td>
                    <td><?php echo $row['completed_at'] ? date('h:i A', strtotime($row['completed_at'])) : 'N/A'; ?></td>
                    <td>
                        <span class="status status-<?php echo htmlspecialchars($row['status']); ?>">
                            <?php echo strtoupper($row['status']); ?>
                        </span>
                    </td>
                    <td><?php echo htmlspecialchars($referredTo); ?></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </body>
    </html>
    <?php
    exit;
}
?>