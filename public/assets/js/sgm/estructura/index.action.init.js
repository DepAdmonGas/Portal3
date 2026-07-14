document.addEventListener('alpine:init', () => {
    Alpine.data('estructuraSm', () => ({

        init(){
            window.estructuraSm = this;
        },

       async crearRevision(){

        const punto = document
                .getElementById('container')
                .dataset.elemento;

         const res = await this.createAction({
                url: '/sgm/estructura-sistema-medicion/create-revision',
                data: {
                    puntosgm: punto
                },
                onSuccess: (res) => {
                    window.location.href = "/sgm/estructura-sistema-medicion/revision-sgm-procedimiento-registro/" + res.id;
                }
        });

        },

        async eliminar(id){

            const res = await this.deleteAction({
                url: "/sgm/estructura-sistema-medicion/delete-revision",
                id: id,
                name: id,
                table: "#table-revision-sgm"
            });

        }

    }));
});