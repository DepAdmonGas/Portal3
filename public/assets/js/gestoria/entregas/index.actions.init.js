document.addEventListener('alpine:init', () => {
    Alpine.data('entregas', () => ({

      modalNuevo: null,

      selectedEstacion:'',
      destinatario: '',

      buscarEstacion: '',

      errors:{
        estaciones: false
      },


      init(){

           window.entregas = this;

        this.modalNuevo = new bootstrap.Modal(
              document.getElementById('modalNuevo')
          );

      },

      openNuevo() {

         this.limpiarModalNuevo();

        this.initModalSelect2({
            modalRef: 'modalNuevo',
            selectRef: 'selectEstacion',
            wrapperRef: 'estacionWrapper',
            model: 'selectedEstacion',
            namespace: 'estaciones',
            options: {
                placeholder: 'Seleccione estación',
                dropdownParent: $('#modalNuevo')
            }
        });

        this.modalNuevo.show();
    },


    limpiarModalNuevo() {

        this.destinatario = '';
        this.selectedEstaciones = '';
        this.selectedEstacion = '';

        this.errors.estaciones = false;

        // Limpiar Select2
        $(this.$refs.selectEstaciones)
            .val('')
            .trigger('change');
    },

    async guardar(){

        if (!this.selectedEstacion) {
          this.errors.estaciones = true;
            this.notify(
                'error',
                'Selecciona una estación de envio'
            );          
            return;
        }
        
        try{

            const res = await this.createAction({

                url:'/gestoria/entregas/create',

                data:{
                  destinatario : this.destinatario,
                  estacion : this.selectedEstacion
                },

                table:'#table-entregas'

            });
           

            if(res.success){

              window.location.href = '/gestoria/entregas/formulario/' + res.id;
              this.modalNuevo.hide();

            }

          }catch(e){

            this.notify(
                'error',
                'Error al guardar.'
            );

          }


    },

    async eliminar(id){
        
          const res = await this.deleteAction({
                  url: "/gestoria/entregas/delete",
                  id: id,
                  name: 'Entrega',
                  table: "#table-entregas"
              });

        },

      
    }));
});