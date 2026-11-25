<?php
require_once __DIR__ . '/../includes/auth.php';

// Strict Role Check: Staff only
requireRole('staff', false);

$staffId = intval($_SESSION['staff_id'] ?? 0);
$staffName = $_SESSION['staff_name'] ?? 'Staff Member';
$departmentName = $_SESSION['department_name'] ?? 'Department';
$departmentId = intval($_SESSION['department_id'] ?? 0);
$windowNumber = intval($_SESSION['window_number'] ?? $departmentId);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Check-In Portal - Hospital Queueing System</title>
    <link rel="stylesheet" href="/STI_Queueing-System/css/style.css?v=<?php echo time(); ?>">
</head>
<body data-user-role="staff" data-department-id="<?php echo $departmentId; ?>" data-department-name="<?php echo htmlspecialchars($departmentName); ?>" data-staff-id="<?php echo $staffId; ?>" data-staff-name="<?php echo htmlspecialchars($staffName); ?>" data-window-number="<?php echo $windowNumber; ?>">
    <div class="dashboard-container">
        <!-- Navigation Bar -->
        <nav class="navbar">
            <div class="navbar-left">
                <h2>Hospital Check-In Desk | <span><?php echo htmlspecialchars($staffName); ?></span> (<?php echo htmlspecialchars($departmentName); ?> - Window <?php echo $windowNumber; ?>)</h2>
            </div>
            <div class="navbar-right">
                <a href="/STI_Queueing-System/attendance" class="duty-nav-widget" id="headerDutyWidget" title="Click to Check In/Out for Duty" style="text-decoration: none;">
                    <span id="headerDutyBadge" class="badge-offduty">⚪ Off Duty</span>
                </a>
                <button class="btn-nav active" onclick="showTab('checkin')" id="btnCheckinTab">
                    ➕ Check-In
                </button>
                <button class="btn-nav" onclick="showTab('history')" id="btnHistoryTab">
                    📊 My Check-Ins
                </button>
                <button class="btn-nav" onclick="showTab('profile')" id="btnProfileTab">
                    👤 My Profile
                </button>
                <button class="btn-logout" onclick="logout()">Logout</button>
            </div>
        </nav>

        <!-- Main Dashboard Content -->
        <main class="dashboard-main">
            <!-- 1. Patient Check-In Tab (Staff Only) -->
            <div id="checkinTab" class="tab-content active">
                <div class="panel checkin-panel" style="max-width: 800px; margin: 0 auto;">
                    <div style="border-bottom: 2px solid #2980b9; padding-bottom: 12px; margin-bottom: 20px;">
                        <h3 style="color: #1a5276; margin: 0; font-size: 20px;">➕ Patient Check-In & Queue Registration</h3>
                        <p style="color: #666; margin: 6px 0 0 0; font-size: 14px;">
                            Department Counter: <strong><?php echo htmlspecialchars($departmentName); ?></strong> (Window <?php echo $windowNumber; ?>)
                        </p>
                    </div>

                    <form id="checkInForm" onsubmit="handleCheckIn(event)">
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                            <!-- Patient Name -->
                            <div class="form-group" style="grid-column: span 2;">
                                <label for="checkInPatientName">Patient Full Name <span class="required">*</span></label>
                                <input type="text" id="checkInPatientName" class="form-input" required placeholder="Enter patient's full name">
                            </div>

                            <!-- Appointment Number -->
                            <div class="form-group">
                                <label for="checkInApptNumber">Appointment Number <span class="required">*</span></label>
                                <input type="text" id="checkInApptNumber" class="form-input" required placeholder="e.g. APPT-2026-001">
                            </div>

                            <!-- Gender -->
                            <div class="form-group">
                                <label for="checkInGender">Gender <span class="required">*</span></label>
                                <select id="checkInGender" class="form-input" required>
                                    <option value="">-- Select Gender --</option>
                                    <option value="Male">Male</option>
                                    <option value="Female">Female</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>

                            <!-- Age -->
                            <div class="form-group">
                                <label for="checkInAge">Age (Years) <small style="color: #888;">(Optional)</small></label>
                                <input type="number" id="checkInAge" class="form-input" min="0" max="120" placeholder="0 - 120">
                            </div>

                            <!-- Weight (kg) -->
                            <div class="form-group">
                                <label for="checkInWeight">Weight (kg) <span class="required">*</span></label>
                                <input type="number" id="checkInWeight" class="form-input" step="0.1" min="0.5" max="499.9" required placeholder="e.g. 68.5">
                            </div>

                            <!-- Blood Pressure -->
                            <div class="form-group">
                                <label for="checkInBp">Blood Pressure (Systolic/Diastolic) <span class="required">*</span></label>
                                <input type="text" id="checkInBp" class="form-input" required placeholder="e.g. 120/80" pattern="^\d{2,3}/\d{2,3}$" title="Format: 120/80">
                                <small class="form-hint">Format: 120/80 (Systolic must be higher than diastolic)</small>
                            </div>

                            <!-- Attending Doctor (MANDATORY) -->
                            <div class="form-group">
                                <label for="checkInDoctor">Assigned Doctor <span class="required">*</span></label>
                                <select id="checkInDoctor" class="form-input" required>
                                    <option value="">-- Loading Doctors --</option>
                                </select>
                                <small class="form-hint">Select the active consulting physician for this appointment</small>
                            </div>

                            <!-- Priority Flag -->
                            <div class="form-group" style="grid-column: span 2; background: #fff8e1; padding: 14px; border-radius: 8px; border: 1px solid #ffe082;">
                                <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; font-weight: bold; color: #b78103; margin: 0;">
                                    <input type="checkbox" id="checkInPriority" style="width: 18px; height: 18px;">
                                    ⚡ Priority Patient (Emergency, Senior Citizen, PWD, Pregnant)
                                </label>
                            </div>
                        </div>

                        <div class="form-actions" style="margin-top: 24px;">
                            <button type="submit" class="btn-submit" id="btnCheckInSubmit" style="background: #27ae60; font-size: 15px; padding: 12px 24px;">
                                ✓ Check In Patient & Issue Ticket
                            </button>
                            <button type="button" class="btn-clear" onclick="clearCheckInForm()">
                                ↺ Reset Form
                            </button>
                        </div>

                        <div id="checkInMessage" class="form-message" style="margin-top: 15px; display: none;"></div>

                        <!-- Ticket Generation Card (shows after successful checkin) -->
                        <div id="ticketSuccessCard" style="display: none; margin-top: 24px; padding: 24px; background: #e8f8f5; border: 2px dashed #2ecc71; border-radius: 10px; text-align: center;">
                            <h4 style="color: #27ae60; margin: 0 0 10px 0; font-size: 20px;">🎉 Ticket Successfully Issued!</h4>
                            <div style="font-size: 44px; font-weight: bold; color: #16a085; letter-spacing: 2px;" id="issuedTicketNumber">---</div>
                            <div style="font-size: 16px; color: #2c3e50; margin-top: 8px; font-weight: 600;" id="issuedTicketDept">---</div>
                            <div style="font-size: 14px; color: #555; margin-top: 4px;" id="issuedTicketDoctor">---</div>
                            <p style="font-size: 13px; color: #666; margin-top: 12px;">Please instruct the patient to proceed to the consultation waiting area and listen for their ticket number on the queue monitor.</p>
                            <button type="button" class="btn-filter" style="margin-top: 10px;" onclick="resetForNextPatient()">Issue Next Ticket</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- 2. Staff Check-Ins Metrics Tab (Counts Only) -->
            <div id="historyTab" class="tab-content">
                <div class="panel" style="max-width: 900px; margin: 0 auto;">
                    <div style="border-bottom: 2px solid #2980b9; padding-bottom: 12px; margin-bottom: 24px;">
                        <h3 style="color: #1a5276; margin: 0; font-size: 20px;">📊 My Patient Check-In Summary</h3>
                        <p style="color: #666; margin: 6px 0 0 0; font-size: 14px;">
                            Summary of queue registrations performed by <strong><?php echo htmlspecialchars($staffName); ?></strong>
                        </p>
                    </div>

                    <!-- Metric Cards -->
                    <div class="metric-cards-grid">
                        <div class="stat-card primary">
                            <div class="stat-label">Today's Check-Ins</div>
                            <div class="stat-value" id="statTodayCount">0</div>
                            <div class="stat-subtext">Patients registered today</div>
                        </div>
                        <div class="stat-card info">
                            <div class="stat-label">This Week</div>
                            <div class="stat-value" id="statWeekCount">0</div>
                            <div class="stat-subtext">Last 7 days</div>
                        </div>
                        <div class="stat-card purple">
                            <div class="stat-label">This Month</div>
                            <div class="stat-value" id="statMonthCount">0</div>
                            <div class="stat-subtext">Current calendar month</div>
                        </div>
                        <div class="stat-card success">
                            <div class="stat-label">All-Time Total</div>
                            <div class="stat-value" id="statTotalCount">0</div>
                            <div class="stat-subtext">Total patients registered</div>
                        </div>
                    </div>

                    <!-- Custom Range Count Card -->
                    <div style="background: #f8f9fa; border: 1px solid #e1e8ed; border-radius: 8px; padding: 20px; margin-top: 20px;">
                        <h4 style="margin: 0 0 12px 0; color: #2c3e50;">Filter by Custom Date Range</h4>
                        <div style="display: flex; flex-wrap: wrap; gap: 12px; align-items: flex-end;">
                            <div class="filter-group">
                                <label style="display: block; font-size: 12px; color: #666; margin-bottom: 4px;">Start Date:</label>
                                <input type="date" id="rangeStart" class="filter-input">
                            </div>
                            <div class="filter-group">
                                <label style="display: block; font-size: 12px; color: #666; margin-bottom: 4px;">End Date:</label>
                                <input type="date" id="rangeEnd" class="filter-input">
                            </div>
                            <button class="btn-filter" onclick="loadCustomRangeStats()">🔍 Calculate Count</button>
                        </div>
                        <div id="customRangeResult" style="margin-top: 15px; font-size: 16px; color: #1a5276; display: none;">
                            Check-ins in selected period: <strong id="customRangeCount" style="font-size: 20px;">0</strong>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 3. Profile Tab -->
            <div id="profileTab" class="tab-content">
                <div class="profile-card">
                    <div style="text-align: center; margin-bottom: 20px;">
                        <div class="profile-avatar" style="margin: 0 auto 15px auto;">
                            <?php echo strtoupper(substr($staffName, 0, 1)); ?>
                        </div>
                        <h2 style="margin: 0; color: #1a5276;"><?php echo htmlspecialchars($staffName); ?></h2>
                        <span class="badge-role role-staff" style="margin-top: 6px; display: inline-block;">Hospital Desk Staff</span>
                    </div>

                    <div class="profile-info-grid">
                        <div class="profile-info-item">
                            <label>Username</label>
                            <span>@<?php echo htmlspecialchars($_SESSION['username'] ?? 'staff'); ?></span>
                        </div>
                        <div class="profile-info-item">
                            <label>Assigned Department</label>
                            <span><?php echo htmlspecialchars($departmentName); ?></span>
                        </div>
                        <div class="profile-info-item">
                            <label>Counter Window</label>
                            <span>Window <?php echo $windowNumber; ?></span>
                        </div>
                        <div class="profile-info-item">
                            <label>Account Status</label>
                            <span class="badge-active">Active</span>
                        </div>
                        <div class="profile-info-item" style="grid-column: span 2;">
                            <label>Current Session Started</label>
                            <span><?php echo date('Y-m-d h:i A'); ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="/STI_Queueing-System/js/staff-dashboard.js?v=<?php echo time(); ?>"></script>
</body>
</html>
