document.addEventListener('alpine:init', () => {
    Alpine.data('facturas', (idReporteCre, year) => ({

        idReporteCre,
        year,
        loading:true,
        meses:[],
        productos:[],
        etapas:[],
        archivos:{},
        guardando:false,

        modal:{
            mes:null,
            etapa:null
        },

        errores:{},

        init(){
            this.cargar();
        },

        async cargar(){

            const {data}=await axios.get(
                '/sasisopa/reporte-diario/facturas/get/' + this.year
            );

            if(data.success){

                this.productos=data.productos;
                this.etapas=data.etapas;
                this.meses=data.meses;

            }

            this.loading=false;

        },

        obtenerArchivo(mes,etapa,producto){

            return mes[
                etapa.prefijo+'_'+producto.campo
            ];

        },

        abrirModal(mes,etapa){

            this.modal.mes=mes;

            this.modal.etapa=etapa;

            this.archivos={};

            this.errores={};

            bootstrap.Modal
                .getOrCreateInstance(
                    document.getElementById('modalFacturas')
                )
                .show();

        },

        validate(){

            this.errores={};

            let valido=true;

            this.productos.forEach(producto=>{

                if(!this.archivos[producto.id]){

                    this.errores[producto.id]=true;

                    valido=false;

                }

            });

            return valido;

        },

        async guardarFacturas(){

            if(!this.validate()){

                this.notify(
                    'error',
                    'Selecciona todos los archivos PDF.'
                );

                return;

            }

            const form=new FormData();

            form.append(
                'idReporte',
                this.modal.mes.id
            );

            form.append(
                'tipo',
                this.modal.etapa.id
            );

            Object.entries(this.archivos)
                .forEach(([id,file])=>{

                    form.append(
                        'file'+id,
                        file
                    );

                });

            try{

                const res=await this.createAction({

                    url:'/sasisopa/reporte-diario/facturas/guardar',

                    data:form,

                    multipart:true

                });

                if(res && res.success){

                    bootstrap.Modal
                        .getInstance(
                            document.getElementById('modalFacturas')
                        )
                        .hide();

                    await this.cargar();

                }

            }catch(e){

                this.notify(
                    'error',
                    'No fue posible guardar las facturas.'
                );

            }

        },

    

    }));

});