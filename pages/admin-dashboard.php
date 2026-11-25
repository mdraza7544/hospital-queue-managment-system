<?php
require_once __DIR__ . '/../includes/auth.php';

// Strict Role Check: Admin only
requireRole('admin', false);

$adminName = $_SESSION['staff_name'] ?? 'Administrator';
$adminUsername = $_SESSION['username'] ?? 'admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Hospital Queueing System</title>
    <link rel="stylesheet" href="/STI_Queueing-System/css/style.css?v=<?php echo time(); ?>">
</head>
<body data-user-role="admin">
    <div class="dashboard-container">
        <!-- Navigation Bar -->
        <nav class="navbar">
            <div class="navbar-left">
                <h2>Hospital Administration | <span style="font-weight: normal; color: #f3c500;"><?php echo htmlspecialchars($adminName); ?></span></h2>
            </div>
            <div class="navbar-right">
                <button class="btn-nav active" onclick="showTab('overview')" id="btnOverviewTab">
                    📊 Overview
                </button>
                <button class="btn-nav" onclick="showTab('history')" id="btnHistoryTab">
                    📋 Patient History
                </button>
                <button class="btn-nav" onclick="showTab('staff')" id="btnStaffTab">
                    👥 Staff
                </button>
                <button class="btn-nav" onclick="showTab('doctors')" id="btnDoctorsTab">
                    🩺 Doctors
                </button>
                <button class="btn-nav" onclick="showTab('attendance')" id="btnAttendanceTab">
                    ⏱️ Attendance
                </button>
                <button class="btn-logout" onclick="logout()">Logout</button>
            </div>
        </nav>

        <!-- Main Dashboard Content -->
        <main class="dashboard-main">
            <!-- 1. Real-Time Overview Tab -->
            <div id="overviewTab" class="tab-content active">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <div>
                        <h2 style="color: #1a5276; margin: 0;">Hospital Live Operations Overview</h2>
                        <p style="color: #666; margin: 4px 0 0 0; font-size: 14px;">Real-time queue analytics and personnel status</p>
                    </div>
                    <div>
                        <span id="overviewLastUpdated" style="font-size: 13px; color: #888; margin-right: 12px;">Updating...</span>
                        <button class="btn-filter" onclick="loadOverviewData()" style="padding: 6px 14px; font-size: 13px;">🔄 Refresh</button>
                    </div>
                </div>

                <!-- High-level Metric Cards -->
                <div class="metric-cards-grid">
                    <div class="stat-card primary">
                        <div class="stat-label">Total Doctors</div>
                        <div class="stat-value" id="cardTotalDoctors">0</div>
                        <div class="stat-subtext"><span id="cardOnlineDoctors" style="color: #27ae60; font-weight: bold;">0</span> currently online</div>
                    </div>
                    <div class="stat-card info">
                        <div class="stat-label">Total Staff</div>
                        <div class="stat-value" id="cardTotalStaff">0</div>
                        <div class="stat-subtext"><span id="cardOnlineStaff" style="color: #27ae60; font-weight: bold;">0</span> currently online</div>
                    </div>
                    <div class="stat-card warning">
                        <div class="stat-label">Patients Waiting</div>
                        <div class="stat-value" id="cardPatientsWaiting">0</div>
                        <div class="stat-subtext">Across all departments</div>
                    </div>
                    <div class="stat-card purple">
                        <div class="stat-label">Currently Serving</div>
                        <div class="stat-value" id="cardPatientsServing">0</div>
                        <div class="stat-subtext">In doctor consultation</div>
                    </div>
                    <div class="stat-card success">
                        <div class="stat-label">Checked-In Today</div>
                        <div class="stat-value" id="cardTodayCheckedIn">0</div>
                        <div class="stat-subtext"><span id="cardTodayCompleted">0</span> completed</div>
                    </div>
                </div>

                <!-- Secondary Metrics Row -->
                <div class="metric-cards-grid" style="grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); margin-bottom: 24px;">
                    <div class="stat-card" style="border-left: 4px solid #16a085; padding: 14px 18px;">
                        <div class="stat-label" style="font-size: 11px;">Completed Today</div>
                        <div class="stat-value" style="font-size: 24px; color: #16a085;" id="cardSecCompleted">0</div>
                    </div>
                    <div class="stat-card" style="border-left: 4px solid #8e44ad; padding: 14px 18px;">
                        <div class="stat-label" style="font-size: 11px;">Referred Today</div>
                        <div class="stat-value" style="font-size: 24px; color: #8e44ad;" id="cardSecReferred">0</div>
                    </div>
                    <div class="stat-card" style="border-left: 4px solid #e67e22; padding: 14px 18px;">
                        <div class="stat-label" style="font-size: 11px;">No-Show Today</div>
                        <div class="stat-value" style="font-size: 24px; color: #e67e22;" id="cardSecNoShow">0</div>
                    </div>
                    <div class="stat-card" style="border-left: 4px solid #7f8c8d; padding: 14px 18px;">
                        <div class="stat-label" style="font-size: 11px;">Cancelled Today</div>
                        <div class="stat-value" style="font-size: 24px; color: #7f8c8d;" id="cardSecCancelled">0</div>
                    </div>
                </div>

                <!-- Two-Column Breakdown: Department Status & Live Staff/Doctor Roster -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px;">
                    <!-- Department Breakdown -->
                    <div class="panel" style="margin-bottom: 0;">
                        <h3 style="color: #2c3e50; margin-bottom: 15px;">🏥 Department Queue Breakdown</h3>
                        <div style="overflow-x: auto; max-height: 400px;">
                            <table class="waiting-table" style="width: 100%; border-collapse: collapse; font-size: 13px;">
                                <thead>
                                    <tr style="background: #f1f4f8; text-align: left;">
                                        <th style="padding: 8px;">Department</th>
                                        <th style="padding: 8px; text-align: center;">Waiting</th>
                                        <th style="padding: 8px; text-align: center;">Serving</th>
                                        <th style="padding: 8px; text-align: center;">Completed</th>
                                        <th style="padding: 8px; text-align: center;">Staff</th>
                                        <th style="padding: 8px; text-align: center;">Doctors</th>
                                    </tr>
                                </thead>
                                <tbody id="overviewDeptBody">
                                    <tr><td colspan="6" style="text-align: center; padding: 15px; color: #888;">Loading departments...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Live Staff & Doctor Roster (Online / Offline Status) -->
                    <div class="panel" style="margin-bottom: 0;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                            <h3 style="color: #2c3e50; margin: 0;">🟢 Live Personnel Attendance</h3>
                            <select id="rosterFilter" class="filter-input" style="padding: 4px 8px; font-size: 12px;" onchange="renderRosterTable()">
                                <option value="all">All Personnel</option>
                                <option value="online">Online Only</option>
                                <option value="doctor">Doctors Only</option>
                                <option value="staff">Staff Only</option>
                            </select>
                        </div>
                        <div style="overflow-x: auto; max-height: 400px;">
                            <table class="waiting-table" style="width: 100%; border-collapse: collapse; font-size: 13px;">
                                <thead>
                                    <tr style="background: #f1f4f8; text-align: left;">
                                        <th style="padding: 8px;">Role</th>
                                        <th style="padding: 8px;">Name</th>
                                        <th style="padding: 8px;">Dept</th>
                                        <th style="padding: 8px;">Last Login</th>
                                        <th style="padding: 8px; text-align: center;">Status</th>
                                    </tr>
                                </thead>
                                <tbody id="overviewRosterBody">
                                    <tr><td colspan="5" style="text-align: center; padding: 15px; color: #888;">Loading personnel...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 2. Patient History Tab (Hospital-Wide) -->
            <div id="historyTab" class="tab-content">
                <div class="history-container">
                    <div class="history-header">
                        <h3>📋 Hospital-Wide Patient Queue History</h3>
                        <p class="dept-info">Complete cross-department audit trail, consultation records & export</p>
                    </div>

                    <!-- Filters Section -->
                    <div class="history-filters">
                        <div class="filter-group">
                            <label>Date Range:</label>
                            <input type="date" id="filterStartDate" class="filter-input">
                            <span>to</span>
                            <input type="date" id="filterEndDate" class="filter-input">
                        </div>

                        <div class="filter-group">
                            <label>Department:</label>
                            <select id="filterDept" class="filter-input" onchange="handleHistoryDeptChange()">
                                <option value="">All Departments</option>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label>Doctor:</label>
                            <select id="filterDoctor" class="filter-input">
                                <option value="">All Doctors</option>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label>Status:</label>
                            <select id="filterStatus" class="filter-input">
                                <option value="all">All Status</option>
                                <option value="completed">Completed</option>
                                <option value="referred">Referred</option>
                                <option value="no-show">No Show</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label>Search:</label>
                            <input type="text" id="filterSearch" class="filter-input" placeholder="Ticket #, Patient Name, Appt #">
                        </div>

                        <div class="filter-actions">
                            <button class="btn-filter" onclick="applyHistoryFilters()">🔍 Apply</button>
                            <button class="btn-filter-reset" onclick="resetHistoryFilters()">↺ Reset</button>
                        </div>
                    </div>

                    <!-- Export Actions -->
                    <div class="export-actions">
                        <button class="btn-export" onclick="exportHistory('csv')">
                            📥 Export CSV
                        </button>
                        <button class="btn-export" onclick="exportHistory('pdf')">
                            📄 Export PDF / Printable Report
                        </button>
                    </div>

                    <!-- History Table -->
                    <div class="history-table-container">
                        <table class="history-table">
                            <thead>
                                <tr>
                                    <th>Queue #</th>
                                    <th>Patient Name</th>
                                    <th>Appt #</th>
                                    <th>Age / Gender</th>
                                    <th>BP / Weight</th>
                                    <th>Department</th>
                                    <th>Doctor</th>
                                    <th>Issued</th>
                                    <th>Called</th>
                                    <th>Completed</th>
                                    <th>Status</th>
                                    <th>Staff</th>
                                    <th>Referred To</th>
                                </tr>
                            </thead>
                            <tbody id="historyTableBody">
                                <tr>
                                    <td colspan="13" class="loading">Loading history...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div class="pagination">
                        <button class="btn-page" onclick="loadPreviousHistoryPage()" id="btnPrevPage">← Previous</button>
                        <span id="pageInfo">Page 1 of 1</span>
                        <button class="btn-page" onclick="loadNextHistoryPage()" id="btnNextPage">Next →</button>
                    </div>
                </div>
            </div>

            <!-- 3. Staff Management Tab -->
            <div id="staffTab" class="tab-content">
                <div class="staff-container">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                        <div>
                            <h3 style="color: #1a5276; margin: 0;">👥 Hospital Staff Management</h3>
                            <p style="color: #666; margin: 4px 0 0 0; font-size: 14px;">Manage department desk staff accounts and access</p>
                        </div>
                        <button class="btn-submit" onclick="openAddStaffModal()" style="padding: 10px 18px; font-size: 14px; background: #0054A6;">
                            ➕ Add New Staff Member
                        </button>
                    </div>

                    <div class="panel">
                        <div class="staff-filters" style="display: flex; gap: 12px; margin-bottom: 16px;">
                            <select id="staffManageDeptFilter" class="filter-input" onchange="loadStaffList()">
                                <option value="">All Departments</option>
                            </select>
                            <input type="text" id="staffManageSearchFilter" class="filter-input" placeholder="Search staff by username or name..." onkeyup="filterStaffTable()">
                        </div>

                        <div class="staff-table-container">
                            <table class="staff-table">
                                <thead>
                                    <tr>
                                        <th>Username</th>
                                        <th>Full Name</th>
                                        <th>Department</th>
                                        <th>Role</th>
                                        <th>Status</th>
                                        <th>Last Login</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="staffManageTableBody">
                                    <tr><td colspan="7" class="loading">Loading staff members...</td></tr>
                                </tbody>
                            </table>
                        </div>
                        <div style="margin-top: 12px; font-size: 13px; color: #666;" id="staffCountDisplay">Total: 0 staff members</div>
                    </div>
                </div>
            </div>

            <!-- 4. Doctor Management Tab (NEW) -->
            <div id="doctorsTab" class="tab-content">
                <div class="staff-container">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                        <div>
                            <h3 style="color: #1a5276; margin: 0;">🩺 Hospital Doctor Management</h3>
                            <p style="color: #666; margin: 4px 0 0 0; font-size: 14px;">Create and manage doctor accounts, room assignments, and credentials</p>
                        </div>
                        <button class="btn-submit" onclick="openAddDoctorModal()" style="padding: 10px 18px; font-size: 14px; background: #27ae60;">
                            ➕ Add New Doctor
                        </button>
                    </div>

                    <div class="panel">
                        <div class="staff-filters" style="display: flex; gap: 12px; margin-bottom: 16px;">
                            <select id="doctorManageDeptFilter" class="filter-input" onchange="loadDoctorList()">
                                <option value="">All Departments</option>
                            </select>
                            <input type="text" id="doctorManageSearchFilter" class="filter-input" placeholder="Search doctor by name, username or room..." onkeyup="filterDoctorTable()">
                        </div>

                        <div class="staff-table-container">
                            <table class="staff-table">
                                <thead>
                                    <tr>
                                        <th>Doctor Name</th>
                                        <th>Specialization</th>
                                        <th>Department</th>
                                        <th>Room / Window</th>
                                        <th>Username</th>
                                        <th>Status</th>
                                        <th>Last Login</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="doctorManageTableBody">
                                    <tr><td colspan="8" class="loading">Loading doctors...</td></tr>
                                </tbody>
                            </table>
                        </div>
                        <div style="margin-top: 12px; font-size: 13px; color: #666;" id="doctorCountDisplay">Total: 0 doctors</div>
                    </div>
                </div>
            </div>

            <!-- 5. Attendance Logs Tab -->
            <div id="attendanceTab" class="tab-content">
                <div class="history-container">
                    <div class="history-header">
                        <h3>⏱️ Staff & Doctor Attendance & Session Monitoring</h3>
                        <p class="dept-info">Real-time duty timesheet records, self check-in/out durations, and login session auditing</p>
                    </div>

                    <!-- Subtabs Navigation -->
                    <div class="subtabs-nav">
                        <button class="btn-subtab active" id="btnSubtabDuty" onclick="switchAttendanceSubtab('duty')">
                            📋 Duty Attendance (Check-In / Out)
                        </button>
                        <button class="btn-subtab" id="btnSubtabSessions" onclick="switchAttendanceSubtab('sessions')">
                            🔑 Login Sessions Audit
                        </button>
                    </div>

                    <!-- Attendance Filters -->
                    <div class="history-filters">
                        <div class="filter-group">
                            <label>Date Range:</label>
                            <input type="date" id="attFilterStart" class="filter-input">
                            <span>to</span>
                            <input type="date" id="attFilterEnd" class="filter-input">
                        </div>

                        <div class="filter-group">
                            <label>Role:</label>
                            <select id="attFilterRole" class="filter-input">
                                <option value="all">All Roles</option>
                                <option value="doctor">Doctors</option>
                                <option value="staff">Staff</option>
                            </select>
                        </div>

                        <div class="filter-group" id="attFilterDeptGroup">
                            <label>Department:</label>
                            <select id="attFilterDept" class="filter-input">
                                <option value="">All Departments</option>
                            </select>
                        </div>

                        <div class="filter-group" id="attFilterStatusGroup">
                            <label>Status:</label>
                            <select id="attFilterStatus" class="filter-input">
                                <option value="all">All Status</option>
                                <option value="on_duty">On Duty</option>
                                <option value="completed">Completed</option>
                                <option value="auto_closed">Auto-Closed</option>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label>Search:</label>
                            <input type="text" id="attFilterSearch" class="filter-input" placeholder="Search by name, username...">
                        </div>

                        <div class="filter-actions">
                            <button class="btn-filter" onclick="applyAttendanceFilters()">🔍 Filter</button>
                            <button class="btn-filter-reset" onclick="resetAttendanceFilters()">↺ Reset</button>
                        </div>
                    </div>

                    <!-- Attendance Table -->
                    <div class="history-table-container">
                        <table class="history-table">
                            <thead id="attendanceTableHeader">
                                <tr>
                                    <th>Personnel</th>
                                    <th>Role</th>
                                    <th>Username</th>
                                    <th>Department</th>
                                    <th>Work Date</th>
                                    <th>Check-In Time</th>
                                    <th>Check-Out Time</th>
                                    <th>Duty Duration</th>
                                    <th>Duty Status</th>
                                </tr>
                            </thead>
                            <tbody id="attendanceTableBody">
                                <tr><td colspan="9" class="loading">Loading attendance records...</td></tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Attendance Pagination -->
                    <div class="pagination">
                        <button class="btn-page" onclick="loadPrevAttendancePage()" id="btnAttPrev">← Previous</button>
                        <span id="attPageInfo">Page 1 of 1</span>
                        <button class="btn-page" onclick="loadNextAttendancePage()" id="btnAttNext">Next →</button>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Add Staff Modal -->
    <div id="addStaffModal" class="custom-modal">
        <div class="custom-modal-content">
            <div class="custom-modal-header">
                <h3>➕ Add New Staff Member</h3>
                <button type="button" class="custom-modal-close" onclick="closeAddStaffModal()">&times;</button>
            </div>
            <form id="addStaffForm" onsubmit="submitAddStaff(event)">
                <div class="custom-modal-body">
                    <div class="form-group" style="margin-bottom: 14px;">
                        <label for="addStaffUsername">Username <span class="required">*</span></label>
                        <input type="text" id="addStaffUsername" class="form-input" required placeholder="e.g. jdoe">
                        <small class="form-hint">Unique username, no spaces</small>
                    </div>
                    <div class="form-group" style="margin-bottom: 14px;">
                        <label for="addStaffPassword">Password <span class="required">*</span></label>
                        <input type="password" id="addStaffPassword" class="form-input" required minlength="8" placeholder="Minimum 8 characters">
                        <small class="form-hint">At least 8 characters</small>
                    </div>
                    <div class="form-group" style="margin-bottom: 14px;">
                        <label for="addStaffFullName">Full Name <span class="required">*</span></label>
                        <input type="text" id="addStaffFullName" class="form-input" required placeholder="e.g. John Doe">
                    </div>
                    <div class="form-group" style="margin-bottom: 14px;">
                        <label for="addStaffDepartment">Department <span class="required">*</span></label>
                        <select id="addStaffDepartment" class="form-input" required>
                            <option value="">-- Select Department --</option>
                        </select>
                    </div>
                    <div id="addStaffMessage" class="form-message" style="display: none; margin-top: 10px;"></div>
                </div>
                <div class="custom-modal-footer">
                    <button type="button" class="btn-clear" onclick="closeAddStaffModal()">Cancel</button>
                    <button type="submit" class="btn-submit" style="background: #0054A6; color: white;">Add Staff</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Staff Modal -->
    <div id="editStaffModal" class="custom-modal">
        <div class="custom-modal-content">
            <div class="custom-modal-header">
                <h3>✏️ Edit Staff Member</h3>
                <button type="button" class="custom-modal-close" onclick="closeEditStaffModal()">&times;</button>
            </div>
            <form id="editStaffForm" onsubmit="submitEditStaff(event)">
                <input type="hidden" id="editStaffId">
                <div class="custom-modal-body">
                    <div class="form-group" style="margin-bottom: 14px;">
                        <label>Username</label>
                        <input type="text" id="editStaffUsername" class="form-input" disabled style="background: #f4f6f9;">
                    </div>
                    <div class="form-group" style="margin-bottom: 14px;">
                        <label for="editStaffFullName">Full Name <span class="required">*</span></label>
                        <input type="text" id="editStaffFullName" class="form-input" required>
                    </div>
                    <div class="form-group" style="margin-bottom: 14px;">
                        <label for="editStaffDepartment">Department <span class="required">*</span></label>
                        <select id="editStaffDepartment" class="form-input" required>
                            <option value="">-- Select Department --</option>
                        </select>
                    </div>
                    <div class="form-group" style="margin-bottom: 14px;">
                        <label for="editStaffPassword">New Password <small style="color: #888;">(Leave blank to keep unchanged)</small></label>
                        <input type="password" id="editStaffPassword" class="form-input" minlength="8" placeholder="Min. 8 characters if changing">
                    </div>
                    <div id="editStaffMessage" class="form-message" style="display: none; margin-top: 10px;"></div>
                </div>
                <div class="custom-modal-footer">
                    <button type="button" class="btn-clear" onclick="closeEditStaffModal()">Cancel</button>
                    <button type="submit" class="btn-submit" style="background: #0054A6; color: white;">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Add Doctor Modal -->
    <div id="addDoctorModal" class="custom-modal">
        <div class="custom-modal-content">
            <div class="custom-modal-header">
                <h3>🩺 Add New Doctor</h3>
                <button type="button" class="custom-modal-close" onclick="closeAddDoctorModal()">&times;</button>
            </div>
            <form id="addDoctorForm" onsubmit="submitAddDoctor(event)">
                <div class="custom-modal-body">
                    <div class="form-group" style="margin-bottom: 14px;">
                        <label for="addDocFullName">Doctor Full Name <span class="required">*</span></label>
                        <input type="text" id="addDocFullName" class="form-input" required placeholder="e.g. Dr. Alan Grant">
                    </div>
                    <div class="form-group" style="margin-bottom: 14px;">
                        <label for="addDocSpecialization">Specialization <span class="required">*</span></label>
                        <input type="text" id="addDocSpecialization" class="form-input" required placeholder="e.g. Interventional Cardiology">
                    </div>
                    <div class="form-group" style="margin-bottom: 14px;">
                        <label for="addDocDepartment">Department <span class="required">*</span></label>
                        <select id="addDocDepartment" class="form-input" required>
                            <option value="">-- Select Department --</option>
                        </select>
                    </div>
                    <div class="form-group" style="margin-bottom: 14px;">
                        <label for="addDocRoom">Consultation Room / Window Label <span class="required">*</span></label>
                        <input type="text" id="addDocRoom" class="form-input" required placeholder="e.g. Room 101, Desk A, Clinic 3">
                        <small class="form-hint">Will be announced on monitor when patient is called</small>
                    </div>
                    <div class="form-group" style="margin-bottom: 14px;">
                        <label for="addDocUsername">Login Username <span class="required">*</span></label>
                        <input type="text" id="addDocUsername" class="form-input" required placeholder="e.g. dr.grant">
                        <small class="form-hint">Unique username for doctor portal</small>
                    </div>
                    <div class="form-group" style="margin-bottom: 14px;">
                        <label for="addDocPassword">Login Password <span class="required">*</span></label>
                        <input type="password" id="addDocPassword" class="form-input" required minlength="8" placeholder="Minimum 8 characters">
                    </div>
                    <div id="addDoctorMessage" class="form-message" style="display: none; margin-top: 10px;"></div>
                </div>
                <div class="custom-modal-footer">
                    <button type="button" class="btn-clear" onclick="closeAddDoctorModal()">Cancel</button>
                    <button type="submit" class="btn-submit" style="background: #27ae60; color: white;">Create Doctor Account</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Doctor Modal -->
    <div id="editDoctorModal" class="custom-modal">
        <div class="custom-modal-content">
            <div class="custom-modal-header">
                <h3>✏️ Edit Doctor Profile</h3>
                <button type="button" class="custom-modal-close" onclick="closeEditDoctorModal()">&times;</button>
            </div>
            <form id="editDoctorForm" onsubmit="submitEditDoctor(event)">
                <input type="hidden" id="editDocId">
                <div class="custom-modal-body">
                    <div class="form-group" style="margin-bottom: 14px;">
                        <label for="editDocFullName">Doctor Full Name <span class="required">*</span></label>
                        <input type="text" id="editDocFullName" class="form-input" required>
                    </div>
                    <div class="form-group" style="margin-bottom: 14px;">
                        <label for="editDocSpecialization">Specialization <span class="required">*</span></label>
                        <input type="text" id="editDocSpecialization" class="form-input" required>
                    </div>
                    <div class="form-group" style="margin-bottom: 14px;">
                        <label for="editDocDepartment">Department <span class="required">*</span></label>
                        <select id="editDocDepartment" class="form-input" required>
                            <option value="">-- Select Department --</option>
                        </select>
                    </div>
                    <div class="form-group" style="margin-bottom: 14px;">
                        <label for="editDocRoom">Consultation Room / Window Label <span class="required">*</span></label>
                        <input type="text" id="editDocRoom" class="form-input" required>
                    </div>
                    <div class="form-group" style="margin-bottom: 14px;">
                        <label for="editDocUsername">Login Username <span class="required">*</span></label>
                        <input type="text" id="editDocUsername" class="form-input" required>
                    </div>
                    <div class="form-group" style="margin-bottom: 14px;">
                        <label for="editDocPassword">New Password <small style="color: #888;">(Leave blank to keep unchanged)</small></label>
                        <input type="password" id="editDocPassword" class="form-input" minlength="8" placeholder="Min. 8 characters if changing">
                    </div>
                    <div id="editDoctorMessage" class="form-message" style="display: none; margin-top: 10px;"></div>
                </div>
                <div class="custom-modal-footer">
                    <button type="button" class="btn-clear" onclick="closeEditDoctorModal()">Cancel</button>
                    <button type="submit" class="btn-submit" style="background: #27ae60; color: white;">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteConfirmModal" class="custom-modal">
        <div class="custom-modal-content" style="max-width: 480px;">
            <div class="custom-modal-header" style="border-bottom: 2px solid #e74c3c;">
                <h3 style="color: #c0392b; margin: 0;">🗑️ Confirm Deletion</h3>
                <button type="button" class="custom-modal-close" onclick="closeDeleteModal()">&times;</button>
            </div>
            <div class="custom-modal-body">
                <p style="font-size: 15px; color: #2c3e50; margin: 0 0 12px 0;" id="deleteConfirmText"></p>
                <div style="background: #fdedec; border-left: 4px solid #e74c3c; padding: 12px; font-size: 13px; color: #c0392b; border-radius: 4px;">
                    <strong>Notice:</strong> Account will be deactivated immediately, open sessions will be terminated, and login revoked. Historical queue and consultation records will remain intact.
                </div>
                <div id="deleteModalMessage" class="form-message" style="display: none; margin-top: 12px;"></div>
            </div>
            <div class="custom-modal-footer">
                <button type="button" class="btn-clear" onclick="closeDeleteModal()">Cancel</button>
                <button type="button" class="btn-submit" id="btnConfirmDeleteAction" style="background: #e74c3c; color: white;">Confirm Delete</button>
            </div>
        </div>
    </div>

    <script src="/STI_Queueing-System/js/admin-dashboard.js?v=<?php echo time(); ?>"></script>
    <script>
        function logout() {
            if (confirm('Are you sure you want to logout from Admin Dashboard?')) {
                window.location.href = '/STI_Queueing-System/api/logout.php';
            }
        }
    </script>
</body>
</html>
