/**
 * Dayflow HRMS - Core JavaScript Utilities
 */

document.addEventListener('DOMContentLoaded', () => {
    // 1. Mobile Sidebar Toggle
    const sidebarToggleBtn = document.getElementById('sidebarToggle');
    const sidebar = document.getElementById('sidebar');

    if (sidebarToggleBtn && sidebar) {
        sidebarToggleBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            sidebar.classList.toggle('show');
        });

        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', (e) => {
            if (window.innerWidth <= 768 && sidebar.classList.contains('show') && !sidebar.contains(e.target) && e.target !== sidebarToggleBtn) {
                sidebar.classList.remove('show');
            }
        });
    }

    // 2. Digital Clock Widget (for Dashboards)
    const clockElement = document.getElementById('liveClock');
    const dateElement = document.getElementById('liveDate');

    function updateLiveClock() {
        const now = new Date();
        if (clockElement) {
            let hours = now.getHours();
            const minutes = String(now.getMinutes()).padStart(2, '0');
            const seconds = String(now.getSeconds()).padStart(2, '0');
            const ampm = hours >= 12 ? 'PM' : 'AM';
            hours = hours % 12;
            hours = hours ? hours : 12; // 0 becomes 12
            const formattedHours = String(hours).padStart(2, '0');
            clockElement.textContent = `${formattedHours}:${minutes}:${seconds} ${ampm}`;
        }
        if (dateElement) {
            const options = { weekday: 'long', year: 'numeric', month: 'short', day: 'numeric' };
            dateElement.textContent = now.toLocaleDateString(undefined, options);
        }
    }

    if (clockElement || dateElement) {
        updateLiveClock();
        setInterval(updateLiveClock, 1000);
    }

    // 3. Modal Controls (Open & Close)
    window.openModal = function(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
        }
    };

    window.closeModal = function(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.remove('active');
            document.body.style.overflow = 'auto';
        }
    };

    // Close modal on click outside modal card
    document.querySelectorAll('.modal-overlay').forEach(overlay => {
        overlay.addEventListener('click', (e) => {
            if (e.target === overlay) {
                overlay.classList.remove('active');
                document.body.style.overflow = 'auto';
            }
        });
    });

    // 4. In-Website Notification Dropdown Toggle
    const notifBellBtn = document.getElementById('notifBellBtn');
    const notifDropdown = document.getElementById('notifDropdown');

    if (notifBellBtn && notifDropdown) {
        notifBellBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            const isOpen = notifDropdown.style.display === 'block';
            notifDropdown.style.display = isOpen ? 'none' : 'block';
        });

        document.addEventListener('click', (e) => {
            if (!notifDropdown.contains(e.target) && e.target !== notifBellBtn) {
                notifDropdown.style.display = 'none';
            }
        });
    }
});
