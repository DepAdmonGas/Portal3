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

        },

        irConsultaSasisopa(){
          if (!this.estacionId) {
                window.location.href = '/sasisopa/consulta';
                return;
            }

            axios.post('/api/module-context/set', {
                module_key: 'sasisopa',
                id_estacion: this.estacionId,
                id_depto: null
            }).then(() => {
                window.location.href = '/sasisopa/consulta';
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
        },

        irConfiguracionBitacora(){
          if (!this.estacionId) {
                window.location.href = '/sasisopa/control-actividades-procesos/configuracion-bitacora';
                return;
            }

            axios.post('/api/module-context/set', {
                module_key: 'sasisopa',
                id_estacion: this.estacionId,
                id_depto: null
            }).then(() => {
                window.location.href = '/sasisopa/control-actividades-procesos/configuracion-bitacora';
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
        },

        irRequisitosLegales(){
          if (!this.estacionId) {
                window.location.href = '/sasisopa/requisitos-legales';
                return;
            }

            axios.post('/api/module-context/set', {
                module_key: 'sasisopa',
                id_estacion: this.estacionId,
                id_depto: null
            }).then(() => {
                window.location.href = '/sasisopa/requisitos-legales';
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
        },

        irProgramaAnual(){
          if (!this.estacionId) {
                window.location.href = '/sasisopa/control-actividades-procesos/programa-anual-mantenimiento';
                return;
            }

            axios.post('/api/module-context/set', {
                module_key: 'sasisopa',
                id_estacion: this.estacionId,
                id_depto: null
            }).then(() => {
                window.location.href = '/sasisopa/control-actividades-procesos/programa-anual-mantenimiento';
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
        },

        irSGM(){
          if (!this.estacionId) {
                window.location.href = '/sgm';
                return;
            }

            axios.post('/api/module-context/set', {
                module_key: 'sasisopa',
                id_estacion: this.estacionId,
                id_depto: null
            }).then(() => {
                window.location.href = '/sgm';
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
        },


        irPersonal(){
          if (!this.estacionId) {
                window.location.href = '/personal';
                return;
            }

            axios.post('/api/module-context/set', {
                module_key: 'sasisopa',
                id_estacion: this.estacionId,
                id_depto: null
            }).then(() => {
                window.location.href = '/personal';
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
        },

    }));

});