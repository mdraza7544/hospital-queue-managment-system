// Duty Attendance JavaScript Controller

let isOnDuty = false;
let checkInAtTime = null;
let timerInterval = null;
let serverOffsetMs = 0;

let currentPage = 1;
let currentLimit = 20;
let totalPages = 1;
let filterStart = '';
let filterEnd = '';

document.addEventListener('DOMContentLoaded', async function() {
    window.isOnDuty = false;

    // Heartbeat & session integrity check immediately and every 30s
    await runHeartbeat();
    setInterval(runHeartbeat, 30000);

    // Initial data load
    await loadAttendanceData();

    // Periodic refresh every 10 seconds to keep totals and background updates synced
    setInterval(loadAttendanceData, 10000);
});

async function runHeartbeat() {
    try {
        const res = await fetch('/STI_Queueing-System/api/heartbeat.php');
        if (res.status === 401) {
            alert('Your session has expired or account is deactivated. Redirecting to login...');
            window.location.href = '/STI_Queueing-System/staff-login';
            return;
        }

        const data = await res.json();
        const expectedRole = document.body.dataset.userRole;

        if (data.success && data.role && data.role !== expectedRole) {
            alert(`You were logged in as ${data.role} in another tab. Redirecting...`);
            const target = data.role === 'admin' 
                ? '/STI_Queueing-System/admin-dashboard' 
                : (data.role === 'doctor' ? '/STI_Queueing-System/doctor-dashboard' : '/STI_Queueing-System/staff-dashboard');
            window.location.href = target;
        }
    } catch (e) {
        // silent on transient network error
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

async function loadAttendanceData() {
    try {
        const params = new URLSearchParams({
            page: currentPage,
            limit: currentLimit
        });
        if (filterStart) params.append('startDate', filterStart);
        if (filterEnd) params.append('endDate', filterEnd);

        const res = await fetch(`/STI_Queueing-System/api/get-my-attendance.php?${params.toString()}`);
        if (res.status === 401) {
            window.location.href = '/STI_Queueing-System/staff-login';
            return;
        }

        const data = await res.json();
        if (data.success) {
            // Synchronize server time
            if (data.server_now) {
                const srvTime = new Date(data.server_now.replace(' ', 'T')).getTime();
                serverOffsetMs = srvTime - Date.now();
            }

            isOnDuty = !!data.is_on_duty;
            window.isOnDuty = isOnDuty;
            checkInAtTime = data.check_in_at ? new Date(data.check_in_at.replace(' ', 'T')).getTime() : null;

            updateUIState();

            // Update Totals Cards
            if (data.totals) {
                document.getElementById('statTodayHours').textContent = data.totals.today_formatted || '00h 00m';
                document.getElementById('statWeekHours').textContent = data.totals.week_formatted || '00h 00m';
                document.getElementById('statMonthHours').textContent = data.totals.month_formatted || '00h 00m';
            }

            // Render History Table
            renderHistoryTable(data.records || [], data.total || 0);
        }
    } catch (e) {
        console.error('Failed to load duty attendance data:', e);
    }
}

function updateUIState() {
    const badge = document.getElementById('dutyStatusBadge');
    const subtitle = document.getElementById('dutySubtitle');
    const btnIn = document.getElementById('btnDutyCheckin');
    const btnOut = document.getElementById('btnDutyCheckout');

    if (isOnDuty) {
        badge.className = 'badge-onduty';
        badge.innerHTML = '🟢 On Duty';
        subtitle.textContent = `Shift active since ${new Date(checkInAtTime).toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' })}`;
        btnIn.disabled = true;
        btnOut.disabled = false;

        startTimer();
    } else {
        badge.className = 'badge-offduty';
        badge.innerHTML = '⚪ Off Duty';
        subtitle.textContent = 'Press Check In to start your duty shift';
        btnIn.disabled = false;
        btnOut.disabled = true;

        stopTimer();
        document.getElementById('dutyTimerDisplay').textContent = '00:00:00';
    }
}

function startTimer() {
    if (timerInterval) clearInterval(timerInterval);

    function update() {
        if (!checkInAtTime) return;
        const currentServerNow = Date.now() + serverOffsetMs;
        const diffMs = Math.max(0, currentServerNow - checkInAtTime);
        const sec = Math.floor(diffMs / 1000);

        const hrs = String(Math.floor(sec / 3600)).padStart(2, '0');
        const mins = String(Math.floor((sec % 3600) / 60)).padStart(2, '0');
        const secs = String(sec % 60).padStart(2, '0');

        document.getElementById('dutyTimerDisplay').textContent = `${hrs}:${mins}:${secs}`;
    }

    update();
    timerInterval = setInterval(update, 1000);
}

function stopTimer() {
    if (timerInterval) {
        clearInterval(timerInterval);
        timerInterval = null;
    }
}

async function handleCheckIn() {
    const msg = document.getElementById('dutyActionMsg');
    const btnIn = document.getElementById('btnDutyCheckin');
    msg.style.display = 'none';

    btnIn.disabled = true;
    btnIn.textContent = 'Checking in...';

    try {
        const res = await fetch('/STI_Queueing-System/api/attendance-checkin.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' }
        });

        if (res.status === 401) {
            alert('Session expired. Please log in again.');
            window.location.href = '/STI_Queueing-System/staff-login';
            return;
        }

        const data = await res.json();
        if (data.success) {
            msg.textContent = data.message;
            msg.className = 'form-message success';
            msg.style.display = 'block';

            await loadAttendanceData();
        } else {
            msg.textContent = data.message || 'Check-in failed.';
            msg.className = 'form-message error';
            msg.style.display = 'block';
        }
    } catch (e) {
        msg.textContent = 'Network error recording check-in.';
        msg.className = 'form-message error';
        msg.style.display = 'block';
    } finally {
        btnIn.textContent = '✓ Check In for Duty';
        updateUIState();
    }
}

