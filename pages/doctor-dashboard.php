<?php
require_once __DIR__ . '/../includes/auth.php';

// Strict Role Check: Doctor only
requireRole('doctor', false);

$doctorId = intval($_SESSION['doctor_id'] ?? 0);
$doctorName = $_SESSION['doctor_name'] ?? 'Doctor';
$departmentName = $_SESSION['department_name'] ?? 'Department';
$departmentId = intval($_SESSION['department_id'] ?? 0);
$roomLabel = $_SESSION['room_label'] ?? 'Consultation Room';
$specialization = $_SESSION['specialization'] ?? 'Physician';
$username = $_SESSION['username'] ?? 'doctor';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doctor Portal - Hospital Queueing System</title>
    <link rel="stylesheet" href="/STI_Queueing-System/css/style.css?v=<?php echo time(); ?>">
</head>
<body data-user-role="doctor" data-doctor-id="<?php echo $doctorId; ?>" data-doctor-name="<?php echo htmlspecialchars($doctorName); ?>" data-department-id="<?php echo $departmentId; ?>" data-department-name="<?php echo htmlspecialchars($departmentName); ?>" data-room-label="<?php echo htmlspecialchars($roomLabel); ?>">
    <div class="dashboard-container">
        <!-- Navigation Bar -->
        <nav class="navbar">
            <div class="navbar-left">
                <h2>Doctor Portal | <span style="color: #27ae60;"><?php echo htmlspecialchars($doctorName); ?></span> <span style="font-size: 14px; font-weight: normal; color: #888;">(<?php echo htmlspecialchars($departmentName); ?> - <?php echo htmlspecialchars($roomLabel); ?>)</span></h2>
            </div>
            <div class="navbar-right">
                <a href="/STI_Queueing-System/attendance" class="duty-nav-widget" id="headerDutyWidget" title="Click to Check In/Out for Duty" style="text-decoration: none;">
                    <span id="headerDutyBadge" class="badge-offduty">⚪ Off Duty</span>
                </a>
                <button class="btn-nav active" onclick="showTab('queue')" id="btnQueueTab">
                    📋 My Queue
                </button>
                <button class="btn-nav" onclick="showTab('consultations')" id="btnConsultationsTab">
                    📊 My Consultations
                </button>
                <button class="btn-nav" onclick="showTab('profile')" id="btnProfileTab">
                    👤 Profile
                </button>
                <button class="btn-logout" onclick="logout()">Logout</button>
            </div>
        </nav>

        <!-- Main Dashboard Content -->
        <main class="dashboard-main">
            <!-- 1. Doctor's Live Queue Tab -->
            <div id="queueTab" class="tab-content active">
                <div class="dashboard-grid">
                    <!-- Panel A: Action Zone (Call Next & Queue Status) -->
                    <div class="panel action-zone">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                            <h3 style="margin: 0; color: #1a5276;">Doctor Queue Control</h3>
                            <span class="badge-role role-doctor"><?php echo htmlspecialchars($roomLabel); ?></span>
                        </div>
                        
                        <button class="btn-call-next" onclick="callNextPatient()" id="btnCallNext" style="background: #0054A6; margin-bottom: 16px;">
                            📢 Call Next Patient
                        </button>

                        <div class="queue-status" style="margin-bottom: 20px;">
                            <div class="queue-status-number" id="doctorQueueCount">0</div>
                            <div class="queue-status-label">Patients Waiting for You</div>
                        </div>

                        <!-- Waiting Queue List Section for this Doctor -->
                        <div class="waiting-queue-section">
                            <h4 style="margin-bottom: 10px; color: #2c3e50;">Waiting In Line (<?php echo htmlspecialchars($departmentName); ?>)</h4>
                            <div class="waiting-list-container" style="max-height: 280px; overflow-y: auto;">
                                <table class="waiting-table" style="width: 100%; border-collapse: collapse; font-size: 13px;">
                                    <thead>
                                        <tr style="background: #f1f4f8; text-align: left;">
                                            <th style="padding: 6px;">Ticket</th>
                                            <th style="padding: 6px;">Patient</th>
                                            <th style="padding: 6px;">Appt #</th>
                                            <th style="padding: 6px;">Triage / BP</th>
                                        </tr>
                                    </thead>
                                    <tbody id="doctorWaitingListBody">
                                        <tr><td colspan="4" style="text-align:center; padding: 12px; color: #888;">No waiting patients</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Panel B: Current Serving Transaction -->
                    <div class="panel current-serving-panel">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                            <h3 style="margin: 0; color: #1a5276;">Active Consultation</h3>
                            <span id="servingStatusBadge" class="badge-status status-waiting">No Patient Serving</span>
                        </div>
                        
                        <div class="ticket-card" style="padding: 20px; background: #ffffff; border-radius: 8px; border: 1px solid #e1e8ed;">
                            <div class="ticket-number" id="servingTicketNumber" style="font-size: 48px; color: #0054A6;">---</div>

                            <!-- Patient Medical / Triage Details -->
                            <div class="patient-details-box" id="patientDetailsBox" style="margin-top: 15px; text-align: left; background: #f8fbfd; padding: 16px; border-radius: 6px; border: 1px solid #d4e6f1;">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                                    <div style="font-size: 18px; font-weight: bold; color: #1a5276;" id="patientName">No Active Patient</div>
                                    <span id="patientPriorityBadge" style="display: none; background: #ffc107; color: #000; font-size: 11px; font-weight: bold; padding: 3px 8px; border-radius: 4px;">⚡ PRIORITY</span>
                                </div>
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; font-size: 13px; color: #444;">
                                    <div><strong>Appointment:</strong> <span id="patientAppt">---</span></div>
                                    <div><strong>Age / Gender:</strong> <span id="patientAgeGender">---</span></div>
                                    <div><strong>Blood Pressure:</strong> <span id="patientBp" style="font-weight: 600; color: #c0392b;">---</span></div>
                                    <div><strong>Weight:</strong> <span id="patientWeight">---</span></div>
                                    <div style="grid-column: span 2;" id="patientReferralInfoRow">
                                        <div id="patientReferralInfo" style="display: none; background: #f4ecf7; padding: 8px 12px; border-radius: 4px; border-left: 3px solid #8e44ad; font-size: 12px; color: #5b2c6f; margin-top: 6px;"></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div class="action-buttons" style="display: flex; flex-wrap: wrap; gap: 10px; margin-top: 16px;">
                            <button class="btn-action btn-done" onclick="markDone()" id="btnActionDone" style="flex: 1 1 45%; background: #27ae60; color: white; padding: 10px; font-size: 14px; font-weight: 600; border: none; border-radius: 6px; cursor: pointer;">
                                ✓ Mark Done
                            </button>
                            <button class="btn-action btn-recall" onclick="recallPatient()" id="btnActionRecall" style="flex: 1 1 45%; background: #2980b9; color: white; padding: 10px; font-size: 14px; font-weight: 600; border: none; border-radius: 6px; cursor: pointer;">
                                🔄 Recall Patient
                            </button>
                            <button class="btn-action btn-noshow" onclick="markNoShow()" id="btnActionNoShow" style="flex: 1 1 30%; background: #e67e22; color: white; padding: 10px; font-size: 13px; font-weight: 600; border: none; border-radius: 6px; cursor: pointer;">
                                ✗ No Show
                            </button>
                            <button class="btn-action btn-cancel" onclick="markCancel()" id="btnActionCancel" style="flex: 1 1 30%; background: #7f8c8d; color: white; padding: 10px; font-size: 13px; font-weight: 600; border: none; border-radius: 6px; cursor: pointer;">
                                🚫 Cancel
                            </button>
                            <button class="btn-action btn-refer" onclick="openReferralModal()" id="btnActionRefer" style="flex: 1 1 30%; background: #8e44ad; color: white; padding: 10px; font-size: 13px; font-weight: 600; border: none; border-radius: 6px; cursor: pointer;">
                                ↗ Refer Patient
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 2. Doctor's Consultations Tab -->
            <div id="consultationsTab" class="tab-content">
                <div class="history-container">
                    <div class="history-header">
                        <h3>📊 My Consultations & Clinical History</h3>
                        <p class="dept-info">Department: <strong><?php echo htmlspecialchars($departmentName); ?></strong> | Room: <strong><?php echo htmlspecialchars($roomLabel); ?></strong></p>
                    </div>

                    <!-- Doctor Metrics Cards -->
                    <div class="metric-cards-grid" style="grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); margin-bottom: 20px;">
                        <div class="stat-card primary">
                            <div class="stat-label">Seen Today</div>
                            <div class="stat-value" id="docSeenToday">0</div>
                            <div class="stat-subtext">Total consultations</div>
                        </div>
                        <div class="stat-card success">
                            <div class="stat-label">Completed Today</div>
                            <div class="stat-value" id="docCompletedToday">0</div>
                            <div class="stat-subtext">Consultations marked done</div>
                        </div>
                        <div class="stat-card purple">
                            <div class="stat-label">Referred Today</div>
                            <div class="stat-value" id="docReferredToday">0</div>
                            <div class="stat-subtext">Sent to other doctor/dept</div>
                        </div>
                        <div class="stat-card warning">
                            <div class="stat-label">No-Show / Cancelled</div>
                            <div class="stat-value" id="docNoShowToday">0</div>
                            <div class="stat-subtext">Missed / cancelled appointments</div>
                        </div>
                    </div>

                    <!-- Filters Section -->
                    <div class="history-filters">
                        <div class="filter-group">
                            <label>Date Range:</label>
                            <input type="date" id="docFilterStart" class="filter-input">
                            <span>to</span>
                            <input type="date" id="docFilterEnd" class="filter-input">
                        </div>

                        <div class="filter-group">
                            <label>Status:</label>
                            <select id="docFilterStatus" class="filter-input">
                                <option value="all">All Status</option>
                                <option value="completed">Completed</option>
                                <option value="referred">Referred</option>
                                <option value="no-show">No Show</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label>Search:</label>
                            <input type="text" id="docFilterSearch" class="filter-input" placeholder="Ticket #, Patient Name, Appt #">
                        </div>

                        <div class="filter-actions">
                            <button class="btn-filter" onclick="applyDoctorFilters()">🔍 Apply</button>
                            <button class="btn-filter-reset" onclick="resetDoctorFilters()">↺ Reset</button>
                        </div>
                    </div>

                    <!-- Export Actions -->
                    <div class="export-actions">
                        <button class="btn-export" onclick="exportDoctorHistory('csv')">
                            📥 Export CSV
                        </button>
                        <button class="btn-export" onclick="exportDoctorHistory('pdf')">
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
                                    <th>Called</th>
                                    <th>Completed</th>
                                    <th>Status</th>
                                    <th>Referred To</th>
                                    <th>Clinical Notes</th>
                                </tr>
                            </thead>
                            <tbody id="doctorHistoryBody">
                                <tr><td colspan="10" class="loading">Loading consultations...</td></tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div class="pagination">
                        <button class="btn-page" onclick="loadPrevDocPage()" id="btnDocPrev">← Previous</button>
                        <span id="docPageInfo">Page 1 of 1</span>
                        <button class="btn-page" onclick="loadNextDocPage()" id="btnDocNext">Next →</button>
                    </div>
                </div>
            </div>

            <!-- 3. Profile Tab -->
            <div id="profileTab" class="tab-content">
                <div class="profile-card">
                    <div style="text-align: center; margin-bottom: 20px;">
                        <div class="profile-avatar" style="margin: 0 auto 15px auto; background: #27ae60;">
                            🩺
                        </div>
                        <h2 style="margin: 0; color: #1a5276;"><?php echo htmlspecialchars($doctorName); ?></h2>
                        <span class="badge-role role-doctor" style="margin-top: 6px; display: inline-block;"><?php echo htmlspecialchars($specialization); ?></span>
                    </div>

                    <div class="profile-info-grid">
                        <div class="profile-info-item">
                            <label>Doctor Username</label>
                            <span>@<?php echo htmlspecialchars($username); ?></span>
                        </div>
                        <div class="profile-info-item">
                            <label>Department</label>
                            <span><?php echo htmlspecialchars($departmentName); ?></span>
                        </div>
                        <div class="profile-info-item">
                            <label>Consultation Room / Window</label>
                            <span style="color: #0054A6;"><?php echo htmlspecialchars($roomLabel); ?></span>
                        </div>
                        <div class="profile-info-item">
                            <label>Account Status</label>
                            <span class="badge-active">Active</span>
                        </div>
                        <div class="profile-info-item" style="grid-column: span 2;">
                            <label>Specialization</label>
                            <span><?php echo htmlspecialchars($specialization); ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Referral Modal -->
    <div id="referralModal" class="custom-modal">
        <div class="custom-modal-content">
            <div class="custom-modal-header" style="border-bottom: 2px solid #8e44ad;">
                <h3 style="color: #8e44ad; margin: 0;">↗ Refer Patient to Specialist / Department</h3>
                <button type="button" class="custom-modal-close" onclick="closeReferralModal()">&times;</button>
            </div>
            <form id="referralForm" onsubmit="submitReferral(event)">
                <div class="custom-modal-body">
                    <div id="referralPatientSummary" style="background: #f8f9fa; padding: 12px; border-radius: 6px; margin-bottom: 16px; font-size: 13px; color: #333; border-left: 3px solid #8e44ad;">
                        <!-- Filled dynamically with current ticket details -->
                    </div>

                    <div class="form-group" style="margin-bottom: 14px;">
                        <label style="font-weight: bold; margin-bottom: 8px; display: block;">Referral Destination <span class="required">*</span></label>
                        <div style="display: flex; gap: 20px;">
                            <label style="display: flex; align-items: center; gap: 6px; cursor: pointer; font-size: 14px;">
                                <input type="radio" name="referType" value="doctor" id="referTypeDoctor" checked onchange="handleReferralTypeChange()">
                                Another Doctor (Same Dept)
                            </label>
                            <label style="display: flex; align-items: center; gap: 6px; cursor: pointer; font-size: 14px;">
                                <input type="radio" name="referType" value="department" id="referTypeDept" onchange="handleReferralTypeChange()">
                                Another Department
                            </label>
                        </div>
                    </div>

                    <!-- Target Department (shown when referType === 'department') -->
                    <div class="form-group" id="targetDeptGroup" style="display: none; margin-bottom: 14px;">
                        <label for="referTargetDept" style="font-weight: bold; margin-bottom: 6px; display: block;">Target Department <span class="required">*</span></label>
                        <select id="referTargetDept" class="form-input" onchange="loadDoctorsForReferralDept()">
                            <option value="">-- Select Target Department --</option>
                        </select>
                    </div>

                    <!-- Target Doctor -->
                    <div class="form-group" id="targetDoctorGroup" style="margin-bottom: 14px;">
                        <label for="referTargetDoctor" style="font-weight: bold; margin-bottom: 6px; display: block;" id="targetDoctorLabel">Target Doctor <span class="required">*</span></label>
                        <select id="referTargetDoctor" class="form-input">
                            <option value="">-- Select Doctor --</option>
                        </select>
                    </div>

                    <!-- Priority Flag -->
                    <div class="form-group" style="margin-bottom: 14px; background: #fff8e1; padding: 10px 12px; border-radius: 6px; border: 1px solid #ffe082;">
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: 13px; font-weight: bold; color: #b78103; margin: 0;">
                            <input type="checkbox" id="referPriority" style="width: 16px; height: 16px;">
                            Maintain / Elevate as Priority Patient
                        </label>
                    </div>

                    <!-- Notes -->
                    <div class="form-group" style="margin-bottom: 14px;">
                        <label for="referNotes" style="font-weight: bold; margin-bottom: 6px; display: block;">Referral Clinical Notes / Reason</label>
                        <textarea id="referNotes" class="form-input" rows="3" placeholder="Enter clinical reason for referral, diagnostic findings, or instructions for the specialist"></textarea>
                    </div>

                    <div id="referralMessage" class="form-message" style="display: none; margin-top: 10px;"></div>
                </div>
                <div class="custom-modal-footer">
                    <button type="button" class="btn-clear" onclick="closeReferralModal()">Cancel</button>
                    <button type="submit" class="btn-submit" style="background: #8e44ad; color: white;">Confirm Referral</button>
                </div>
            </form>
        </div>
    </div>

    <script src="/STI_Queueing-System/js/doctor-dashboard.js?v=<?php echo time(); ?>"></script>
</body>
</html>
