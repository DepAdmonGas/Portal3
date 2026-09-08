document.addEventListener('alpine:init', () => {

    Alpine.data('estaciones', () => ({

        loading: false,

        modo: 'create',

        modal: null,

        table: null,

        form: {
            id: null,
            nombre: '',
            es: '',
            permisocre: '',
            razonsocial: '',
            rfc: '',
            direccioncompleta: '',
            di_estado: '',
            di_municipio: '',
            apoderado_legal: '',
            franquicia: '',
            producto_uno: '',
            producto_dos: '',
            producto_tres: '',
            sasisopa: '',
            fecha_autorizacion: '',
            organigrama: '',
            volumetrico: '',
            distmax: '',
            noregistro_generador: '',
            categoria: '',
        },

        errors: {
            nombre: false,
            permisocre: false,
            razonsocial: false,
            rfc: false,
            direccioncompleta: false,
            di_estado: false,
            di_municipio: false,
            apoderado_legal: false,
            fecha_autorizacion: false,
            distmax: false
        },


        init() {

            this.modal = new bootstrap.Modal(
                document.getElementById('modalEstacion')
            );

            this.$nextTick(() => {

                this.inicializarEventosTabla();

            });

        },


        //----------------------------------------------------------
        // Modal
        //----------------------------------------------------------

        openModalCrear() {

            this.modo = 'create';

            this.limpiarFormulario();

            this.modal.show();

        },


        async openModalEditar(id) {

            this.loading = true;

            try {

                const { data } = await axios.get(
                    `/estaciones/${id}`
                );


                if (!data.success) {

                    this.notify(
                        'error',
                        data.message || 'No fue posible cargar la estación'
                    );

                    return;
                }


                this.modo = 'edit';

                this.asignarFormulario(
                    data.data
                );

                this.modal.show();

            } catch (error) {

                console.error(error);

                this.notify(
                    'error',
                    'No fue posible cargar la estación'
                );

            } finally {

                this.loading = false;

            }

        },


        asignarFormulario(item) {

            this.limpiarErrores();

            this.form = {

                id: item.id,

                nombre:
                    item.nombre ?? '',

                es:
                    item.es ?? '',

                permisocre:
                    item.permisocre ?? '',

                razonsocial:
                    item.razonsocial ?? '',

                rfc:
                    item.rfc ?? '',

                direccioncompleta:
                    item.direccioncompleta ?? '',

                di_estado:
                    item.di_estado ?? '',

                di_municipio:
                    item.di_municipio ?? '',

                apoderado_legal:
                    item.apoderado_legal ?? '',

                franquicia:
                    item.franquicia ?? '',

                producto_uno:
                    item.producto_uno ?? '',

                producto_dos:
                    item.producto_dos ?? '',

                producto_tres:
                    item.producto_tres ?? '',

                sasisopa:
                    item.sasisopa ?? '',

                fecha_autorizacion:
                    this.formatearFecha(
                        item.fecha_autorizacion
                    ),

                organigrama:
                    item.organigrama ?? '',

                volumetrico:
                    item.volumetrico ?? '',

                noregistro_generador:
                    item.noregistro_generador ?? '',

                categoria: item.categoria ?? '',

                distmax:
                    item.distmax ?? ''

            };

        },


        limpiarFormulario() {

            this.form = {

                id: null,

                nombre: '',

                es: '',

                permisocre: '',

                razonsocial: '',

                rfc: '',

                direccioncompleta: '',

                di_estado: '',

                di_municipio: '',

                apoderado_legal: '',

                franquicia: '',

                producto_uno: '',

                producto_dos: '',

                producto_tres: '',

                sasisopa: '',

                fecha_autorizacion: '',

                organigrama: '',

                volumetrico: '',

                distmax: '',

                noregistro_generador: '',
                categoria: '',

            };

            this.limpiarErrores();

        },


        //----------------------------------------------------------
        // Validación
        //----------------------------------------------------------

        limpiarErrores() {

            Object.keys(
                this.errors
            ).forEach(key => {

                this.errors[key] = false;

            });

        },


        validar() {

            this.limpiarErrores();

            let valido = true;


            const requeridos = [

                'nombre',

                'permisocre',

                'razonsocial',

                'rfc',

                'direccioncompleta',

                'di_estado',

                'di_municipio',

                'apoderado_legal',

                'fecha_autorizacion',

                'distmax'

            ];


            requeridos.forEach(campo => {

                const valor =
                    this.form[campo];

                if (
                    valor === null
                    || valor === undefined
                    || String(valor).trim() === ''
                ) {

                    this.errors[campo] = true;

                    valido = false;

                }

            });


            return valido;

        },


        //----------------------------------------------------------
        // Guardar
        //----------------------------------------------------------

        async guardar() {

            if (!this.validar()) {

                this.notify(
                    'error',
                    'Completa los campos obligatorios'
                );

                return;
            }


            this.loading = true;


            try {

                const url =
                    this.modo === 'create'
                        ? '/estaciones/create'
                        : '/estaciones/update';


                const { data } =
                    await axios.post(
                        url,
                        this.form
                    );


                if (!data.success) {

                    this.notify(
                        data.type || 'error',
                        data.message || 'No fue posible guardar la estación'
                    );

                    return;
                }


                this.notify(
                    'success',
                    data.message
                );


                this.modal.hide();

                this.recargarTabla();


            } catch (error) {

                console.error(error);

                this.notify(
                    'error',
                    'No fue posible guardar la estación'
                );

            } finally {

                this.loading = false;

            }

        },


        //----------------------------------------------------------
        // Eliminar / baja lógica
        //----------------------------------------------------------

        async eliminar(id, nombre) {

            const result =
                await Swal.fire({

                    title: 'Cancelar estación',

                    html:
                        `¿Deseas cancelar la estación <strong>${this.escapeHtml(nombre)}</strong>?`,

                    icon: 'warning',

                    showCancelButton: true,

                    confirmButtonText: 'Sí, cancelar',

                    cancelButtonText: 'No',

                    reverseButtons: true

                });


            if (!result.isConfirmed) {
                return;
            }


            this.loading = true;


            try {

                const { data } =
                    await axios.post(
                        '/estaciones/delete',
                        {
                            id: id
                        }
                    );


                this.notify(
                    data.type || (
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
                    'No fue posible cancelar la estación'
                );

            } finally {

                this.loading = false;

            }

        },


        //----------------------------------------------------------
        // DataTables
        //----------------------------------------------------------

        inicializarEventosTabla() {

            const tabla =
                document.getElementById(
                    'table-estaciones'
                );


            if (!tabla) {
                return;
            }


            tabla.addEventListener(
                'click',
                (event) => {

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

                        this.openModalEditar(id);

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

                        const nombre =
                            eliminar.dataset.nombre || '';

                        this.eliminar(
                            id,
                            nombre
                        );

                    }

                }
            );

        },


        recargarTabla() {

            const tabla =
                $('#table-estaciones')
                    .DataTable();


            tabla.ajax.reload(
                null,
                false
            );

        },


        //----------------------------------------------------------
        // Utilidades
        //----------------------------------------------------------

        formatearFecha(fecha) {

            if (!fecha) {
                return '';
            }


            if (
                typeof fecha === 'string'
            ) {

                return fecha.substring(
                    0,
                    10
                );

            }


            return '';

        },


        escapeHtml(value) {

            const element =
                document.createElement('div');

            element.textContent =
                value ?? '';

            return element.innerHTML;

        }

    }));

});