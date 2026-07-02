const sidebar = document.getElementById('sidebar');
const toggleBtn = document.getElementById('sidebarToggleBtn');
const closeBtn = document.getElementById('sidebarCloseBtn');

if (toggleBtn && sidebar) {
    toggleBtn.addEventListener('click', () => {
        sidebar.classList.add('open');
    });
}

if (closeBtn && sidebar) {
    closeBtn.addEventListener('click', () => {
        sidebar.classList.remove('open');
    });
}
