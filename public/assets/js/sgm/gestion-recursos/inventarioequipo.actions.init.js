document.addEventListener('alpine:init', () => {
    Alpine.data('inventario', () => ({
     
     modo: 'create',
     modalNuevo: null,

     form:{
        id:null,
        nombre:'',
        identificacion:'',
        funcion:'',
        fecha_instalacion:'',
        manuales:[]
    },

    errors:{

        nombre:false,
        identificacion:false,
        funcion: false,

    },

    modalManuales: null,

    manuales: {
        nombre: '',
        identificacion: '',
        lista: []
    },

      init(){

          window.inventario = this;

          this.modalNuevo = new bootstrap.Modal(
              document.getElementById('modalNuevo')
          );

          this.modalManuales = new bootstrap.Modal(
              document.getElementById('modalManuales')
          );

      },

      openNuevo(){

          this.modo='create';

          this.resetForm();

          this.modalNuevo.show();

      },

      resetForm(){

        this.form={

            id:null,

            nombre:'',

            identificacion:'',

            funcion:'',

            fecha_instalacion:'',

            manuales:[]

        };

        this.resetErrors();

    },

    resetErrors(){

        Object.keys(this.errors).forEach(key=>{

            this.errors[key]=false;

        });

    },

    validaForm(){

        this.resetErrors();

        let valid=true;

        if(!this.form.nombre){

            this.errors.nombre=true;

            valid=false;

        }

        if(!this.form.identificacion){

            this.errors.identificacion=true;

            valid=false;

        }

        if(!this.form.funcion){

            this.errors.funcion=true;

            valid=false;

        }

        return valid;

    },

    async openEditar(id){

        this.resetForm();

        this.modo = 'edit';

        const res = await this.getAction({

            url: '/sgm/gestion-recursos/inventario-equipo/detalle/' + id

        });

        if(!res.success){
            return;
        }

        this.form = res.data;

        this.modalNuevo.show();

    },

    async guardarRegistro(){

        if(!this.validaForm()){

            this.notify(
                'error',
                'Completa los campos obligatorios.'
            );

            return;

        }

        if(this.modo=='create'){

            return await this.guardar();

        }

        await this.actualizar();

    },

    async guardar(){

    if(!this.validaForm()){
        return;
    }

    try{

        const res = await this.createAction({

            url:'/sgm/gestion-recursos/inventario-equipo/create',

            data:this.form,

            table:'#table-inventario-equipo'

        });

        if(res.success){

            this.modo = 'edit';
            await this.openEditar(res.id);
        }

    }catch(e){

        this.notify(
            'error',
            'Error al guardar.'
        );

    }

},

    async actualizar(){

      try{

          const res=await this.createAction({

              url:'/sgm/gestion-recursos/inventario-equipo/update',

              data:this.form,

              table:'#table-inventario-equipo'

          });

          if(res.success){

              this.modalNuevo.hide();

          }

      }catch(e){

          this.notify(
              'error',
              'Error al actualizar.'
          );

      }

  },

  async eliminar(id){

    await this.deleteAction({

        url:'/sgm/gestion-recursos/inventario-equipo/delete',
        id,
        name:'Equipo',
        table:'#table-inventario-equipo'

    });

},

async subirManual() {

    const archivo = this.$refs.manual.files[0];

    if (!archivo) {

        this.notify(
            'error',
            'Seleccione un archivo'
        );

        return;
    }

    const formData = new FormData();

    formData.append(
        'id_equipo',
        this.form.id
    );

    formData.append(
        'archivo',
        archivo
    );

    try {

        const res = await this.createAction({

            url: '/sgm/gestion-recursos/inventario-equipo/manual/create',

            data: formData

        });

        if (res.success) {

            await this.cargarManuales();

            this.$refs.manual.value = '';

        }

    } catch (e) {

        this.notify(
            'error',
            'No fue posible guardar el manual.'
        );

    }

},

async eliminarManual(id){

    const res = await this.deleteAction({

        url:'/sgm/gestion-recursos/inventario-equipo/manual/delete',

        id,

        name:'Manual'

    });

    if(res.success){

        await this.cargarManuales();

    }

},

async cargarManuales(){

    const res = await this.getAction({

        url:
            '/sgm/gestion-recursos/inventario-equipo/manuales/'
            + this.form.id

    });

    if(res.success){

        this.form.manuales = res.data;

    }

},

async openManuales(id){

    try{

        const res = await this.getAction({

            url:'/sgm/gestion-recursos/inventario-equipo/detalle/' + id

        });

        if(!res.success){
            return;
        }

        this.manuales.nombre = res.data.nombre;
        this.manuales.identificacion = res.data.identificacion;
        this.manuales.lista = res.data.manuales ?? [];

        this.modalManuales.show();

    }catch(e){

        this.notify(
            'error',
            'No fue posible cargar los manuales.'
        );

    }

}    
      
   }));
});