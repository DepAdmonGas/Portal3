document.addEventListener('alpine:init', () => {

    Alpine.data('calibracionDispensario', () => ({

        calibracion: {
            id: null,
            folio: '',
            fecha: '',
            hora: '',
            fecha_termino: '',
            hora_termino: '',
            unidad_verificacion: '',
            numero_acreditacion: '',
            observaciones: '',
            responsable_verificacion: '',
            categoria: 1,
            estado: 0,
            fecha_formateada: '',
            fecha_termino_formateada: '',
            dispensarios: []
        },

        calibracionOriginal: {},
        dispensariosDisponibles: [],
        nuevoDispensario: '',

        init() {

            const json = document.getElementById('calibracion-data');

            if (!json) {
                return;
            }

            const data = JSON.parse(json.textContent);

            this.calibracion = {
                ...this.calibracion,
                ...data,
                categoria: Number( data.categoria || 1)
            };

            this.calibracionOriginal =
                JSON.parse(
                    JSON.stringify(this.calibracion)
                );

            if (Array.isArray(this.calibracion.dispensarios)) {

                this.calibracion.dispensarios
                    .forEach(item => {
                        item._original = {
                            resultado1: item.resultado1,
                            resultado2: item.resultado2,
                            resultado3: item.resultado3,
                            resultado4: item.resultado4
                        };
                    });
            }

        },

        async editarCampo(input,valor,campo) {

            const valorAnterior = this.calibracionOriginal[campo];

            if (valor === valorAnterior) {
                return;
            }

            try {

                const response = await this.createAction({
                        url: '/sasisopa/control-actividades-procesos/calibracion-equipos/bitacora-calibracion-equipos-dispensario/update',
                        notify: false,
                        data: {
                            id: this.calibracion.id,
                            input,
                            valor
                        }
                    });

                if (!response || !response.success) {

                    this.calibracion[campo] = valorAnterior;
                    this.notify('error',response.message);
                    return;

                }

                this.calibracionOriginal[campo] = valor;

            } catch (error) {

                this.notify('error',response.message);
                this.calibracion[campo] = valorAnterior;

            }
        },

        async editarDispensario(item,input,campo) {

            const valor = item[campo];
            const valorAnterior = item._original[campo];

            if (valor === valorAnterior) {
                return;
            }

            try {

                const response = await this.createAction({

                        url: '/sasisopa/control-actividades-procesos/calibracion-equipos/bitacora-calibracion-equipos-dispensario/update',
                        notify: false,
                        data: {
                            id: item.id,
                            input,
                            valor
                        }
                    });

                if (!response || !response.success) {

                    item[campo] = valorAnterior;
                    this.notify('error',response.message);
                    return;

                }

                item._original[campo] = valor;

            } catch (error) {

                this.notify('error',response.message);
                item[campo] =  valorAnterior;

            }
        },

    async cargarDispensariosDisponibles() {

        try {

            const response = await axios.get(
                    '/sasisopa/control-actividades-procesos/calibracion-equipos/bitacora-calibracion-equipos-dispensario/get-dispensarios',
                    {
                        params: {
                            id_calibracion: this.calibracion.id
                        }
                    }
                );

            if (response.data.success) {
                this.dispensariosDisponibles = response.data.data;
            }

        } catch (e) {

            this.notify('error','Error al cargar dispensarios');

        }
    },

        async agregarDispensario() {

    if (!this.nuevoDispensario) {

        this.notify(
            'error',
            'Seleccione un dispensario'
        );

        return;
    }

    try {

        const response =
            await this.createAction({

                url:
                '/sasisopa/control-actividades-procesos/calibracion-equipos/bitacora-calibracion-equipos-dispensario/create',

                data: {

                    id_calibracion:
                        this.calibracion.id,

                    id_dispensario:
                        this.nuevoDispensario
                }
            });

        if (!response.success) {
            return;
        }

        this.calibracion
            .dispensarios
            .push(
                response.dispensario
            );

        this.dispensariosDisponibles =
            this.dispensariosDisponibles
                .filter(
                    x =>
                    x.id !=
                    this.nuevoDispensario
                );

        this.nuevoDispensario = '';

        bootstrap
            .Modal
            .getInstance(
                document.getElementById(
                    'modalDispensario'
                )
            )
            ?.hide();

    } catch (e) {

        this.notify(
            'error',
            'Error al agregar'
        );
    }
},

    async eliminarDispensario(id) {

        try {

            const response = await this.deleteAction({
                url: '/sasisopa/control-actividades-procesos/calibracion-equipos/bitacora-calibracion-equipos-dispensario/delete',
                id,
                name: 'Dispensario'
                });

                if (response && response.success) {

                    this.calibracion.dispensarios = 
                    this.calibracion
                    .dispensarios
                    .filter(
                        item => item.id !== id
                    );
                }

        } catch (error) {
            this.notify('error','Error al eliminar');
        }
                
    },

    async finalizar(equipo) {

            try {

                const response =
                    await this.createAction({

                        url: '/sasisopa/control-actividades-procesos/calibracion-equipos/bitacora-calibracion-equipos-dispensario/finalizar',

                        data: {
                            id: this.calibracion.id,
                            equipo: equipo
                        }
                    });

                if (!response.success) {
                    return;
                }

                this.calibracion.estado = 1;

                window.location.href =
                    '/sasisopa/control-actividades-procesos/calibracion-equipos/bitacora-calibracion-equipos';

            } catch (e) {

                this.notify(
                    'error',
                    'Error al finalizar'
                );
            }
        },

    }));

});