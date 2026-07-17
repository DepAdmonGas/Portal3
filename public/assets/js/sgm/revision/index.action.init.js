document.addEventListener('alpine:init', () => {
    Alpine.data('revision', () => ({

        init(){
            window.revision = this;
        },

       async crearRevision(){

        const punto = document
                .getElementById('container')
                .dataset.elemento;

         const res = await this.createAction({
                url: '/sgm/revision/create',
                data: {
                    puntosgm: punto
                },
                onSuccess: (res) => {
                    window.location.href = "/sgm/revision/editar/" + res.id;
                }
        });

        },

        async eliminar(id){

            const res = await this.deleteAction({
                url: "/sgm/revision/delete",
                id: id,
                name: id,
                table: "#table-revision-sgm"
            });

        }

    }));
});