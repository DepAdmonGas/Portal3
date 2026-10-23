(function () {
    'use strict';

    function urlConEstacion(idEstacion) {
        var partes = window.location.pathname.split('/');
        var indiceGestoria = partes.indexOf('gestoria');

        if (indiceGestoria === -1) {
            return null;
        }

        for (var i = indiceGestoria + 1; i < partes.length; i++) {
            if (/^\d+$/.test(partes[i])) {
                partes[i] = String(idEstacion);
                return partes.join('/');
            }
        }

        return null;
    }

    document.addEventListener('DOMContentLoaded', function () {
        if (typeof ModuleStationSelector === 'undefined') {
            return;
        }

        if (!document.getElementById('module-station-selector-gestoria')) {
            return;
        }

        ModuleStationSelector.init('gestoria', {
            customReload: function (selector) {
                var valor = selector.getValue();
                var idEstacion = valor && valor.id_estacion;

                if (!idEstacion) {
                    window.location.reload();
                    return;
                }

                var url = urlConEstacion(idEstacion);

                if (url) {
                    window.location.href = url;
                } else {
                    window.location.reload();
                }
            }
        });
    });
})();
