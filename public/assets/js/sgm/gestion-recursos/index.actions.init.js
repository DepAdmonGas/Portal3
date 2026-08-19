document.addEventListener('alpine:init', () => {
    Alpine.data('gestionRecursos', () => ({

      lista:[],
      form:{

      fecha:'',
      responsable:'',
      auxiliar:''

      },

      modal: null,

      errors: {
          fecha: false,
          responsable: false,
          auxiliar: false
      },

      init(){

        this.modal = new bootstrap.Modal(document.getElementById('modalNuevo'));

        this.cargar();

      },

      async cargar(){


      let response = await fetch(
      '/sgm/gestion-recursos/responsable/table'
      );


      let json = await response.json();


      this.lista=json.data;


      },

      openNuevo() {
          this.resetForm();
          this.modal.show();
      },

    resetForm() {

        this.form = {
            fecha: '',
            responsable: '',
            auxiliar: ''
        };

        this.errors = {
            fecha: false,
            responsable: false,
            auxiliar: false
        };

    },

      async guardar() {

        if (!this.validar()) {
           this.notify('error', 'Completa los campos obligatorios');
            return;
        }


        const res = await this.createAction({
        url: '/sgm/gestion-recursos/responsable/create',
        data: this.form
        });

        if (res?.success) {
            this.modal.hide();
            this.cargar();
        }

    },

    validar() {

        this.errors = {
            fecha: false,
            responsable: false,
            auxiliar: false
        };

        let valido = true;

        if (!this.form.fecha) {
            this.errors.fecha = true;
            valido = false;
        }

        if (!this.form.responsable) {
            this.errors.responsable = true;
            valido = false;
        }

        if (!this.form.auxiliar) {
            this.errors.auxiliar = true;
            valido = false;
        }

        return valido;
    },



      async eliminar(id){

        const res = await this.deleteAction({
                url: '/sgm/gestion-recursos/responsable/delete',
                id: id,
                name: 'Responsable'
            });

      if (res?.success) {
            this.modal.hide();
            this.cargar();
        }
      }


    }));
});