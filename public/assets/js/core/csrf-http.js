// ============================================================
// Portal3 — CSRF para axios / fetch (antes inline en el layout
// departamento-operativo). Se carga como archivo externo para
// cumplir la CSP (script-src 'self').
// Debe incluirse DESPUÉS de axios.
// ============================================================
(function() {
    function getCsrfToken() {
        const meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : null;
    }

    const csrfToken = getCsrfToken();
    if (csrfToken && window.axios) {
        axios.defaults.headers.common['X-CSRF-TOKEN'] = csrfToken;

        axios.interceptors.request.use(
            function(config) {
                config.headers['X-CSRF-TOKEN'] = getCsrfToken();
                return config;
            },
            function(error) {
                return Promise.reject(error);
            }
        );

        axios.interceptors.response.use(
            function(response) {
                return response;
            },
            function(error) {
                if (error.response && error.response.status === 419) {
                    const meta = document.querySelector('meta[name="csrf-token"]');
                    const newToken = error.response.data && error.response.data.new_token;
                    if (meta && newToken) {
                        meta.setAttribute('content', newToken);
                        if (error.config && !error.config._csrfRetried) {
                            error.config._csrfRetried = true;
                            return axios(error.config);
                        }
                    }
                    window.location.reload();
                }
                return Promise.reject(error);
            }
        );
    }

    const __origFetch = window.fetch;
    if (typeof __origFetch === 'function') {
        window.fetch = function(input, init) {
            init = init || {};
            var method = ((init.method || (input && input.method) || 'GET') + '').toUpperCase();
            if (method === 'POST' || method === 'PUT' || method === 'DELETE' || method === 'PATCH') {
                var url = typeof input === 'string' ? input : (input && input.url);
                var sameOrigin = true;
                if (url && /^https?:\/\//i.test(url)) {
                    try {
                        sameOrigin = new URL(url, window.location.href).origin === window.location.origin;
                    } catch (e) {
                        sameOrigin = false;
                    }
                }
                if (sameOrigin) {
                    var headers = new Headers(init.headers || (input && input.headers) || undefined);
                    var token = getCsrfToken();
                    if (token && !headers.has('X-CSRF-TOKEN')) {
                        headers.set('X-CSRF-TOKEN', token);
                    }
                    init.headers = headers;
                }
            }
            return __origFetch.call(window, input, init);
        };
    }
})();
