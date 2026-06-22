document.addEventListener('alpine:init', () => {
    Alpine.data('atencionHallazgos', () => ({

         registros: [],
        loading: false,

        init(){
            this.buscar();
        },

        async buscar() {

            this.loading = true;

            try {

                const { data } = await axios.get(
                    '/sasisopa/monitoreo-verificacion-evaluacion/atencion-hallazgos/datatable'
                );

                this.registros = data.data;


            } catch (error) {

                console.error(error);

            } finally {

                this.loading = false;
            }
        },

        async nuevo(){

            try {

                const res =
                await this.createAction({
                    url: '/sasisopa/monitoreo-verificacion-evaluacion/atencion-hallazgos/create'
                });

            if (res && res.success) {
                 this.editar(res.id);
            }

            } catch (e) {

            this.notify(
                'error', e
            );
        }

        },

        editar(id){
            window.location.href =
                '/sasisopa/monitoreo-verificacion-evaluacion/atencion-hallazgos/nuevo/' + id;
        },

        async eliminar(id)
        {
            const res =
                await this.deleteAction({
                    url:'/sasisopa/monitoreo-verificacion-evaluacion/atencion-hallazgos/delete',
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