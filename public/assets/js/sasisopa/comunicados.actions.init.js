document.addEventListener('alpine:init', () => {
    Alpine.data('comunicados', () => ({

        loading: false,
        comunicados: [],
        puestos: [],

        modalComunicado: null,

        comunicado: {
            tema: '',
            detalle: '',
            archivo: null
        },

        selectedPuestos: [],

        errors: {
            tema: false,
            detalle: false,
            dirigidoa: false
        },

        modalDetalleComunicado: null,

        detalleComunicado: {
            fecha_larga: '',
            tema: '',
            detalle: '',
            archivo: '',
            dirigidoa: []
        },

        

        init(){

        if (!document.getElementById('modalComunicado')) {
            return;
        }

        this.modalComunicado =
            new bootstrap.Modal(
                document.getElementById('modalComunicado')
            );

        this.modalDetalleComunicado =
        new bootstrap.Modal(
            document.getElementById('modalDetalleComunicado')
        );

        this.cargarComunicados();

        window.comunicadosInstance = this;

        this.bindModalSelect2({

            modalRef:'modalComunicado',

            selectRef:'selectDirigidoa',

            wrapperRef:'dirigidoaWrapper',

            model:'selectedPuestos',

            namespace:'comunicados',

            options:{
                placeholder:'Seleccione uno o varios puestos',
                multiple:true,
                closeOnSelect:false
            },

            onShown(){

                if(!this.puestos.length){

                    this.getPuestos();

                    return false;

                }

                return true;

            }

        });

        },

        async cargarComunicados(){
        this.loading = true;

        try {

            const { data } = await axios.get(
                '/sasisopa/comunicados/datatable'
            );

            if (data.success) {

                this.comunicados =
                    data.data;
            }

        } finally {

            this.loading = false;
        }
        },

        openModalComunicado(){

    this.limpiar();

    this.modalComunicado.show();

    if(!this.puestos.length){

        this.getPuestos();

    }

        },

        limpiar(){

            this.comunicado={

                tema:'',
                detalle:'',
                archivo:null

            };

            this.selectedPuestos=[];

            Object.keys(this.errors).forEach(k=>{

                this.errors[k]=false;

            });

            this.$nextTick(()=>{

                $(this.$refs.selectDirigidoa)
                    .val(null)
                    .trigger('change');

            });

        },

        async getPuestos(){

            try{

                const res = await axios.get(
                    '/puestos/get-puestos'
                );

                this.puestos = res.data;

                this.$nextTick(()=>{

                    const modal =
                        this.$refs.modalComunicado ??
                        document.getElementById('modalComunicado');

                    if(modal.classList.contains('show')){

                        this.initModalSelect2({

                            modalRef:'modalComunicado',

                            selectRef:'selectDirigidoa',

                            wrapperRef:'dirigidoaWrapper',

                            model:'selectedPuestos',

                            namespace:'comunicados',

                            options:{
                                placeholder:'Seleccione uno o varios puestos',
                                multiple:true,
                                closeOnSelect:false
                            }

                        });

                    }

                });

            }catch(e){

                this.notify(
                    'error',
                    'Error al obtener puestos'
                );

            }

        },


        validar()
        {
        
         Object.keys(this.errors).forEach(k => this.errors[k] = false);
        let valid = true;

        if (!this.comunicado.tema) {
        this.errors.tema = true;
        valid = false;
        }

        if (!this.comunicado.detalle) {
        this.errors.detalle = true;
        valid = false;
        }

        return valid;
        },

        async guardarComunicado(){

            if(!this.validar()){

                this.notify(
                    'error',
                    'Completa los campos obligatorios'
                );

                return;

            }

            const payload=new FormData();

            payload.append(
                'tema',
                this.comunicado.tema
            );

            payload.append(
                'detalle',
                this.comunicado.detalle
            );

            payload.append(
                'dirigidoa',
                JSON.stringify(
                    this.selectedPuestos
                )
            );

            if(this.comunicado.archivo){

                payload.append(
                    'archivo',
                    this.comunicado.archivo
                );

            }

            const res =
                await this.createAction({

                    url:'/sasisopa/comunicados/create',

                    data:payload

                });

            if(res.success){

                this.modalComunicado.hide();

                this.cargarComunicados();

            }

        },

        async eliminar(item) {

        const res = await this.deleteAction({

        url: '/sasisopa/comunicados/delete',
        id: item.id,
        name: item.id
        });

        if (res?.success) {
        this.cargarComunicados();
        }

        },

        verDetalle(item){

            this.detalleComunicado = {

                fecha_larga: item.fecha_larga,

                tema: item.tema,

                detalle: item.detalle,

                archivo: item.archivo,

                dirigidoa: item.dirigidoa

            };

            this.modalDetalleComunicado.show();

        },

    }));
});