document.addEventListener('alpine:init', () => {
    Alpine.data('atencionHallazgos', () => ({

        id: '',
        fecha_auditoria: '',
        no_control: '',
        tipo_auditoria: '',
        registros: [],
        loading: false,
        sasisopaOptions: [],

        modoHallazgo: 'create',
        editIdHallazgo: '',
        modalHallazgo: null,

        hallazgo: {
            id_sasisopa: '',
            hallazgo: '',
            accion: '',
            fecha: ''
        },

       errors: {
            id_sasisopa: false,
            hallazgo: false,
            accion: false,
            fecha: false
        },

        modalEvidencia: null,
        idHallazgoEvidencia: null,
        archivo: null,
        evidencias: [],
        loadingEvidencia: false,

        init(){
             this.$nextTick(() => {
                this.modalHallazgo = new bootstrap.Modal(
                    document.getElementById('modalHallazgo')
                );
             });

            this.modalEvidencia = new bootstrap.Modal(document.getElementById('modalEvidencia'));
        },

        async buscarHallazgos() {

      
            this.loading = true;

            try {

                const { data } = await axios.get(
                    `/sasisopa/monitoreo-verificacion-evaluacion/atencion-hallazgos/detalle/${this.id}`
                );

                if (data.success) {

                    this.registros = data.data;

                }

            } catch (error) {

                this.loading = false;

            } finally {

                this.loading = false;
            }
        },

        async guardarEncabezado() {

        try {

             const payload = {
                    id: this.id,
                    fecha_auditoria: this.fecha_auditoria,
                    no_control: this.no_control,
                    tipo_auditoria: this.tipo_auditoria
                };

                const res =
                await this.createAction({
                    url: '/sasisopa/monitoreo-verificacion-evaluacion/atencion-hallazgos/update-encabezados',
                    data: payload,
                    notify: false

                });

            if (res && res.success) {
                 
            }

            } catch (e) {

            this.notify(
                'error', e
            );
        }
        },

        abrirModal(){
            this.limpiarHallazgo();
            this.cargarSasisopa();
            this.modalHallazgo.show();
        },

    async cargarSasisopa() {

        this.loadingSasisopa = true;

        try {

            const response = await axios.get(
                '/sasisopa/monitoreo-verificacion-evaluacion/atencion-hallazgos/sasisopa?id_atencion='+ this.id
            );

            this.sasisopaOptions = response.data.data;

        } finally {
            this.loadingSasisopa = false;
        }
    },

    limpiarHallazgo(){
            this.modoHallazgo = 'create';
            this.editIdHallazgo = null;

            this.hallazgo = {
                id_sasisopa: '',
                hallazgo: '',
                accion: '',
                fecha: ''
            };

            this.errors = {
                id_sasisopa: false,
                hallazgo: false,
                accion: false,
                fecha: false
            };
    },

    validarHallazgo()
    {
        let valid = true;

            Object.keys(this.errors)
                .forEach(k => this.errors[k] = false);

            if (!this.hallazgo.id_sasisopa) {
                this.errors.id_sasisopa = true;
                valid = false;
            }

            if (!this.hallazgo.hallazgo) {
                this.errors.hallazgo = true;
                valid = false;
            }

            if (!this.hallazgo.accion) {
                this.errors.accion = true;
                valid = false;
            }

            if (!this.hallazgo.fecha) {
                this.errors.fecha = true;
                valid = false;
            }

            return valid;
    },

    async guardar() {

        if (!this.validarHallazgo()) {

            this.notify(
                'error',
                'Completa los campos obligatorios'
            );

            return;
        }



    try {

        const payload = {
            id: this.id,
            editIdHallazgo: this.editIdHallazgo,
            id_sasisopa: this.hallazgo.id_sasisopa ,
            hallazgos: this.hallazgo.hallazgo ,
            accion: this.hallazgo.accion ,
            fecha_implementacion: this.hallazgo.fecha
            
        };

        const url =
                this.modoHallazgo === 'create'
                ? '/sasisopa/monitoreo-verificacion-evaluacion/atencion-hallazgos/create-detalle'
                : '/sasisopa/monitoreo-verificacion-evaluacion/atencion-hallazgos/update-detalle';

        const res = await this.createAction({
            url: url,
            data: payload
        });

        if (res?.success) {

            this.modalHallazgo.hide();
            await this.buscarHallazgos();
        }

    } catch (e) {
        this.notify('error', e);
    }
    },

    editar(registro){

        this.modoHallazgo = 'edit';
        this.cargarSasisopa();
        this.editIdHallazgo = registro.id;

        this.hallazgo = {
            id_sasisopa: String(registro.id_sasisopa),
            hallazgo: registro.hallazgos,
            accion: registro.accion,
            fecha: registro.fecha
        };

        this.modalHallazgo.show();
    },

    async eliminar(id){

        const res =
            await this.deleteAction({
                url:'/sasisopa/monitoreo-verificacion-evaluacion/atencion-hallazgos/delete-hallazgo',
                id,
                name: 'Hallazgo'
            });

            if(!res?.success){
                return;
            }

        await this.buscarHallazgos();

    },

    //---------------------------------------------

    async abrirModalEvidencia(idHallazgo){

    this.idHallazgoEvidencia = idHallazgo;

    this.archivo = null;

    await this.cargarEvidencias();

    this.modalEvidencia.show();
    },

    seleccionarArchivo(event){
    this.archivo = event.target.files[0] ?? null;
    },

    async cargarEvidencias(){

    this.loadingEvidencia = true;

    try {

    const { data } = await axios.get(
            `/sasisopa/monitoreo-verificacion-evaluacion/atencion-hallazgos/evidencias/${this.idHallazgoEvidencia}`
    );

    if(data.success){
    this.evidencias = data.data;
    }

    } finally {
    this.loadingEvidencia = false;
    }
    },

    async subirEvidencia(){

    if(!this.archivo){

        this.notify(
            'warning',
            'Seleccione un archivo'
        );

        return;
    }

    const formData = new FormData();

    formData.append(
        'id_hallazgo',
        this.idHallazgoEvidencia
    );

    formData.append(
        'archivo',
        this.archivo
    );

    try {

        const url = '/sasisopa/monitoreo-verificacion-evaluacion/atencion-hallazgos/evidencia/create';
        const res =
            await this.createAction({
                url,
                data: formData
            });

        if(res && res.success){

            await this.cargarEvidencias();
            await this.buscarHallazgos();
            this.archivo = null;
        }

    } catch (e){

        this.notify('error', e);
    }
    },

    async eliminarEvidencia(id){

    const res =
        await this.deleteAction({
            url:
            '/sasisopa/monitoreo-verificacion-evaluacion/atencion-hallazgos/evidencia/delete',
            id,
            name:'Evidencia'
        });

    if(!res?.success){
        return;
    }

    await this.cargarEvidencias();

    await this.buscarHallazgos();
    },

    }));
});