document.addEventListener('alpine:init', () => {

    Alpine.data(
        'calibracionTanquesDetalle',(idEstacion,idReporte) => ({
            idEstacion:
                Number(idEstacion),

            idReporte:
                Number(idReporte),

            cargando:
                false,

            guardando:
                false,

            finalizando:
                false,

            reporte:
                null,

            documentos:
                [],

            documentoSeleccionado:
                null,

            modalDocumentos:
                null,

            documentoNombre:
                '',

            errors: {

                documento:
                    false,

                documentoMensaje:
                    '',

                fecha:
                    false,

            },

            form: {

                fecha:
                    ''

            },

            init() {

                window.calibracionTanquesDetalle =
                    this;


                const modal =
                    document.getElementById(
                        'modalDocumentos'
                    );


                if (modal) {

                    this.modalDocumentos =
                        new bootstrap.Modal(
                            modal
                        );

                }


                this.cargarData();

            },

      async cargarData(actualizarFecha = true) {

          try {

              this.cargando = true;


              const response =
                  await axios.get(
                      '/gestoria/calibracion-tanques/' +
                      this.idEstacion +
                      '/editar/' +
                      this.idReporte +
                      '/data'
                  );


              const data =
                  response.data?.data
                  ?? null;


              if (!data) {

                  this.reporte = null;

                  this.documentos = [];

                  return;

              }


              this.reporte =
                  data.reporte
                  ?? null;


              this.documentos =
                  data.documentos
                  ?? [];

              if (actualizarFecha) {

                  this.form.fecha =
                      this.reporte?.fecha
                      ?? '';

              }

              if (
                  this.documentoSeleccionado
              ) {

                  const idDocumento =
                      Number(
                          this.documentoSeleccionado.id
                      );


                  this.documentoSeleccionado =
                      this.documentos.find(
                          documento =>
                              Number(documento.id)
                              === idDocumento
                      )
                      ?? null;

              }

          } catch (e) {

              console.error(
                  'Error al cargar calibración:',
                  e
              );


              this.notify(
                  'error',
                  'No fue posible cargar la información.'
              );

          } finally {

              this.cargando = false;

          }

      },

            abrirDocumentos(documento) {

                this.documentoSeleccionado =
                    documento;


                this.limpiarDocumento();


                this.modalDocumentos
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


                /*
                |--------------------------------------------------------------------------
                | PDF
                |--------------------------------------------------------------------------
                */

                const esPdf =
                    archivo.type
                        === 'application/pdf'
                    ||
                    archivo.name
                        .toLowerCase()
                        .endsWith('.pdf');


                if (!esPdf) {

                    this.errors.documento =
                        true;

                    this.errors.documentoMensaje =
                        'El archivo debe ser un PDF.';


                    input.value =
                        '';

                    return false;

                }


                /*
                |--------------------------------------------------------------------------
                | 10 MB
                |--------------------------------------------------------------------------
                */

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
            'Selecciona un tipo de documento.'
        );

        return;

    }


    if (this.guardando) {

        return;

    }


    const archivo =
        this.$refs
            .documento
            ?.files?.[0];


    if (!archivo) {

        this.errors.documento = true;

        this.errors.documentoMensaje =
            'Selecciona un archivo PDF.';

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


        this.guardando = true;


        const formData =
            new FormData();


        formData.append(
            'idDocumento',
            this.documentoSeleccionado.id
        );


        formData.append(
            'documento',
            archivo
        );


        const response =
            await axios.post(

                '/gestoria/calibracion-tanques/' +
                this.idEstacion +
                '/editar/' +
                this.idReporte +
                '/documentos/create',

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

        await this.cargarData(
            false
        );

    }

},


            async eliminarDocumento(id) {

    id =
        Number(id);


    if (!id) {

        this.notify(
            'error',
            'El documento no es válido.'
        );

        return;

    }

    const result =
        await Swal.fire({

            title:
                '¿Eliminar documento?',

            text:
                'El archivo se eliminará de forma permanente.',

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

        const res =
            await this.createAction({

                url:
                    '/gestoria/calibracion-tanques/' +
                    this.idEstacion +
                    '/editar/' +
                    this.idReporte +
                    '/documentos/eliminar/' +
                    id,

                data:
                    {}

            });


        if (
            res.success
        ) {

            await this.cargarData(
                false
            );

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

    }

},

            validarFecha() {

                this.errors.fecha =
                    false;


                if (
                    !this.form.fecha
                ) {

                    this.errors.fecha =
                        true;

                    this.notify(
                        'error',
                        'Selecciona la fecha.'
                    );

                    return false;

                }


                return true;

            },

            async finalizar() {

                if (
                    !this.validarFecha()
                ) {

                    return;

                }


                const result =
                    await Swal.fire({

                        title:
                            '¿Finalizar calibración?',

                        text:
                            'Se guardará la fecha seleccionada y se finalizará la captura.',

                        icon:
                            'question',

                        showCancelButton:
                            true,

                        confirmButtonText:
                            'Sí, finalizar',

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

                    this.finalizando =
                        true;


                    const res =
                        await this.createAction({

                            url:
                                '/gestoria/calibracion-tanques/' +
                                this.idEstacion +
                                '/editar/' +
                                this.idReporte +
                                '/finalizar',

                            data: {

                                fecha:
                                    this.form.fecha

                            }

                        });


                    if (
                        res.success
                    ) {

                        window.location.href =
                            '/gestoria/calibracion-tanques/'
                            + this.idEstacion;

                    }

                } catch (e) {

                    console.error(
                        'Error al finalizar calibración:',
                        e
                    );


                    this.notify(
                        'error',
                        'No fue posible finalizar la calibración.'
                    );

                } finally {

                    this.finalizando =
                        false;

                }

            }

        }))

});