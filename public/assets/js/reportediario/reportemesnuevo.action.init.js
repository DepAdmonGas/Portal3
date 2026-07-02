document.addEventListener('alpine:init', () => {
    Alpine.data('corteNuevo', (config) => ({

       /*
    =====================================
    CONFIGURACIÓN
    =====================================
    */

    modo: config.modo,          // nuevo | editar
    idReporteCre: config.idReporteCre,
    fecha: config.fecha ?? '',

    /*
    =====================================
    DATOS
    =====================================
    */

    productos: [],

    loading: false,

    saving: false,

    /*
    =====================================
    VALIDACIONES
    =====================================
    */

    errors: {

        fecha: false,

        volumen: {}

    },

    /*
    =====================================
    INIT
    =====================================
    */

    async init(){

        if(this.modo === 'editar'){

            await this.cargarEdicion();

        }else{

            await this.cargarBase();

        }

    },

        async cargarBase(){

        this.loading = true;

        try{

            const {data} = await axios.get(
                '/sasisopa/reporte-diario/nuevo/base-reporte-diario'
            );

            if(data.success){

                this.productos = data.data;

                this.inicializarErrores();

            }

        }finally{

            this.loading = false;

        }

    },

        async cargarEdicion(){

         this.loading = true;

        try{

            const {data} = await axios.get(

                `/sasisopa/reporte-diario/editar/base/${this.idReporteCre}/${this.fecha}`

            );

            if(data.success){

                this.productos = data.data;

                this.inicializarErrores();

            }

        }finally{

            this.loading = false;

        }

    },

        inicializarErrores(){

        this.errors.volumen = {};

        this.productos.forEach((producto,index)=>{

            this.errors.volumen[index]={

                inicial:false,
                venta:false,
                final:false

            };

        });

    },

        agregarPipa(producto){

        producto.pipas.push({

            id:null,

            volumen:null,

            precio:null,

            costo:null,

            factura:'',

            transportista:'',

            importe:null,

            nueva:true

        });

    },

        eliminarPipa(producto,index){

        producto.pipas.splice(index,1);

    },

        calcularPrecio(pipa){

        const volumen = parseFloat(pipa.volumen);

        const importe = parseFloat(pipa.importe);

        if(!volumen || volumen<=0 || !importe){

            pipa.precio='';

            return;

        }

        pipa.precio=(importe/volumen).toFixed(2);

    },

            /*=====================================================
        =            CARGAR INFORMACIÓN BASE / EDITAR         =
        =====================================================*/

        async cargar() {

            this.loading = true;

            try {

                let url = '/sasisopa/reporte-diario/nuevo/base-reporte-diario';

                if (this.modo === 'editar') {

                    url = `/sasisopa/reporte-diario/editar/base/${this.idReporteCre}/${this.fecha}`;

                }

                const { data } = await axios.get(url);

                if (data.success) {

                    this.productos = data.data;

                    this.productos.forEach((producto, index) => {

                        this.errors.volumen[index] = {
                            inicial: false,
                            venta: false,
                            final: false
                        };

                        if (!producto.pipas) {
                            producto.pipas = [];
                        }

                        producto.pipas.forEach(pipa => {

                            this.calcularPrecio(pipa);

                        });

                    });

                }

            } finally {

                this.loading = false;

            }

        },

        /*=====================================================
        =            AGREGAR PIPA                             =
        =====================================================*/

        agregarPipa(producto) {

            producto.pipas.push({

                id: null,

                volumen: null,
                precio: null,
                costo: null,
                factura: '',
                transportista: '',
                importe: null,

                eliminar: false

            });

        },

        /*=====================================================
        =            ELIMINAR PIPA                            =
        =====================================================*/

        eliminarPipa(producto,index){

            const pipa = producto.pipas[index];

            /*
                Si todavía no existe en BD simplemente la quitamos
            */

            if(!pipa.id){

                producto.pipas.splice(index,1);

                return;

            }

            /*
                Si existe la marcamos para eliminar
            */

            pipa.eliminar = true;

        },

        /*=====================================================
        =            CALCULAR PRECIO                          =
        =====================================================*/

        calcularPrecio(pipa){

            const volumen = parseFloat(pipa.volumen);
            const importe = parseFloat(pipa.importe);

            if(!volumen || volumen<=0 || !importe){

                pipa.precio='';

                return;

            }

            pipa.precio=(importe/volumen).toFixed(2);

        },

        /*=====================================================
        =            TOTAL MERMA                             =
        =====================================================*/

        totalMerma(producto){

            let compra=0;

            producto.pipas
                .filter(p=>!p.eliminar)
                .forEach(p=>{

                    compra += parseFloat(p.volumen || 0);

                });

            return (
                parseFloat(producto.volumen.inicial || 0)
                +
                compra
                -
                parseFloat(producto.volumen.venta || 0)
                -
                parseFloat(producto.volumen.final || 0)
            ).toFixed(2);

        },

        /*=====================================================
        =            VALIDAR                                  =
        =====================================================*/

        validar(){

            let valido=true;

            this.errors.fecha=false;

            Object.keys(this.errors.volumen).forEach(index=>{

                this.errors.volumen[index].inicial=false;
                this.errors.volumen[index].venta=false;
                this.errors.volumen[index].final=false;

            });

            if(!this.fecha){

                this.errors.fecha=true;

                valido=false;

            }

            this.productos.forEach((producto,index)=>{

                if(producto.volumen.inicial==='' || producto.volumen.inicial===null){

                    this.errors.volumen[index].inicial=true;

                    valido=false;

                }

                if(producto.volumen.venta==='' || producto.volumen.venta===null){

                    this.errors.volumen[index].venta=true;

                    valido=false;

                }

                if(producto.volumen.final==='' || producto.volumen.final===null){

                    this.errors.volumen[index].final=true;

                    valido=false;

                }

            });

            if(!valido){

                this.notify(
                    'error',
                    'Complete todos los campos obligatorios.'
                );

            }

            return valido;

        },

        /*=====================================================
        =            GUARDAR                                  =
        =====================================================*/

        async submit(){

            if(!this.validar()){

                return;

            }

            try{

                const url = this.modo==='crear'
                    ? '/sasisopa/reporte-diario/nuevo/create'
                    : '/sasisopa/reporte-diario/editar/update';

                const res = await this.createAction({

                    url,

                    data:{

                        idReporteCre:this.idReporteCre,

                        fecha:this.fecha,

                        productos:this.productos

                    }

                });

                if(res && res.success){

                    this.back();

                }

            }catch(e){

                this.notify(
                    'error',
                    e.message
                );

            }

        }

    }));

});