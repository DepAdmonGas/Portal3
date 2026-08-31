document.addEventListener('alpine:init', () => {
Alpine.data('objetivoForm',()=>({

    objetivos:[],
    objetivo:null,

    async init(){

        await this.cargar();

    },

    async cargar(){

        if (!document.getElementById('sgm-content')) {
            return;
        }

        const {data}=await axios.get(
            '/sgm/establecimiento-objetivos-enfocados-cliente/datatable-objetivos'
        );

        this.objetivos=data;

        //mostrar la última
        this.objetivo=this.objetivos[0] ?? null;

    },

    seleccionar(item){

        this.objetivo=item;

    },

    async eliminar(item){

        const res=await this.deleteAction({

            url:'/sgm/establecimiento-objetivos-enfocados-cliente/objetivo-cliente/delete',

            id:item.id,

            name:'Objetivo'

        });

        if(res.success){

            await this.cargar();

        }

    }

}));

});