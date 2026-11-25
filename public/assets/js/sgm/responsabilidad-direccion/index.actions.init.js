let tablaPoliticasSgm = null;

document.addEventListener('alpine:init', () => {
    Alpine.data('politicaForm', () => ({
        politicas: [],
        politica: null,

        async init() {
            if (!document.getElementById('sgm-content')) return;
            await this.cargar();
        },

        async cargar() {
            const { data } = await axios.get(
                '/sgm/responsabilidades-direccion/datatable-politica'
            );

            const politicaSeleccionada = this.politica?.id;
            this.politicas = Array.isArray(data) ? data : [];
            this.politica = this.politicas.find(item => Number(item.id) === Number(politicaSeleccionada))
                ?? this.politicas[0]
                ?? null;

            this.actualizarTabla();
        },

        actualizarTabla() {
            const selector = '#table-politicas-sgm';
            if (!window.jQuery?.fn?.DataTable || !document.querySelector(selector)) return;

            const componente = this;
            if (!tablaPoliticasSgm) {
                tablaPoliticasSgm = $(selector).DataTable({
                    processing: true,
                    serverSide: false,
                    autoWidth: false,
                    stateSave: true,
                    order: [],
                    language: {
                        url: '/assets/libs/datatables.net/js/es-ES.json'
                    },
                    data: this.politicas,
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
                                <a href="#" class="text-danger js-eliminar-politica" data-politica-id="${Number(row.id)}" aria-label="Eliminar política">
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
                            fila.classList.toggle('table-primary', Number(item?.id) === Number(componente.politica?.id));
                            fila.style.cursor = 'pointer';
                        });
                    }
                });

                tablaPoliticasSgm.on('click', 'tbody tr', function (event) {
                    if (event.target.closest('.js-eliminar-politica')) return;
                    const item = tablaPoliticasSgm.row(this).data();
                    if (item) componente.seleccionar(item);
                });

                tablaPoliticasSgm.on('click', 'tbody .js-eliminar-politica', async function (event) {
                    event.preventDefault();
                    event.stopPropagation();
                    const item = tablaPoliticasSgm.row($(this).closest('tr')).data();
                    if (item) await componente.eliminar(item);
                });

                return;
            }

            tablaPoliticasSgm.clear().rows.add(this.politicas).draw();
        },

        seleccionar(item) {
            this.politica = item;
            if (!tablaPoliticasSgm) return;

            tablaPoliticasSgm.rows().every(function () {
                const fila = this.node();
                const dato = this.data();
                fila?.classList.toggle('table-primary', Number(dato?.id) === Number(item.id));
            });
        },

        async eliminar(item) {
            const res = await this.deleteAction({
                url: '/sgm/responsabilidades-direccion/delete-politica',
                id: item.id,
                name: 'la política'
            });

            if (res.success) await this.cargar();
        }
    }));
});
