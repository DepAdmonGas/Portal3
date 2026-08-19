document.addEventListener('alpine:init', () => {
    Alpine.data('capacitacion', () => ({

      buscar:{

          year:new Date().getFullYear()

      },

      errors:{

          year:false

      },

      modalBuscar:null,

      init(){

        this.modalBuscar = new bootstrap.Modal(
            document.getElementById('modalBuscar')
        );

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
              '/sgm/gestion-recursos/programa-capacitacion-interna/datatable/'
              + this.buscar.year
          ).load();

          this.modalBuscar.hide();

      },

   }));
});