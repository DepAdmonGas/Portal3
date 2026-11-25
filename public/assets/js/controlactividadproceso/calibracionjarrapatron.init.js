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
    jarrasDisponibles: [],
    nuevaJarra: '',


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
                headers: { 'X-Portal3-Skip-Action-Loader': '1' },

                data: {
                    id: item.id,
                    input,
                    valor
                }
            });

        if (!response || !response.success) {

            item[campo] = original;

            this.notify(
                'error',
                (response && response.message) || 'Error al guardar'
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
    }
},

async cargarJarrasDisponibles() {
    try {
        const response = await axios.get(
            '/sasisopa/control-actividades-procesos/calibracion-equipos/bitacora-calibracion-equipos-jarra-patron/get-jarras',
            { params: { id_calibracion: this.calibracion.id } }
        );
        this.jarrasDisponibles = response.data?.success ? response.data.data : [];
    } catch (error) {
            this.notify('error', error.response?.data?.message || 'Error al cargar jarras disponibles');
    }
},

async abrirModalJarras() {
    await this.cargarJarrasDisponibles();
    bootstrap.Modal.getOrCreateInstance(document.getElementById('modalJarra')).show();
},

async agregarJarra() {
    if (!this.nuevaJarra) {
        this.notify('error', 'Seleccione una jarra patrón');
        return;
    }

    try {
        const response = await this.createAction({
            url: '/sasisopa/control-actividades-procesos/calibracion-equipos/bitacora-calibracion-equipos-jarra-patron/create',
            data: { id_calibracion: this.calibracion.id, id_jarra: this.nuevaJarra }
        });
        if (!response?.success) return;
        response.jarra._original = { resultado1: response.jarra.resultado1 };
        this.calibracion.jarras.push(response.jarra);
        this.jarrasDisponibles = this.jarrasDisponibles.filter(item => Number(item.id) !== Number(this.nuevaJarra));
        this.nuevaJarra = '';
        bootstrap.Modal.getInstance(document.getElementById('modalJarra'))?.hide();
    } catch (error) {
        this.notify('error', 'Error al agregar jarra patrón');
    }
},

async eliminarJarra(id) {
    try {
        const response = await this.deleteAction({
            url: '/sasisopa/control-actividades-procesos/calibracion-equipos/bitacora-calibracion-equipos-jarra-patron/delete',
            id,
            name: 'Jarra patrón'
        });
        if (response?.success) {
            this.calibracion.jarras = this.calibracion.jarras.filter(item => Number(item.id) !== Number(id));
            await this.cargarJarrasDisponibles();
        }
    } catch (error) {
        this.notify('error', 'Error al eliminar jarra patrón');
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

        if (!response || !response.success) {
            return;
        }

        this.calibracion.estado = 1;

        const fecha = String(this.calibracion.fecha_formateada || this.calibracion.fecha || '');
        const year = /^\d{4}/.exec(fecha)?.[0] || new Date().getFullYear();
        window.location.href = `/sasisopa/control-actividades-procesos/calibracion-equipos/bitacora-calibracion-equipos?year=${year}`;

    } catch (e) {

        this.notify(
            'error',
            'Error al finalizar'
        );
    }
},



    }));

});
