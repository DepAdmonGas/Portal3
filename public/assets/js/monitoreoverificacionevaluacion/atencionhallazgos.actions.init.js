document.addEventListener('alpine:init', () => {
    Alpine.data('atencionHallazgos', () => ({

        init(){
            window.atencionHallazgos = this;
        },

        async buscar() {

            if (!window.tableAtencionHallazgos) return;

            await new Promise(resolve => window.tableAtencionHallazgos.ajax.reload(resolve, false));
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
