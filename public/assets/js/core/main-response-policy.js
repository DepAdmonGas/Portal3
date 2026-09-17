(function () {
    const meta = document.querySelector('meta[name="csrf-token"]');
    if (!window.axios || !meta || !meta.getAttribute('content') || window.__portal3MainResponsePolicyInitialized) return;

    window.__portal3MainResponsePolicyInitialized = true;

    window.axios.interceptors.request.use(
        function (config) {
            const currentMeta = document.querySelector('meta[name="csrf-token"]');
            config.headers['X-CSRF-TOKEN'] = currentMeta.getAttribute('content');
            return config;
        },
        function (error) {
            return Promise.reject(error);
        }
    );

    window.axios.interceptors.response.use(
        function (response) {
            return response;
        },
        function (error) {
            if (error.response && error.response.status === 419) {
                window.location.reload();
            }
            return Promise.reject(error);
        }
    );
})();
