document.addEventListener('alpine:init', () => {
    const textoSeguro = valor => $('<div>').text(String(valor ?? '')).html();
    const rutaArchivo = archivo => String(archivo ?? '')
        .split('/')
        .map(segmento => encodeURIComponent(segmento))
        .join('/');

    Alpine.data('informesDesempeno', () => ({

        loading: false,

        evaluaciones: [],
        modoEvaluacion: 'create',
        modalEvaluacion: null,

        evaluacion: {
            id: null,
            fecha: '',
            archivo: null,
            archivo_actual: ''
        },

        //-----------------------------------

        implementaciones: [],

        modalDetalleImplementacion: null,

        detalleImplementacion: [],

        //-----------------------------------

        errors:{
            fecha: false
        },

        init(){       
        window.informesDesempeno = this;
        this.inicializarTablas();
        this.cargarEvaluacion();
        this.cargarImplementacion();

        this.modalEvaluacion = new bootstrap.Modal(document.getElementById('modalEvaluacion'));
        this.modalDetalleImplementacion = new bootstrap.Modal(document.getElementById('modalDetalleImplementacion'));

        },

        inicializarTablas() {
            if (!window.jQuery?.fn?.DataTable) return;
            if (!document.querySelector('#table-evaluaciones-desempeno') || !document.querySelector('#table-implementaciones-desempeno')) return;

            const opcionesComunes = {
                processing: true,
                serverSide: false,
                autoWidth: false,
                stateSave: true,
                order: [],
                language: {
                    url: '/assets/libs/datatables.net/js/es-ES.json',
                    emptyTable: 'No se encontró información para mostrar'
                }
            };

            window.tableEvaluacionesDesempeno = $('#table-evaluaciones-desempeno').DataTable({
                ...opcionesComunes,
                data: [],
                columns: [
                    { data: 'id', className: 'text-center fw-bolder align-middle' },
                    {
                        data: 'fecha_larga',
                        className: 'text-center align-middle',
                        render: (fecha, tipo, fila) => tipo === 'sort' || tipo === 'type'
                            ? fila.fecha
                            : textoSeguro(fecha)
                    },
                    { data: 'usuario', defaultContent: 'S/I', className: 'text-center align-middle', render: $.fn.dataTable.render.text() },
                    {
                        data: null,
                        className: 'text-center align-middle',
                        orderable: false,
                        searchable: false,
                        render: (dato, tipo, fila) => {
                            const id = Number(fila.id);
                            const archivo = String(fila.archivo ?? '');
                            const nombreArchivo = archivo.split('/').pop() || 'documento';
                            const descarga = fila.tiene_archivo && archivo
                                ? `<a class="dropdown-item pointer d-flex align-items-center gap-3" href="${textoSeguro(rutaArchivo(archivo))}" download="${textoSeguro(nombreArchivo)}" data-download-loader><i class="fs-4 ti ti-download"></i>Descargar</a>`
                                : '<span class="dropdown-item d-flex align-items-center gap-3 text-muted"><i class="fs-4 ti ti-download"></i>Descargar</span>';

                            return `
                                <div class="dropdown dropstart">
                                    <a class="pointer" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Opciones del registro">
                                        <i class="ti ti-dots-vertical fs-6"></i>
                                    </a>
                                    <ul class="dropdown-menu">
                                        <li><a class="dropdown-item pointer d-flex align-items-center gap-3" href="#" data-evaluacion-accion="editar" data-id="${id}"><i class="fs-4 ti ti-edit"></i>Editar</a></li>
                                        <li>${descarga}</li>
                                        <li><a class="dropdown-item pointer d-flex align-items-center gap-3" href="#" data-evaluacion-accion="eliminar" data-id="${id}"><i class="fs-4 ti ti-trash"></i>Eliminar</a></li>
                                    </ul>
                                </div>`;
                        }
                    }
                ]
            });

            window.tableImplementacionesDesempeno = $('#table-implementaciones-desempeno').DataTable({
                ...opcionesComunes,
                data: [],
                columns: [
                    { data: 'id', className: 'text-center align-middle' },
                    {
                        data: 'fecha_larga',
                        className: 'text-center align-middle',
                        render: (fecha, tipo, fila) => tipo === 'sort' || tipo === 'type'
                            ? fila.fecha
                            : textoSeguro(fecha)
                    },
                    { data: 'usuario', defaultContent: 'S/I', className: 'text-center align-middle', render: $.fn.dataTable.render.text() },
                    {
                        data: null,
                        className: 'text-center align-middle',
                        orderable: false,
                        searchable: false,
                        render: (dato, tipo, fila) => {
                            const id = Number(fila.id);
                            return `
                                <div class="dropdown dropstart">
                                    <a class="pointer" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Opciones del registro">
                                        <i class="ti ti-dots-vertical fs-6"></i>
                                    </a>
                                    <ul class="dropdown-menu">
                                        <li><a class="dropdown-item pointer d-flex align-items-center gap-3" href="#" data-implementacion-accion="detalle" data-id="${id}"><i class="fs-4 ti ti-eye"></i>Detalle</a></li>
                                        <li><a class="dropdown-item pointer d-flex align-items-center gap-3" href="#" data-implementacion-accion="editar" data-id="${id}"><i class="fs-4 ti ti-edit"></i>Editar</a></li>
                                        <li><a class="dropdown-item d-flex align-items-center gap-3" href="/sasisopa/informes-desempeno/implementacion/pdf/${id}" data-download-loader><i class="fs-4 ti ti-download"></i>Descargar</a></li>
                                        <li><a class="dropdown-item pointer d-flex align-items-center gap-3" href="#" data-implementacion-accion="eliminar" data-id="${id}"><i class="fs-4 ti ti-trash"></i>Eliminar</a></li>
                                    </ul>
                                </div>`;
                        }
                    }
                ]
            });

            $('#table-evaluaciones-desempeno')
                .off('click.informesDesempeno', '[data-evaluacion-accion]')
                .on('click.informesDesempeno', '[data-evaluacion-accion]', event => {
                    event.preventDefault();
                    const componente = window.informesDesempeno;
                    const id = Number(event.currentTarget.dataset.id);
                    const accion = event.currentTarget.dataset.evaluacionAccion;
                    const registro = componente?.evaluaciones.find(item => Number(item.id) === id);
                    if (!componente || !registro) return;

                    if (accion === 'editar') componente.editarEvaluacion(registro);
                    if (accion === 'eliminar') componente.eliminarEvaluacion(id);
                });

            $('#table-implementaciones-desempeno')
                .off('click.informesDesempeno', '[data-implementacion-accion]')
                .on('click.informesDesempeno', '[data-implementacion-accion]', event => {
                    event.preventDefault();
                    const componente = window.informesDesempeno;
                    const id = Number(event.currentTarget.dataset.id);
                    const accion = event.currentTarget.dataset.implementacionAccion;
                    if (!componente) return;

                    if (accion === 'detalle') componente.verImplementacion(id);
                    if (accion === 'editar') componente.editarImplementacion(id);
                    if (accion === 'eliminar') componente.eliminarImplementacion(id);
                });
        },

        async cargarEvaluacion(){
        this.loading = true;

        try {

            const { data } = await axios.get(
                '/sasisopa/informes-desempeno/evaluacion/datatable'
            );

            if (data.success) {

                this.evaluaciones =
                    data.data;
                window.tableEvaluacionesDesempeno?.clear().rows.add(this.evaluaciones).draw(false);
            }

        } finally {

            this.loading = false;
        }
        },

        openModalEvaluacion(){
        this.modoEvaluacion = 'create';

        this.limpiarEvaluacion();

        this.evaluacion.fecha = new Date().toISOString().split('T')[0];

        this.modalEvaluacion.show();
        },

        limpiarEvaluacion(){

            this.evaluacion = {
            id: null,
            fecha: '',
            archivo: null,
            archivo_actual: ''
            };

        this.limpiarInputArchivo();
        },

        limpiarInputArchivo() {

            const input = document.getElementById('archivo');

            if (input) {
                input.value = '';
            }

        },

        editarEvaluacion(item){

        this.modoEvaluacion = 'edit';
        this.evaluacion.id = item.id;
        this.evaluacion.fecha = item.fecha;
        this.evaluacion.archivo_actual = item.archivo;
        this.evaluacion.archivo = null;

        this.limpiarInputArchivo()
        this.modalEvaluacion.show();
        },

        validarEvaluacion()
        {
        let valido = true;
        this.errors.fecha = false;

        if (!this.evaluacion.fecha) {
            this.errors.fecha = true;
            valido = false;
        }

        return valido;
        },

        async guardarEvaluacion(){

        if (!this.validarEvaluacion()) {
            this.notify(
                'error',
                'Completa los campos obligatorios'
            );

            return;
        }

        const payload = new FormData();

        payload.append(
            'fecha',
            this.evaluacion.fecha
        );

        if(this.evaluacion.archivo){

            payload.append(
                'archivo',
                this.evaluacion.archivo
            );
        }

        let url =
            '/sasisopa/informes-desempeno/evaluacion/create';

        if(this.modoEvaluacion === 'edit'){

            payload.append(
                'id',
                this.evaluacion.id
            );

            url =
            '/sasisopa/informes-desempeno/evaluacion/update';
        }

        const res =
            await this.createAction({

                url: url,
                data: payload
            });

        if(res.success){

            this.modalEvaluacion.hide();
            this.cargarEvaluacion();
        }

        },

        async eliminarEvaluacion(id) {

        const res = await this.deleteAction({

        url: '/sasisopa/informes-desempeno/evaluacion/delete',
        id: id,
        name: id
        });

        if (res?.success) {
        this.cargarEvaluacion();
        }

        },

        editarImplementacion(id){
            window.location.href = `/sasisopa/informes-desempeno/implementacion/editar/${id}`;
        },

        //--------------------------------------------------
        //--------------------------------------------------

        async cargarImplementacion(){

        this.loading = true;

        try {

            const { data } = await axios.get(
                '/sasisopa/informes-desempeno/implementacion/datatable'
            );

            if (data.success) {

                this.implementaciones =
                    data.data;
                window.tableImplementacionesDesempeno?.clear().rows.add(this.implementaciones).draw(false);
            }

        } finally {

            this.loading = false;
        }

        },

        async createImplementacion(){

        const res = await this.createAction({
        url: '/sasisopa/informes-desempeno/implementacion/create',
        data: {}
        });

        if(res.success){

        window.location.href = `/sasisopa/informes-desempeno/implementacion/editar/${res.id}`;
        }

        },

         async eliminarImplementacion(id) {

        const res = await this.deleteAction({

        url: '/sasisopa/informes-desempeno/implementacion/delete',
        id: id,
        name: id
        });

        if (res?.success) {
        this.cargarImplementacion();
        }

        },

        async verImplementacion(idReporte)
        {
            this.loading = true;

            try {

                const { data } =
                    await axios.get(
                        `/sasisopa/informes-desempeno/implementacion/editar/datatable/${idReporte}`
                    );

                if(data.success){

                    this.detalleImplementacion =
                        data.procedimientos;

                    this.modalDetalleImplementacion.show();

                }

            } finally {

                this.loading = false;

            }

        }


    }));
});