async function handleCheckOut() {
    const msg = document.getElementById('dutyActionMsg');
    const btnOut = document.getElementById('btnDutyCheckout');
    msg.style.display = 'none';

    if (!confirm('Are you sure you want to end your duty shift and check out?')) {
        return;
    }

    btnOut.disabled = true;
    btnOut.textContent = 'Checking out...';

    try {
        const res = await fetch('/STI_Queueing-System/api/attendance-checkout.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' }
        });

        if (res.status === 401) {
            alert('Session expired. Please log in again.');
            window.location.href = '/STI_Queueing-System/staff-login';
            return;
        }

        const data = await res.json();
        if (data.success) {
            msg.textContent = data.message;
            msg.className = 'form-message success';
            msg.style.display = 'block';

            await loadAttendanceData();
        } else {
            msg.textContent = data.message || 'Check-out failed.';
            msg.className = 'form-message error';
            msg.style.display = 'block';
        }
    } catch (e) {
        msg.textContent = 'Network error recording check-out.';
        msg.className = 'form-message error';
        msg.style.display = 'block';
    } finally {
        btnOut.textContent = '⏹ Check Out (End Duty)';
        updateUIState();
    }
}

function renderHistoryTable(records, total) {
    const tbody = document.getElementById('attendanceTableBody');
    totalPages = Math.max(1, Math.ceil(total / currentLimit));

    document.getElementById('pageInfo').textContent = `Page ${currentPage} of ${totalPages} (${total} total)`;
    document.getElementById('btnPrevPage').disabled = currentPage <= 1;
    document.getElementById('btnNextPage').disabled = currentPage >= totalPages;

    if (records.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" style="text-align: center; padding: 20px; color: #888;">No attendance records found.</td></tr>';
        return;
    }

    tbody.innerHTML = records.map(r => {
        let statusBadge = '<span class="badge-completed">Completed</span>';
        if (r.is_open) {
            statusBadge = '<span class="badge-onduty">On Duty</span>';
        } else if (r.auto_closed) {
            statusBadge = '<span class="badge-autoclosed">Auto-Closed</span>';
        }

        const checkOutText = r.check_out_at ? escapeHtml(r.check_out_at) : '<em>In Progress</em>';

        return `
            <tr>
                <td><strong>${escapeHtml(r.work_date)}</strong></td>
                <td>${escapeHtml(r.check_in_at)}</td>
                <td>${checkOutText}</td>
                <td><strong>${escapeHtml(r.duration_formatted)}</strong></td>
                <td>${statusBadge}</td>
            </tr>
        `;
    }).join('');
}

function applyFilters() {
    filterStart = document.getElementById('attFilterStart').value;
    filterEnd = document.getElementById('attFilterEnd').value;
    currentPage = 1;
    loadAttendanceData();
}

function resetFilters() {
    document.getElementById('attFilterStart').value = '';
    document.getElementById('attFilterEnd').value = '';
    filterStart = '';
    filterEnd = '';
    currentPage = 1;
    loadAttendanceData();
}

function loadPrevPage() {
    if (currentPage > 1) {
        currentPage--;
        loadAttendanceData();
    }
}
function loadNextPage() {
    if (currentPage < totalPages) {
        currentPage++;
        loadAttendanceData();
    }
}
