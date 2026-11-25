// Hospital Doctor Dashboard JavaScript

let currentTicket = null;
let doctorId = null;
let departmentId = null;
let currentTab = 'queue';
let allDepartments = [];
let sameDeptDoctors = [];
let isOnDuty = false;

// History state
let docHistoryPage = 1;
let docHistoryLimit = 25;
let docHistoryTotalPages = 1;
let docFilters = {};

document.addEventListener('DOMContentLoaded', async function() {
    doctorId = parseInt(document.body.dataset.doctorId);
    departmentId = parseInt(document.body.dataset.departmentId);

    // Heartbeat every 30s with role check
    await runHeartbeat();
    setInterval(runHeartbeat, 30000);

    // Check duty attendance status
    await checkDutyStatus();
    setInterval(checkDutyStatus, 15000);

    // Initial load
    await loadDepartments();
    await loadSameDeptDoctors();
    await updateDoctorQueue();

    // Auto-refresh queue every 3 seconds
    setInterval(() => {
        if (currentTab === 'queue') {
            updateDoctorQueue();
        }
    }, 3000);

    showTab('queue');
});

async function runHeartbeat() {
    try {
        const res = await fetch('/STI_Queueing-System/api/heartbeat.php');
        if (res.status === 401) {
            window.location.href = '/STI_Queueing-System/doctor-login';
            return;
        }
        const data = await res.json();
        if (!data.success) {
            window.location.href = '/STI_Queueing-System/doctor-login';
            return;
        }
        if (data.role !== 'doctor') {
            if (data.role === 'admin') {
                window.location.href = '/STI_Queueing-System/admin-dashboard';
            } else if (data.role === 'staff') {
                window.location.href = '/STI_Queueing-System/staff-dashboard';
            } else {
                window.location.href = '/STI_Queueing-System/doctor-login';
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
            window.location.href = '/STI_Queueing-System/doctor-login';
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
    const tabs = ['queue', 'consultations', 'profile'];
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

    if (tabName === 'queue') {
        updateDoctorQueue();
    } else if (tabName === 'consultations') {
        loadDoctorStats();
        loadDoctorHistory(1);
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

async function loadDepartments() {
    try {
        const res = await fetch('/STI_Queueing-System/api/get-departments.php');
        const data = await res.json();
        if (data.success && data.departments) {
            allDepartments = data.departments.filter(d => d.name !== 'Admin');
            const targetDeptSelect = document.getElementById('referTargetDept');
            if (targetDeptSelect) {
                targetDeptSelect.innerHTML = '<option value="">-- Select Target Department --</option>' +
                    allDepartments.map(d => `<option value="${d.id}">${escapeHtml(d.name)} (${escapeHtml(d.prefix)} - Window ${d.window_number})</option>`).join('');
            }
        }
    } catch (e) {
        console.error('Failed to load departments:', e);
    }
}

async function loadSameDeptDoctors() {
    try {
        const res = await fetch(`/STI_Queueing-System/api/get-doctors.php?department_id=${encodeURIComponent(departmentId)}`);
        const data = await res.json();
        if (data.success && data.doctors) {
            // Exclude current doctor
            sameDeptDoctors = data.doctors.filter(d => d.id !== doctorId);
        }
    } catch (e) {
        console.error('Failed to load same department doctors:', e);
    }
}

// ========================================
// 1. DOCTOR LIVE QUEUE
// ========================================
async function updateDoctorQueue() {
    try {
        const res = await fetch('/STI_Queueing-System/api/get-doctor-queue.php');
        const data = await res.json();

        if (data.success) {
            const serving = data.serving;
            const waiting = data.waiting || [];

            // Update waiting count
            document.getElementById('doctorQueueCount').textContent = waiting.length;

            // Render waiting list table
            renderWaitingList(waiting);

            // Render current serving ticket
            renderServingTicket(serving);
        }
    } catch (e) {
        console.error('Error fetching doctor queue:', e);
    }
}

function renderWaitingList(waiting) {
    const tbody = document.getElementById('doctorWaitingListBody');
    if (!tbody) return;

    if (waiting.length === 0) {
        tbody.innerHTML = '<tr><td colspan="4" style="text-align:center; padding: 12px; color: #888;">No waiting patients</td></tr>';
        return;
    }

    tbody.innerHTML = waiting.map(t => {
        const priorityTag = t.is_priority 
            ? '<span style="background:#ffc107; color:#000; font-size:10px; font-weight:bold; padding:1px 5px; border-radius:3px; margin-left:4px;">PRIORITY</span>' 
            : '';
        const bpWeight = [t.bp ? `BP: ${t.bp}` : '', t.weight_kg ? `${t.weight_kg}kg` : ''].filter(Boolean).join(' / ') || '---';

        return `
            <tr style="border-bottom: 1px solid #eef2f5;">
                <td style="padding: 8px;"><strong>${escapeHtml(t.ticket_number)}</strong>${priorityTag}</td>
                <td style="padding: 8px; font-weight: 500;">${escapeHtml(t.patient_name || 'Patient')}</td>
                <td style="padding: 8px; color: #666;">${escapeHtml(t.appointment_number || '---')}</td>
                <td style="padding: 8px; font-size: 12px; color: #555;">${escapeHtml(bpWeight)}</td>
            </tr>
        `;
    }).join('');
}

function renderServingTicket(serving) {
    currentTicket = serving;

    const ticketNumberEl = document.getElementById('servingTicketNumber');
    const badgeEl = document.getElementById('servingStatusBadge');
    const nameEl = document.getElementById('patientName');
    const apptEl = document.getElementById('patientAppt');
    const ageGenderEl = document.getElementById('patientAgeGender');
    const bpEl = document.getElementById('patientBp');
    const weightEl = document.getElementById('patientWeight');
    const priorityBadgeEl = document.getElementById('patientPriorityBadge');
    const referralBox = document.getElementById('patientReferralInfo');

    const actionBtns = [
        document.getElementById('btnActionDone'),
        document.getElementById('btnActionRecall'),
        document.getElementById('btnActionNoShow'),
        document.getElementById('btnActionCancel'),
        document.getElementById('btnActionRefer')
    ];

    if (!serving) {
        ticketNumberEl.textContent = '---';
        badgeEl.textContent = 'No Patient Serving';
        badgeEl.className = 'badge-status status-waiting';
        nameEl.textContent = 'No Active Patient';
        apptEl.textContent = '---';
        ageGenderEl.textContent = '---';
        bpEl.textContent = '---';
        weightEl.textContent = '---';
        priorityBadgeEl.style.display = 'none';
        referralBox.style.display = 'none';

        actionBtns.forEach(btn => {
            if (btn) btn.disabled = true;
        });
    } else {
        ticketNumberEl.textContent = serving.ticket_number;
        badgeEl.textContent = 'In Consultation';
        badgeEl.className = 'badge-status status-serving';
        nameEl.textContent = serving.patient_name || 'Patient';
        apptEl.textContent = serving.appointment_number || '---';

        const ageGender = [serving.age ? `${serving.age} yrs` : '', serving.gender || ''].filter(Boolean).join(' / ') || '---';
        ageGenderEl.textContent = ageGender;
        bpEl.textContent = serving.bp || '---';
        weightEl.textContent = serving.weight_kg ? `${serving.weight_kg} kg` : '---';

        if (serving.is_priority) {
            priorityBadgeEl.style.display = 'inline-block';
        } else {
            priorityBadgeEl.style.display = 'none';
        }

        if (serving.referred_from_ticket_id) {
            referralBox.style.display = 'block';
            referralBox.innerHTML = `<strong>↗ Referral:</strong> Patient referred from previous ticket #${escapeHtml(serving.referred_from_ticket_id)}. Notes: <em>${escapeHtml(serving.referral_notes || 'None')}</em>`;
        } else {
            referralBox.style.display = 'none';
        }

        actionBtns.forEach(btn => {
            if (btn) btn.disabled = false;
        });
    }
}

// ========================================
// QUEUE ACTIONS
// ========================================
async function callNextPatient() {
    const btn = document.getElementById('btnCallNext');
    btn.disabled = true;
    btn.textContent = 'Calling next...';

    try {
        const res = await fetch('/STI_Queueing-System/api/call-next.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' }
        });
        if (res.status === 401) {
            window.location.href = '/STI_Queueing-System/doctor-login';
            return;
        }
        const data = await res.json();

        if (data.success) {
            await updateDoctorQueue();
        } else {
            alert(data.message || 'No patients waiting in your queue.');
        }
    } catch (e) {
        alert('Network error while calling next patient.');
    } finally {
        btn.disabled = false;
        btn.textContent = '📢 Call Next Patient';
    }
}

async function markDone() {
    if (!currentTicket) return;
    if (!confirm(`Mark consultation as completed for ticket ${currentTicket.ticket_number}?`)) return;

    try {
        const res = await fetch('/STI_Queueing-System/api/complete-ticket.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ ticket_id: currentTicket.id, action: 'complete' })
        });
        if (res.status === 401) {
            window.location.href = '/STI_Queueing-System/doctor-login';
            return;
        }
        const data = await res.json();
        if (data.success) {
            updateDoctorQueue();
        } else {
            alert(data.message || 'Failed to complete consultation.');
        }
    } catch (e) {
        alert('Network error completing consultation.');
    }
}

async function recallPatient() {
    if (!currentTicket) return;

    try {
        const res = await fetch('/STI_Queueing-System/api/recall-ticket.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ ticket_id: currentTicket.id })
        });
        if (res.status === 401) {
            window.location.href = '/STI_Queueing-System/doctor-login';
            return;
        }
        const data = await res.json();
        if (data.success) {
            alert(`Patient ${currentTicket.ticket_number} recalled.`);
        } else {
            alert(data.message || 'Failed to recall patient.');
        }
    } catch (e) {
        alert('Network error recalling patient.');
    }
}

async function markNoShow() {
    if (!currentTicket) return;
    if (!confirm(`Mark patient ${currentTicket.ticket_number} as No-Show?`)) return;

    try {
        const res = await fetch('/STI_Queueing-System/api/complete-ticket.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ ticket_id: currentTicket.id, action: 'no-show' })
        });
        if (res.status === 401) {
            window.location.href = '/STI_Queueing-System/doctor-login';
            return;
        }
        const data = await res.json();
        if (data.success) {
            updateDoctorQueue();
        } else {
            alert(data.message || 'Failed to update ticket.');
        }
    } catch (e) {
        alert('Network error updating ticket.');
    }
}

