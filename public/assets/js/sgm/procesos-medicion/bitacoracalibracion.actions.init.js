document.addEventListener('alpine:init', () => {
    Alpine.data('bitacoraCalibracion', () => ({

       modal: null,

        bitacora: {},

        detalles: [],

        init() {

          window.bitacoraCalibracion = this;

            this.modal = new bootstrap.Modal(
                document.getElementById('modalDetalleBitacora')
            );

        },

        async abrir(id) {


            const response = await fetch(
                `/sgm/procesos-medicion/bitacora-calibracion-equipos/detalle/${id}`
            );

            const json = await response.json();

            this.bitacora = json.bitacora;

            this.detalles = json.bitacora.detalles ?? [];

            this.modal.show();

        }

    }));
});