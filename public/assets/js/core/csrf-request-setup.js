(function () {
    'use strict';

    function getCsrfToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : null;
    }

    var axiosInterceptorsReady = false;
    function initializeAxiosInterceptors() {
        var axiosClient = window.axios;
        var csrfToken = getCsrfToken();
        if (!axiosClient || !csrfToken || axiosInterceptorsReady) return axiosInterceptorsReady;

        axiosClient.defaults.headers.common['X-CSRF-TOKEN'] = csrfToken;
        axiosClient.interceptors.request.use(
            function (config) {
                config.headers['X-CSRF-TOKEN'] = getCsrfToken();
                return config;
            },
            function (error) {
                return Promise.reject(error);
            }
        );

        axiosClient.interceptors.response.use(
            function (response) {
                return response;
            },
            function (error) {
                if (error.response && error.response.status === 419) {
                    var meta = document.querySelector('meta[name="csrf-token"]');
                    var newToken = error.response.data && error.response.data.new_token;
                    if (meta && newToken) {
                        meta.setAttribute('content', newToken);

                        var method = String((error.config && error.config.method) || 'get').toUpperCase();
                        var safeMethod = method === 'GET' || method === 'HEAD' || method === 'OPTIONS';
                        if (safeMethod && error.config && !error.config._csrfRetried) {
                            error.config._csrfRetried = true;
                            return axiosClient(error.config);
                        }
                    }

                    window.dispatchEvent(new CustomEvent('portal3:csrf-rejected', {
                        detail: {
                            status: 419,
                            method: String((error.config && error.config.method) || 'unknown').toUpperCase()
                        }
                    }));
                }
                return Promise.reject(error);
            }
        );

        axiosInterceptorsReady = true;
        return true;
    }

    if (!initializeAxiosInterceptors()) {
        window.addEventListener('portal3:script-fallback-loaded', initializeAxiosInterceptors);
    }

    var originalFetch = window.fetch;
    if (typeof originalFetch === 'function') {
        window.fetch = function (input, init) {
            init = init || {};
            var method = ((init.method || (input && input.method) || 'GET') + '').toUpperCase();
            if (method === 'POST' || method === 'PUT' || method === 'DELETE' || method === 'PATCH') {
                var url = typeof input === 'string' ? input : (input && input.url);
                var sameOrigin = true;
                if (url && /^https?:\/\//i.test(url)) {
                    try {
                        sameOrigin = new URL(url, window.location.href).origin === window.location.origin;
                    } catch (error) {
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
            return originalFetch.call(window, input, init);
        };
    }
})();
