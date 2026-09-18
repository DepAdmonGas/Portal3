(function () {
    const meta = document.querySelector('meta[name="csrf-token"]');
    const token = meta && meta.getAttribute('content');

    if (!window.axios || !token) return;

    window.axios.defaults.headers.common['X-CSRF-TOKEN'] = token;
    window.__portal3HttpSecurityConfigured = true;
})();
