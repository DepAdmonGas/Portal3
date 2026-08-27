document.addEventListener('alpine:init', () => {

    Alpine.data('entregas', (idEntrega) => ({

        idEntrega,

        entrega: {
            id: null,
            fecha: '',
            destinatario: '',
            estacion: '',
            recibe: '',
            estatus: 0
        },

        estaciones: [],
        documentos: [],

        modalDocumento: null,

        selectedEstacion: '',
        selectEstacionDocumento: '',
        documento: '',
        fechaOficio: '',
        originalCopia: '',

        errors: {
          fecha: false,
          destinatario: false,
            estacion: false,
            documento: false,
            fechaOficio: false,
            originalCopia: false,
            acuse: false,
            recibe: false
        },

        idDocumento: '',
        nombreDocumento: '',
        archivo: '',

        modalAcuse: null,

        idDocumentoAcuse: null,

        acusePreview: '',

        async init() {

            this.modalDocumento = new bootstrap.Modal(
                document.getElementById('modalDocumento')
            );

            this.modalAcuse = new bootstrap.Modal(
                document.getElementById('modalAcuse')
            );

            await this.data();

            this.$nextTick(() => {
                this.initSelect2Body();
                this.initSelect2Documento();
            });

        },

        async data() {

                const response = await axios.get(
                    `/gestoria/entregas/formulario/${this.idEntrega}/data`
                );

                const data = response.data;

                this.entrega = data.entrega;
                this.estaciones = data.estaciones ?? [];
                this.documentos = data.documentos ?? [];

        },

        initSelect2Body() {

            const select = $(this.$refs.selectEstacionBody);

            select.select2({
                width: '100%',
                placeholder: 'Selecciona una estación',
                allowClear: true
            });

            select.on('change', () => {

                this.entrega.estacion = select.val();

            });

            if (this.entrega.estacion) {

                select
                    .val(this.entrega.estacion)
                    .trigger('change');

            }

        },

        initSelect2Documento() {

            const select = $(this.$refs.selectEstacionDocumento);

            select.select2({
                width: '100%',
                placeholder: 'Selecciona una estación',
                allowClear: true,
                dropdownParent: $('#modalDocumento')
            });

            select.on('change', () => {

                this.selectedEstacion = select.val();

                this.errors.estacion = false;

            });

        },

        openDocumento() {

            this.limpiarDocumento();

            this.modalDocumento.show();

        },

        limpiarDocumento() {

            this.selectedEstacion = '';
            this.documento = '';
            this.fechaOficio = '';
            this.originalCopia = '';

            this.errors = {
                estacion: false,
                documento: false,
                fechaOficio: false,
                originalCopia: false
            };


            $(this.$refs.selectEstacionDocumento)
                .val('')
                .trigger('change');

        },

        validarDocumento() {

            let valido = true;

            if (!this.documento.trim()) {

                this.errors.documento = true;

                valido = false;

            }


            if (!this.fechaOficio) {

                this.errors.fechaOficio = true;

                valido = false;

            }


            if (!this.originalCopia) {

                this.errors.originalCopia = true;

                valido = false;

            }


            return valido;

        },

        async agregarDocumento() {

            if (!this.validarDocumento()) {

                this.notify(
                    'error',
                    'Completa los campos requeridos.'
                );

                return;

            }

            try {

                const res = await this.createAction({

                    url: '/gestoria/entregas/formulario/' + this.idEntrega + '/create/documento',

                    data: {

                        id_entrega: this.idEntrega,
                        id_estacion: this.selectedEstacion,
                        documento: this.documento,
                        fecha: this.fechaOficio,
                        detalle: this.originalCopia

                    }

                });

                if (res.success) {

                    this.modalDocumento.hide();

                    await this.data();

                }

            } catch (error) {

                this.notify(
                    'error',
                    'No fue posible agregar el documento.'
                );

            }

        },

        openAcuse(idDocumento, documento, archivo){
          this.limpiarAcuse();
          this.idDocumento = idDocumento;
          this.nombreDocumento = documento;
          if (archivo) {
              this.acusePreview =
                  '/uploads/archivos/entregas/' + archivo;
          }
          this.modalAcuse.show();
        },

        async agregarAcuse() {

        const archivo = this.$refs.acuse.files[0];

        if (!archivo) {

            this.errors.acuse = true;

            this.notify(
                'error',
                'Seleccione una imagen.'
            );

            return;

        }

      const tiposPermitidos = [
          'image/jpeg',
          'image/png',
          'image/gif',
          'image/webp'
      ];


      if (!tiposPermitidos.includes(archivo.type)) {

          this.errors.acuse = true;

          this.notify(
              'error',
              'Solo se permiten imágenes JPG, PNG, GIF o WEBP.'
          );

          return;

      }


      this.errors.acuse = false;

      const formData = new FormData();

      formData.append(
          'id',
          this.idDocumento
      );

      formData.append(
          'acuse',
          archivo
      );


    try {

        const res = await this.createAction({

            url: '/gestoria/entregas/formulario/' + this.idEntrega + '/create/acuse',

            data: formData

        });


        if (res.success) {

            this.acusePreview = res.url;
            await this.data();
            this.$refs.acuse.value = '';

        }


    } catch (e) {

        this.notify(
            'error',
            'No fue posible guardar el acuse.'
        );

    }

        },

        limpiarAcuse() {

          this.idDocumentoAcuse = null;

          this.acusePreview = '';

          this.errors.acuse = false;

          if (this.$refs.acuse) {

              this.$refs.acuse.value = '';

          }

        },

        previsualizarAcuse() {

          const archivo = this.$refs.acuse.files[0];

          if (!archivo) {

              this.acusePreview = '';

              return;

          }


          if (!archivo.type.startsWith('image/')) {

              this.errors.acuse = true;

              this.acusePreview = '';

              return;

          }


          this.errors.acuse = false;

          this.acusePreview = URL.createObjectURL(archivo);

        },

        async eliminar(id){

           const res = await this.deleteAction({
                  url: "/gestoria/entregas/formulario/" + this.idEntrega + "/delete/documento",
                  id: id,
                  name: 'Documento'
              });

          if (res.success) {
          await this.data();
          }

        },

        async finalizar(){

          if (!this.validarFormulario()) {

                this.notify(
                    'error',
                    'Completa los campos requeridos.'
                );

                return;

            }
        
        try{

            const res = await this.createAction({

                url:'/gestoria/entregas/formulario/' + this.idEntrega + '/finalizar/formulario',

                data:{
                  fecha : this.entrega.fecha,
                  destinatario : this.entrega.destinatario,
                  estacion : this.entrega.estacion
                }

            });
           

            if(res.success){

             window.history.back();

            }

          }catch(e){

            this.notify(
                'error',
                'Error al finalizar.'
            );

          }
        },

        validarFormulario() {

            let valido = true;


            if (!this.entrega.fecha) {

                this.errors.fecha = true;

                valido = false;

            }


            if (!this.entrega.destinatario) {

                this.errors.destinatario = true;

                valido = false;

            }


            return valido;

        },

        async finalizarEntrega(){

          if (!this.entrega.recibe) {

            this.errors.recibe = true;

            this.notify(
                'error',
                'Completa los campos requeridos.'
            );

            return;

        }

        try{

            const res = await this.createAction({

                url:'/gestoria/entregas/formulario/' + this.idEntrega + '/finalizar/entrega',

                data:{
                  nombre : this.entrega.recibe
                }

            });
           

            if(res.success){

             window.history.back();

            }

          }catch(e){

            this.notify(
                'error',
                'Error al finalizar entrega.'
            );

          }

        }


    }));

});