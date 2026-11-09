document.addEventListener('alpine:init', () => {
    Alpine.data('cursos', (categoria, multiestacion) => ({

        categoria,
        multiestacion: !!multiestacion,

        loadingModulos: true,
        loadingPendientes: true,
        modulos: [],

        total: 0,
        cursos: [],

        async init() {

            await Promise.all([
                this.cargar(),
                this.pendientes()
            ]);

        },

         async cargar() {

            this.loadingModulos = true;

            try {

                const { data } = await axios.get(
                    '/sasisopa/cursos/modulos/get?categoria=' + this.categoria
                );

                if (data.success) {

                    this.modulos = data.data;

                }

            } finally {

                this.loadingModulos = false;

            }

        },

        detalle(modulo) {

            window.location =
                '/sasisopa/cursos/modulos/' + modulo.id;

        },

        async pendientes() {

            this.loadingPendientes = true;

            try {

                const { data } = await axios.get(
                    '/sasisopa/cursos/pendientes/get?categoria=' + this.categoria
                );

                if (data.success) {

                    this.cursos = data.data;

                    this.total = data.total;

                }

            } finally {

                this.loadingPendientes = false;
                await new Promise(resolve => this.$nextTick(resolve));
                this.inicializarTablaPendientes();

            }

        },

        inicializarTablaPendientes() {

            const tabla = $('#table-cursos-pendientes');

            if (!tabla.length || !window.jQuery?.fn?.DataTable) return;

            if ($.fn.DataTable.isDataTable(tabla)) {
                tabla.DataTable().destroy();
            }

            const escapar = valor => $('<div>').text(valor ?? '').html();
            const columnas = [
                {
                    data: 'fecha_raw',
                    width: '18%',
                    className: 'text-center align-middle',
                    render: (valor, tipo, curso) => tipo === 'display'
                        ? escapar(curso.fecha)
                        : (valor ?? '')
                }
            ];

            if (this.multiestacion) {
                columnas.push({
                    data: 'personal',
                    width: '22%',
                    className: 'text-center align-middle',
                    defaultContent: 'N/A',
                    render: valor => escapar(valor || 'N/A')
                });
            }

            columnas.push(
                {
                    data: 'titulo',
                    width: this.multiestacion ? '48%' : '51%',
                    className: 'text-start align-middle',
                    defaultContent: 'N/A',
                    render: (valor, tipo) => {
                        const texto = escapar(valor || 'N/A');
                        return tipo === 'display'
                            ? `<div style="white-space: normal; overflow-wrap: anywhere;">${texto}</div>`
                            : (valor || 'N/A');
                    }
                },
                {
                    data: 'categoria',
                    width: this.multiestacion ? '12%' : '15%',
                    className: 'text-center align-middle',
                    render: valor => `<span class="badge bg-primary">${escapar(valor)}</span>`
                }
            );

            if (!this.multiestacion) {
                columnas.push({
                    data: 'id',
                    width: '16%',
                    className: 'text-center align-middle',
                    orderable: false,
                    searchable: false,
                    render: id => `<button type="button" class="btn btn-success js-iniciar-curso" data-curso-id="${Number(id)}"><i class="ti ti-player-play me-1"></i>Iniciar</button>`
                });
            }

            tabla.DataTable({
                data: this.cursos,
                columns: columnas,
                autoWidth: false,
                stateSave: false,
                pageLength: 10,
                order: [[0, 'asc']],
                language: {
                    url: '/assets/libs/datatables.net/js/es-ES.json'
                }
            });

            $(document)
                .off('click.cursosPendientes', '#table-cursos-pendientes .js-iniciar-curso')
                .on('click.cursosPendientes', '#table-cursos-pendientes .js-iniciar-curso', event => {
                    const id = Number(event.currentTarget.dataset.cursoId);
                    if (Number.isInteger(id) && id > 0) this.iniciar({ id });
                });

        },

        iniciar(curso) {

            window.location =
                '/sasisopa/cursos/iniciar/' + curso.id;

        }

    }));
});
