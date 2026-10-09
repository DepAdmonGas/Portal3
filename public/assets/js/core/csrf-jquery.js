// ============================================================
// Portal3 — CSRF para jQuery (antes inline al final del layout
// departamento-operativo). Se carga como archivo externo para
// cumplir la CSP (script-src 'self').
// Debe incluirse DESPUÉS de jQuery.
// ============================================================
(function() {
    function getCsrfToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : null;
    }
    if (window.jQuery) {
        jQuery.ajaxSetup({
            beforeSend: function(jqXHR) {
                var token = getCsrfToken();
                if (token) {
                    jqXHR.setRequestHeader('X-CSRF-TOKEN', token);
                }
            }
        });
    }
})();
