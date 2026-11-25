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
        tanquesDisponibles: [],
        nuevoTanque: '',

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
                headers: { 'X-Portal3-Skip-Action-Loader': '1' },
                data: {
                    id: this.calibracion.id,
                    input,
                    valor
                }
            });

            if (!response || !response.success) {

                this.calibracion[campo] = original;

                this.notify('error', (response && response.message) || 'Error al guardar');
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
                headers: { 'X-Portal3-Skip-Action-Loader': '1' },
                data: {
                    id: item.id,
                    input,
                    valor
                }
            });

            if (!response || !response.success) {
                item[campo] = original;
                return;
            }

            item._original[campo] = valor;

        } catch (e) {

            item[campo] = original;
        }
    },

    async cargarTanquesDisponibles() {
        try {
            const response = await axios.get(
                '/sasisopa/control-actividades-procesos/calibracion-equipos/bitacora-calibracion-equipos-tanques-almacenamiento/get-tanques',
                { params: { id_calibracion: this.calibracion.id } }
            );
            this.tanquesDisponibles = response.data?.success ? response.data.data : [];
        } catch (error) {
            this.notify('error', error.response?.data?.message || 'Error al cargar tanques disponibles');
        }
    },

    async abrirModalTanques() {
        await this.cargarTanquesDisponibles();
        bootstrap.Modal.getOrCreateInstance(document.getElementById('modalTanque')).show();
    },

    async agregarTanque() {
        if (!this.nuevoTanque) {
            this.notify('error', 'Seleccione un tanque');
            return;
        }

        try {
            const response = await this.createAction({
                url: '/sasisopa/control-actividades-procesos/calibracion-equipos/bitacora-calibracion-equipos-tanques-almacenamiento/create',
                data: { id_calibracion: this.calibracion.id, id_tanque: this.nuevoTanque }
            });

            if (!response?.success) return;
            response.tanque._original = { resultado1: response.tanque.resultado1, resultado2: response.tanque.resultado2 };
            this.calibracion.tanques.push(response.tanque);
            this.tanquesDisponibles = this.tanquesDisponibles.filter(item => Number(item.id) !== Number(this.nuevoTanque));
            this.nuevoTanque = '';
            bootstrap.Modal.getInstance(document.getElementById('modalTanque'))?.hide();
        } catch (error) {
            this.notify('error', 'Error al agregar tanque');
        }
    },

    async eliminarTanque(id) {
        try {
            const response = await this.deleteAction({
                url: '/sasisopa/control-actividades-procesos/calibracion-equipos/bitacora-calibracion-equipos-tanques-almacenamiento/delete',
                id,
                name: 'Tanque'
            });
            if (response?.success) {
                this.calibracion.tanques = this.calibracion.tanques.filter(item => Number(item.id) !== Number(id));
                await this.cargarTanquesDisponibles();
            }
        } catch (error) {
            this.notify('error', 'Error al eliminar tanque');
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
                const fecha = String(this.calibracion.fecha_formateada || this.calibracion.fecha || '');
                const year = /^\d{4}/.exec(fecha)?.[0] || new Date().getFullYear();
                window.location.href = `/sasisopa/control-actividades-procesos/calibracion-equipos/bitacora-calibracion-equipos?year=${year}`;

            } catch (e) {

                this.notify('error','Error al finalizar');

            }
    },

    }));

});
