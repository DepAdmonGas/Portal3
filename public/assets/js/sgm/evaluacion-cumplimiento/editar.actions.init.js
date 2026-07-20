document.addEventListener('alpine:init', () => {
    Alpine.data('evaluacionForm',(id)=>({
    
    id,
    revision: {},
    usuarios: [],
    usuariosDisponibles: [],
    asistentes: [],
    asistentesSeleccionados: [],

    async init(){

        await this.cargar();

    },

    async cargar() {

        const { data } = await axios.get(
            `/sgm/evaluacion-cumplimiento-objetivos-revision-direccion/editar/detalle/${this.id}`
        );

        this.usuarios = data.usuarios;
        this.revision = data.cumplimiento;
        
        this.usuariosDisponibles = data.usuariosDisponibles;
        this.asistentes = data.cumplimiento.asistentes;

        this.asistentesSeleccionados = [];

        await this.$nextTick();

        this.initSelect2();

    },

    initSelect2() {

        const select = $(this.$refs.usuarios);

        if (select.hasClass('select2-hidden-accessible')) {
            select.off('change');
            select.select2('destroy');
        }

        const self = this;

        select.select2({
            width: '100%',
            placeholder: 'Seleccione asistentes',
            allowClear: true
        });

        select.on('change', function () {

            self.asistentesSeleccionados = $(this).val() || [];

        });

    },

async guardarCampo() {

    await this.createAction({
        url: '/sgm/evaluacion-cumplimiento-objetivos-revision-direccion/update',
        data: this.revision,
        notify: false

    });

},

    async agregarAsistentes(){

    if(this.asistentesSeleccionados.length===0){

        this.notify(
            'error',
            'Seleccione al menos un asistente'
        );

        return;

    }

    await this.createAction({

        url:'/sgm/evaluacion-cumplimiento-objetivos-revision-direccion/asistentes',

        data:{
            id:this.revision.id,
            usuarios:this.asistentesSeleccionados
        },

        onSuccess: async()=>{

            this.asistentesSeleccionados=[];

            $(this.$refs.usuarios)
                .val(null)
                .trigger('change');

            await this.cargar();

        }

    });

},

    async eliminarAsistente(id){

         const res = await this.deleteAction({

                url: '/sgm/evaluacion-cumplimiento-objetivos-revision-direccion/asistentes/delete',
                id: id,
                name: 'Asistente'
            });
            
            if(res.success){
            await this.cargar();
            }

    },

    async finalizar(){

    await this.createAction({
                url: '/sgm/evaluacion-cumplimiento-objetivos-revision-direccion/finalizar',
                data: {
                    id: this.id

                },
                onSuccess: () => {
                    history.back();
                }
            });

    }


    }));
});