document.addEventListener('alpine:init', () => {
Alpine.data('calibracionVerificacion', () => ({

    equipos: [],
    calendario: [],

    loadingEquipos: false,
    loadingCalendario: false,

    init() {

        this.buscar();
        this.obtenerCalendario();

    },

    async buscar() {

        this.loadingEquipos = true;

        try {

            const { data } = await axios.get(
                '/sasisopa/monitoreo-verificacion-evaluacion/calibracion-verificacion-mantenimiento-equipos/get-equipos-calibracion'
            );

            if (data.success) {

                this.equipos = data.data;

            }

        } catch (error) {

            console.error(error);

        } finally {

            this.loadingEquipos = false;

        }

    },

    async obtenerCalendario() {

        this.loadingCalendario = true;

        try {

            const { data } = await axios.get(
                '/sasisopa/monitoreo-verificacion-evaluacion/calibracion-verificacion-mantenimiento-equipos/get-calendario-calibracion'
            );

            if (data.success) {

                this.calendario = data.data;

            }

        } catch (error) {

            console.error(error);

        } finally {

            this.loadingCalendario = false;

        }

    }

    }));
});