document.addEventListener('alpine:init', () => {
Alpine.data('editRevision', (id) => ({

    id,
    revision:{
        categorias:{}
    },

    async init(){

         await this.cargar();

    },

    async cargar(){

        const {data}=await axios.get(
            `/sgm/revision/detalle/${this.id}`
        );

        this.revision=data;

    },

    async actualizar(campo, valor) {

    const res = await this.createAction({
        url: '/sgm/revision/update',
        data: {
            id: this.id,
            campo,
            valor
        }
    });

    return res;
},

async actualizarDetalle(item) {

    const res = await this.createAction({
        url: '/sgm/revision/update-detalle',
        data: {
            id: item.id,
            respuesta: item.respuesta
        }
    });

    return res;
},

    async finalizar(){

        const res = await this.createAction({
        url: '/sgm/revision/finalizar',
        data: {
            id: this.id
        }
        });

        if(res.success){
            history.back();
        }

    }

}));

});