document.addEventListener('alpine:init', () => {
    Alpine.data('calibracionTanques', () => ({

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
            tanques: []
        },

        calibracionOriginal: {},

        archivoPdf: null,
       tanqueSeleccionado: {
            id: null,
            resultados: ''
        },

    init() {

        const json = document.getElementById('calibracion-data');

        if (!json) {
            return;
        }

        const data = JSON.parse(json.textContent);
        this.calibracion = data;
        this.calibracionOriginal = JSON.parse(JSON.stringify(data));

        if (Array.isArray(this.calibracion.tanques)) {

        this.calibracion.tanques.forEach(
            item => {

                item._original = {
                    resultado1: item.resultado1,
                    resultado2: item.resultado2
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
                url: '/sasisopa/control-actividades-procesos/calibracion-equipos/bitacora-calibracion-equipos-tanques-almacenamiento/update',
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

    async editarTanque(item, input, campo) {

        const valor = item[campo];
        const original = item._original[campo];

        if (valor === original) {
            return;
        }

        try {

            const response = await this.createAction({
                url: '/sasisopa/control-actividades-procesos/calibracion-equipos/bitacora-calibracion-equipos-tanques-almacenamiento/update',
                notify: false,
                data: {
                    id: item.id,
                    input,
                    valor
                }
            });

            if (!response.success) {
                item[campo] = original;
                return;
            }

            item._original[campo] = valor;

        } catch (e) {

            item[campo] = original;
        }
    },

    abrirModalResultados(item)
    {
        this.tanqueSeleccionado = item;
        this.archivoPdf = null;
        new bootstrap.Modal(document.getElementById('modalResultados')).show();
    },

    async subirResultados()
    {
        if (!this.archivoPdf) {
            this.notify('error','Seleccione un PDF');
            return;
        }

        try {

        const formData = new FormData();
        formData.append('id',this.tanqueSeleccionado.id);
        formData.append('documento',this.archivoPdf);

            const response =
                 await this.createAction({
                    url: '/sasisopa/control-actividades-procesos/calibracion-equipos/bitacora-calibracion-equipos-tanques-almacenamiento/upload-resultado',
                    data: formData,
                    isFile: true
            });
           
            if(response && response.success){

            this.tanqueSeleccionado.resultados = response.archivo;
            bootstrap.Modal.getInstance(document.getElementById('modalResultados')).hide();
                
            }

        } catch (e) {

            this.notify(
                'error',
                'Error al guardar'
            );
        }
    },

    async finalizar(equipo) {

            try {

                const response =
                    await this.createAction({
                        url: '/sasisopa/control-actividades-procesos/calibracion-equipos/bitacora-calibracion-equipos-tanques-almacenamiento/finalizar',
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