async function markCancel() {
    if (!currentTicket) return;
    if (!confirm(`Cancel consultation for ticket ${currentTicket.ticket_number}?`)) return;

    try {
        const res = await fetch('/STI_Queueing-System/api/complete-ticket.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ ticket_id: currentTicket.id, action: 'cancel' })
        });
        if (res.status === 401) {
            window.location.href = '/STI_Queueing-System/doctor-login';
            return;
        }
        const data = await res.json();
        if (data.success) {
            updateDoctorQueue();
        } else {
            alert(data.message || 'Failed to cancel ticket.');
        }
    } catch (e) {
        alert('Network error cancelling ticket.');
    }
}

// ========================================
// REFERRAL MODAL & ACTIONS
// ========================================
function openReferralModal() {
    if (!currentTicket) {
        alert('No active patient to refer.');
        return;
    }

    const summaryBox = document.getElementById('referralPatientSummary');
    summaryBox.innerHTML = `
        <strong>Referring Patient:</strong> ${escapeHtml(currentTicket.patient_name)} | 
        <strong>Ticket:</strong> ${escapeHtml(currentTicket.ticket_number)} | 
        <strong>Appt #:</strong> ${escapeHtml(currentTicket.appointment_number)}
    `;

    document.getElementById('referTypeDoctor').checked = true;
    handleReferralTypeChange();

    document.getElementById('referPriority').checked = !!currentTicket.is_priority;
    document.getElementById('referNotes').value = '';
    document.getElementById('referralMessage').style.display = 'none';

    document.getElementById('referralModal').classList.add('active');
}

