document.addEventListener('alpine:init', () => {

    Alpine.data('modulos', () => ({

        temas: window.temas ?? [],

        detalle: {
            modulo: '',
            tema: '',
            calendarios: []
        },

        modal: null,

        init() {

            this.modal = new bootstrap.Modal(
                document.getElementById('detalleTemaModal')
            );

        },

        async verDetalle(id) {

            const response = await fetch(
                `/sasisopa/cursos/modulos/temas/${id}`
            );

            const json = await response.json();

            if (!json.success) {
                return;
            }

            this.detalle = json;

            this.modal.show();

        }

    }));

});