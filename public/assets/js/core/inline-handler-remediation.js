(function () {
    document.addEventListener('click', function (event) {
        const action = event.target.closest('[data-action]');
        if (!action) return;

        if (action.dataset.action === 'logout') {
            event.preventDefault();
            if (typeof window.performLogout === 'function') window.performLogout();
        }

        if (action.dataset.action === 'history-back') {
            event.preventDefault();
            window.history.back();
        }
    });
})();
