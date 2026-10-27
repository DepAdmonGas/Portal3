document.addEventListener('alpine:init', () => {

    Alpine.data('corteDiario', (initialStationId = null) => ({

        idEstacion: initialStationId ? String(initialStationId) : '',

        init() {

            if (typeof ModuleStationSelector !== 'undefined') {
                ModuleStationSelector.init('corte-diario', {
                    customReload: selector => {
                        const selectedStation = selector.getValue().id_estacion;
                        this.idEstacion = selectedStation ? String(selectedStation) : '';

                        const table = $('#table-corte-diario').DataTable();
                        if (selectedStation) {
                            table.ajax.reload(null, false);
                        } else {
                            table.clear().draw();
                        }
                    }
                });
            }

            this.inicializarEventos();
        },

        inicializarEventos() {

            const table = document.getElementById(
                'table-corte-diario'
            );

            table.addEventListener('click', event => {

                const activar = event.target.closest(
                    '.btn-activate'
                );

                if (activar) {

                    this.activarCorte(
                        activar.dataset.id
                    );

                    return;
                }


                const finalizar = event.target.closest(
                    '.btn-finalize'
                );

                if (finalizar) {

                    this.finalizarCorte(
                        finalizar.dataset.id
                    );
                }

            });
        },

        async activarCorte(id) {

            const confirmacion = await Swal.fire({
                title: 'Activar corte',
                text: '¿Estás seguro de que deseas activar este corte?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Sí, activar',
                cancelButtonText: 'Cancelar'
            });

            if (!confirmacion.isConfirmed) {
                return;
            }

            try {

                await this.createAction({
                    url: '/corte-diario/activate',

                    data: {
                        id: id
                    },

                    table: '#table-corte-diario'
                });

            } catch (e) {

                console.error(e);

                this.notify(
                    'error',
                    'No fue posible activar el corte'
                );
            }
        },

        async finalizarCorte(id) {

            const confirmacion = await Swal.fire({
                title: 'Finalizar corte',
                text: '¿Estás seguro de que deseas finalizar este corte?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Sí, finalizar',
                cancelButtonText: 'Cancelar'
            });

            if (!confirmacion.isConfirmed) {
                return;
            }

            try {

                await this.createAction({
                    url: '/corte-diario/finalize',

                    data: {
                        id: id
                    },

                    table: '#table-corte-diario'
                });

            } catch (e) {

                console.error(e);

                this.notify(
                    'error',
                    'No fue posible finalizar el corte'
                );
            }
        }

    }));

});
