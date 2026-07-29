document.addEventListener('alpine:init',()=>{

Alpine.data('editarBitacoraCalibracion',(id)=>({

    id,

    bitacora:{},

    detalles:[],

    loading:false,

    async init(){

        await this.obtener();

    },

    async obtener(){

        this.loading=true;

        try{

            const {data}=await axios.get(

                `/sgm/procesos-medicion/bitacora-calibracion-equipos/detalle/${this.id}`

            );

            this.bitacora=data.bitacora;

            this.detalles=this.bitacora.detalles;

        }finally{

            this.loading=false;

        }

    },

    async guardar(campo){

        await this.createAction({

            url:'/sgm/procesos-medicion/bitacora-calibracion-equipos/update',

            data:{

                id:this.bitacora.id,

                campo,

                valor:this.bitacora[campo]

            },
            notify: false

        });

    },

    async guardarResultado(detalle){

        await this.createAction({

            url:'/sgm/procesos-medicion/bitacora-calibracion-equipos/update-resultado',

            data:{

                id:detalle.id,

                valor:detalle.resultado
            },
            notify: false

        });

    },

    async finalizar(){

        const res=await this.createAction({

            url:`/sgm/procesos-medicion/bitacora-calibracion-equipos/finalizar`,
             data:{

                id: this.id
            },

        });

        if(res.success){

            history.back();

        }

    }

}));

});