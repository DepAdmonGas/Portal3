document.addEventListener('alpine:init', () => {

    Alpine.data('programarHorarioDetalleComponent', () => ({

        cargando: true,
        secciones: [],
        reporte: null,

        DIAS: [
            { key: 'lunes', num: 1, label: 'Lunes' },
            { key: 'martes', num: 2, label: 'Martes' },
            { key: 'miercoles', num: 3, label: 'Miércoles' },
            { key: 'jueves', num: 4, label: 'Jueves' },
            { key: 'viernes', num: 5, label: 'Viernes' },
            { key: 'sabado', num: 6, label: 'Sábado' },
            { key: 'domingo', num: 7, label: 'Domingo' }
        ],

        async init() {
            var c = document.getElementById('container');
            if (!c) return;
            var idReporte = c.dataset.idReporte;
            if (!idReporte) return;
            await this.cargarDetalle(idReporte);
        },

        async cargarDetalle(idReporte) {
            this.cargando = true;
            try {
                var res = await fetch('/departamento-operativo/recursos-humanos/programar-horario/get-detalle?id=' + idReporte);
                var json = await res.json();
                if (json.success) {
                    this.reporte = json.reporte;
                    this.agruparPorEstacion(json.data);
                    var self = this;
                    this.$nextTick(function() {
                        requestAnimationFrame(function() {
                            self.initDataTables();
                        });
                    });
                }
            } catch (e) {
                console.error('[ProgramarHorarioDetalle] Error cargando detalle:', e);
            }
            this.cargando = false;
        },

        agruparPorEstacion(personal) {
            var map = {};
            var order = [];
            for (var i = 0; i < personal.length; i++) {
                var p = personal[i];
                var key = p.id_estacion + '_' + p.nombre_estacion;
                if (!map[key]) {
                    map[key] = { nombre: p.nombre_estacion, personal: [] };
                    order.push(key);
                }
                map[key].personal.push(p);
            }
            this.secciones = order.map(function(k) { return map[k]; });
        },

        initDataTables() {
            var self = this;
            var DIAS = this.DIAS;

            this.secciones.forEach(function(seccion, idx) {
                var tableEl = document.getElementById('ph-detalle-table-' + idx);
                if (!tableEl) return;
                if ($.fn.DataTable.isDataTable(tableEl)) {
                    $(tableEl).DataTable().destroy();
                    tableEl.innerHTML = '<thead>' + tableEl.querySelector('thead').outerHTML + '</thead><tbody></tbody>';
                }

                var columns = [
                    { title: '#', data: 'id', className: 'align-middle text-center fw-normal', width: '48px'},
                    { title: 'Nombre completo', data: 'nombre_completo', className: 'align-middle text-start' },
                    { title: 'Puesto', data: 'puesto', className: 'align-middle text-center' }
                ];

                DIAS.forEach(function(dia) {
                    columns.push({
                        title: dia.label,
                        data: dia.key,
                        className: 'align-middle text-center',
                        orderable: false,
                        searchable: false,
                        render: function(data, type, row) {
                            return self._buildDetalleCell(row, dia);
                        }
                    });
                });

                $(tableEl).DataTable({
                    data: seccion.personal,
                    columns: columns,
                    destroy: true,
                    processing: true,
                    autoWidth: false,
                    ordering: true,
                    searching: true,
                    paging: true,
                    info: true,
                    pageLength: 10,
                    lengthMenu: [10, 25, 50, 100],
                    language: { url: '/assets/libs/datatables.net/js/es-ES.json' },
                    order: [[0, 'asc']],
                    drawCallback: function() {
                        var dropdowns = tableEl.querySelectorAll('.dropdown-toggle');
                        dropdowns.forEach(function(el) {
                            new bootstrap.Dropdown(el);
                        });
                    }
                });
            });
        },

        _buildDetalleCell(persona, dia) {
            var val = persona[dia.key];
            if (!val) return '<span class="text-muted">S/I</span>';

            return '<span>' + (val.formateado || 'S/I') + '</span>';
        },

    }));
});
