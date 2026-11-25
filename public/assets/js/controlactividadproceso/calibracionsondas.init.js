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
        sondasDisponibles: [],
        nuevaSonda: '',

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

async editarSonda(item, input, campo) {

    const valor = item[campo];
    const original = item._original?.[campo];

    if (valor === original) return;

    try {

        const response = await this.createAction({
            url: '/sasisopa/control-actividades-procesos/calibracion-equipos/bitacora-calibracion-equipos-sonda/update',
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
            this.notify('error', (response && response.message) || 'Error al guardar');
            return;
        }

        item._original[campo] = valor;

    } catch (e) {

        item[campo] = original;
        this.notify('error', 'Error al guardar');
    }
},

    async cargarSondasDisponibles() {
        try {
            const response = await axios.get(
                '/sasisopa/control-actividades-procesos/calibracion-equipos/bitacora-calibracion-equipos-sonda/get-sondas',
                { params: { id_calibracion: this.calibracion.id } }
            );
            this.sondasDisponibles = response.data?.success ? response.data.data : [];
        } catch (error) {
            this.notify('error', error.response?.data?.message || 'Error al cargar sondas disponibles');
        }
    },

    async abrirModalSondas() {
        await this.cargarSondasDisponibles();
        bootstrap.Modal.getOrCreateInstance(document.getElementById('modalSonda')).show();
    },

    async agregarSonda() {
        if (!this.nuevaSonda) {
            this.notify('error', 'Seleccione una sonda');
            return;
        }

        try {
            const response = await this.createAction({
                url: '/sasisopa/control-actividades-procesos/calibracion-equipos/bitacora-calibracion-equipos-sonda/create',
                data: { id_calibracion: this.calibracion.id, id_sonda: this.nuevaSonda }
            });
            if (!response?.success) return;
            response.sonda._original = { resultado1: response.sonda.resultado1 };
            this.calibracion.sondas.push(response.sonda);
            this.sondasDisponibles = this.sondasDisponibles.filter(item => Number(item.id) !== Number(this.nuevaSonda));
            this.nuevaSonda = '';
            bootstrap.Modal.getInstance(document.getElementById('modalSonda'))?.hide();
        } catch (error) {
            this.notify('error', 'Error al agregar sonda');
        }
    },

    async eliminarSonda(id) {
        try {
            const response = await this.deleteAction({
                url: '/sasisopa/control-actividades-procesos/calibracion-equipos/bitacora-calibracion-equipos-sonda/delete',
                id,
                name: 'Sonda'
            });
            if (response?.success) {
                this.calibracion.sondas = this.calibracion.sondas.filter(item => Number(item.id) !== Number(id));
                await this.cargarSondasDisponibles();
            }
        } catch (error) {
            this.notify('error', 'Error al eliminar sonda');
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

                if (!response || !response.success) {
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
