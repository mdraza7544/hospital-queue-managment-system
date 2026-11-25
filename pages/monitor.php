<?php
require_once __DIR__ . '/../includes/db.php';

// Fetch active clinical departments (excluding Admin)
$deptQuery = "SELECT id, name, prefix, window_number FROM departments WHERE name != 'Admin' ORDER BY window_number ASC, id ASC";
$deptResult = $conn->query($deptQuery);
$departments = [];
if ($deptResult) {
    while ($d = $deptResult->fetch_assoc()) {
        $departments[] = $d;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hospital Queue Monitor</title>
    <link rel="stylesheet" href="http://localhost/STI_Queueing-System/css/style.css">
</head>
<body>

    <div class="monitor-container">
        <div class="monitor-header">
            <div class="monitor-title-group">
                <img src="http://localhost/STI_Queueing-System/images/hospital_logo.png" alt="Hospital Logo" class="monitor-logo">
                <div>
                    <h1>Hospital Queue Monitor</h1>
                    <p class="monitor-date" id="date-display">Loading...</p>
                </div>
            </div>
            <div class="monitor-time" id="time-display">--:--:--</div>
        </div>

        <div class="monitor-grid">
            <?php foreach ($departments as $dept): 
                $deptKey = 'dept-' . $dept['id'];
            ?>
            <div class="monitor-column" id="col-<?php echo $deptKey; ?>" data-dept-id="<?php echo $dept['id']; ?>" data-dept-name="<?php echo htmlspecialchars($dept['name']); ?>">
                <div class="column-header">
                    <h2><?php echo htmlspecialchars($dept['name']); ?></h2>
                </div>
                <div class="now-serving" id="<?php echo $deptKey; ?>-box">
                    <div class="serving-label">NOW SERVING</div>
                    <div class="serving-number" id="<?php echo $deptKey; ?>-serving">---</div>
                    <div class="serving-window" id="<?php echo $deptKey; ?>-window" data-default-window="Proceed to Window <?php echo htmlspecialchars($dept['window_number']); ?>">Proceed to Window <?php echo htmlspecialchars($dept['window_number']); ?></div>
                </div>
                <div class="next-in-line">
                    <div class="next-label">NEXT IN LINE</div>
                    <div class="next-list" id="<?php echo $deptKey; ?>-next">
                        <div class="next-item">---</div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="monitor-footer">
            <p>Please watch the screen and proceed to the designated counter window when your ticket number is called.</p>
        </div>
    </div>

    <script src="http://localhost/STI_Queueing-System/js/monitor.js?v=<?php echo time(); ?>"></script>
</body>
</html>