function closeReferralModal() {
    document.getElementById('referralModal').classList.remove('active');
}

function handleReferralTypeChange() {
    const isDept = document.getElementById('referTypeDept').checked;
    const targetDeptGroup = document.getElementById('targetDeptGroup');
    const targetDocSelect = document.getElementById('referTargetDoctor');

    if (isDept) {
        targetDeptGroup.style.display = 'block';
        targetDocSelect.innerHTML = '<option value="">-- Select Department First --</option>';
    } else {
        targetDeptGroup.style.display = 'none';
        // Populate same department doctors
        if (sameDeptDoctors.length === 0) {
            targetDocSelect.innerHTML = '<option value="">-- No other doctors in this department --</option>';
        } else {
            targetDocSelect.innerHTML = '<option value="">-- Select Colleague Doctor --</option>' +
                sameDeptDoctors.map(d => `<option value="${d.id}">${escapeHtml(d.full_name)} (${escapeHtml(d.specialization)} - ${escapeHtml(d.room_label)})</option>`).join('');
        }
    }
}

async function loadDoctorsForReferralDept() {
    const deptId = document.getElementById('referTargetDept').value;
    const docSelect = document.getElementById('referTargetDoctor');
    docSelect.innerHTML = '<option value="">-- Select Doctor --</option>';

    if (!deptId) return;

    try {
        const res = await fetch(`/STI_Queueing-System/api/get-doctors.php?department_id=${encodeURIComponent(deptId)}`);
        const data = await res.json();
        if (data.success && data.doctors) {
            if (data.doctors.length === 0) {
                docSelect.innerHTML = '<option value="">-- No active doctors in selected department --</option>';
            } else {
                data.doctors.forEach(d => {
                    const opt = document.createElement('option');
                    opt.value = d.id;
                    opt.textContent = `${d.full_name} (${d.specialization} - ${d.room_label})`;
                    docSelect.appendChild(opt);
                });
            }
        }
    } catch (e) {
        console.error('Failed to load referral doctors:', e);
    }
}

