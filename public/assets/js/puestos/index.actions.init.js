document.addEventListener('alpine:init', () => {

    Alpine.data('puestos', () => ({

        loading: false,

        modo: 'create',

        modal: null,


        form: {
            id: null,
            tipo_puesto: ''
        },


        errors: {
            tipo_puesto: false
        },


        /**
         * --------------------------------------------------
         * INIT
         * --------------------------------------------------
         */
        init() {

            const modalElement =
                document.getElementById(
                    'modalPuesto'
                );

            if (modalElement) {

                this.modal =
                    new bootstrap.Modal(
                        modalElement
                    );
            }


            this.$nextTick(() => {

                this.inicializarEventosTabla();

            });
        },


        /**
         * --------------------------------------------------
         * CREAR
         * --------------------------------------------------
         */
        openModalCrear() {

            this.modo = 'create';

            this.limpiarFormulario();

            this.modal?.show();
        },


        /**
         * --------------------------------------------------
         * EDITAR
         * --------------------------------------------------
         */
        async openModalEditar(id) {

            this.loading = true;

            try {

                const { data } =
                    await axios.get(
                        '/puestos/detail',
                        {
                            params: {
                                id: id
                            }
                        }
                    );


                if (!data.success) {

                    this.notify(
                        'error',
                        data.message
                    );

                    return;
                }


                this.modo = 'edit';

                this.limpiarErrores();


                this.form = {

                    id:
                        data.data.id,

                    tipo_puesto:
                        data.data.tipo_puesto ?? ''

                };


                this.modal?.show();

            } catch (error) {

                console.error(error);

                this.notify(
                    'error',
                    'No fue posible cargar el puesto'
                );

            } finally {

                this.loading = false;
            }
        },


        /**
         * --------------------------------------------------
         * LIMPIAR
         * --------------------------------------------------
         */
        limpiarFormulario() {

            this.form = {
                id: null,
                tipo_puesto: ''
            };

            this.limpiarErrores();
        },


        limpiarErrores() {

            this.errors.tipo_puesto =
                false;
        },


        /**
         * --------------------------------------------------
         * VALIDAR
         * --------------------------------------------------
         */
        validar() {

            this.limpiarErrores();

            if (
                !this.form.tipo_puesto
                || String(
                    this.form.tipo_puesto
                ).trim() === ''
            ) {

                this.errors.tipo_puesto =
                    true;

                return false;
            }

            return true;
        },


        /**
         * --------------------------------------------------
         * GUARDAR
         * --------------------------------------------------
         */
        async guardar() {

            if (!this.validar()) {

                this.notify(
                    'error',
                    'Ingresa el nombre del puesto'
                );

                return;
            }


            this.loading = true;


            try {

                const url =
                    this.modo === 'create'
                        ? '/puestos/create'
                        : '/puestos/update';


                const { data } =
                    await axios.post(
                        url,
                        this.form
                    );


                if (!data.success) {

                    this.notify(
                        data.type || 'error',
                        data.message
                    );

                    return;
                }


                this.notify(
                    'success',
                    data.message
                );


                this.modal?.hide();


                this.recargarTabla();

            } catch (error) {

                console.error(error);

                this.notify(
                    'error',
                    'No fue posible guardar el puesto'
                );

            } finally {

                this.loading = false;
            }
        },


        /**
         * --------------------------------------------------
         * CANCELAR PUESTO
         * --------------------------------------------------
         */
        async eliminar(
            id,
            nombre
        ) {

            const result =
                await Swal.fire({

                    title:
                        'Cancelar puesto',

                    html:
                        `
                            ¿Deseas cancelar el puesto
                            <strong>${this.escapeHtml(nombre)}</strong>?
                        `,

                    icon:
                        'warning',

                    showCancelButton:
                        true,

                    confirmButtonText:
                        'Sí, cancelar',

                    cancelButtonText:
                        'No',

                    reverseButtons:
                        true
                });


            if (!result.isConfirmed) {
                return;
            }


            this.loading = true;


            try {

                const { data } =
                    await axios.post(
                        '/puestos/delete',
                        {
                            id: id
                        }
                    );


                this.notify(
                    data.type
                        || (
                            data.success
                                ? 'success'
                                : 'error'
                        ),

                    data.message
                );


                if (data.success) {

                    this.recargarTabla();
                }

            } catch (error) {

                console.error(error);

                this.notify(
                    'error',
                    'No fue posible cancelar el puesto'
                );

            } finally {

                this.loading = false;
            }
        },


        /**
         * --------------------------------------------------
         * EVENTOS DATATABLE
         * --------------------------------------------------
         */
        inicializarEventosTabla() {

            const tabla =
                document.getElementById(
                    'table-puestos'
                );


            if (!tabla) {
                return;
            }


            tabla.addEventListener(
                'click',
                event => {

                    /**
                     * EDITAR
                     */
                    const editar =
                        event.target.closest(
                            '.btn-edit'
                        );


                    if (editar) {

                        event.preventDefault();


                        const id =
                            parseInt(
                                editar.dataset.id,
                                10
                            );


                        if (id > 0) {

                            this.openModalEditar(
                                id
                            );
                        }

                        return;
                    }


                    /**
                     * CANCELAR
                     */
                    const eliminar =
                        event.target.closest(
                            '.btn-delete'
                        );


                    if (eliminar) {

                        event.preventDefault();


                        if (
                            eliminar.classList.contains(
                                'disabled'
                            )
                        ) {

                            return;
                        }


                        const id =
                            parseInt(
                                eliminar.dataset.id,
                                10
                            );


                        if (id > 0) {

                            this.eliminar(
                                id,
                                eliminar.dataset.nombre
                                    || ''
                            );
                        }
                    }
                }
            );
        },


        /**
         * --------------------------------------------------
         * RECARGAR DATATABLE
         * --------------------------------------------------
         */
        recargarTabla() {

            if (
                $.fn.DataTable.isDataTable(
                    '#table-puestos'
                )
            ) {

                $('#table-puestos')
                    .DataTable()
                    .ajax
                    .reload(
                        null,
                        false
                    );
            }
        },


        /**
         * --------------------------------------------------
         * ESCAPE HTML
         * --------------------------------------------------
         */
        escapeHtml(value) {

            const element =
                document.createElement(
                    'div'
                );

            element.textContent =
                value ?? '';

            return element.innerHTML;
        }

    }));

});