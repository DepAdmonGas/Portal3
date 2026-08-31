document.addEventListener('alpine:init', () => {
    Alpine.data('capacitacion', () => ({

      modo: 'create',
      selectedUsuarios:[],
      
      form:{
          id:null,
          nombre_curso:'',
          fecha_programada:'',
          duracion:'',
          instructor:'',
          fecha_real:'',
          usuarios: [],
          personal:[]
      },

       buscar:{

          year:new Date().getFullYear()

      },

      errors:{

          year: false,
          nombre_curso: false,
          fecha_programada: false,
          duracion: false,
          instructor : false

      },

      modalNuevo:null,
      modalBuscar:null,
     

      init(){

          window.capacitacion = this;

          if (!document.getElementById('sgm-content')) {
              return;
          }

          this.modalNuevo = new bootstrap.Modal(
              document.getElementById('modalNuevo')
          );

          this.modalBuscar = new bootstrap.Modal(
              document.getElementById('modalBuscar')
          );

          this.bindModalSelect2({

              modalRef:'modalNuevo',

              selectRef:'selectPersonal',

              wrapperRef:'personalWrapper',

              model:'selectedUsuarios',

              namespace:'capacitacion',

              options:{
                  placeholder:'Seleccione personal',
                  multiple:true,
                  closeOnSelect:false
              }

          });

      },

      openNuevo(){

          this.resetForm();
          this.modo = 'create';
          this.modalNuevo.show();

      },

      guardarRegistro(){

          if(this.modo=='create'){
              this.guardar();
          }else{
              this.actualizar();
          }

      },

      async guardar(){

        if (!this.validaForm()) {
                this.notify(
                    'error',
                    'Completa todos los campos obligatorios'
                );
                return;
            }

            

            try {

            url = '/sgm/gestion-recursos/programa-capacitacion-externa/create';

            payload = {
                capacitacion: 'Externa',
                nombre_curso: this.form.nombre_curso,
                fecha_programada: this.form.fecha_programada,
                duracion: this.form.duracion,
                instructor: this.form.instructor,
            };

            const res = await this.createAction({
                url,
                data: payload,
                table: '#table-capacitacion-externa'
            });

            if(res.success){
              this.modalNuevo.hide();
            }

        } catch (error) {
            this.notify('error', 'Error al guardar');
        }

      },

      validaForm() {

            this.resetErrors();

            let valid = true;

            if (!this.form.nombre_curso) {
                this.errors.nombre_curso = true;
                valid = false;
            }

            if (!this.form.fecha_programada) {
                this.errors.fecha_programada = true;
                valid = false;
            }

            if (!this.form.duracion) {
                this.errors.duracion = true;
                valid = false;
            }

            if (!this.form.instructor) {
                this.errors.instructor = true;
                valid = false;
            }

            return valid;

        },

        resetForm(){

          this.form = {

              id:null,

              nombre_curso:'',

              fecha_programada:'',

              duracion:'',

              instructor:'',

              fecha_real:'',

              usuarios:[],

              personal:[],

              evidencias:[]

          };

          this.selectedUsuarios=[];

          this.resetErrors();

      },

        resetErrors() {

            Object.keys(this.errors).forEach(key => {
                this.errors[key] = false;
            });

        },

        async cargarDetalle(id){

        const res = await fetch(
            '/sgm/gestion-recursos/programa-capacitacion-externa/detalle/' + id
        );

        this.form = await res.json();

        this.selectedUsuarios = [];

        this.$nextTick(() => {

            this.initModalSelect2({

                modalRef:'modalNuevo',

                selectRef:'selectPersonal',

                wrapperRef:'personalWrapper',

                model:'selectedUsuarios',

                namespace:'capacitacion',

                options:{
                    placeholder:'Seleccione personal',
                    multiple:true,
                    closeOnSelect:false
                }

            });

        });

    },

      async editar(id){

          try{

              this.resetForm();

              this.modo = 'edit';

              await this.cargarDetalle(id);

              this.modalNuevo.show();

          }catch(e){

              this.notify(
                  'error',
                  'No fue posible cargar la información'
              );

          }

      },

        async actualizar(){

              if(!this.validaForm()){
                  return;
              }

              const res = await this.createAction({

                  url: '/sgm/gestion-recursos/programa-capacitacion-externa/update',

                  data: this.form,

                  table:'#table-capacitacion-externa'

              });

              if(res.success){

                  this.modalNuevo.hide();

              }

          },

        async eliminar(id){

            const res = await this.deleteAction({
              url: "/sgm/gestion-recursos/programa-capacitacion-externa/delete",
              id,
              name: 'Capacitación',
              table: '#table-capacitacion-externa'
          });

        },

      openBuscar(){

          this.buscar.year = new Date().getFullYear();
          this.errors.year = false;
          this.modalBuscar.show();

      },

      buscarProgramacion(){

          this.errors.year = false;

          if(!this.buscar.year){

              this.errors.year = true;

              return;

          }

          table1.ajax.url(
              '/sgm/gestion-recursos/programa-capacitacion-externa/datatable/'
              + this.buscar.year
          ).load();

          this.modalBuscar.hide();

      },

      async guardarPersonal() {

    try {

       const usuarios = this.selectedUsuarios;

        if (!usuarios || usuarios.length === 0) {

            this.notify(
                'error',
                'Selecciona al menos un usuario'
            );

            return;

        }

        const res = await this.createAction({

            url: '/sgm/gestion-recursos/programa-capacitacion-externa/personal/create',

            data: {
                id_capacitacion: this.form.id,
                usuarios: usuarios
            },

            table: '#table-capacitacion-externa'

        });

        if (res.success) {

            // Recargar información del registro
           await this.cargarDetalle(this.form.id);

            // Limpiar selección
         this.selectedUsuarios = [];

          $(this.$refs.selectPersonal)
              .val(null)
              .trigger('change');
          }

    } catch (error) {

        this.notify(
            'error',
            'Error al agregar personal'
        );

    }

},

  async eliminarPersonal(id){

     const res = await this.deleteAction({
              url: "/sgm/gestion-recursos/programa-capacitacion-externa/personal/delete",
              id,
              name: id,
              table: '#table-capacitacion-externa'
          });

    if (res.success) {
    await this.cargarDetalle(this.form.id);   
    }
  },

  async guardarEvidencia() {

    const archivo = document
        .getElementById('FileEvidencia')
        .files[0];

    if (!archivo) {

        this.notify(
            'error',
            'Seleccione un archivo'
        );

        return;
    }

    const formData = new FormData();

    formData.append(
        'id_capacitacion',
        this.form.id
    );

    formData.append(
        'archivo',
        archivo
    );

    try {

        const res = await this.createAction({
        url: `/sgm/gestion-recursos/programa-capacitacion-externa/evidencia/create`,
        data: formData
    });

           if (res.success) {

            await this.cargarDetalle(this.form.id);

            document.getElementById('FileEvidencia').value = '';

        }

    } catch (e) {

        this.notify(
            'error',
            'No fue posible guardar la evidencia'
        );

    }

},

async eliminarEvidencia(id){

    const res = await this.deleteAction({

        url: '/sgm/gestion-recursos/programa-capacitacion-externa/evidencia/delete',

        id,

        name: 'evidencia'

    });

    if(res.success){

        await this.cargarDetalle(
            this.form.id
        );

    }

}

      
   }));
});