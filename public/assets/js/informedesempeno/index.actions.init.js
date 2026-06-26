document.addEventListener('alpine:init', () => {
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
        this.cargarEvaluacion();
        this.cargarImplementacion();

        this.modalEvaluacion = new bootstrap.Modal(document.getElementById('modalEvaluacion'));
        this.modalDetalleImplementacion = new bootstrap.Modal(document.getElementById('modalDetalleImplementacion'));

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