document.addEventListener('DOMContentLoaded', function () {

    var BASE = '/departamento-operativo/importacion/precios-diarios-combustible';

    function getCsrfToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    function val(id) {
        var el = document.getElementById(id);
        if (!el || el.value === '') return 0;
        var n = parseFloat(el.value);
        return isNaN(n) ? 0 : n;
    }

    function set(id, value) {
        var el = document.getElementById(id);
        if (el) el.value = value.toFixed(4);
    }

    function tarifaOf(id) {
        var el = document.getElementById(id);
        if (!el) return 0;
        var n = parseFloat(el.getAttribute('data-tarifa'));
        return isNaN(n) ? 0 : n;
    }

    // Recalcula las columnas "Diferencia vs Pemex" de una fila de producto
    function recalcularDiferencias(id) {
        var pemex = val('PemexV' + id);

        set('MonterraD' + id, val('MonterraVD' + id) - pemex);
        set('VopakD' + id, val('VopakVD' + id) - pemex);
        set('TuxpanD' + id, val('TuxpanVD' + id) - pemex);

        set('VopakP' + id, (tarifaOf('VopakP' + id) + val('VopakVP' + id)) - pemex);
        set('TuxpanP' + id, (tarifaOf('TuxpanP' + id) + val('TuxpanVP' + id)) - pemex);
        set('MonterraP' + id, (tarifaOf('MonterraP' + id) + val('MonterraVP' + id)) - pemex);
        set('TizayuP' + id, (tarifaOf('TizayuP' + id) + val('TizayuVP' + id)) - pemex);
        set('PueblaP' + id, (tarifaOf('PueblaP' + id) + val('PueblaVP' + id)) - pemex);
    }

    function recalcularTransporte(idTransporte, precio) {
        var iva = precio * 0.16;
        var retencion = precio * 0.04;
        var tarifa = precio + iva - retencion;

        var elIva = document.getElementById('inputIVA' + idTransporte);
        var elRet = document.getElementById('inputRetencion' + idTransporte);
        var elTot = document.querySelector('input[name="inputTotalPU' + idTransporte + '"]');

        if (elIva) elIva.value = iva.toFixed(4);
        if (elRet) elRet.value = retencion.toFixed(4);
        if (elTot) elTot.value = tarifa.toFixed(4);
    }

    window.EditPrecio = function (e, id, num) {
        var valor = e && e.value !== undefined ? e.value : '';

        axios.post(BASE + '/update', {
            id: id,
            valor: valor,
            num: num
        }, {
            headers: { 'X-CSRF-TOKEN': getCsrfToken() }
        })
        .then(function (r) {
            if (!r.data.success) {
                Notify.error(r.data.message || 'Error al editar');
                return;
            }

            if (num === 1 || num === 2 || num === 3 || num === 4 || num === 8 ||
                num === 9 || num === 10 || num === 12 || num === 13) {
                recalcularDiferencias(id);

                if (num === 2) {
                    var tuxpan = document.getElementById('TuxpanVD' + id);
                    if (tuxpan) tuxpan.value = valor;
                }
                if (num === 9) {
                    var monterra = document.getElementById('MonterraVP' + id);
                    if (monterra) monterra.value = valor;
                }
            } else if (num === 14) {
                recalcularTransporte(id, parseFloat(valor) || 0);
                window.location.reload();
            } else if (num === 15) {
                Notify.success('Fecha actualizada exitosamente');
            }
        })
        .catch(function (err) {
            var msg = (err.response && err.response.data && err.response.data.message)
                ? err.response.data.message
                : 'Error de conexión al editar';
            Notify.error(msg);
        });
    };

    var btnFinalizar = document.getElementById('btnFinalizar');
    if (btnFinalizar) {
        btnFinalizar.addEventListener('click', function () {
            var idFormato = getContainerIdFormato();
            if (!idFormato) return;

            btnFinalizar.disabled = true;
            window.loader.show();

            axios.post(BASE + '/finalizar', { id: idFormato }, {
                headers: { 'X-CSRF-TOKEN': getCsrfToken() }
            })
            .then(function (r) {
                window.loader.hide();

                if (r.data.ok) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Finalizado',
                        text: r.data.message || 'El formato fue finalizado exitosamente.',
                        timer: 2500,
                        showConfirmButton: false
                    }).then(function () {
                        window.location.href = BASE;
                    });
                } else {
                    btnFinalizar.disabled = false;
                    Swal.fire({
                        icon: 'warning',
                        title: 'Aviso',
                        text: r.data.message || 'Error al finalizar.'
                    });
                }
            })
            .catch(function (err) {
                window.loader.hide();
                btnFinalizar.disabled = false;
                var msg = (err.response && err.response.data && err.response.data.message)
                    ? err.response.data.message
                    : 'Error de conexión al finalizar';
                Notify.error(msg);
            });
        });
    }

    function getContainerIdFormato() {
        var header = document.querySelector('[data-id-formato]');
        if (header) return parseInt(header.getAttribute('data-id-formato'), 10) || 0;
        var match = window.location.pathname.match(/formulario\/(\d+)/);
        return match ? parseInt(match[1], 10) : 0;
    }
});
