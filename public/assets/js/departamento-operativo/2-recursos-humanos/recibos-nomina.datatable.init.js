document.addEventListener('DOMContentLoaded', () => {
    const c = document.getElementById('container');
    if (!c) return;

    const idYear = parseInt(c.dataset.idYear);
    const $table = $('#tabla-recibos-nomina');
    if (!$table.length) return;

    const moduleStationKey = c.dataset.moduleStationKey || '';
    const esDirector = c.dataset.esDirector === 'true';
    const esMexdesa = c.dataset.esMexdesa === 'true';
    const puedeDescargar = c.dataset.puedeDescargar === 'true';
    const puedeEditar = c.dataset.puedeEditar === 'true';
    const puedeEliminar = c.dataset.puedeEliminar === 'true';
    let puedeEditarFila = false;

    // column().visible() provoca un redibujado completo de la tabla. Si se
    // llama en cada respuesta AJAX, la tabla se repinta tres veces extra en cada
    // recarga y el usuario ve un parpadeo. Aqui se guarda la ultima visibilidad
    // aplicada y solo se redibuja cuando el valor cambia de verdad.
    const visibilidadColumnas = { 5: esDirector, 8: true, 9: esMexdesa };

    function aplicarVisibilidadColumnas(esDirector, esUltimo, esMexdesa) {
        if (!window.tablaRecibosNomina) return;

        const cambios = [
            [5, esDirector],
            [8, esUltimo],
            [9, esMexdesa]
        ];

        let cambio = false;
        cambios.forEach(([indice, valor]) => {
            if (visibilidadColumnas[indice] !== valor) {
                visibilidadColumnas[indice] = valor;
                window.tablaRecibosNomina.column(indice).visible(valor);
                cambio = true;
            }
        });

        return cambio;
    }

    // Escapa el texto que se inyecta dentro de un atributo HTML.
    function escaparAttr(v) {
        return String(v)
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }

    function descargarArchivo(file, tipo, icono, color) {
        if (!file) return '<i class="ti ti-file-off text-muted fs-6"></i>';
        if (!puedeDescargar) {
            return `<i class="${icono} ${color} fs-6 opacity-50 pointer" title="Sin permiso de descarga"></i>`;
        }
        return `<a href="/download?tipo=${tipo}&file=${encodeURIComponent(file)}" target="_blank"><i class="${icono} ${color} fs-6"></i></a>`;
    }

    // Detección automática del prefijo de ruta para evitar 404
    const prefix = window.location.pathname.includes('/departamento-operativo')
        ? '/departamento-operativo/recursos-humanos/recibos-nomina'
        : '/recursos-humanos/recibos-nomina';

    if ($.fn.DataTable && $.fn.DataTable.isDataTable($table)) {$table.DataTable().destroy();
    }

    // Estaciones y departamentos son filas de op_rh_localidades, asi que el
    // id del selector se envia igual en ambos casos.
    function getEstacionId() {
        if (typeof ModuleStationSelector !== 'undefined' && ModuleStationSelector._instances) {
            const inst = ModuleStationSelector._instances[moduleStationKey];
            if (inst) {
                const v = inst.getValue();
                const id = parseInt(v.id_depto || v.id_estacion || 0, 10);
                if (id > 0) return id;
            }
        }
        const sel = document.getElementById('module-station-selector-' + moduleStationKey);
        if (sel && sel.value) {
            const id = parseInt(String(sel.value).replace('depto_', '').replace('estacion_', ''), 10) || 0;
            if (id > 0) return id;
        }
        return parseInt(c.dataset.idEstacion || '0');
    }

    window.tablaRecibosNomina = $table.DataTable({
        processing: true,
        serverSide: false,
        ajax: {
            type: 'POST',
            url: prefix + '/data/' + idYear,
            data: function (d) {
                const rn = window.rnComponentInstance;
                d.id_estacion = getEstacionId();
                d.periodo = rn && rn.periodoActual ? rn.periodoActual : 0;
            },
            dataSrc: function (json) {
                if (!json || !json.success) return [];
                const rn = window.rnComponentInstance;
                if (rn) {
                    rn.resumenInfo = json.resumen;
                    rn.actualizarTotalFooter(json.resumen ? json.resumen.total_general : 0);

                    const esUltimo = json.resumen ? json.resumen.es_ultimo_periodo : false;
                    puedeEditarFila = json.resumen ? !!json.resumen.finalizado_mexdesa : false;

                    aplicarVisibilidadColumnas(esDirector, esUltimo, esMexdesa);
                }
                return json.data || [];
            },
            error: function (xhr, textStatus, errorThrown) {
                    // Al cambiar de estacion, de periodo o de anio se dispara un
                    // redibujado de la tabla y DataTables aborta la peticion que
                    // este en vuelo. DataTables reporta esa cancelacion con
                    // textStatus "abort", que no es un fallo del modulo y antes
                    // terminaba mostrando un aviso que el usuario leia como
                    // error del sistema.
                    if (textStatus === 'abort') return false;

                    // Respuesta vacia o no-JSON (por ejemplo una sesion caida o
                    // un 404 de la ruta): se deja la tabla vacia sin interrumpir
                    // al usuario.
                    const texto = xhr.responseText || '';
                    if (!texto.trim()) {
                        console.warn('DataTable recibos nomina: respuesta vacia', xhr.status);
                        return false;
                    }

                    let resp = null;
                    try {
                        resp = JSON.parse(texto);
                    } catch (e) {
                        console.warn('DataTable recibos nomina: respuesta no interpretable', xhr.status);
                        return false;
                    }

                    // Si el servidor respondio con un mensaje propio se respeta,
                    // si no se registra en consola sin molestar al usuario.
                    if (resp && resp.success === false) {
                        console.warn('DataTable recibos nomina:', resp.message || xhr.status);
                    } else {
                        console.warn('DataTable recibos nomina:', textStatus, errorThrown);
                    }
                    return false;
                }
        },
        autoWidth: false,
        createdRow: function (row, data) {
            if (data.bg_color) {
                $(row).css('background-color', data.bg_color);
            }
        },
        order: [[0, 'asc']],
        pageLength: 10,
        lengthMenu: [10, 25, 50, 100],
        language: { url: '/assets/libs/datatables.net/js/es-ES.json' },
        columns: [
            {
                data: null,
                className: 'align-middle text-center',
                width: '48px',
                render: (data, type, row, meta) => meta.row + 1
            },
            { data: 'no_colaborador', className: 'align-middle text-center' },
            { data: 'nombre_completo', className: 'align-middle text-start' },
            { data: 'puesto', className: 'align-middle text-center' },
            {
                data: 'importe_total',
                className: 'align-middle text-end',
                render: (v) => '$' + parseFloat(v || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
            },
            {
                data: 'prima_vacacional',
                className: 'align-middle text-center',
                visible: esDirector,
                render: (v) => {
                    if (v === 2) return '<span class="badge rounded-pill bg-success">Pago realizado</span>';
                    if (v === 1) return '<span class="badge rounded-pill bg-info">Pago no realizado</span>';
                    return '<span class="badge rounded-pill bg-danger">Pendiente</span>';
                }
            },
            {
                data: null,
                className: 'align-middle text-center',
                orderable: false,
                render: (row) => descargarArchivo(row.doc_nomina, 'recibos-nomina', 'ti ti-file-text fs-7', 'text-primary')
            },
            {
                data: null,
                className: 'align-middle text-center',
                orderable: false,
                render: (row) => descargarArchivo(row.doc_nomina_firma, 'recibos-nomina-firma', 'ti ti-signature fs-8', 'text-success')
            },
            {
                data: null,
                className: 'align-middle text-center',
                orderable: false,
                render: (row) => descargarArchivo(row.doc_nomina_aguinaldo, 'recibos-aguinaldo', 'ti ti-gift fs-7', 'text-warning')
            },
            {
                data: 'nomina_original',
                className: 'align-middle text-center',
                visible: esMexdesa,
                render: (v) => v === 1
                    ? '<i class="ti ti-check text-success fs-7"></i>'
                    : '<i class="ti ti-x text-danger fs-7"></i>'
            },
            {
                data: null,
                className: 'align-middle text-center position-relative',
                orderable: false,
                render: (row) => {
                    const count = row.comentarios_count || 0;
                    const badge = count > 0
                        ? `<span class="badge-historico position-absolute top-0 start-100 translate-middle">${count}</span>`
                        : '';
                    return `<a href="javascript:void(0)" class="btn-badge-historico d-inline-flex align-items-center justify-content-center btn-comentario pointer" data-id="${row.id}"><i class="ti ti-message fs-7"></i>${badge}</a>`;
                }
            },
            {
                data: 'estatus',
                className: 'align-middle text-center',
                render: (v) => {
                    if (v === 'Finalizado') return '<span class="badge rounded-pill bg-success">Finalizado</span>';
                    if (v === 'En proceso') return '<span class="badge rounded-pill bg-warning">En proceso</span>';
                    return '<span class="badge rounded-pill bg-danger">Pendiente</span>';
                }
            },
            {
                data: null,
                className: 'align-middle text-center',
                orderable: false,
                searchable: false,
                title: '<i class="ti ti-dots-vertical fs-6"></i>',
                render: (row) => {
                    const activaEditar = puedeEditarFila && puedeEditar;
                    const activaEliminar = puedeEliminar;
                    const cls = (v) => (v ? '' : ' disabled');

                    // data-row viaja en comillas simples, hay que escapar las
                    // comillas simples y dobles del nombre antes de serializar.
                    const rowJson = JSON.stringify(row)
                        .replace(/'/g, '&#39;')
                        .replace(/"/g, '&quot;');

                    let html = '<div x-data="actions()" class="dropdown dropstart"><a class="pointer" data-bs-toggle="dropdown"><i class="ti ti-dots-vertical fs-6"></i></a><div class="dropdown-menu">';

                    html += '<a class="dropdown-item pointer btn-editar' + cls(activaEditar) + '" data-editar="' + (activaEditar ? 1 : 0) + '" data-row=\'' + rowJson + '\'><i class="ti ti-pencil me-1"></i> Editar</a>';

                    // DELETE GLOBAL: se invoca desde el propio ambito
                    // x-data="actions()", que es el patron del proyecto para
                    // reutilizar actions.alpine.js. "table" hace que el propio
                    // deleteAction recargue el DataTable al terminar.
                    //
                    // El nombre viaja en data-name y se lee con $el.dataset
                    // para no meter texto del usuario dentro de una cadena JS.
                    html += '<a class="dropdown-item pointer btn-eliminar' + cls(activaEliminar) + '" data-id="' + row.id + '" data-name="' + escaparAttr(row.nombre_completo || '') + '" data-eliminar="' + (activaEliminar ? 1 : 0) + '"';
                    if (activaEliminar) {
                        html += ' @click="deleteAction({ url: \'' + prefix + '/eliminar\', id: ' + row.id + ', name: $el.dataset.name, table: \'#tabla-recibos-nomina\' })"';
                    }
                    html += '><i class="ti ti-trash me-1"></i> Eliminar</a>';

                    html += '</div></div>';
                    return html;
                }
            }
        ],
        drawCallback: function () {
            if (window.Alpine) {
                Alpine.initTree(document.querySelector('#tabla-recibos-nomina'));
            }
        }
    });

    $table.on('click', '.btn-comentario', function (e) {
        e.preventDefault();
        const id = parseInt(this.dataset.id, 10);
        if (window.rnComponentInstance) window.rnComponentInstance.abrirModalComentarios(id);
    });

    // El borrado ya no se enruta por jQuery: el markup de la fila llama a
    // deleteAction() del DELETE GLOBAL mediante x-data="actions()". Aqui solo
    // queda el handler de editar, que necesita el objeto de la fila.
    $table.on('click', '.btn-editar', function (e) {
        e.preventDefault();
        if (this.dataset.editar !== '1') return;
        const row = JSON.parse(this.dataset.row);
        if (window.rnComponentInstance) window.rnComponentInstance.abrirModalEditar(row);
    });

    // El recarga del selector la engancha el componente Alpine en
    // registrarCustomReload(), que reemplaza este _customReload para que
    // ModuleStationSelector._notify() llegue al componente. Aqui solo se
    // instancia el selector.
    if (moduleStationKey && typeof ModuleStationSelector !== 'undefined') {
        ModuleStationSelector.init(moduleStationKey, {});
    }
});