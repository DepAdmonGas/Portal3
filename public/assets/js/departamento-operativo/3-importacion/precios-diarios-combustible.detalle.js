document.addEventListener('DOMContentLoaded', function () {

    var BASE = '/departamento-operativo/importacion/precios-diarios-combustible';

    function getCsrfToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    function getDocId() {
        var match = window.location.pathname.match(/precios-diarios-combustible-detalle\/(\d+)/);
        return match ? parseInt(match[1], 10) : 0;
    }

    function recargarReporte(idFormato) {
        var cont = document.getElementById('DivReportePrecios');
        if (!cont) return;

        fetch(BASE + '/reporte/' + idFormato, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function (r) { return r.text(); })
        .then(function (html) {
            cont.innerHTML = html;
        })
        .catch(function () {
            if (window.Notify) Notify.error('Error al cargar el reporte');
        });
    }

    window.SelPrecioBajo = function (idPrecio, valCheck, num, producto) {
        var msg = (valCheck == 0)
            ? 'Precio seleccionado exitosamente.'
            : 'Precio desmarcado exitosamente';

        axios.post(BASE + '/toggle-precio-bajo', {
            idPrecio: idPrecio,
            valCheck: valCheck,
            num: num,
            producto: producto
        }, {
            headers: { 'X-CSRF-TOKEN': getCsrfToken() }
        })
        .then(function (r) {
            if (r.data.success) {
                if (window.Notify) Notify.success(msg);
                recargarReporte(idPrecio);
            } else {
                if (window.Notify) Notify.error('Error al seleccionar el precio.');
            }
        })
        .catch(function () {
            if (window.Notify) Notify.error('Error al seleccionar el precio.');
        });
    };

    window.recargarReportePrecios = function () {
        recargarReporte(getDocId());
    };
});
