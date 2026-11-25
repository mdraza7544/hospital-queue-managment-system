// Hospital Queue Monitor Display System

const previousServingMap = {};
let audioInitialized = false;

// Initialize user interaction to permit audio play
document.addEventListener('click', () => {
    audioInitialized = true;
}, { once: true });

// Update live time and date
function updateDateTime() {
    const now = new Date();
    
    const timeStr = now.toLocaleTimeString('en-US', {
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hour12: true
    });
    
    const dateStr = now.toLocaleDateString('en-US', {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    });
    
    const timeEl = document.getElementById('time-display');
    const dateEl = document.getElementById('date-display');
    if (timeEl) timeEl.textContent = timeStr;
    if (dateEl) dateEl.textContent = dateStr;
}

// Play notification sound chime when a ticket is called
function playChime() {
    try {
        const audio = new Audio('/STI_Queueing-System/sounds/notification.wav');
        audio.play().catch(() => {
            // Autoplay might be blocked until user interacts with document
        });
    } catch (e) {
        // Silently catch audio errors
    }
}

// Fetch and update queue data for all department columns
async function updateQueue() {
    try {
        const response = await fetch('/STI_Queueing-System/api/get-queue.php');
        const result = await response.json();

        if (result.success) {
            const tickets = result.tickets || [];
            const columns = document.querySelectorAll('.monitor-column');
            let shouldPlayChime = false;

            columns.forEach(col => {
                const deptId = parseInt(col.dataset.deptId);
                const deptKey = `dept-${deptId}`;

                // Filter tickets strictly for this department
                const deptTickets = tickets.filter(t => parseInt(t.department_id) === deptId);

                const servingTicket = deptTickets.find(t => t.status === 'serving');
                const waitingTickets = deptTickets.filter(t => t.status === 'waiting');

                const servingElement = document.getElementById(`${deptKey}-serving`);
                const nextListElement = document.getElementById(`${deptKey}-next`);

                const newServingNumber = servingTicket ? servingTicket.ticket_number : '---';

                // Check if serving number has newly changed
                if (servingTicket && previousServingMap[deptId] !== newServingNumber) {
                    if (previousServingMap[deptId] !== undefined) {
                        shouldPlayChime = true;
                    }
                }
                previousServingMap[deptId] = newServingNumber;

                // Update "NOW SERVING"
                if (servingElement) {
                    servingElement.textContent = newServingNumber;
                }

                // Update "Proceed to" Window / Room Label
                const windowElement = document.getElementById(`${deptKey}-window`);
                if (windowElement) {
                    if (servingTicket) {
                        const destination = servingTicket.room_label ? servingTicket.room_label : `Window ${servingTicket.window_number || ''}`;
                        windowElement.textContent = `Proceed to ${destination}`;
                    } else {
                        windowElement.textContent = windowElement.dataset.defaultWindow || '---';
                    }
                }

                // Update "NEXT IN LINE" (top 3)
                if (nextListElement) {
                    nextListElement.innerHTML = '';
                    const topWaiting = waitingTickets.slice(0, 3);

                    if (topWaiting.length > 0) {
                        topWaiting.forEach(ticket => {
                            const item = document.createElement('div');
                            item.className = 'next-item';
                            if (ticket.is_priority) {
                                item.classList.add('priority');
                            }
                            item.textContent = ticket.ticket_number;
                            nextListElement.appendChild(item);
                        });
                    } else {
                        const emptyItem = document.createElement('div');
                        emptyItem.className = 'next-item';
                        emptyItem.textContent = '---';
                        nextListElement.appendChild(emptyItem);
                    }
                }
            });

            if (shouldPlayChime) {
                playChime();
            }

        } else {
            console.error('Queue API error:', result.message || 'Unknown error');
        }
    } catch (error) {
        console.error('Error fetching queue status:', error);
    }
}

// Initial calls
updateDateTime();
updateQueue();

// Interval timers
setInterval(updateDateTime, 1000);
setInterval(updateQueue, 3000);
