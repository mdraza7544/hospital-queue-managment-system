// Hospital Staff Dashboard JavaScript

let departmentId = null;
let departmentName = '';
let currentTab = 'checkin';
let deptDoctors = [];
let isOnDuty = false;

document.addEventListener('DOMContentLoaded', async function() {
    departmentId = parseInt(document.body.dataset.departmentId);
    departmentName = document.body.dataset.departmentName || 'Department';

    // Start heartbeat immediately and every 30s
    await runHeartbeat();
    setInterval(runHeartbeat, 30000);

    // Check attendance duty status
    await checkDutyStatus();
    setInterval(checkDutyStatus, 15000);

    // Load doctors for this department
    await loadDoctorsForCheckIn();

    // Default tab
    showTab('checkin');
});

async function runHeartbeat() {
    try {
        const res = await fetch('/STI_Queueing-System/api/heartbeat.php');
        if (res.status === 401) {
            window.location.href = '/STI_Queueing-System/staff-login';
            return;
        }
        const data = await res.json();
        if (!data.success) {
            window.location.href = '/STI_Queueing-System/staff-login';
            return;
        }
        if (data.role !== 'staff') {
            if (data.role === 'admin') {
                window.location.href = '/STI_Queueing-System/admin-dashboard';
            } else if (data.role === 'doctor') {
                window.location.href = '/STI_Queueing-System/doctor-dashboard';
            } else {
                window.location.href = '/STI_Queueing-System/staff-login';
            }
        }
    } catch (e) {
        // network glitch
    }
}

async function checkDutyStatus() {
    try {
        const res = await fetch('/STI_Queueing-System/api/get-my-attendance.php');
        if (res.status === 401) {
            window.location.href = '/STI_Queueing-System/staff-login';
            return;
        }
        const data = await res.json();
        if (data.success) {
            isOnDuty = !!data.is_on_duty;
            const badge = document.getElementById('headerDutyBadge');
            if (badge) {
                if (isOnDuty) {
                    badge.className = 'badge-onduty';
                    badge.innerHTML = '🟢 On Duty';
                } else {
                    badge.className = 'badge-offduty';
                    badge.innerHTML = '⚪ Off Duty';
                }
            }
        }
    } catch (e) {
        // network glitch
    }
}

// ========================================
// TAB SWITCHING
// ========================================
function showTab(tabName) {
    currentTab = tabName;
    const tabs = ['checkin', 'history', 'profile'];
    tabs.forEach(t => {
        const tabEl = document.getElementById(t + 'Tab');
        const btnEl = document.getElementById('btn' + t.charAt(0).toUpperCase() + t.slice(1) + 'Tab');
        if (tabEl) tabEl.classList.remove('active');
        if (btnEl) btnEl.classList.remove('active');
    });

    const activeTab = document.getElementById(tabName + 'Tab');
    const activeBtn = document.getElementById('btn' + tabName.charAt(0).toUpperCase() + tabName.slice(1) + 'Tab');
    if (activeTab) activeTab.classList.add('active');
    if (activeBtn) activeBtn.classList.add('active');

    if (tabName === 'checkin') {
        loadDoctorsForCheckIn();
    } else if (tabName === 'history') {
        loadStaffStats();
    }
}

