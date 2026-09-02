document.addEventListener('alpine:init', () => {

    Alpine.data(
        'controlDocumentalSgm',
        (idEstacion) => ({

            idEstacion:
                Number(idEstacion),

            cargando:
                false,

            guardando:
                false,

            eliminando:
                false,

            estacion:
                null,

            seccion3:
                [],

            seccion1:
                [],

            seccion2:
                [],

            documentoSeleccionado:
                null,

            documentoNombre:
                '',

            modalDocumento:
                null,

            errors: {

                documento:
                    false,

                documentoMensaje:
                    '',

            },

            init() {

                window.controlDocumentalSgm =
                    this;


                const modal =
                    document.getElementById(
                        'modalDocumentoSgm'
                    );


                if (modal) {

                    this.modalDocumento =
                        new bootstrap.Modal(
                            modal
                        );

                }


                this.cargarData();

            },

            async cargarData() {

                try {

                    this.cargando =
                        true;


                    const response =
                        await axios.get(

                            '/gestoria/control-documental-sgm/' +
                            this.idEstacion +
                            '/data'

                        );


                    const data =
                        response.data?.data
                        ?? null;


                    if (!data) {

                        this.estacion =
                            null;

                        this.seccion3 =
                            [];

                        this.seccion1 =
                            [];

                        this.seccion2 =
                            [];

                        return;

                    }


                    this.estacion =
                        data.estacion
                        ?? null;


                    this.seccion3 =
                        data.seccion3
                        ?? [];


                    this.seccion1 =
                        data.seccion1
                        ?? [];


                    this.seccion2 =
                        data.seccion2
                        ?? [];

                    if (
                        this.documentoSeleccionado
                    ) {

                        const idDocumento =
                            Number(
                                this.documentoSeleccionado.id
                            );


                        this.documentoSeleccionado =
                            this.buscarDocumento(
                                idDocumento
                            );

                    }

                } catch (e) {

                    console.error(
                        'Error al cargar control documental:',
                        e
                    );


                    this.notify(
                        'error',
                        'No fue posible cargar el control documental.'
                    );

                } finally {

                    this.cargando =
                        false;

                }

            },

            buscarDocumento(id) {

                id =
                    Number(id);


                const documentos = [

                    ...this.seccion3,

                    ...this.seccion1,

                    ...this.seccion2,

                ];


                return documentos.find(
                    documento =>
                        Number(
                            documento.id
                        ) === id
                )
                ?? null;

            },

            abrirDocumento(documento) {

                this.documentoSeleccionado =
                    documento;


                this.limpiarDocumento();


                this.modalDocumento
                    ?.show();

            },

            limpiarDocumento() {

                this.documentoNombre =
                    '';


                this.errors.documento =
                    false;


                this.errors.documentoMensaje =
                    '';


                if (
                    this.$refs.documento
                ) {

                    this.$refs.documento.value =
                        '';

                }

            },

            validarDocumento() {

                const input =
                    this.$refs.documento;


                const archivo =
                    input?.files?.[0];


                this.errors.documento =
                    false;


                this.errors.documentoMensaje =
                    '';


                this.documentoNombre =
                    '';

                if (!archivo) {

                    return false;

                }

                const maxSize =
                    10 * 1024 * 1024;


                if (
                    archivo.size
                    > maxSize
                ) {

                    this.errors.documento =
                        true;


                    this.errors.documentoMensaje =
                        'El archivo no puede superar los 10 MB.';


                    input.value =
                        '';


                    return false;

                }

                this.documentoNombre =
                    archivo.name;


                return true;

            },

            async guardarDocumento() {

                if (
                    !this.documentoSeleccionado
                ) {

                    this.notify(
                        'error',
                        'El documento no es válido.'
                    );

                    return;

                }

                if (
                    this.guardando
                ) {

                    return;

                }

                const archivo =
                    this.$refs
                        .documento
                        ?.files?.[0];


                if (!archivo) {

                    this.errors.documento =
                        true;


                    this.errors.documentoMensaje =
                        'Selecciona un archivo.';


                    return;

                }

                if (
                    !this.validarDocumento()
                ) {

                    return;

                }


                let guardado =
                    false;


                try {

                    this.guardando =
                        true;

                    const formData =
                        new FormData();


                    formData.append(
                        'documento',
                        archivo
                    );

                    const response =
                        await axios.post(

                            '/gestoria/control-documental-sgm/' +
                            this.idEstacion +
                            '/documentos/' +
                            this.documentoSeleccionado.id +
                            '/guardar',

                            formData

                        );

                    if (
                        response.data?.success
                    ) {

                        guardado =
                            true;


                        this.notify(
                            'success',
                            response.data?.message
                            ?? 'Documento guardado correctamente.'
                        );


                        this.limpiarDocumento();

                    } else {

                        this.notify(
                            'error',
                            response.data?.message
                            ?? 'No fue posible guardar el documento.'
                        );

                    }

                } catch (e) {

                    console.error(
                        'Error al guardar documento:',
                        e
                    );


                    this.notify(
                        'error',
                        e.response?.data?.message
                        ?? 'No fue posible guardar el documento.'
                    );

                } finally {

                    this.guardando =
                        false;

                }

                if (guardado) {

                    await this.cargarData();

                }

            },

            async eliminarDocumento(id) {

                id =
                    Number(id);


                if (!id) {

                    this.notify(
                        'error',
                        'El archivo no es válido.'
                    );

                    return;

                }

                const result =
                    await Swal.fire({

                        title:
                            '¿Eliminar documento?',

                        text:
                            'El archivo será eliminado de forma permanente.',

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


                if (
                    !result.isConfirmed
                ) {

                    return;

                }


                try {

                    this.eliminando =
                        true;


                    const res =
                        await this.createAction({

                            url:
                                '/gestoria/control-documental-sgm/' +
                                this.idEstacion +
                                '/archivos/' +
                                id +
                                '/eliminar',

                            data:
                                {}

                        });


                    if (
                        res.success
                    ) {

                        await this.cargarData();

                    }

                } catch (e) {

                    console.error(
                        'Error al eliminar documento:',
                        e
                    );


                    this.notify(
                        'error',
                        'No fue posible eliminar el documento.'
                    );

                } finally {

                    this.eliminando =
                        false;

                }

            }

        }))

});