async function submitReferral(e) {
    e.preventDefault();
    const msgEl = document.getElementById('referralMessage');
    msgEl.style.display = 'none';

    if (!currentTicket) return;

    const isDept = document.getElementById('referTypeDept').checked;
    let targetDeptId = isDept ? document.getElementById('referTargetDept').value : departmentId;
    let targetDocId = document.getElementById('referTargetDoctor').value;
    const notes = document.getElementById('referNotes').value.trim();
    const isPriority = document.getElementById('referPriority').checked;

    if (isDept && !targetDeptId) {
        msgEl.textContent = 'Please select target department.';
        msgEl.className = 'form-message error';
        msgEl.style.display = 'block';
        return;
    }

    const payload = {
        ticket_id: currentTicket.id,
        target_department_id: targetDeptId ? parseInt(targetDeptId, 10) : null,
        target_doctor_id: targetDocId ? parseInt(targetDocId, 10) : null,
        notes: notes,
        is_priority: isPriority
    };

    try {
        const res = await fetch('/STI_Queueing-System/api/refer-patient.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        if (res.status === 401) {
            window.location.href = '/STI_Queueing-System/doctor-login';
            return;
        }
        const data = await res.json();

        if (data.success) {
            closeReferralModal();
            updateDoctorQueue();
            alert('Patient referred successfully. Please advise patient to proceed to the target counter with their report.');
        } else {
            msgEl.textContent = data.message || 'Referral failed.';
            msgEl.className = 'form-message error';
            msgEl.style.display = 'block';
        }
    } catch (err) {
        msgEl.textContent = 'Network error processing referral.';
        msgEl.className = 'form-message error';
        msgEl.style.display = 'block';
    }
}

// ========================================
// 2. DOCTOR CONSULTATIONS TAB
// ========================================
async function loadDoctorStats() {
    try {
        const res = await fetch('/STI_Queueing-System/api/get-doctor-stats.php');
        const data = await res.json();

        if (data.success && data.stats) {
            const s = data.stats;
            document.getElementById('docSeenToday').textContent = s.today_seen || 0;
            document.getElementById('docCompletedToday').textContent = s.today_completed || 0;
            document.getElementById('docReferredToday').textContent = s.today_referred || 0;
            document.getElementById('docNoShowToday').textContent = s.today_noshow || 0;
        }
    } catch (e) {
        console.error('Failed to load doctor stats:', e);
    }
}

function applyDoctorFilters() {
    docFilters = {
        startDate: document.getElementById('docFilterStart').value,
        endDate: document.getElementById('docFilterEnd').value,
        status: document.getElementById('docFilterStatus').value,
        search: document.getElementById('docFilterSearch').value.trim()
    };
    loadDoctorHistory(1);
}

function resetDoctorFilters() {
    document.getElementById('docFilterStart').value = '';
    document.getElementById('docFilterEnd').value = '';
    document.getElementById('docFilterStatus').value = 'all';
    document.getElementById('docFilterSearch').value = '';
    docFilters = {};
    loadDoctorHistory(1);
}

