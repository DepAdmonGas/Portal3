document.addEventListener('alpine:init', () => {

    Alpine.data('modulos', () => ({

        temas: (() => {
            const element = document.getElementById('modulos-data');
            if (!element) return [];
            try { return JSON.parse(element.textContent || '[]'); } catch (_) { return []; }
        })(),

        detalle: {
            modulo: '',
            tema: '',
            calendarios: []
        },

        modal: null,

        init() {

            if (!document.getElementById('detalleTemaModal')) {
                return;
            }

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