function escapeHtml(text) {
    if (text === null || text === undefined) return '';
    return String(text)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

// ========================================
// 1. CHECK-IN LOGIC
// ========================================
async function loadDoctorsForCheckIn() {
    const docSelect = document.getElementById('checkInDoctor');
    if (!docSelect || !departmentId) return;

    try {
        const res = await fetch(`/STI_Queueing-System/api/get-doctors.php?department_id=${encodeURIComponent(departmentId)}`);
        const data = await res.json();

        if (data.success && data.doctors) {
            deptDoctors = data.doctors;
            if (deptDoctors.length === 0) {
                docSelect.innerHTML = '<option value="">-- No Active Doctors Available in Department --</option>';
            } else {
                docSelect.innerHTML = '<option value="">-- Select Attending Doctor (Required) --</option>' +
                    deptDoctors.map(d => `<option value="${d.id}">${escapeHtml(d.full_name)} (${escapeHtml(d.specialization || 'General')} - ${escapeHtml(d.room_label || 'Desk')})</option>`).join('');
            }
        }
    } catch (e) {
        docSelect.innerHTML = '<option value="">Failed to load doctors</option>';
    }
}

async function handleCheckIn(event) {
    event.preventDefault();
    const msgEl = document.getElementById('checkInMessage');
    const successCard = document.getElementById('ticketSuccessCard');
    const submitBtn = document.getElementById('btnCheckInSubmit');

    msgEl.style.display = 'none';
    successCard.style.display = 'none';

    const patientName = document.getElementById('checkInPatientName').value.trim();
    const apptNumber = document.getElementById('checkInApptNumber').value.trim();
    const gender = document.getElementById('checkInGender').value;
    const ageVal = document.getElementById('checkInAge').value;
    const weightVal = parseFloat(document.getElementById('checkInWeight').value);
    const bpVal = document.getElementById('checkInBp').value.trim();
    const doctorVal = document.getElementById('checkInDoctor').value;
    const isPriority = document.getElementById('checkInPriority').checked;

    // Client-side validations
    if (!patientName) {
        showMessage('Patient Name is required.', 'error');
        return;
    }
    if (!apptNumber) {
        showMessage('Appointment Number is required.', 'error');
        return;
    }
    if (!gender) {
        showMessage('Please select patient gender.', 'error');
        return;
    }
    if (isNaN(weightVal) || weightVal < 0.5 || weightVal > 499.9) {
        showMessage('Weight must be between 0.5 and 499.9 kg.', 'error');
        return;
    }

    // BP Validation
    const bpMatch = bpVal.match(/^(\d{2,3})\/(\d{2,3})$/);
    if (!bpMatch) {
        showMessage('Blood pressure must be in format 120/80.', 'error');
        return;
    }
    const systolic = parseInt(bpMatch[1], 10);
    const diastolic = parseInt(bpMatch[2], 10);
    if (systolic <= diastolic) {
        showMessage('Systolic pressure (top number) must be greater than diastolic (bottom number).', 'error');
        return;
    }

    // Doctor Selection is MANDATORY
    if (!doctorVal) {
        showMessage('Please select an assigned doctor for this patient.', 'error');
        return;
    }

    submitBtn.disabled = true;
    submitBtn.textContent = 'Registering patient...';

    const payload = {
        department_id: departmentId,
        patient_name: patientName,
        appointment_number: apptNumber,
        gender: gender,
        age: ageVal ? parseInt(ageVal, 10) : null,
        weight_kg: weightVal,
        bp: bpVal,
        doctor_id: parseInt(doctorVal, 10),
        is_priority: isPriority
    };

    try {
        const response = await fetch('/STI_Queueing-System/api/checkin-patient.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });

        if (response.status === 401) {
            alert('Your session has expired. Redirecting to login...');
            window.location.href = '/STI_Queueing-System/staff-login';
            return;
        }
        if (response.status === 403) {
            showMessage('Access Denied: You do not have permission to check in patients with current session role. If you switched users in another tab, please refresh.', 'error');
            return;
        }

        const result = await response.json();

        if (result.success) {
            // Display Ticket Success Card
            document.getElementById('issuedTicketNumber').textContent = result.ticket_number;
            document.getElementById('issuedTicketDept').textContent = result.department_name || departmentName;
            
            const selectedDoc = deptDoctors.find(d => d.id == doctorVal);
            const docInfo = selectedDoc ? `Doctor: ${selectedDoc.full_name} (${selectedDoc.room_label || 'Desk'})` : '';
            document.getElementById('issuedTicketDoctor').textContent = docInfo;

            successCard.style.display = 'block';

            // Reset inputs
            document.getElementById('checkInPatientName').value = '';
            document.getElementById('checkInApptNumber').value = '';
            document.getElementById('checkInAge').value = '';
            document.getElementById('checkInWeight').value = '';
            document.getElementById('checkInBp').value = '';
            document.getElementById('checkInPriority').checked = false;

            successCard.scrollIntoView({ behavior: 'smooth' });
        } else {
            showMessage(result.message || 'Check-in failed. Please try again.', 'error');
        }
    } catch (error) {
        showMessage('Network error during check-in. Please try again.', 'error');
    } finally {
        submitBtn.disabled = false;
        submitBtn.textContent = '✓ Check In Patient & Issue Ticket';
    }
}

function showMessage(msg, type) {
    const msgEl = document.getElementById('checkInMessage');
    msgEl.textContent = msg;
    msgEl.className = 'form-message ' + type;
    msgEl.style.display = 'block';
}

function clearCheckInForm() {
    document.getElementById('checkInForm').reset();
    document.getElementById('checkInMessage').style.display = 'none';
    document.getElementById('ticketSuccessCard').style.display = 'none';
}

function resetForNextPatient() {
    clearCheckInForm();
    document.getElementById('checkInPatientName').focus();
}

// ========================================
// 2. CHECK-IN SUMMARY STATS (COUNTS ONLY)
// ========================================
async function loadStaffStats() {
    try {
        const res = await fetch('/STI_Queueing-System/api/get-staff-stats.php');
        const data = await res.json();

        if (data.success && data.stats) {
            const s = data.stats;
            document.getElementById('statTodayCount').textContent = s.today_count || 0;
            document.getElementById('statWeekCount').textContent = s.week_count || 0;
            document.getElementById('statMonthCount').textContent = s.month_count || 0;
            document.getElementById('statTotalCount').textContent = s.total_count || 0;
        }
    } catch (e) {
        console.error('Failed to load staff stats:', e);
    }
}

async function loadCustomRangeStats() {
    const start = document.getElementById('rangeStart').value;
    const end = document.getElementById('rangeEnd').value;

    if (!start || !end) {
        alert('Please select both start and end dates.');
        return;
    }

    try {
        const res = await fetch(`/STI_Queueing-System/api/get-staff-stats.php?startDate=${encodeURIComponent(start)}&endDate=${encodeURIComponent(end)}`);
        const data = await res.json();

        if (data.success && data.stats) {
            const resBox = document.getElementById('customRangeResult');
            document.getElementById('customRangeCount').textContent = data.stats.custom_range_count || 0;
            resBox.style.display = 'block';
        }
    } catch (e) {
        alert('Failed to calculate check-in count for selected range.');
    }
}

// ========================================
// LOGOUT WITH DUTY WARNING
// ========================================
function logout() {
    if (isOnDuty) {
        if (!confirm('You are currently marked ON DUTY.\nDo you want to logout now? (Note: Your duty session remains active until checked out).\n\nClick OK to proceed with logout.')) {
            return;
        }
    } else {
        if (!confirm('Are you sure you want to logout from Staff Portal?')) {
            return;
        }
    }
    window.location.href = '/STI_Queueing-System/api/logout.php';
}
window.logout = logout;