async function loadDoctorHistory(page = 1) {
    docHistoryPage = page;
    const tbody = document.getElementById('doctorHistoryBody');
    tbody.innerHTML = '<tr><td colspan="10" class="loading">Loading clinical history...</td></tr>';

    const params = new URLSearchParams({
        page: docHistoryPage,
        limit: docHistoryLimit
    });

    if (docFilters.startDate) params.append('startDate', docFilters.startDate);
    if (docFilters.endDate) params.append('endDate', docFilters.endDate);
    if (docFilters.status && docFilters.status !== 'all') params.append('status', docFilters.status);
    if (docFilters.search) params.append('search', docFilters.search);

    try {
        const res = await fetch(`/STI_Queueing-System/api/get-queue-history.php?${params.toString()}`);
        const data = await res.json();

        if (data.success) {
            const records = data.history || [];
            const total = data.total || 0;
            docHistoryTotalPages = Math.max(1, Math.ceil(total / docHistoryLimit));

            document.getElementById('docPageInfo').textContent = `Page ${docHistoryPage} of ${docHistoryTotalPages} (${total} total)`;
            document.getElementById('btnDocPrev').disabled = docHistoryPage <= 1;
            document.getElementById('btnDocNext').disabled = docHistoryPage >= docHistoryTotalPages;

            if (records.length === 0) {
                tbody.innerHTML = '<tr><td colspan="10" style="text-align: center; padding: 20px; color: #888;">No consultation records found.</td></tr>';
                return;
            }

            tbody.innerHTML = records.map(r => {
                const statusBadge = `<span class="badge-status status-${escapeHtml(r.status)}">${escapeHtml(r.status.toUpperCase())}</span>`;
                const ageGender = [r.age ? `${r.age}y` : '', r.gender || ''].filter(Boolean).join(' / ') || '---';
                const bpWeight = [r.bp ? `BP: ${r.bp}` : '', r.weight_kg ? `${r.weight_kg}kg` : ''].filter(Boolean).join(' / ') || '---';

                let referralText = '---';
                if (r.referred_to_department_name || r.referred_to_doctor_name) {
                    referralText = [r.referred_to_department_name, r.referred_to_doctor_name].filter(Boolean).join(' - ');
                }

                return `
                    <tr>
                        <td><strong>${escapeHtml(r.ticket_number)}</strong></td>
                        <td>${escapeHtml(r.patient_name || 'Patient')}</td>
                        <td>${escapeHtml(r.appointment_number || '---')}</td>
                        <td>${escapeHtml(ageGender)}</td>
                        <td>${escapeHtml(bpWeight)}</td>
                        <td>${escapeHtml(r.called_at || '---')}</td>
                        <td>${escapeHtml(r.completed_at || '---')}</td>
                        <td>${statusBadge}</td>
                        <td>${escapeHtml(referralText)}</td>
                        <td style="font-size: 12px; color: #555;">${escapeHtml(r.referral_notes || r.notes || '---')}</td>
                    </tr>
                `;
            }).join('');
        } else {
            tbody.innerHTML = `<tr><td colspan="10" style="color:red; text-align:center;">${escapeHtml(data.message)}</td></tr>`;
        }
    } catch (e) {
        tbody.innerHTML = `<tr><td colspan="10" style="color:red; text-align:center;">Network error loading history</td></tr>`;
    }
}

function loadPrevDocPage() {
    if (docHistoryPage > 1) loadDoctorHistory(docHistoryPage - 1);
}
function loadNextDocPage() {
    if (docHistoryPage < docHistoryTotalPages) loadDoctorHistory(docHistoryPage + 1);
}

function exportDoctorHistory(format) {
    const params = new URLSearchParams({ format });
    if (docFilters.startDate) params.append('startDate', docFilters.startDate);
    if (docFilters.endDate) params.append('endDate', docFilters.endDate);
    if (docFilters.status && docFilters.status !== 'all') params.append('status', docFilters.status);
    if (docFilters.search) params.append('search', docFilters.search);

    window.open(`/STI_Queueing-System/api/export-queue-history.php?${params.toString()}`, '_blank');
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
        if (!confirm('Are you sure you want to logout from Doctor Portal?')) {
            return;
        }
    }
    window.location.href = '/STI_Queueing-System/api/logout.php';
}
window.logout = logout;
