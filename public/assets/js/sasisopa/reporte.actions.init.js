document.addEventListener('alpine:init', () => {
    Alpine.data('reporte', (fechaInicio, fechaTermino) => ({

        loading: false,
        fechaInicio,
        fechaTermino,

        get yearInicio() {
        return this.fechaInicio
            ? this.fechaInicio.split('-')[0]
            : '';
        },

        get yearFin() {
            return this.fechaTermino
                ? this.fechaTermino.split('-')[0]
                : '';
        },

        politica: [],

        elemento2:{
            analisis:[],
            asistencias:[]
        },

        elemento3: [],
        elemento5: [],
        elemento6: [],
        elemento7: {
            comunicaciones: [],
            quejas: []
        },
        elemento10: {},
        elemento12: [],
        elemento13: [],

        year:null,
        informes:[],
        hallazgos:[],

        auditoriasInternas:[],
        auditoriasExternas:[],
        ingresosAsea:[],
        modalAsea:null,

        registros: [],
        grupo: [],
        tercer: {
            id: '',
            nombre: '',
            numero: '',
            lider: '',
            fecha: '',
            archivo: ''
        },
        modalGrupo: null,
        modalTercer: null,
        sinAccidentes: [],

        revisionResultados: [],
        loadingRevision: false,

        evaluaciones: [],
        implementaciones: [],
        loading18: false,

    init(){

        this.modalAsea = new bootstrap.Modal(document.getElementById('modalAsea'));
        this.modalGrupo = new bootstrap.Modal(document.getElementById('modalGrupoInterdisciplinario'));
        this.modalTercer = new bootstrap.Modal(document.getElementById('modalTercerAutorizado'));

        this.cargarElemento1();
        this.cargarElemento2();
        this.cargarElemento3();
        this.cargarElemento5();
        this.cargarElemento6();
        this.cargarElemento7();
        this.cargarElemento10();
        this.cargarElemento12();
        this.cargarElemento13();
        this.cargarElemento14();
        this.cargarElemento15();
        this.cargarElemento16();
        this.cargarElemento17();
        this.cargarElemento18();

    },

    async cargarElemento1(){
    this.loading=true;

    try{
        const res=await axios.get(
            '/sasisopa/reporte/elemento1',
            {
                params:{
                    inicio: this.fechaInicio,
                    fin: this.fechaTermino
                }
            }
        );

        this.politica=res.data;

    }finally{

        this.loading=false;

    }

    },

    async cargarElemento2(){
    try{

        const {data} = await axios.get(
            '/sasisopa/reporte/elemento2',
            {
                params:{
                    inicio:this.fechaInicio,
                    fin:this.fechaTermino
                }
            }
        );

        this.elemento2 = data;

    }catch(e){

        this.loading=false;

    }
    },

    async cargarElemento3(){

    try{

        const {data} = await axios.get(
            '/sasisopa/reporte/elemento3',
            {
                params:{
                    inicio:this.fechaInicio,
                    fin:this.fechaTermino
                }
            }
        );


        this.elemento3 = data;


    }catch(e){

        console.error(e);

    }finally{

        this.loading = false;

    }

    },

    async cargarElemento5(){

    try{

        const {data} = await axios.get(
            '/sasisopa/reporte/elemento5',
            {
                params:{
                    inicio:this.fechaInicio,
                    fin:this.fechaTermino
                }
            }
        );


        this.elemento5 = data;


    }catch(e){

        console.error(e);

    }finally{

        this.loading = false;

    }

    },

    async cargarElemento6(){
        try{

        const {data} = await axios.get(
            '/sasisopa/reporte/elemento6',
            {
                params:{
                    inicio:this.fechaInicio,
                    fin:this.fechaTermino
                }
            }
        );


        this.elemento6 = data;


    }catch(e){

        console.error(e);

    }finally{

        this.loading = false;

    }
    },

    async cargarElemento7(){

        try{

            const {data} = await axios.get(
                '/sasisopa/reporte/elemento7',
                {
                    params:{
                        inicio:this.fechaInicio,
                        fin:this.fechaTermino
                    }
                }
            );

            this.elemento7 = data;

        }catch(e){

            console.error(e);

        }

    },

    async cargarElemento10() {

    try {

        const { data } = await axios.get(
            '/sasisopa/reporte/elemento10',
            {
                params: {
                    inicio: this.fechaInicio,
                    fin: this.fechaTermino
                }
            }
        );

        this.elemento10 = data;

    } catch (e) {

        console.error(e);

    }

    },

    async cargarElemento12(){

        this.loading = true;

        try{

            const {data} = await axios.get(
                '/sasisopa/reporte/elemento12',
                {
                    params: {
                    inicio: this.fechaInicio,
                    fin: this.fechaTermino
                }
                }
            );

            this.elemento12 = data.data;

        }finally{

            this.loading = false;

        }

    },

    async cargarElemento13(){

        this.loading = true;

        try{

            const {data} = await axios.get(
                '/sasisopa/reporte/elemento13',
                {
                    params: {
                    inicio: this.fechaInicio,
                    fin: this.fechaTermino
                    }
                }
            );

            this.elemento13 = data.data;

        }finally{

            this.loading = false;

        }

    },

    async cargarElemento14(){

        this.loading=true;
        try{
           const {data} = await axios.get('/sasisopa/reporte/elemento14',
            {
            params:{
                inicio: this.fechaInicio,
                fin: this.fechaTermino

            }

        });

            this.year=data.year;

            this.informes=data.informes;

            this.hallazgos=data.hallazgos;

            this.loading=false;

        }finally{

            this.loading = false;

        }

    },

    async cargarElemento15(){

        this.loading=true;

        const {data}=await axios.get(
            '/sasisopa/reporte/elemento15',
            {
                params:{
                    inicio:this.fechaInicio,
                    fin:this.fechaFin
                }
            }
        );

        this.auditoriasInternas=data.interna;

        this.auditoriasExternas=data.externa;

        this.loading=false;

    },

    abrirAsea(registro){

        this.ingresosAsea=registro.asea;
        this.modalAsea.show();

    },

    async cargarElemento16(){

    this.loading = true;

    try{

        const { data } = await axios.get(
            '/sasisopa/reporte/elemento16',
            {
                params:{
                    inicio:this.fechaInicio,
                    fin:this.fechaTermino
                }
            }
        );

        this.registros = data.investigaciones ?? [];
        this.sinAccidentes = data.sin_accidentes ?? [];

    }catch(e){

        this.registros = [];
        this.sinAccidentes = [];

    }finally{

        this.loading = false;

    }
    },

    async abrirGrupo(id) {

            try {

                const { data } = await axios.get(
                    `/sasisopa/reporte/elemento16/grupo/${id}`
                );

                this.grupo = data.data ?? [];

                this.modalGrupo.show();

            } catch (e) {

                console.error(e);

            }

    },

    async abrirTercer(id) {

            try {

                const { data } = await axios.get(
                    `/sasisopa/reporte/elemento16/tercer/${id}`
                );

                this.tercer = data.data;

                this.modalTercer.show();

            } catch (e) {

                console.error(e);

            }

    },

    async cargarElemento17(){

        this.loadingRevision = true;

        try{

            const {data} = await axios.get(

                '/sasisopa/reporte/elemento17',

                {
                    params:{

                        inicio:this.fechaInicio,

                        fin:this.fechaTermino

                    }
                }

            );

            this.revisionResultados = data.data ?? [];

        }catch(e){

            this.revisionResultados=[];

        }finally{

            this.loadingRevision=false;

        }

    },

    async cargarElemento18(){

    this.loading18 = true;

    try{

        const {data} = await axios.get(

            '/sasisopa/reporte/elemento18',

            {
                params:{
                    inicio:this.fechaInicio,
                    fin:this.fechaTermino
                }
            }

        );

        this.evaluaciones = data.evaluaciones ?? [];

        this.implementaciones = data.implementaciones ?? [];

    }catch(e){

        this.evaluaciones = [];

        this.implementaciones = [];

    }finally{

        this.loading18 = false;

    }

    },

    }));

});