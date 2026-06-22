document.addEventListener('alpine:init', () => {
    Alpine.data('evaluacionRequisitos', () => ({

        registros: [],
        loading: false,

        modalNuevo: null,
        fecha: '',
        documento: null,

        errors: {
            fecha: false,
            documento: false
        },

        init() {

            this.buscar();
            this.modalNuevo = new bootstrap.Modal(document.getElementById('modalNuevo'));

        },

        async buscar() {

            this.loading = true;

            try {

                const { data } = await axios.get(
                    '/sasisopa/monitoreo-verificacion-evaluacion/evaluacion-cumplimiento-requisitos-legales/datatable'
                );

                this.registros = data.data;


            } catch (error) {

                console.error(error);

            } finally {

                this.loading = false;
            }
        },

        //----------------------------------------------------------

        openNuevo(){
        this.limpiar();
        this.modalNuevo.show();
        },

        limpiar(){
            Object.keys(this.errors).forEach(k => this.errors[k] = false);
            fecha: '';
            documento: null;

        },

        validar(){
        Object.keys(this.errors).forEach(k => this.errors[k] = false);
        let valid = true;

        if (!this.fecha) {

            this.errors.fecha = true;
            valid = false;
        }
        if (!this.documento) {
            this.errors.documento = true;
            valid = false;
        }

        return valid;

        },

    async guardar()
    {

        if (!this.validar()) {

            this.notify(
                'error',
                'Completa los campos obligatorios'
            );

            return;
        }

        try {

            const formData = new FormData();

            formData.append(
                'fecha',
                this.fecha
            );

            formData.append(
                    'documento',
                    this.documento
            );

            const url = '/sasisopa/monitoreo-verificacion-evaluacion/evaluacion-cumplimiento-requisitos-legales/create';

            const res =
                await this.createAction({
                    url,
                    data: formData
                });

            if (res && res.success) {

            await this.buscar();
            this.modalNuevo.hide();
            this.limpiar();

            }

        } catch(error){

            this.notify(
                'error',
                'Error al guardar protocolo'
            );
        }
    },

    async eliminar(id)
    {
        const res =
            await this.deleteAction({
                url:'/sasisopa/monitoreo-verificacion-evaluacion/evaluacion-cumplimiento-requisitos-legales/delete',
                id,
                name: 'Informe'
            });

        if(!res?.success){
            return;
        }

        await this.buscar();
    },

    }));
});