(function () {
    'use strict';

    function getCsrfToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : null;
    }

    if (window.jQuery) {
        window.jQuery.ajaxSetup({
            beforeSend: function (jqXHR) {
                var token = getCsrfToken();
                if (token) jqXHR.setRequestHeader('X-CSRF-TOKEN', token);
            }
        });
    }
})();
