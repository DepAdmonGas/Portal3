document.addEventListener('DOMContentLoaded', () => {

    const sidebar = document.getElementById('sidebarnav');
    if (!sidebar) return;

    const enlace = sidebar.querySelector('a.sidebar-link[href="/departamento-operativo/procedimientos"]');
    if (!enlace) return;

    enlace.classList.remove('active');

    const item = enlace.closest('li');
    if (item) {
        item.classList.remove('active');
        item.classList.remove('selected');
    }

});
