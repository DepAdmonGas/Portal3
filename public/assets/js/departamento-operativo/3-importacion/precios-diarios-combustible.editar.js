document.addEventListener('DOMContentLoaded', function () {

    var BASE = '/departamento-operativo/importacion/precios-diarios-combustible';

    function getCsrfToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    function val(id) {
        var el = document.getElementById(id);
        if (!el || el.value === '') return 0;
        var n = parseFloat(String(el.value).replace(/,/g, ''));
        return isNaN(n) ? 0 : n;
    }

    function formatearNumero(valor, decimales) {
        var n = Number(String(valor == null ? '' : valor).replace(/,/g, ''));
        return Number.isFinite(n) ? n.toLocaleString('en-US', {
            minimumFractionDigits: decimales,
            maximumFractionDigits: decimales
        }) : '';
    }

    window.formatearPrecioInput = function (input) {
        if (!input) return;
        var original = input.value;
        var cursor = input.selectionStart == null ? original.length : input.selectionStart;
        var digitosAntes = original.slice(0, cursor).replace(/,/g, '').replace(/[^0-9.]/g, '').length;
        var normalizado = original.replace(/,/g, '').replace(/[^0-9.]/g, '');
        var punto = normalizado.indexOf('.');
        if (punto !== -1) normalizado = normalizado.slice(0, punto + 1) + normalizado.slice(punto + 1).replace(/\./g, '');
        var partes = normalizado.split('.');
        var entero = partes[0] || '';
        var fraccion = partes.length > 1 ? '.' + partes[1] : '';
        var agrupado = entero.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        input.value = agrupado + fraccion;

        var siguiente = 0;
        var encontrados = 0;
        while (siguiente < input.value.length && encontrados < digitosAntes) {
            if (/[0-9.]/.test(input.value[siguiente])) encontrados++;
            siguiente++;
        }
        input.setSelectionRange(siguiente, siguiente);
    };

    function indicarGuardado(input, estado) {
        var indicador = input && input.parentElement
            ? input.parentElement.querySelector('.precios-cell-save-indicator')
            : null;
        if (!indicador) return;
        indicador.classList.remove('d-none', 'text-success', 'text-danger');
        indicador.dataset.estado = estado;
        if (estado === 'saving') {
            indicador.classList.remove('text-danger');
            indicador.innerHTML = '<span class="spinner-border spinner-border-sm" aria-label="Guardando"></span>';
        } else if (estado === 'saved') {
            indicador.classList.add('text-success');
            indicador.innerHTML = '<i class="ti ti-check" aria-label="Cambio guardado"></i>';
            window.setTimeout(function () {
                if (indicador.dataset.estado === 'saved') indicador.classList.add('d-none');
            }, 1800);
        } else {
            indicador.classList.add('text-danger');
            indicador.innerHTML = '<i class="ti ti-alert-circle" aria-label="No se pudo guardar el cambio"></i>';
        }
    }

    function set(id, value) {
        var el = document.getElementById(id);
        if (el) el.value = formatearNumero(value, 4);
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

        if (elIva) elIva.value = formatearNumero(iva, 4);
        if (elRet) elRet.value = formatearNumero(retencion, 4);
        if (elTot) elTot.value = formatearNumero(tarifa, 4);
    }

    window.EditPrecio = function (e, id, num) {
        var input = e && e.value !== undefined ? e : null;
        var valor = input ? input.value : '';
        var valorNumerico = String(valor).replace(/,/g, '').trim();
        if (!input) return;

        window.clearTimeout(input.__preciosSaveTimer);
        indicarGuardado(input, 'saving');
        input.__preciosSaveTimer = window.setTimeout(function () {
            delete input.__preciosSaveTimer;
            axios.post(BASE + '/update', {
                id: id,
                valor: valorNumerico,
                num: num
            }, {
                headers: {
                    'X-CSRF-TOKEN': getCsrfToken(),
                    'X-Portal3-Skip-Action-Loader': '1'
                }
            })
            .then(function (r) {
                if (!r.data.success || r.data.ok === false) {
                    indicarGuardado(input, 'error');
                    return;
                }

                indicarGuardado(input, 'saved');
                if (num === 1 || num === 2 || num === 3 || num === 4 || num === 8 ||
                    num === 9 || num === 10 || num === 12 || num === 13) {
                    recalcularDiferencias(id);

                    if (num === 2) {
                        var tuxpan = document.getElementById('TuxpanVD' + id);
                        if (tuxpan) tuxpan.value = formatearNumero(valorNumerico, 4);
                    }
                    if (num === 9) {
                        var monterra = document.getElementById('MonterraVP' + id);
                        if (monterra) monterra.value = formatearNumero(valorNumerico, 4);
                    }
                } else if (num === 14) {
                    recalcularTransporte(id, parseFloat(valorNumerico) || 0);
                }
            })
            .catch(function () {
                indicarGuardado(input, 'error');
            });
        }, 350);
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
