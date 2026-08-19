document.addEventListener('alpine:init', () => {

    Alpine.data('index', () => ({

        estacionId: null,

        estacionNombre: null,

        offcanvas: null,

        init() {

            this.offcanvas = new bootstrap.Offcanvas(
                this.$refs.offcanvas
            );

        },

        handleClick(event) {

            const button = event.target.closest(
                '.btn-menu-estacion'
            );

            if (!button) {
                return;
            }

            this.estacionId = button.dataset.estacionId;

            this.estacionNombre = button.dataset.estacionNombre;

            this.offcanvas.show();

        }

    }));

});