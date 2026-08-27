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

        },

        irSasisopa() {

            if (!this.estacionId) {
                window.location.href = '/sasisopa';
                return;
            }

            axios.post('/api/module-context/set', {
                module_key: 'sasisopa',
                id_estacion: this.estacionId,
                id_depto: null
            }).then(() => {
                window.location.href = '/sasisopa';
            }).catch(() => {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'No se pudo seleccionar la estación. Intente de nuevo.',
                        timer: 3000,
                        showConfirmButton: false
                    });
                }
            });

        }

    }));

});