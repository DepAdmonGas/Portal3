// Canonical CSRF setup asset for authenticated department-operative pages.
// Kept alongside csrf-http.js for older templates still using that URL.
(function() {
    if (window.__portal3CsrfHttpConfigured) return;
    window.__portal3CsrfHttpConfigured = true;

    function getCsrfToken() {
        const meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : null;
    }

    const csrfToken = getCsrfToken();
    if (csrfToken && window.axios) {
        window.axios.interceptors.request.use(function(config) {
            const method = String(config.method || 'get').toUpperCase();
            const mutating = ['POST', 'PUT', 'PATCH', 'DELETE'].includes(method);
            const url = config.url ? new URL(config.url, config.baseURL || window.location.href) : new URL(window.location.href);
            if (mutating && url.origin === window.location.origin) {
                config.headers = config.headers || {};
                config.headers['X-CSRF-TOKEN'] = getCsrfToken();
            }
            return config;
        }, function(error) {
            return Promise.reject(error);
        });

        window.axios.interceptors.response.use(function(response) {
            return response;
        }, function(error) {
            if (error.response && error.response.status === 419) {
                const meta = document.querySelector('meta[name="csrf-token"]');
                const newToken = error.response.data && error.response.data.new_token;
                if (meta && newToken) meta.setAttribute('content', newToken);
                // Do not replay POST/PUT/PATCH/DELETE automatically.
                window.location.reload();
            }
            return Promise.reject(error);
        });
    }

    const __origFetch = window.fetch;
    if (typeof __origFetch === 'function') {
        window.fetch = function(input, init) {
            init = init || {};
            var method = ((init.method || (input && input.method) || 'GET') + '').toUpperCase();
            if (method === 'POST' || method === 'PUT' || method === 'DELETE' || method === 'PATCH') {
                var url = typeof input === 'string' ? input : (input && input.url);
                var sameOrigin = true;
                if (url) {
                    try { sameOrigin = new URL(url, window.location.href).origin === window.location.origin; }
                    catch (e) { sameOrigin = false; }
                }
                if (sameOrigin) {
                    var headers = new Headers(init.headers || (input && input.headers) || undefined);
                    var token = getCsrfToken();
                    if (token && !headers.has('X-CSRF-TOKEN')) headers.set('X-CSRF-TOKEN', token);
                    init.headers = headers;
                }
            }
            return __origFetch.call(window, input, init);
        };
    }
})();
