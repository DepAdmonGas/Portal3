document.addEventListener('alpine:init', () => {
Alpine.data('politicaForm',()=>({

    politicas:[],

    politica:null,

    async init(){

        await this.cargar();

    },

    async cargar(){

        const {data}=await axios.get(
            '/sgm/responsabilidades-direccion/datatable-politica'
        );

        this.politicas=data;

        //mostrar la última
        this.politica=this.politicas[0] ?? null;

    },

    seleccionar(item){

        this.politica=item;

    },

    async eliminar(item){

        const res=await this.deleteAction({

            url:'/sgm/responsabilidades-direccion/delete-politica',

            id:item.id,

            name:'la política'

        });

        if(res.success){

            await this.cargar();

        }

    }

}));

});