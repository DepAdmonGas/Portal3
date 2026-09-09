document.addEventListener('alpine:init', () => {

    Alpine.data(
        'usuarios',
        (initialStationId = null) => ({

            loading: false,

            modo: 'create',

            modal: null,

            filtroEstacion:
                initialStationId
                    ? String(initialStationId)
                    : '',


            form: {
                id: null,

                nombre: '',

                email: '',

                telefono: '',

                usuario: '',

                password: '',

                id_gas: '',

                id_puesto: '',

                fecha_nacimiento: '',

                estado_civil: '',

                seguro_social: '',

                domicilio: '',

                fecha_ingreso: '',

                responsabilidad_sgm: ''
            },


            errors: {
                nombre: false,

                usuario: false,

                password: false,

                id_gas: false,

                id_puesto: false,

                fecha_nacimiento: false,

                fecha_ingreso: false,
            },


            init() {

                const modalElement =
                    document.getElementById(
                        'modalUsuario'
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
             * ----------------------------------------------------
             * FILTRO ESTACIÓN
             * ----------------------------------------------------
             */
            cambiarEstacion() {

                const url =
                    new URL(
                        window.location.href
                    );

                if (this.filtroEstacion) {

                    url.searchParams.set(
                        'idEstacion',
                        this.filtroEstacion
                    );

                } else {

                    url.searchParams.delete(
                        'idEstacion'
                    );
                }

                window.history.replaceState(
                    {},
                    '',
                    url
                );

                this.recargarTabla();
            },


            /**
             * ----------------------------------------------------
             * CREAR
             * ----------------------------------------------------
             */
            openModalCrear() {

                this.modo = 'create';

                this.limpiarFormulario();

                /**
                 * Si existe filtro activo,
                 * seleccionamos automáticamente
                 * la estación.
                 */
                if (this.filtroEstacion) {

                    this.form.id_gas =
                        String(
                            this.filtroEstacion
                        );
                }

                this.modal?.show();
            },


            /**
             * ----------------------------------------------------
             * EDITAR
             * ----------------------------------------------------
             */
            async openModalEditar(id) {

                this.loading = true;

                try {

                    const { data } =
                        await axios.get(
                            '/usuarios/detail',
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

                        nombre:
                            data.data.nombre ?? '',

                        email:
                            data.data.email ?? '',

                        telefono:
                            data.data.telefono ?? '',

                        usuario:
                            data.data.usuario ?? '',

                        /**
                         * Nunca regresamos el password
                         * almacenado.
                         */
                        password:
                            '',

                        id_gas:
                            data.data.id_gas
                                ? String(
                                    data.data.id_gas
                                )
                                : '',

                        id_puesto:
                            data.data.id_puesto
                                ? String(
                                    data.data.id_puesto
                                )
                                : '',

                        fecha_nacimiento:
                            this.formatearFecha(
                                data.data.fecha_nacimiento
                            ),

                        estado_civil:
                            data.data.estado_civil ?? '',

                        seguro_social:
                            data.data.seguro_social ?? '',

                        domicilio:
                            data.data.domicilio ?? '',

                        fecha_ingreso:
                            this.formatearFecha(
                                data.data.fecha_ingreso
                            ),

                        responsabilidad_sgm:
                            data.data.responsabilidad_sgm ?? ''
                    };

                    this.modal?.show();

                } catch (error) {

                    console.error(error);

                    this.notify(
                        'error',
                        'No fue posible cargar el usuario'
                    );

                } finally {

                    this.loading = false;
                }
            },


            /**
             * ----------------------------------------------------
             * LIMPIAR FORMULARIO
             * ----------------------------------------------------
             */
            limpiarFormulario() {

                this.form = {
                    id: null,

                    nombre: '',

                    email: '',

                    telefono: '',

                    usuario: '',

                    password: '',

                    id_gas: '',

                    id_puesto: '',

                    fecha_nacimiento: '',

                    estado_civil: '',

                    seguro_social: '',

                    domicilio: '',

                    fecha_ingreso: '',

                    responsabilidad_sgm: ''
                };

                this.limpiarErrores();
            },


            limpiarErrores() {

                Object.keys(
                    this.errors
                ).forEach(
                    campo => {

                        this.errors[campo] =
                            false;
                    }
                );
            },


            /**
             * ----------------------------------------------------
             * VALIDACIÓN FRONTEND
             * ----------------------------------------------------
             */
            validar() {

                this.limpiarErrores();

                let valido = true;

                const requeridos = [
                    'nombre',
                    'usuario',
                    'id_gas',
                    'id_puesto',
                    'fecha_nacimiento',
                    'fecha_ingreso'
                ];

                requeridos.forEach(
                    campo => {

                        const valor =
                            this.form[campo];

                        if (
                            valor === null
                            || valor === undefined
                            || String(valor).trim() === ''
                        ) {

                            this.errors[campo] =
                                true;

                            valido = false;
                        }
                    }
                );

                /**
                 * Password obligatorio al crear.
                 */
                if (
                    this.modo === 'create'
                    && String(
                        this.form.password || ''
                    ).trim() === ''
                ) {

                    this.errors.password =
                        true;

                    valido = false;
                }

                 return valido;
            },


            validarEmail(email) {

                return /^[^\s@]+@[^\s@]+\.[^\s@]+$/
                    .test(email);
            },


            /**
             * ----------------------------------------------------
             * GUARDAR
             * ----------------------------------------------------
             */
            async guardar() {

                if (!this.validar()) {

                    this.notify(
                        'error',
                        'Completa correctamente los campos obligatorios'
                    );

                    return;
                }

                this.loading = true;

                try {

                    const url =
                        this.modo === 'create'
                            ? '/usuarios/create'
                            : '/usuarios/update';

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
                        'No fue posible guardar el usuario'
                    );

                } finally {

                    this.loading = false;
                }
            },


            /**
             * ----------------------------------------------------
             * ELIMINAR
             * ----------------------------------------------------
             */
            async eliminar(
                id,
                nombre
            ) {

                const result =
                    await Swal.fire({

                        title:
                            'Eliminar usuario',

                        html:
                            `¿Deseas eliminar a <strong>${this.escapeHtml(nombre)}</strong>?`,

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

                this.loading = true;

                try {

                    const { data } =
                        await axios.post(
                            '/usuarios/delete',
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
                        'No fue posible eliminar el usuario'
                    );

                } finally {

                    this.loading = false;
                }
            },


            /**
             * ----------------------------------------------------
             * EVENTOS DEL DATATABLE
             * ----------------------------------------------------
             */
            inicializarEventosTabla() {

                const tabla =
                    document.getElementById(
                        'table-usuarios'
                    );

                if (!tabla) {
                    return;
                }

                tabla.addEventListener(
                    'click',
                    event => {

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

                                this.openModalEditar(id);
                            }

                            return;
                        }


                        const eliminar =
                            event.target.closest(
                                '.btn-delete'
                            );

                        if (eliminar) {

                            event.preventDefault();

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
             * ----------------------------------------------------
             * DATATABLE
             * ----------------------------------------------------
             */
            recargarTabla() {

                if (
                    $.fn.DataTable
                        .isDataTable(
                            '#table-usuarios'
                        )
                ) {

                    $('#table-usuarios')
                        .DataTable()
                        .ajax
                        .reload(
                            null,
                            false
                        );
                }
            },


            /**
             * ----------------------------------------------------
             * UTILIDADES
             * ----------------------------------------------------
             */
            formatearFecha(fecha) {

                if (!fecha) {
                    return '';
                }

                return String(fecha)
                    .substring(
                        0,
                        10
                    );
            },


            escapeHtml(value) {

                const element =
                    document.createElement(
                        'div'
                    );

                element.textContent =
                    value ?? '';

                return element.innerHTML;
            }

        })
    );
});