// Hospital Admin Dashboard JavaScript

let currentTab = 'overview';
let allDepartments = [];
let allRoster = [];

// History state
let historyPage = 1;
let historyLimit = 25;
let historyTotalPages = 1;
let historyFilters = {};

// Staff & Doctor state
let allStaff = [];
let allDoctors = [];

// Attendance state
let attendancePage = 1;
let attendanceLimit = 25;
let attendanceTotalPages = 1;
let attendanceFilters = {};
let currentAttendanceSubtab = 'duty';
let pendingDelete = null;

document.addEventListener('DOMContentLoaded', async function() {
    // Start heartbeat immediately and every 30s with role validation
    await runHeartbeat();
    setInterval(runHeartbeat, 30000);

    // Bind delete confirmation modal action
    const btnConfirmDelete = document.getElementById('btnConfirmDeleteAction');
    if (btnConfirmDelete) {
        btnConfirmDelete.onclick = executeDelete;
    }

    // Load common dependencies
    await loadDepartments();

    // Load initial tab
    await loadOverviewData();

    // Auto-refresh overview every 5 seconds
    setInterval(() => {
        if (currentTab === 'overview') {
            loadOverviewData(true);
        }
    }, 5000);
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
        if (data.role !== 'admin') {
            if (data.role === 'staff') {
                window.location.href = '/STI_Queueing-System/staff-dashboard';
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

// ========================================
// TAB SWITCHING
// ========================================
function showTab(tabName) {
    currentTab = tabName;
    const tabs = ['overview', 'history', 'staff', 'doctors', 'attendance'];
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

    if (tabName === 'overview') {
        loadOverviewData();
    } else if (tabName === 'history') {
        loadHistory(1);
    } else if (tabName === 'staff') {
        loadStaffList();
    } else if (tabName === 'doctors') {
        loadDoctorList();
    } else if (tabName === 'attendance') {
        loadAttendanceLogs(1);
    }
}

// ========================================
// COMMON HELPERS
// ========================================
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
            allDepartments = data.departments;
            populateDeptDropdowns();
        }
    } catch (e) {
        console.error('Failed to load departments:', e);
    }
}

function populateDeptDropdowns() {
    const dropdownIds = [
        'filterDept',
        'staffManageDeptFilter',
        'doctorManageDeptFilter',
        'addStaffDepartment',
        'editStaffDepartment',
        'addDocDepartment',
        'editDocDepartment',
        'attFilterDept'
    ];

    dropdownIds.forEach(id => {
        const el = document.getElementById(id);
        if (!el) return;

        const currentVal = el.value;
        const isFilter = id.includes('Filter') || id === 'filterDept';
        el.innerHTML = isFilter ? '<option value="">All Departments</option>' : '<option value="">-- Select Department --</option>';

        allDepartments.forEach(dept => {
            // Do not show Admin department for doctor registration
            if (id.includes('Doc') && dept.name === 'Admin') return;
            const opt = document.createElement('option');
            opt.value = dept.id;
            opt.textContent = `${dept.name} (${dept.prefix} - Window ${dept.window_number})`;
            el.appendChild(opt);
        });

        if (currentVal) el.value = currentVal;
    });
}

// ========================================
// 1. OVERVIEW TAB
// ========================================
async function loadOverviewData(isBackground = false) {
    try {
        const res = await fetch('/STI_Queueing-System/api/get-admin-overview.php');
        const data = await res.json();

        if (data.success) {
            const m = data.metrics || {};
            document.getElementById('cardTotalDoctors').textContent = m.total_active_doctors || 0;
            document.getElementById('cardTotalStaff').textContent = m.total_active_staff || 0;
            document.getElementById('cardPatientsWaiting').textContent = m.waiting_now || 0;
            document.getElementById('cardPatientsServing').textContent = m.serving_now || 0;
            document.getElementById('cardTodayCheckedIn').textContent = m.checked_in_today || 0;
            document.getElementById('cardTodayCompleted').textContent = m.completed_today || 0;

            document.getElementById('cardSecCompleted').textContent = m.completed_today || 0;
            document.getElementById('cardSecReferred').textContent = m.referred_today || 0;
            document.getElementById('cardSecNoShow').textContent = m.noshow_today || 0;
            document.getElementById('cardSecCancelled').textContent = m.cancelled_today || 0;

            // Combine doctors and staff for roster
            const rosterDocs = (data.roster && data.roster.doctors) ? data.roster.doctors : [];
            const rosterStaff = (data.roster && data.roster.staff) ? data.roster.staff : [];

            const onlineDocs = rosterDocs.filter(d => parseInt(d.is_online) === 1).length;
            const onlineStaff = rosterStaff.filter(s => parseInt(s.is_online) === 1).length;

            document.getElementById('cardOnlineDoctors').textContent = onlineDocs;
            document.getElementById('cardOnlineStaff').textContent = onlineStaff;

            // Update timestamp
            const now = new Date();
            const timeStr = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
            document.getElementById('overviewLastUpdated').textContent = `Last updated: ${timeStr}`;

            // Render department breakdown
            renderDepartmentBreakdown(data.departments || []);

            // Save and render personnel roster
            allRoster = [...rosterDocs, ...rosterStaff];
            renderRosterTable();
        }
    } catch (e) {
        if (!isBackground) console.error('Overview loading error:', e);
    }
}

function renderDepartmentBreakdown(depts) {
    const tbody = document.getElementById('overviewDeptBody');
    if (!tbody) return;

    if (depts.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" style="text-align: center; padding: 15px; color: #888;">No departments found</td></tr>';
        return;
    }

    tbody.innerHTML = depts.map(d => `
        <tr style="border-bottom: 1px solid #eef2f5;">
            <td style="padding: 10px 8px; font-weight: 600; color: #2c3e50;">
                ${escapeHtml(d.name)}
                <span style="font-size: 11px; color: #888; font-weight: normal; margin-left: 4px;">(${escapeHtml(d.prefix)} - W${d.window_number})</span>
            </td>
            <td style="padding: 10px 8px; text-align: center; font-weight: bold; color: ${d.waiting_count > 0 ? '#e67e22' : '#888'};">
                ${d.waiting_count}
            </td>
            <td style="padding: 10px 8px; text-align: center; font-weight: bold; color: ${d.serving_count > 0 ? '#27ae60' : '#888'};">
                ${d.serving_count}
            </td>
            <td style="padding: 10px 8px; text-align: center; color: #2980b9;">
                ${d.completed_today}
            </td>
            <td style="padding: 10px 8px; text-align: center; color: #555;">
                ${d.staff_count !== undefined ? d.staff_count : (d.active_staff_count || 0)}
            </td>
            <td style="padding: 10px 8px; text-align: center; color: #555;">
                ${d.doctor_count !== undefined ? d.doctor_count : (d.active_doctor_count || 0)}
            </td>
        </tr>
    `).join('');
}

function renderRosterTable() {
    const tbody = document.getElementById('overviewRosterBody');
    if (!tbody) return;

    const filterVal = document.getElementById('rosterFilter').value;
    let filtered = allRoster.slice();

    if (filterVal === 'online') {
        filtered = filtered.filter(p => p.is_online);
    } else if (filterVal === 'doctor') {
        filtered = filtered.filter(p => p.role === 'doctor');
    } else if (filterVal === 'staff') {
        filtered = filtered.filter(p => p.role === 'staff' || p.role === 'admin');
    }

    if (filtered.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" style="text-align: center; padding: 15px; color: #888;">No personnel match criteria</td></tr>';
        return;
    }

    tbody.innerHTML = filtered.map(p => {
        const isDoc = p.role === 'doctor';
        const roleBadgeClass = isDoc ? 'role-doctor' : (p.role === 'admin' ? 'role-admin' : 'role-staff');
        const roleLabel = isDoc ? 'Doctor' : (p.role === 'admin' ? 'Admin' : 'Staff');
        const statusBadge = p.is_online 
            ? '<span class="badge-online">Online</span>' 
            : '<span class="badge-offline">Offline</span>';
        const dutyBadge = p.is_on_duty 
            ? '<span class="badge-onduty" style="margin-left: 4px;">On Duty</span>' 
            : '<span class="badge-offduty" style="margin-left: 4px;">Off Duty</span>';

        return `
            <tr style="border-bottom: 1px solid #eef2f5;">
                <td style="padding: 8px;"><span class="badge-role ${roleBadgeClass}">${roleLabel}</span></td>
                <td style="padding: 8px; font-weight: 600; color: #2c3e50;">
                    ${escapeHtml(p.full_name)}
                    <span style="font-size: 11px; color: #888; display: block; font-weight: normal;">@${escapeHtml(p.username || '')}</span>
                </td>
                <td style="padding: 8px; color: #555;">${escapeHtml(p.department_name || '---')}</td>
                <td style="padding: 8px; font-size: 12px; color: #666;">${escapeHtml(p.last_login_at || 'Never')}</td>
                <td style="padding: 8px; text-align: center;">${statusBadge} ${dutyBadge}</td>
            </tr>
        `;
    }).join('');
}

// ========================================
// 2. PATIENT HISTORY TAB
// ========================================
async function handleHistoryDeptChange() {
    const deptId = document.getElementById('filterDept').value;
    const docSelect = document.getElementById('filterDoctor');
    docSelect.innerHTML = '<option value="">All Doctors</option>';

    if (!deptId) return;

    try {
        const res = await fetch(`/STI_Queueing-System/api/get-doctors.php?department_id=${encodeURIComponent(deptId)}`);
        const data = await res.json();
        if (data.success && data.doctors) {
            data.doctors.forEach(d => {
                const opt = document.createElement('option');
                opt.value = d.id;
                opt.textContent = `${d.full_name} (${d.specialization})`;
                docSelect.appendChild(opt);
            });
        }
    } catch (e) {
        console.error('Failed to load doctors for filter:', e);
    }
}

function applyHistoryFilters() {
    historyFilters = {
        startDate: document.getElementById('filterStartDate').value,
        endDate: document.getElementById('filterEndDate').value,
        department_id: document.getElementById('filterDept').value,
        doctor_id: document.getElementById('filterDoctor').value,
        status: document.getElementById('filterStatus').value,
        search: document.getElementById('filterSearch').value.trim()
    };
    loadHistory(1);
}

function resetHistoryFilters() {
    document.getElementById('filterStartDate').value = '';
    document.getElementById('filterEndDate').value = '';
    document.getElementById('filterDept').value = '';
    document.getElementById('filterDoctor').innerHTML = '<option value="">All Doctors</option>';
    document.getElementById('filterStatus').value = 'all';
    document.getElementById('filterSearch').value = '';
    historyFilters = {};
    loadHistory(1);
}

async function loadHistory(page = 1) {
    historyPage = page;
    const tbody = document.getElementById('historyTableBody');
    tbody.innerHTML = '<tr><td colspan="13" class="loading">Loading patient history...</td></tr>';

    const params = new URLSearchParams({
        page: historyPage,
        limit: historyLimit
    });

    if (historyFilters.startDate) params.append('startDate', historyFilters.startDate);
    if (historyFilters.endDate) params.append('endDate', historyFilters.endDate);
    if (historyFilters.department_id) params.append('department_id', historyFilters.department_id);
    if (historyFilters.doctor_id) params.append('doctor_id', historyFilters.doctor_id);
    if (historyFilters.status && historyFilters.status !== 'all') params.append('status', historyFilters.status);
    if (historyFilters.search) params.append('search', historyFilters.search);

    try {
        const res = await fetch(`/STI_Queueing-System/api/get-queue-history.php?${params.toString()}`);
        const data = await res.json();

        if (data.success) {
            const records = data.history || [];
            const total = data.total || 0;
            historyTotalPages = Math.max(1, Math.ceil(total / historyLimit));

            document.getElementById('pageInfo').textContent = `Page ${historyPage} of ${historyTotalPages} (${total} total)`;
            document.getElementById('btnPrevPage').disabled = historyPage <= 1;
            document.getElementById('btnNextPage').disabled = historyPage >= historyTotalPages;

            if (records.length === 0) {
                tbody.innerHTML = '<tr><td colspan="13" style="text-align: center; padding: 20px; color: #888;">No history records found.</td></tr>';
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
                        <td>${escapeHtml(r.department_name || '---')}</td>
                        <td>${escapeHtml(r.doctor_name || 'Unassigned')}</td>
                        <td>${escapeHtml(r.created_at || '---')}</td>
                        <td>${escapeHtml(r.called_at || '---')}</td>
                        <td>${escapeHtml(r.completed_at || '---')}</td>
                        <td>${statusBadge}</td>
                        <td>${escapeHtml(r.staff_name || r.checked_in_by_staff_name || '---')}</td>
                        <td>${escapeHtml(referralText)}</td>
                    </tr>
                `;
            }).join('');
        } else {
            tbody.innerHTML = `<tr><td colspan="13" style="color:red; text-align:center;">${escapeHtml(data.message || 'Failed to load history')}</td></tr>`;
        }
    } catch (e) {
        tbody.innerHTML = `<tr><td colspan="13" style="color:red; text-align:center;">Network error loading history</td></tr>`;
    }
}

function loadPreviousHistoryPage() {
    if (historyPage > 1) loadHistory(historyPage - 1);
}

function loadNextHistoryPage() {
    if (historyPage < historyTotalPages) loadHistory(historyPage + 1);
}

function exportHistory(format) {
    const params = new URLSearchParams({ format });
    if (historyFilters.startDate) params.append('startDate', historyFilters.startDate);
    if (historyFilters.endDate) params.append('endDate', historyFilters.endDate);
    if (historyFilters.department_id) params.append('department_id', historyFilters.department_id);
    if (historyFilters.doctor_id) params.append('doctor_id', historyFilters.doctor_id);
    if (historyFilters.status && historyFilters.status !== 'all') params.append('status', historyFilters.status);
    if (historyFilters.search) params.append('search', historyFilters.search);

    window.open(`/STI_Queueing-System/api/export-queue-history.php?${params.toString()}`, '_blank');
}

// ========================================
// 3. STAFF MANAGEMENT TAB
// ========================================
async function loadStaffList() {
    const tbody = document.getElementById('staffManageTableBody');
    tbody.innerHTML = '<tr><td colspan="7" class="loading">Loading staff members...</td></tr>';

    const deptId = document.getElementById('staffManageDeptFilter').value;
    const params = new URLSearchParams({ action: 'list' });
    if (deptId) params.append('department_id', deptId);

    try {
        const res = await fetch(`/STI_Queueing-System/api/manage-staff.php?${params.toString()}`);
        const data = await res.json();

        if (data.success) {
            allStaff = data.staff || [];
            filterStaffTable();
        } else {
            tbody.innerHTML = `<tr><td colspan="7" style="color:red; text-align:center;">${escapeHtml(data.message)}</td></tr>`;
        }
    } catch (e) {
        tbody.innerHTML = `<tr><td colspan="7" style="color:red; text-align:center;">Network error loading staff</td></tr>`;
    }
}

function filterStaffTable() {
    const tbody = document.getElementById('staffManageTableBody');
    const searchVal = document.getElementById('staffManageSearchFilter').value.toLowerCase().trim();
    
    let filtered = allStaff;
    if (searchVal) {
        filtered = filtered.filter(s => 
            (s.username && s.username.toLowerCase().includes(searchVal)) ||
            (s.full_name && s.full_name.toLowerCase().includes(searchVal)) ||
            (s.department_name && s.department_name.toLowerCase().includes(searchVal))
        );
    }

    document.getElementById('staffCountDisplay').textContent = `Total: ${filtered.length} staff members`;

    if (filtered.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" style="text-align: center; padding: 15px; color: #888;">No staff members found</td></tr>';
        return;
    }

    tbody.innerHTML = filtered.map(s => {
        const roleLabel = s.role === 'admin' ? '<span class="badge-role role-admin">Admin</span>' : '<span class="badge-role role-staff">Staff</span>';
        const statusBadge = s.is_active ? '<span class="badge-active">Active</span>' : '<span class="badge-inactive">Inactive</span>';
        const toggleBtnLabel = s.is_active ? 'Deactivate' : 'Activate';
        const toggleBtnClass = s.is_active ? 'btn-filter-reset' : 'btn-filter';

        return `
            <tr>
                <td><strong>@${escapeHtml(s.username)}</strong></td>
                <td>${escapeHtml(s.full_name)}</td>
                <td>${escapeHtml(s.department_name || '---')}</td>
                <td>${roleLabel}</td>
                <td>${statusBadge}</td>
                <td>${escapeHtml(s.last_login_at || 'Never')}</td>
                <td>
                    <button class="btn-filter" style="padding: 4px 8px; font-size: 12px;" onclick="openEditStaffModal(${s.id})">✏️ Edit</button>
                    ${s.username !== 'admin' ? `
                        <button class="${toggleBtnClass}" style="padding: 4px 8px; font-size: 12px; margin-left: 4px;" onclick="toggleStaffActive(${s.id}, ${s.is_active})">${toggleBtnLabel}</button>
                        <button class="btn-filter-reset" style="padding: 4px 8px; font-size: 12px; margin-left: 4px; background: #e74c3c; color: white; border: none; border-radius: 4px; cursor: pointer;" onclick="promptDeleteStaff(${s.id}, '${escapeHtml(s.username)}', '${escapeHtml(s.full_name)}')">🗑️ Delete</button>
                    ` : ''}
                </td>
            </tr>
        `;
    }).join('');
}

function openAddStaffModal() {
    document.getElementById('addStaffForm').reset();
    document.getElementById('addStaffMessage').style.display = 'none';
    document.getElementById('addStaffModal').classList.add('active');
}
function closeAddStaffModal() {
    document.getElementById('addStaffModal').classList.remove('active');
}

async function submitAddStaff(e) {
    e.preventDefault();
    const msgEl = document.getElementById('addStaffMessage');
    msgEl.style.display = 'none';

    const payload = {
        action: 'create',
        username: document.getElementById('addStaffUsername').value.trim(),
        password: document.getElementById('addStaffPassword').value,
        full_name: document.getElementById('addStaffFullName').value.trim(),
        department_id: document.getElementById('addStaffDepartment').value
    };

    try {
        const res = await fetch('/STI_Queueing-System/api/manage-staff.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const data = await res.json();
        if (data.success) {
            closeAddStaffModal();
            loadStaffList();
            alert('Staff member created successfully!');
        } else {
            msgEl.textContent = data.message || 'Failed to add staff member';
            msgEl.className = 'form-message error';
            msgEl.style.display = 'block';
        }
    } catch (err) {
        msgEl.textContent = 'Network error adding staff member';
        msgEl.className = 'form-message error';
        msgEl.style.display = 'block';
    }
}

function openEditStaffModal(staffId) {
    const s = allStaff.find(item => item.id == staffId);
    if (!s) return;

    document.getElementById('editStaffId').value = s.id;
    document.getElementById('editStaffUsername').value = s.username;
    document.getElementById('editStaffFullName').value = s.full_name;
    document.getElementById('editStaffDepartment').value = s.department_id;
    document.getElementById('editStaffPassword').value = '';
    document.getElementById('editStaffMessage').style.display = 'none';
    document.getElementById('editStaffModal').classList.add('active');
}
function closeEditStaffModal() {
    document.getElementById('editStaffModal').classList.remove('active');
}

async function submitEditStaff(e) {
    e.preventDefault();
    const msgEl = document.getElementById('editStaffMessage');
    msgEl.style.display = 'none';

    const payload = {
        action: 'edit',
        id: document.getElementById('editStaffId').value,
        full_name: document.getElementById('editStaffFullName').value.trim(),
        department_id: document.getElementById('editStaffDepartment').value
    };
    const pwd = document.getElementById('editStaffPassword').value;
    if (pwd) payload.password = pwd;

    try {
        const res = await fetch('/STI_Queueing-System/api/manage-staff.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const data = await res.json();
        if (data.success) {
            closeEditStaffModal();
            loadStaffList();
            alert('Staff member updated successfully!');
        } else {
            msgEl.textContent = data.message || 'Failed to update staff';
            msgEl.className = 'form-message error';
            msgEl.style.display = 'block';
        }
    } catch (err) {
        msgEl.textContent = 'Network error updating staff';
        msgEl.className = 'form-message error';
        msgEl.style.display = 'block';
    }
}

async function toggleStaffActive(staffId, currentStatus) {
    const actionLabel = currentStatus ? 'deactivate' : 'activate';
    if (!confirm(`Are you sure you want to ${actionLabel} this staff member?`)) return;

    try {
        const res = await fetch('/STI_Queueing-System/api/manage-staff.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'toggle_active', id: staffId })
        });
        const data = await res.json();
        if (data.success) {
            loadStaffList();
        } else {
            alert(data.message || 'Action failed');
        }
    } catch (e) {
        alert('Network error modifying staff status');
    }
}

// ========================================
// 4. DOCTOR MANAGEMENT TAB
// ========================================
async function loadDoctorList() {
    const tbody = document.getElementById('doctorManageTableBody');
    tbody.innerHTML = '<tr><td colspan="8" class="loading">Loading doctors...</td></tr>';

    const deptId = document.getElementById('doctorManageDeptFilter').value;
    const params = new URLSearchParams({ action: 'list' });
    if (deptId) params.append('department_id', deptId);

    try {
        const res = await fetch(`/STI_Queueing-System/api/manage-doctors.php?${params.toString()}`);
        const data = await res.json();

        if (data.success) {
            allDoctors = data.doctors || [];
            filterDoctorTable();
        } else {
            tbody.innerHTML = `<tr><td colspan="8" style="color:red; text-align:center;">${escapeHtml(data.message)}</td></tr>`;
        }
    } catch (e) {
        tbody.innerHTML = `<tr><td colspan="8" style="color:red; text-align:center;">Network error loading doctors</td></tr>`;
    }
}

function filterDoctorTable() {
    const tbody = document.getElementById('doctorManageTableBody');
    const searchVal = document.getElementById('doctorManageSearchFilter').value.toLowerCase().trim();

    let filtered = allDoctors;
    if (searchVal) {
        filtered = filtered.filter(d => 
            (d.full_name && d.full_name.toLowerCase().includes(searchVal)) ||
            (d.username && d.username.toLowerCase().includes(searchVal)) ||
            (d.specialization && d.specialization.toLowerCase().includes(searchVal)) ||
            (d.room_label && d.room_label.toLowerCase().includes(searchVal)) ||
            (d.department_name && d.department_name.toLowerCase().includes(searchVal))
        );
    }

    document.getElementById('doctorCountDisplay').textContent = `Total: ${filtered.length} doctors`;

    if (filtered.length === 0) {
        tbody.innerHTML = '<tr><td colspan="8" style="text-align: center; padding: 15px; color: #888;">No doctors found</td></tr>';
        return;
    }

    tbody.innerHTML = filtered.map(d => {
        const statusBadge = d.is_active ? '<span class="badge-active">Active</span>' : '<span class="badge-inactive">Inactive</span>';
        const toggleBtnLabel = d.is_active ? 'Deactivate' : 'Activate';
        const toggleBtnClass = d.is_active ? 'btn-filter-reset' : 'btn-filter';

        return `
            <tr>
                <td><strong>${escapeHtml(d.full_name)}</strong></td>
                <td>${escapeHtml(d.specialization || 'General')}</td>
                <td>${escapeHtml(d.department_name || '---')}</td>
                <td><span style="font-weight: 600; color: #0054A6;">${escapeHtml(d.room_label || '---')}</span></td>
                <td>@${escapeHtml(d.username || '---')}</td>
                <td>${statusBadge}</td>
                <td>${escapeHtml(d.last_login_at || 'Never')}</td>
                <td>
                    <button class="btn-filter" style="padding: 4px 8px; font-size: 12px;" onclick="openEditDoctorModal(${d.id})">✏️ Edit</button>
                    <button class="${toggleBtnClass}" style="padding: 4px 8px; font-size: 12px; margin-left: 4px;" onclick="toggleDoctorActive(${d.id}, ${d.is_active})">${toggleBtnLabel}</button>
                    <button class="btn-filter-reset" style="padding: 4px 8px; font-size: 12px; margin-left: 4px; background: #e74c3c; color: white; border: none; border-radius: 4px; cursor: pointer;" onclick="promptDeleteDoctor(${d.id}, '${escapeHtml(d.full_name)}')">🗑️ Delete</button>
                </td>
            </tr>
        `;
    }).join('');
}

function openAddDoctorModal() {
    document.getElementById('addDoctorForm').reset();
    document.getElementById('addDoctorMessage').style.display = 'none';
    document.getElementById('addDoctorModal').classList.add('active');
}
function closeAddDoctorModal() {
    document.getElementById('addDoctorModal').classList.remove('active');
}

async function submitAddDoctor(e) {
    e.preventDefault();
    const msgEl = document.getElementById('addDoctorMessage');
    msgEl.style.display = 'none';

    const payload = {
        action: 'create',
        full_name: document.getElementById('addDocFullName').value.trim(),
        specialization: document.getElementById('addDocSpecialization').value.trim(),
        department_id: document.getElementById('addDocDepartment').value,
        room_label: document.getElementById('addDocRoom').value.trim(),
        username: document.getElementById('addDocUsername').value.trim(),
        password: document.getElementById('addDocPassword').value
    };

    try {
        const res = await fetch('/STI_Queueing-System/api/manage-doctors.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const data = await res.json();
        if (data.success) {
            closeAddDoctorModal();
            loadDoctorList();
            alert('Doctor account created successfully!');
        } else {
            msgEl.textContent = data.message || 'Failed to add doctor';
            msgEl.className = 'form-message error';
            msgEl.style.display = 'block';
        }
    } catch (err) {
        msgEl.textContent = 'Network error adding doctor';
        msgEl.className = 'form-message error';
        msgEl.style.display = 'block';
    }
}

function openEditDoctorModal(doctorId) {
    const d = allDoctors.find(item => item.id == doctorId);
    if (!d) return;

    document.getElementById('editDocId').value = d.id;
    document.getElementById('editDocFullName').value = d.full_name;
    document.getElementById('editDocSpecialization').value = d.specialization || '';
    document.getElementById('editDocDepartment').value = d.department_id;
    document.getElementById('editDocRoom').value = d.room_label || '';
    document.getElementById('editDocUsername').value = d.username || '';
    document.getElementById('editDocPassword').value = '';
    document.getElementById('editDoctorMessage').style.display = 'none';
    document.getElementById('editDoctorModal').classList.add('active');
}
function closeEditDoctorModal() {
    document.getElementById('editDoctorModal').classList.remove('active');
}

async function submitEditDoctor(e) {
    e.preventDefault();
    const msgEl = document.getElementById('editDoctorMessage');
    msgEl.style.display = 'none';

    const payload = {
        action: 'edit',
        id: document.getElementById('editDocId').value,
        full_name: document.getElementById('editDocFullName').value.trim(),
        specialization: document.getElementById('editDocSpecialization').value.trim(),
        department_id: document.getElementById('editDocDepartment').value,
        room_label: document.getElementById('editDocRoom').value.trim(),
        username: document.getElementById('editDocUsername').value.trim()
    };
    const pwd = document.getElementById('editDocPassword').value;
    if (pwd) payload.password = pwd;

    try {
        const res = await fetch('/STI_Queueing-System/api/manage-doctors.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const data = await res.json();
        if (data.success) {
            closeEditDoctorModal();
            loadDoctorList();
            alert('Doctor profile updated successfully!');
        } else {
            msgEl.textContent = data.message || 'Failed to update doctor';
            msgEl.className = 'form-message error';
            msgEl.style.display = 'block';
        }
    } catch (err) {
        msgEl.textContent = 'Network error updating doctor';
        msgEl.className = 'form-message error';
        msgEl.style.display = 'block';
    }
}

async function toggleDoctorActive(doctorId, currentStatus) {
    const actionLabel = currentStatus ? 'deactivate' : 'activate';
    if (!confirm(`Are you sure you want to ${actionLabel} this doctor?`)) return;

    try {
        const res = await fetch('/STI_Queueing-System/api/manage-doctors.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'toggle_active', id: doctorId })
        });
        const data = await res.json();
        if (data.success) {
            loadDoctorList();
        } else {
            alert(data.message || 'Action failed');
        }
    } catch (e) {
        alert('Network error modifying doctor status');
    }
}

// ========================================
// 5. ATTENDANCE LOGS TAB
// ========================================
function switchAttendanceSubtab(subtab) {
    currentAttendanceSubtab = subtab;
    const btnDuty = document.getElementById('btnSubtabDuty');
    const btnSessions = document.getElementById('btnSubtabSessions');
    const statusGroup = document.getElementById('attFilterStatusGroup');
    const tableHead = document.getElementById('attendanceTableHeader');

    if (subtab === 'duty') {
        if (btnDuty) btnDuty.classList.add('active');
        if (btnSessions) btnSessions.classList.remove('active');
        if (statusGroup) statusGroup.style.display = 'flex';
        if (tableHead) {
            tableHead.innerHTML = `
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
            `;
        }
    } else {
        if (btnDuty) btnDuty.classList.remove('active');
        if (btnSessions) btnSessions.classList.add('active');
        if (statusGroup) statusGroup.style.display = 'none';
        if (tableHead) {
            tableHead.innerHTML = `
                <tr>
                    <th>Personnel</th>
                    <th>Role</th>
                    <th>Username</th>
                    <th>Department</th>
                    <th>Session Start (Login)</th>
                    <th>Session End (Logout)</th>
                    <th>Session Duration</th>
                    <th>Session Status</th>
                </tr>
            `;
        }
    }
    loadAttendanceLogs(1);
}

function applyAttendanceFilters() {
    attendanceFilters = {
        startDate: document.getElementById('attFilterStart').value,
        endDate: document.getElementById('attFilterEnd').value,
        role: document.getElementById('attFilterRole').value,
        department_id: document.getElementById('attFilterDept') ? document.getElementById('attFilterDept').value : '',
        status: document.getElementById('attFilterStatus') ? document.getElementById('attFilterStatus').value : 'all',
        search: document.getElementById('attFilterSearch') ? document.getElementById('attFilterSearch').value.trim() : ''
    };
    loadAttendanceLogs(1);
}

function resetAttendanceFilters() {
    document.getElementById('attFilterStart').value = '';
    document.getElementById('attFilterEnd').value = '';
    document.getElementById('attFilterRole').value = 'all';
    if (document.getElementById('attFilterDept')) document.getElementById('attFilterDept').value = '';
    if (document.getElementById('attFilterStatus')) document.getElementById('attFilterStatus').value = 'all';
    if (document.getElementById('attFilterSearch')) document.getElementById('attFilterSearch').value = '';
    attendanceFilters = {};
    loadAttendanceLogs(1);
}

async function loadAttendanceLogs(page = 1) {
    attendancePage = page;
    const tbody = document.getElementById('attendanceTableBody');
    tbody.innerHTML = '<tr><td colspan="9" class="loading">Loading attendance logs...</td></tr>';

    const params = new URLSearchParams({
        tab: currentAttendanceSubtab,
        page: attendancePage,
        limit: attendanceLimit
    });
    if (attendanceFilters.startDate) params.append('startDate', attendanceFilters.startDate);
    if (attendanceFilters.endDate) params.append('endDate', attendanceFilters.endDate);
    if (attendanceFilters.role && attendanceFilters.role !== 'all') params.append('role', attendanceFilters.role);
    if (attendanceFilters.department_id) params.append('department_id', attendanceFilters.department_id);
    if (currentAttendanceSubtab === 'duty' && attendanceFilters.status && attendanceFilters.status !== 'all') {
        params.append('status', attendanceFilters.status);
    }
    if (attendanceFilters.search) params.append('search', attendanceFilters.search);

    try {
        const res = await fetch(`/STI_Queueing-System/api/get-attendance.php?${params.toString()}`);
        if (res.status === 401) {
            window.location.href = '/STI_Queueing-System/staff-login';
            return;
        }
        const data = await res.json();

        if (data.success) {
            const records = data.records || [];
            const total = data.total || 0;
            attendanceTotalPages = Math.max(1, Math.ceil(total / attendanceLimit));

            document.getElementById('attPageInfo').textContent = `Page ${attendancePage} of ${attendanceTotalPages} (${total} total)`;
            document.getElementById('btnAttPrev').disabled = attendancePage <= 1;
            document.getElementById('btnAttNext').disabled = attendancePage >= attendanceTotalPages;

            if (records.length === 0) {
                tbody.innerHTML = '<tr><td colspan="9" style="text-align: center; padding: 20px; color: #888;">No attendance records found matching filters.</td></tr>';
                return;
            }

            if (currentAttendanceSubtab === 'duty') {
                tbody.innerHTML = records.map(r => {
                    const roleBadgeClass = r.role === 'doctor' ? 'role-doctor' : (r.role === 'admin' ? 'role-admin' : 'role-staff');
                    const roleLabel = r.role === 'doctor' ? 'Doctor' : (r.role === 'admin' ? 'Admin' : 'Staff');
                    let statusBadge = '<span class="badge-completed">Completed</span>';
                    if (r.is_on_duty) {
                        statusBadge = '<span class="badge-onduty">🟢 On Duty</span>';
                    } else if (r.auto_closed) {
                        statusBadge = '<span class="badge-autoclosed">⚠️ Auto-Closed</span>';
                    }

                    return `
                        <tr>
                            <td><strong>${escapeHtml(r.full_name)}</strong></td>
                            <td><span class="badge-role ${roleBadgeClass}">${roleLabel}</span></td>
                            <td>@${escapeHtml(r.username || '---')}</td>
                            <td>${escapeHtml(r.department_name || '---')}</td>
                            <td>${escapeHtml(r.work_date || '---')}</td>
                            <td>${escapeHtml(r.check_in_at || '---')}</td>
                            <td>${escapeHtml(r.check_out_at || (r.is_on_duty ? 'In Progress' : '---'))}</td>
                            <td>${escapeHtml(r.duration_formatted || (r.is_on_duty ? 'Active' : '---'))}</td>
                            <td>${statusBadge}</td>
                        </tr>
                    `;
                }).join('');
            } else {
                tbody.innerHTML = records.map(r => {
                    const roleBadgeClass = r.role === 'doctor' ? 'role-doctor' : (r.role === 'admin' ? 'role-admin' : 'role-staff');
                    const roleLabel = r.role === 'doctor' ? 'Doctor' : (r.role === 'admin' ? 'Admin' : 'Staff');
                    const sessionStatus = r.logout_at 
                        ? '<span class="badge-offline">Logged Out</span>' 
                        : '<span class="badge-online">Active Session</span>';

                    return `
                        <tr>
                            <td><strong>${escapeHtml(r.full_name)}</strong></td>
                            <td><span class="badge-role ${roleBadgeClass}">${roleLabel}</span></td>
                            <td>@${escapeHtml(r.username || '---')}</td>
                            <td>${escapeHtml(r.department_name || '---')}</td>
                            <td>${escapeHtml(r.login_at || '---')}</td>
                            <td>${escapeHtml(r.logout_at || 'Active')}</td>
                            <td>${escapeHtml(r.duration_formatted || '---')}</td>
                            <td>${sessionStatus}</td>
                        </tr>
                    `;
                }).join('');
            }
        } else {
            tbody.innerHTML = `<tr><td colspan="9" style="color:red; text-align:center;">${escapeHtml(data.message)}</td></tr>`;
        }
    } catch (e) {
        tbody.innerHTML = `<tr><td colspan="9" style="color:red; text-align:center;">Network error loading attendance logs</td></tr>`;
    }
}

function loadPrevAttendancePage() {
    if (attendancePage > 1) loadAttendanceLogs(attendancePage - 1);
}
function loadNextAttendancePage() {
    if (attendancePage < attendanceTotalPages) loadAttendanceLogs(attendancePage + 1);
}

// ========================================
// 6. DELETE PERSONNEL MODAL & ACTIONS
// ========================================
function promptDeleteStaff(id, username, fullName) {
    pendingDelete = { type: 'staff', id, name: fullName, username };
    const textEl = document.getElementById('deleteConfirmText');
    if (textEl) textEl.textContent = `Are you sure you want to delete staff account @${username} (${fullName})?`;
    const msgEl = document.getElementById('deleteModalMessage');
    if (msgEl) msgEl.style.display = 'none';
    const modal = document.getElementById('deleteConfirmModal');
    if (modal) modal.classList.add('active');
}

function promptDeleteDoctor(id, fullName) {
    pendingDelete = { type: 'doctor', id, name: fullName };
    const textEl = document.getElementById('deleteConfirmText');
    if (textEl) textEl.textContent = `Are you sure you want to delete Dr. ${fullName}?`;
    const msgEl = document.getElementById('deleteModalMessage');
    if (msgEl) msgEl.style.display = 'none';
    const modal = document.getElementById('deleteConfirmModal');
    if (modal) modal.classList.add('active');
}

function closeDeleteModal() {
    pendingDelete = null;
    const modal = document.getElementById('deleteConfirmModal');
    if (modal) modal.classList.remove('active');
}

async function executeDelete() {
    if (!pendingDelete) return;
    const btn = document.getElementById('btnConfirmDeleteAction');
    const msgEl = document.getElementById('deleteModalMessage');
    btn.disabled = true;
    btn.textContent = 'Deleting...';

    try {
        const endpoint = pendingDelete.type === 'staff'
            ? '/STI_Queueing-System/api/manage-staff.php'
            : '/STI_Queueing-System/api/manage-doctors.php';
        
        const res = await fetch(endpoint, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'delete', id: pendingDelete.id })
        });

        if (res.status === 401) {
            window.location.href = '/STI_Queueing-System/staff-login';
            return;
        }

        const data = await res.json();
        if (data.success) {
            closeDeleteModal();
            if (pendingDelete.type === 'staff') {
                await loadStaffList();
            } else {
                await loadDoctorList();
            }
            alert(`${pendingDelete.type === 'staff' ? 'Staff member' : 'Doctor'} deleted successfully.`);
        } else {
            if (msgEl) {
                msgEl.textContent = data.message || 'Deletion failed.';
                msgEl.className = 'form-message error';
                msgEl.style.display = 'block';
            } else {
                alert(data.message || 'Deletion failed.');
            }
        }
    } catch (e) {
        if (msgEl) {
            msgEl.textContent = 'Network error during deletion.';
            msgEl.className = 'form-message error';
            msgEl.style.display = 'block';
        }
    } finally {
        btn.disabled = false;
        btn.textContent = 'Confirm Delete';
    }
}
