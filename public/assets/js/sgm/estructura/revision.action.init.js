document.addEventListener('alpine:init', () => {

Alpine.data('revision', (id) => ({

    id,
    revision:{
        categorias:{}
    },

    async init(){

         await this.cargar();

    },

    async cargar(){

        const {data}=await axios.get(
            `/sgm/estructura-sistema-medicion/revision-sgm-procedimiento-registro/detalle/${this.id}`
        );

        this.revision=data;

    },

    async actualizar(campo, valor) {

    const res = await this.createAction({
        url: '/sgm/estructura-sistema-medicion/revision-sgm-procedimiento-registro/update',
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
        url: '/sgm/estructura-sistema-medicion/revision-sgm-procedimiento-registro/update-detalle',
        data: {
            id: item.id,
            respuesta: item.respuesta
        }
    });

    return res;
},

    async finalizar(){

        const res = await this.createAction({
        url: '/sgm/estructura-sistema-medicion/revision-sgm-procedimiento-registro/finalizar',
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