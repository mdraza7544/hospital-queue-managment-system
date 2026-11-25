<?php
require_once __DIR__ . '/../includes/auth.php';

// Only staff and doctors have duty attendance
$user = requireRole(['staff', 'doctor'], false);

$userType = $user['user_type'];
$userName = $user['full_name'];
$userRole = $user['role'];
$departmentName = $user['department_name'] ?? 'Department';
$dashboardUrl = ($userRole === 'doctor') ? '/STI_Queueing-System/doctor-dashboard' : '/STI_Queueing-System/staff-dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Duty Attendance - Hospital Queueing System</title>
    <link rel="stylesheet" href="/STI_Queueing-System/css/style.css?v=<?php echo time(); ?>">
</head>
<body data-user-role="<?php echo htmlspecialchars($userRole); ?>" data-user-type="<?php echo htmlspecialchars($userType); ?>">
    <div class="dashboard-container">
        <!-- Navigation Bar -->
        <nav class="navbar">
            <div class="navbar-left">
                <h2>⏱️ Duty Attendance | <span style="color: #f3c500; font-weight: normal;"><?php echo htmlspecialchars($userName); ?></span> <span style="font-size: 13px; font-weight: normal; color: #fff;">(<?php echo htmlspecialchars($departmentName); ?>)</span></h2>
            </div>
            <div class="navbar-right">
                <a href="<?php echo $dashboardUrl; ?>" class="btn-nav" style="text-decoration: none; display: flex; align-items: center; gap: 6px;">
                    ← Back to Dashboard
                </a>
                <button class="btn-logout" onclick="logout()">Logout</button>
            </div>
        </nav>

        <!-- Main Content -->
        <main class="dashboard-main" style="max-width: 1000px; margin: 0 auto;">
            <!-- Duty Check-In / Check-Out Hero Card -->
            <div class="duty-hero-card">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                    <span style="font-size: 14px; color: #666; font-weight: 600; text-transform: uppercase;">Duty Status</span>
                    <span id="dutyStatusBadge" class="badge-offduty">⚪ Off Duty</span>
                </div>

                <div class="duty-timer-display" id="dutyTimerDisplay">00:00:00</div>
                <div style="font-size: 13px; color: #7f8c8d;" id="dutySubtitle">Press Check In to start your duty shift</div>

                <div class="duty-actions-row">
                    <button class="btn-duty-checkin" id="btnDutyCheckin" onclick="handleCheckIn()">
                        ✓ Check In for Duty
                    </button>
                    <button class="btn-duty-checkout" id="btnDutyCheckout" onclick="handleCheckOut()" disabled>
                        ⏹ Check Out (End Duty)
                    </button>
                </div>

                <div id="dutyActionMsg" class="form-message" style="display: none; margin-top: 16px;"></div>
            </div>

            <!-- Worked Time Summary Metric Cards -->
            <div class="metric-cards-grid" style="grid-template-columns: repeat(3, 1fr); margin-bottom: 30px;">
                <div class="stat-card primary">
                    <div class="stat-label">Today's Duty Time</div>
                    <div class="stat-value" id="statTodayHours">00h 00m</div>
                    <div class="stat-subtext">Total hours worked today</div>
                </div>
                <div class="stat-card info">
                    <div class="stat-label">This Week</div>
                    <div class="stat-value" id="statWeekHours">00h 00m</div>
                    <div class="stat-subtext">Current week total</div>
                </div>
                <div class="stat-card purple">
                    <div class="stat-label">This Month</div>
                    <div class="stat-value" id="statMonthHours">00h 00m</div>
                    <div class="stat-subtext">Current calendar month</div>
                </div>
            </div>

            <!-- Personal Attendance Log History -->
            <div class="history-container">
                <div class="history-header">
                    <h3>📋 My Duty Attendance Log</h3>
                    <p class="dept-info">Self check-in and check-out timesheet records</p>
                </div>

                <!-- Filters Section -->
                <div class="history-filters">
                    <div class="filter-group">
                        <label>Date Range:</label>
                        <input type="date" id="attFilterStart" class="filter-input">
                        <span>to</span>
                        <input type="date" id="attFilterEnd" class="filter-input">
                    </div>
                    <div class="filter-actions">
                        <button class="btn-filter" onclick="applyFilters()">🔍 Apply</button>
                        <button class="btn-filter-reset" onclick="resetFilters()">↺ Reset</button>
                    </div>
                </div>

                <!-- Table -->
                <div class="history-table-container">
                    <table class="history-table">
                        <thead>
                            <tr>
                                <th>Work Date</th>
                                <th>Check-In Time</th>
                                <th>Check-Out Time</th>
                                <th>Total Duration</th>
                                <th>Duty Status</th>
                            </tr>
                        </thead>
                        <tbody id="attendanceTableBody">
                            <tr><td colspan="5" class="loading">Loading attendance records...</td></tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="pagination">
                    <button class="btn-page" onclick="loadPrevPage()" id="btnPrevPage">← Previous</button>
                    <span id="pageInfo">Page 1 of 1</span>
                    <button class="btn-page" onclick="loadNextPage()" id="btnNextPage">Next →</button>
                </div>
            </div>
        </main>
    </div>

    <script src="/STI_Queueing-System/js/attendance.js?v=<?php echo time(); ?>"></script>
    <script>
        function logout() {
            if (window.isOnDuty) {
                if (!confirm('You are currently marked On Duty. Remember to check out before leaving! Are you sure you want to log out?')) {
                    return;
                }
            } else {
                if (!confirm('Are you sure you want to log out?')) return;
            }
            window.location.href = '/STI_Queueing-System/api/logout.php';
        }
    </script>
</body>
</html>
