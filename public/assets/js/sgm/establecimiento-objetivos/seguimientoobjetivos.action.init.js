document.addEventListener('alpine:init', () => {
    Alpine.data('seguimientoObjetivos', () => ({

        id: null,

        seguimiento: {},

        implementacion: {},

        calibracion: {},

        satisfaccion: {},

        asistentes: [],

        usuarios: [],

        usuariosSeleccionados: [],

        init(){

            this.id = this.$el.dataset.id;

            if(this.id != 0){
            this.cargar();
            }

        },

       async crearSeguimiento(){

            const res = await this.createAction({
                url: '/sgm/establecimiento-objetivos-enfocados-cliente/seguimiento-objetivos-indicadores/create',
                onSuccess: (res) => {

                    window.location.href = "/sgm/establecimiento-objetivos-enfocados-cliente/seguimiento-objetivos-indicadores/" + res.id;

                }

            });

            

       },

        async cargar() {

            if (!document.getElementById('sgm-content')) {
                return;
            }

            const { data } = await axios.get(
                `/sgm/establecimiento-objetivos-enfocados-cliente/seguimiento-objetivos-indicadores/${this.id}/detalle`
            );

            this.seguimiento     = data.seguimiento;
            this.implementacion  = data.seguimiento.implementacion;
            this.calibracion     = data.seguimiento.calibracion;
            this.satisfaccion    = data.seguimiento.satisfaccion;
            this.asistentes      = data.seguimiento.asistentes;
            this.usuarios        = data.usuarios;

            await this.$nextTick();

            this.initSelect2();

        },

        initSelect2() {

            if ($(this.$refs.usuarios).hasClass('select2-hidden-accessible')) {

                $(this.$refs.usuarios).select2('destroy');

            }

            const self = this;

            $(this.$refs.usuarios)
                .select2({
                    width: '100%',
                    placeholder: 'Seleccione asistentes',
                })
                .on('change', function () {

                    self.usuariosSeleccionados = $(this).val() || [];

                });

        },

        async agregarAsistentes() {

            if (this.usuariosSeleccionados.length === 0) {

                this.notify(
                    'error',
                    'Seleccione al menos un asistente.'
                );

                return;

            }

            await this.createAction({

                url: '/sgm/establecimiento-objetivos-enfocados-cliente/seguimiento-objetivos-indicadores/asistente/create',

                data: {

                    id: this.id,

                    usuarios: this.usuariosSeleccionados

                },

                onSuccess: async () => {

                    this.usuariosSeleccionados = [];

                    $(this.$refs.usuarios).select2('destroy');

                    await this.cargar();

                }

            });

        },

        async eliminarAsistente(id) {

            const res = await this.deleteAction({

                url: '/sgm/establecimiento-objetivos-enfocados-cliente/seguimiento-objetivos-indicadores/asistente/delete',
                id: id,
                name: 'Asistente'
            });
            
            if(res.success){
            await this.cargar();
            }


        },

        async guardarCampo(seccion, campo, valor) {

            await axios.post(

                '/sgm/establecimiento-objetivos-enfocados-cliente/seguimiento-objetivos-indicadores/update',

                {

                    id: this.id,

                    seccion,

                    campo,

                    valor

                }

            );

        },

        async guardarObjetivo(detalle) {

            await axios.post(

                '/sgm/establecimiento-objetivos-enfocados-cliente/seguimiento-objetivos-indicadores/update',

                {

                    id: this.id,

                    seccion: 3,

                    campo: 6,

                    contenido: detalle

                }

            );

        },

        async finalizar() {

            await this.createAction({

                url: '/sgm/establecimiento-objetivos-enfocados-cliente/seguimiento-objetivos-indicadores/finalizar',

                data: {

                    id: this.id,

                    fecha: this.seguimiento.fecha,

                    hora: this.seguimiento.hora,

                    lugar: this.seguimiento.lugar

                },

                onSuccess: () => {

                    history.back();

                }

            });

        }

    }));
});