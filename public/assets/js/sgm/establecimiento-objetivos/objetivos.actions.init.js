let tablaObjetivosClienteSgm = null;

document.addEventListener('alpine:init', () => {
    Alpine.data('objetivoForm', () => ({
        objetivos: [],
        objetivo: null,

        async init() {
            if (!document.getElementById('sgm-content')) return;
            await this.cargar();
        },

        async cargar() {
            const { data } = await axios.get(
                '/sgm/establecimiento-objetivos-enfocados-cliente/datatable-objetivos'
            );

            const objetivoSeleccionado = this.objetivo?.id;
            this.objetivos = Array.isArray(data) ? data : [];
            this.objetivo = this.objetivos.find(item => Number(item.id) === Number(objetivoSeleccionado))
                ?? this.objetivos[0]
                ?? null;

            this.actualizarTabla();
        },

        actualizarTabla() {
            const selector = '#table-objetivos-cliente';
            if (!window.jQuery?.fn?.DataTable || !document.querySelector(selector)) return;

            const componente = this;
            if (!tablaObjetivosClienteSgm) {
                tablaObjetivosClienteSgm = $(selector).DataTable({
                    processing: true,
                    serverSide: false,
                    autoWidth: false,
                    stateSave: true,
                    order: [],
                    language: {
                        url: '/assets/libs/datatables.net/js/es-ES.json'
                    },
                    data: this.objetivos,
                    columns: [
                        {
                            data: null,
                            width: '96px',
                            className: 'text-center align-middle',
                            orderable: false,
                            searchable: false,
                            render: (data, type, row, meta) => meta.row + 1
                        },
                        {
                            data: 'fecha',
                            className: 'text-center align-middle'
                        },
                        {
                            data: null,
                            width: '48px',
                            className: 'text-center align-middle',
                            orderable: false,
                            searchable: false,
                            render: (data, type, row) => `
                                <a href="#" class="text-danger js-eliminar-objetivo-cliente" aria-label="Eliminar objetivo">
                                    <i class="ti ti-trash fs-6"></i>
                                </a>
                            `
                        }
                    ],
                    drawCallback: function () {
                        const api = this.api();
                        $(api.rows().nodes()).each(function () {
                            const fila = this;
                            const item = api.row(fila).data();
                            fila.classList.toggle('table-primary', Number(item?.id) === Number(componente.objetivo?.id));
                            fila.style.cursor = 'pointer';
                        });
                    }
                });

                tablaObjetivosClienteSgm.on('click', 'tbody tr', function (event) {
                    if (event.target.closest('.js-eliminar-objetivo-cliente')) return;
                    const item = tablaObjetivosClienteSgm.row(this).data();
                    if (item) componente.seleccionar(item);
                });

                tablaObjetivosClienteSgm.on('click', 'tbody .js-eliminar-objetivo-cliente', async function (event) {
                    event.preventDefault();
                    event.stopPropagation();
                    const item = tablaObjetivosClienteSgm.row($(this).closest('tr')).data();
                    if (item) await componente.eliminar(item);
                });

                return;
            }

            tablaObjetivosClienteSgm.clear().rows.add(this.objetivos).draw();
        },

        seleccionar(item) {
            this.objetivo = item;
            if (!tablaObjetivosClienteSgm) return;

            tablaObjetivosClienteSgm.rows().every(function () {
                const fila = this.node();
                const dato = this.data();
                fila?.classList.toggle('table-primary', Number(dato?.id) === Number(item.id));
            });
        },

        async eliminar(item) {
            const res = await this.deleteAction({
                url: '/sgm/establecimiento-objetivos-enfocados-cliente/objetivo-cliente/delete',
                id: item.id,
                name: 'Objetivo'
            });

            if (res.success) await this.cargar();
        }
    }));
});
