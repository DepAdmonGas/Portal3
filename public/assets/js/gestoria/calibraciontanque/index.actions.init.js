document.addEventListener('alpine:init', () => {

    Alpine.data(
        'calibracionTanques',
        (idEstacion) => ({

            idEstacion:
                Number(idEstacion),

            guardando:
                false,

            init() {

                window.calibracionTanques =
                    this;

            },

            async crear() {

                if (this.guardando) {

                    return;

                }

                const result =
                    await Swal.fire({

                        title:
                            '¿Crear calibración?',

                        text:
                            'Se creará un nuevo registro de calibración de tanques.',

                        icon:
                            'question',

                        showCancelButton:
                            true,

                        confirmButtonText:
                            'Sí, crear',

                        cancelButtonText:
                            'Cancelar',

                        reverseButtons:
                            true

                    });


                if (!result.isConfirmed) {

                    return;

                }

                try {

                    this.guardando =
                        true;


                    const res =
                        await this.createAction({

                            url:
                                '/gestoria/calibracion-tanques/' +
                                this.idEstacion +
                                '/create',

                            data: {},

                            table:
                                '#table-calibracion-tanques'

                        });

                    if (
                        res.success &&
                        res.id
                    ) {

                        window.location.href =
                            '/gestoria/calibracion-tanques/' +
                            this.idEstacion +
                            '/editar/' +
                            res.id;

                    }

                } catch (e) {

                    console.error(
                        'Error al crear calibración:',
                        e
                    );


                    this.notify(
                        'error',
                        'No fue posible crear la calibración.'
                    );

                } finally {

                    this.guardando =
                        false;

                }

            },

            editar(id) {

                id =
                    Number(id);


                if (!id) {

                    this.notify(
                        'error',
                        'El registro no es válido.'
                    );

                    return;

                }


                window.location.href =
                    '/gestoria/calibracion-tanques/' +
                    this.idEstacion +
                    '/editar/' +
                    id;

            },

            async eliminar(id) {

                id =
                    Number(id);


                if (!id) {

                    this.notify(
                        'error',
                        'El registro no es válido.'
                    );

                    return;

                }

                const result =
                    await Swal.fire({

                        title:
                            '¿Eliminar calibración?',

                        text:
                            'Esta acción eliminará el registro de calibración.',

                        icon:
                            'warning',

                        showCancelButton:
                            true,

                        confirmButtonText:
                            'Sí, eliminar',

                        cancelButtonText:
                            'Cancelar',

                        reverseButtons:
                            true

                    });


                if (!result.isConfirmed) {

                    return;

                }

                try {

                    await this.createAction({

                        url:
                            '/gestoria/calibracion-tanques/' +
                            this.idEstacion +
                            '/delete/' +
                            id,

                        data: {},

                        table:
                            '#table-calibracion-tanques'

                    });

                } catch (e) {

                    console.error(
                        'Error al eliminar calibración:',
                        e
                    );


                    this.notify(
                        'error',
                        'No fue posible eliminar la calibración.'
                    );

                }

            }

        }))

});