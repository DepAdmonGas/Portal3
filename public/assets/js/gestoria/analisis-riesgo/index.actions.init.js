document.addEventListener('alpine:init', () => {

    Alpine.data('analisisRiesgo', (idEstacion) => ({

        idEstacion,

        form: {
            fecha: '',
            descripcion: ''
        },

        errors: {
            fecha: false,
            descripcion: false,
            documento: false,
            documentoMensaje: ''
        },

        documentoNombre: '',
        guardando: false,
        modalNuevo: null,

        modoFormulario: 'crear',
        idEditar: null,
        documentoActual: '',

        modalAnexos: null,

        idAnalisis: null,

        analisisSeleccionado: {
            id: null,
            fecha: '',
            fecha_formateada: '',
            descripcion: ''
        },

        anexos: [],

        anexoForm: {
            descripcion: ''
        },

        anexoErrors: {
            descripcion: false,
            documento: false,
            documentoMensaje: ''
        },

        anexoDocumentoNombre: '',

        guardandoAnexo: false,

        cargandoAnexos: false,

        init() {

            window.analisisRiesgo = this;

            this.modalNuevo = new bootstrap.Modal(
                document.getElementById('modalNuevo')
            );

            this.modalAnexos = new bootstrap.Modal(
                document.getElementById('modalAnexos')
            );

        },

        openNuevo() {

          this.modoFormulario = 'crear';
          this.idEditar = null;
          this.documentoActual = '';
          this.limpiarFormulario();
          this.modalNuevo.show();

        },

        limpiarFormulario() {

            this.form.fecha = '';
            this.form.descripcion = '';
            this.errors.fecha = false;
            this.errors.descripcion = false;
            this.errors.documento = false;
            this.errors.documentoMensaje = '';
            this.documentoNombre = '';
            this.documentoActual = '';
            this.guardando = false;
            if (this.$refs.documento) {
                this.$refs.documento.value = '';
            }

        },

        limpiarDocumento() {

            this.documentoNombre = '';
            this.errors.documento = false;
            this.errors.documentoMensaje = '';

            if (this.$refs.documento) {
                this.$refs.documento.value = '';
            }

        },

        validarDocumento() {

            const archivo = this.$refs.documento?.files[0];

            this.errors.documento = false;
            this.errors.documentoMensaje = '';
            this.documentoNombre = '';

            if (!archivo) {
                return;
            }

            if (archivo.type !== 'application/pdf') {

                this.errors.documento = true;
                this.errors.documentoMensaje =
                    'Solo se permiten archivos PDF.';
                this.$refs.documento.value = '';
                return;
            }

            const maxSize = 10 * 1024 * 1024;

            if (archivo.size > maxSize) {
                this.errors.documento = true;
                this.errors.documentoMensaje =
                    'El documento no puede superar los 10 MB.';
                this.$refs.documento.value = '';
                return;
            }

            this.documentoNombre = archivo.name;

        },

        validarFormulario() {

            let valido = true;

            this.errors.fecha = false;
            this.errors.descripcion = false;
            this.errors.documento = false;
            this.errors.documentoMensaje = '';

            if (!this.form.fecha) {

                this.errors.fecha = true;
                valido = false;

            }

            if (!this.form.descripcion.trim()) {

                this.errors.descripcion = true;
                valido = false;

            }

            const archivo =
                this.$refs.documento?.files[0];

            if (
                this.modoFormulario === 'crear' &&
                !archivo
            ) {

                this.errors.documento = true;

                this.errors.documentoMensaje =
                    'Debe seleccionar un documento PDF.';

                valido = false;

            }

            if (archivo) {

                if (
                    archivo.type !==
                    'application/pdf'
                ) {

                    this.errors.documento = true;

                    this.errors.documentoMensaje =
                        'Solo se permiten archivos PDF.';

                    valido = false;

                }

                const maxSize =
                    10 * 1024 * 1024;

                if (
                    archivo.size > maxSize
                ) {

                    this.errors.documento = true;

                    this.errors.documentoMensaje =
                        'El documento no puede superar los 10 MB.';

                    valido = false;

                }

            }

            return valido;

        },

        async guardar() {

            if (!this.validarFormulario()) {

                this.notify(
                    'error',
                    'Complete los campos obligatorios.'
                );

                return;

            }

            const archivo =
                this.$refs.documento?.files[0];

            const formData =
                new FormData();

            formData.append(
                'fecha',
                this.form.fecha
            );

            formData.append(
                'descripcion',
                this.form.descripcion.trim()
            );

            if (archivo) {

                formData.append(
                    'documento',
                    archivo
                );

            }

            let url = '';

            if (
                this.modoFormulario === 'crear'
            ) {

                url =
                    '/gestoria/analisis-riesgo/' +
                    this.idEstacion +
                    '/create';

            } else {

                url =
                    '/gestoria/analisis-riesgo/' +
                    this.idEstacion +
                    '/' +
                    this.idEditar +
                    '/update';

            }

            this.guardando = true;

            try {

                const res =
                    await this.createAction({

                        url: url,

                        data: formData,

                        table:
                            '#table-analisis-riesgo'

                    });

                if (res.success) {

                    this.modalNuevo.hide();

                    this.limpiarFormulario();

                    this.idEditar = null;

                    this.modoFormulario =
                        'crear';

                }

            } catch (error) {

                console.error(error);

                this.notify(
                    'error',

                    this.modoFormulario === 'crear'
                        ? 'No fue posible guardar el análisis de riesgo.'
                        : 'No fue posible actualizar el análisis de riesgo.'
                );

            } finally {

                this.guardando = false;

            }

        },

        async eliminar(id) {

            const res = await this.deleteAction({
                url:
                    '/gestoria/analisis-riesgo/' +
                    this.idEstacion +
                    '/delete',
                id: id,
                name: 'Analisis de Riesgo',
                table: '#table-analisis-riesgo'
            });

        },

        async openEditar(id) {

          this.limpiarFormulario();

          this.modoFormulario = 'editar';

          this.idEditar = Number(id);

          try {

              const response = await axios.get(
                  '/gestoria/analisis-riesgo/' +
                  this.idEstacion +
                  '/' +
                  this.idEditar +
                  '/data'
              );

              const data = response.data.data;

              this.form.fecha =
                  data.fecha ?? '';

              this.form.descripcion =
                  data.descripcion ?? '';

              this.documentoActual =
                  data.documento ?? '';

              this.documentoNombre =
                  data.documento ?? '';

              this.modalNuevo.show();

          } catch (error) {

              console.error(error);

              this.notify(
                  'error',
                  'No fue posible cargar el análisis de riesgo.'
              );

          }

        },

        //------------------------------------------------------------------------------------------

        async openAnexos(idAnalisis) {

        this.idAnalisis = Number(idAnalisis);

        this.limpiarFormularioAnexo();

        this.anexos = [];

        this.analisisSeleccionado = {
            id: null,
            fecha: '',
            fecha_formateada: '',
            descripcion: ''
        };

        this.modalAnexos.show();

        await this.cargarAnexos();

        },

      async cargarAnexos() {

        if (!this.idAnalisis) {
            return;
        }

        this.cargandoAnexos = true;

        try {

            const response = await axios.get(
                '/gestoria/analisis-riesgo/' +
                this.idEstacion +
                '/' +
                this.idAnalisis +
                '/anexos/data'
            );

            const data = response.data.data;

            this.analisisSeleccionado =
                data.analisis ?? {};

            this.anexos =
                data.anexos ?? [];

        } catch (error) {

            console.error(error);

            this.anexos = [];

            this.notify(
                'error',
                'No fue posible cargar los anexos.'
            );

        } finally {

            this.cargandoAnexos = false;

        }

      },

      validarAnexoDocumento() {

        const archivo =
            this.$refs.anexoDocumento?.files[0];

        this.anexoErrors.documento = false;

        this.anexoErrors.documentoMensaje = '';

        this.anexoDocumentoNombre = '';

        if (!archivo) {
            return;
        }

        if (archivo.type !== 'application/pdf') {

            this.anexoErrors.documento = true;

            this.anexoErrors.documentoMensaje =
                'Solo se permiten archivos PDF.';

            this.$refs.anexoDocumento.value = '';

            return;

        }

        const maxSize =
            10 * 1024 * 1024;

        if (archivo.size > maxSize) {

            this.anexoErrors.documento = true;

            this.anexoErrors.documentoMensaje =
                'El documento no puede superar los 10 MB.';

            this.$refs.anexoDocumento.value = '';

            return;

        }

        this.anexoDocumentoNombre =
            archivo.name;

      },

      validarFormularioAnexo() {

          let valido = true;

          this.anexoErrors.descripcion = false;

          this.anexoErrors.documento = false;

          this.anexoErrors.documentoMensaje = '';

          if (!this.anexoForm.descripcion.trim()) {

              this.anexoErrors.descripcion = true;

              valido = false;

          }

          const archivo =
              this.$refs.anexoDocumento?.files[0];

          if (!archivo) {

              this.anexoErrors.documento = true;

              this.anexoErrors.documentoMensaje =
                  'Debe seleccionar un documento PDF.';

              valido = false;

          } else if (
              archivo.type !== 'application/pdf'
          ) {

              this.anexoErrors.documento = true;

              this.anexoErrors.documentoMensaje =
                  'Solo se permiten archivos PDF.';

              valido = false;

          } else {

              const maxSize =
                  10 * 1024 * 1024;

              if (archivo.size > maxSize) {

                  this.anexoErrors.documento = true;

                  this.anexoErrors.documentoMensaje =
                      'El documento no puede superar los 10 MB.';

                  valido = false;

              }

          }

          return valido;

      },

      async guardarAnexo() {

          if (!this.validarFormularioAnexo()) {

              this.notify(
                  'error',
                  'Complete los campos obligatorios.'
              );

              return;

          }

          const archivo =
              this.$refs.anexoDocumento.files[0];

          const formData =
              new FormData();

          formData.append(
              'descripcion',
              this.anexoForm.descripcion.trim()
          );

          formData.append(
              'documento',
              archivo
          );

          this.guardandoAnexo = true;

          try {

              const res =
                  await this.createAction({

                      url:
                          '/gestoria/analisis-riesgo/' +
                          this.idEstacion +
                          '/' +
                          this.idAnalisis +
                          '/anexos/create',

                      data: formData

                  });

              if (res.success) {

                  this.limpiarFormularioAnexo();

                  await this.cargarAnexos();

              }

          } catch (error) {

              console.error(error);

              this.notify(
                  'error',
                  'No fue posible guardar el anexo.'
              );

          } finally {

              this.guardandoAnexo = false;

          }

      },

      limpiarFormularioAnexo() {

          this.anexoForm.descripcion = '';

          this.anexoErrors.descripcion = false;

          this.anexoErrors.documento = false;

          this.anexoErrors.documentoMensaje = '';

          this.anexoDocumentoNombre = '';

          this.guardandoAnexo = false;

          if (this.$refs.anexoDocumento) {

              this.$refs.anexoDocumento.value = '';

          }

      },

      limpiarAnexoDocumento() {

          this.anexoDocumentoNombre = '';

          this.anexoErrors.documento = false;

          this.anexoErrors.documentoMensaje = '';

          if (this.$refs.anexoDocumento) {

              this.$refs.anexoDocumento.value = '';

          }

      },

      async eliminarAnexo(id) {

          const confirmacion =
              await Swal.fire({

                  title: '¿Eliminar anexo?',

                  text:
                      'El documento será eliminado de forma permanente.',

                  icon: 'warning',

                  showCancelButton: true,

                  confirmButtonText:
                      'Sí, eliminar',

                  cancelButtonText:
                      'Cancelar'

              });

          if (!confirmacion.isConfirmed) {
              return;
          }

          try {

              const response =
                  await axios.post(

                      '/gestoria/analisis-riesgo/' +
                      this.idEstacion +
                      '/' +
                      this.idAnalisis +
                      '/anexos/delete',

                      {
                          id: id
                      }

                  );

              const res =
                  response.data;

              if (res.success) {

                  this.notify(
                      'success',
                      res.message ??
                          'Anexo eliminado correctamente.'
                  );

                  await this.cargarAnexos();

                  return;

              }

              this.notify(
                  'error',
                  res.message ??
                      'No fue posible eliminar el anexo.'
              );

          } catch (error) {

              console.error(error);

              this.notify(
                  'error',
                  'No fue posible eliminar el anexo.'
              );

          }

      },

    }));

});