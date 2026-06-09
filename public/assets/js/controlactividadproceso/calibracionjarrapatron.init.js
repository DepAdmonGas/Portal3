document.addEventListener('alpine:init', () => {
    Alpine.data('calibracionJarraPatron', () => ({

    calibracion: {
        id: null,
        folio: '',
        fecha_formateada: '',
        hora: '',
        observaciones: '',
        responsable_verificacion: '',

        temperatura_ambiente: '',
        presion_atmosferica: '',
        humedad: '',
        liquido_calibracion: '',
        temperatura_liquido: '',
        laboratorio_calibracion: '',
        numero_acreditacion: '',
        metodo_calibracion: '',

        estado: 0,

        jarras: []
    },

    calibracionOriginal: {},


    init() {

         const json =
            document.getElementById(
                'calibracion-data'
            );

        if (!json) {
            return;
        }

        const data =
            JSON.parse(json.textContent);

        this.calibracion = data;

         this.calibracionOriginal =
            JSON.parse(
                JSON.stringify(data)
            );

        if (Array.isArray(this.calibracion.jarras)) {

            this.calibracion.jarras.forEach(
                item => {

                    item._original = {
                        resultado1:
                            item.resultado1
                    };

                }
            );

        }


    },

    async editarJarra(item, input, campo) {

    const valor = item[campo];
    const original = item._original?.[campo];

    if (valor === original) {
        return;
    }

    try {

        const response =
            await this.createAction({

                url: '/sasisopa/control-actividades-procesos/calibracion-equipos/bitacora-calibracion-equipos-jarra-patron/update',

                notify: false,

                data: {
                    id: item.id,
                    input,
                    valor
                }
            });

        if (!response.success) {

            item[campo] = original;

            this.notify(
                'error',
                response.message
            );

            return;
        }

        item._original[campo] = valor;

    } catch (e) {

        item[campo] = original;

        this.notify(
            'error',
            'Error al guardar'
        );

        console.error(e);
    }
},

    async editarCampo(input, valor, campo) {

    const original = this.calibracionOriginal[campo];

    if (valor === original) {
        return;
    }

    try {

        const response =
            await this.createAction({

                url: '/sasisopa/control-actividades-procesos/calibracion-equipos/bitacora-calibracion-equipos-jarra-patron/update',

                notify: false,

                data: {
                    id: this.calibracion.id,
                    input,
                    valor
                }
            });

        if (!response.success) {

            this.calibracion[campo] = original;
            return;
        }

        this.calibracionOriginal[campo] = valor;

    } catch (e) {

        this.calibracion[campo] = original;
    }
},

async finalizar() {

    try {

        const response =
            await this.createAction({

                url: '/sasisopa/control-actividades-procesos/calibracion-equipos/bitacora-calibracion-equipos-jarra-patron/finalizar',

                data: {
                    id: this.calibracion.id
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