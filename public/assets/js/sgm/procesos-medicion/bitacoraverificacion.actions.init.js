document.addEventListener('alpine:init', () => {
    Alpine.data('bitacoraVerificacion', () => ({

       modal: null,

        programa: {
          equipo: {}
        },

        bitacora: {},

        resultados: [],

        detalles: [],

        init() {

          window.bitacoraVerificacion = this;

            this.modal = new bootstrap.Modal(
                document.getElementById('modalDetalleBitacora')
            );

        },

        async abrir(id) {


            const respuesta = await fetch(
                `/sgm/procesos-medicion/bitacora-verificacion-equipo-medicion/detalle/${id}`
            );

             const json = await respuesta.json();
            
            this.programa = json.programa;

            this.bitacora = json.bitacora;

            this.resultados = json.categorias ?? [];

            this.detalles = json.detalles ?? [];

            this.modal.show();

        }

    }));
});