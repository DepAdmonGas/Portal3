document.addEventListener('alpine:init', () => {
    Alpine.data('calibracionSondas', () => ({

        calibracion: {
            id: null,
            folio: '',
            fecha: '',
            hora: '',
            fecha_termino: '',
            hora_termino: '',           
            observaciones: '',
            responsable_verificacion: '',
            categoria: 1,
            estado: 0,
            fecha_formateada: '',
            fecha_termino_formateada: '',
            sondas: []
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

        if (Array.isArray(this.calibracion.sondas)) {

            this.calibracion.sondas.forEach(
                item => {

                    item._original = {
                        resultado1:
                            item.resultado1
                    };

                }
            );

        }

    },

async editarCampo(input, valor, campo) {

    const original = this.calibracionOriginal[campo];

    if (valor === original) return;

    try {

        const response = await this.createAction({
            url: '/sasisopa/control-actividades-procesos/calibracion-equipos/bitacora-calibracion-equipos-sonda/update',
            notify: false,
            data: {
                id: this.calibracion.id,
                input,
                valor
            }
        });

        if (!response.success) {

            this.calibracion[campo] = original;

            this.notify('error', response.message);
            return;
        }

        this.calibracionOriginal[campo] = valor;

    } catch (e) {

        this.calibracion[campo] = original;

        this.notify('error', 'Error al guardar');
    }
},

async editarSonda(item, input, campo) {

    const valor = item[campo];
    const original = item._original?.[campo];

    if (valor === original) return;

    try {

        const response = await this.createAction({
            url: '/sasisopa/control-actividades-procesos/calibracion-equipos/bitacora-calibracion-equipos-sonda/update',
            notify: false,
            data: {
                id: item.id,
                input,
                valor
            }
        });

        if (!response.success) {

            item[campo] = original;
            this.notify('error', response.message);
            return;
        }

        item._original[campo] = valor;

    } catch (e) {

        item[campo] = original;
        this.notify('error', 'Error al guardar');
    }
},

    async finalizar(equipo) {

            try {

                const response =
                    await this.createAction({
                        url: '/sasisopa/control-actividades-procesos/calibracion-equipos/bitacora-calibracion-equipos-sonda/finalizar',
                        data: {
                            id: this.calibracion.id,
                            equipo: equipo
                        }
                    });

                if (!response.success) {
                    return;
                }

                this.calibracion.estado = 1;
                window.location.href = '/sasisopa/control-actividades-procesos/calibracion-equipos/bitacora-calibracion-equipos';

            } catch (e) {

                this.notify('error','Error al finalizar');

            }
    },


    }));

});