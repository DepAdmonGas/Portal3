document.addEventListener('alpine:init', () => {
    Alpine.data('programaAuditoria', () => ({

         registros: [],
        years: [],

        loading: false,

        fechaInicio: '',
        fechaFin: '',
        periodoVisualizado: '',

        modalBuscar: null,
        tabla: null,

        init() {

            this.modalBuscar =
                new bootstrap.Modal(
                    document.getElementById('modalBuscar')
                );

            const year = new Date().getFullYear();

            this.fechaInicio = `${year}-01-01`;
            this.fechaFin = `${year + 1}-12-31`;
            
            this.buscar();
        },

        abrirModalBuscar() {
            this.modalBuscar.show();
        },

        async buscar() {

            this.loading = true;

            try {

                const { data } = await axios.get(
                    '/sasisopa/auditorias/programa/formato',
                    {
                        params: {
                            inicio: this.fechaInicio,
                            fin: this.fechaFin
                        }
                    }
                );

                if (data.success) {

                    this.registros = data.data;
                    this.periodoVisualizado = this.formatearPeriodo(
                        this.fechaInicio,
                        this.fechaFin
                    );

                    this.years = [];

                    for (
                        let y = Number(data.inicio);
                        y <= Number(data.fin);
                        y++
                    ) {
                        this.years.push(y);
                    }

                    this.inicializarTabla();
                }

            } finally {

                this.loading = false;
            }

            this.modalBuscar.hide();
        },

        inicializarTabla() {
            const selector = '#table-programa-auditorias';
            if (!window.jQuery?.fn?.DataTable) return;

            let tabla = document.querySelector(selector);
            if (!tabla?.tHead?.rows?.length) return;

            if ($.fn.DataTable.isDataTable(tabla)) {
                const encabezadosBase = Array.from(tabla.tHead.rows[0].cells)
                    .slice(0, 3)
                    .map(celda => celda.cloneNode(true));
                const wrapper = tabla.closest('.dataTables_wrapper');
                const contenedor = wrapper?.parentNode;
                const siguienteNodo = wrapper?.nextSibling ?? null;

                // destroy(true) evita que DataTables reinserte la tabla usando
                // una referencia de DOM que puede haber quedado obsoleta.
                $(tabla).DataTable().destroy(true);

                if (!contenedor) return;

                const nuevaTabla = tabla.cloneNode(false);
                const thead = document.createElement('thead');
                const fila = document.createElement('tr');
                encabezadosBase.forEach(celda => fila.appendChild(celda));
                thead.appendChild(fila);
                nuevaTabla.append(thead, document.createElement('tbody'));
                contenedor.insertBefore(
                    nuevaTabla,
                    siguienteNodo?.parentNode === contenedor ? siguienteNodo : null
                );
                tabla = nuevaTabla;
            }

            const encabezado = tabla.tHead.rows[0];
            tabla.tBodies[0]?.replaceChildren();
            while (encabezado.cells.length > 3) {
                encabezado.deleteCell(encabezado.cells.length - 1);
            }

            const years = this.years.map(Number);
            years.forEach(year => {
                const th = document.createElement('th');
                th.className = 'text-center';
                th.textContent = year;
                encabezado.appendChild(th);
            });

            const mesAuditoria = fecha => {
                const [year, month, day] = String(fecha || '').split('T')[0].split('-').map(Number);
                if (!year || !month || !day) return '';
                const mes = new Intl.DateTimeFormat('es-MX', { month: 'long' })
                    .format(new Date(year, month - 1, day));
                return mes.charAt(0).toUpperCase() + mes.slice(1);
            };

            const columnas = [
                { data: 'tipo_auditoria', defaultContent: '', className: 'text-center align-middle', render: $.fn.dataTable.render.text() },
                { data: 'responsable', defaultContent: '', className: 'text-center align-middle', render: $.fn.dataTable.render.text() },
                { data: 'periodicidad', defaultContent: '', className: 'text-center align-middle', render: $.fn.dataTable.render.text() },
                ...years.map(year => ({
                    data: null,
                    className: 'text-center align-middle',
                    orderable: false,
                    render: (data, tipo, registro) => {
                        const fecha = String(registro.fecha || '').split('T')[0];
                        return Number(fecha.slice(0, 4)) === year ? mesAuditoria(fecha) : '';
                    },
                    createdCell: (celda, data, registro) => {
                        const fecha = String(registro.fecha || '').split('T')[0];
                        if (Number(fecha.slice(0, 4)) !== year) return;
                        celda.classList.add(registro.tipo_auditoria === 'Interna' ? 'table-primary' : 'table-success');
                    }
                }))
            ];

            this.tabla = $(selector).DataTable({
                data: this.registros,
                columns: columnas,
                autoWidth: false,
                order: [],
                language: {
                    url: '/assets/libs/datatables.net/js/es-ES.json',
                    emptyTable: 'No se encontró información para mostrar'
                }
            });
        },

        mes(fecha) {

            const mes = new Date(fecha)
                .toLocaleDateString(
                    'es-MX',
                    {
                        month: 'long'
                    }
                );

            return mes.charAt(0).toUpperCase() + mes.slice(1);
        },

        formatearPeriodo(inicio, fin) {
            const formatearFecha = fecha => {
                const [year, month, day] = String(fecha || '').split('-').map(Number);
                if (!year || !month || !day) return '';

                return new Intl.DateTimeFormat('es-MX', {
                    day: 'numeric',
                    month: 'long',
                    year: 'numeric'
                }).format(new Date(year, month - 1, day));
            };

            const desde = formatearFecha(inicio);
            const hasta = formatearFecha(fin);
            return desde && hasta ? `${desde} – ${hasta}` : '';
        },

        existeEnYear(registro, year) {

            return new Date(registro.fecha)
                .getFullYear() == year;
        },

    }));
});
