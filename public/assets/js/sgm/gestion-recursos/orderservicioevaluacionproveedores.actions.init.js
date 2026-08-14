document.addEventListener('alpine:init', () => {
    Alpine.data('evaluacion', () => ({

      ordenes: [],
      modo: 'create',
      modalNuevo: null,

      form:{
          id:null,
          descripcion:'',
          justificacion:'',
          folio:null,
          fecha:'',
          hora:''
      },

      errors:{
          descripcion:false,
          justificacion:false
      },

      modalDetalle:null,

      detalle:{

          fecha:'',
          hora:'',
          solicitante:'',
          puesto:'',
          razon_social:'',
          rfc:'',
          direccion:'',
          descripcion:'',
          justificacion:''

      },

      evaluacion:{},

      preguntas:[
      {
          campo:'respuesta_1',
          texto:'El trabajo fue ejecutado conforme a lo solicitado'
      },
      {
          campo:'respuesta_2',
          texto:'Se verificó que el proveedor contara con procedimientos para ejecutar los trabajos'
      },
      {
          campo:'respuesta_3',
          texto:'Mientras el personal permaneció en las instalaciones utilizó EPP'
      },
      {
          campo:'respuesta_4',
          texto:'Los trabajos ejecutados tomaron en cuenta los procedimientos de seguridad'
      },
      {
          campo:'respuesta_5',
          texto:'Al culminar el trabajo se encuentra a entera satisfacción'
      }
      ],

      usuarios:[],
      modalEvaluacion:null,
      modalDetalleEvaluacion:null,

      init(){

        this.cargarOrdenes();

        this.modalNuevo = new bootstrap.Modal(
        document.getElementById('modalNuevo')
        );

        this.modalDetalle = new bootstrap.Modal(
        document.getElementById('modalDetalle')
        );

        this.modalEvaluacion = new bootstrap.Modal(
            document.getElementById('modalEvaluacion')
        );

        this.modalDetalleEvaluacion = new bootstrap.Modal(
            document.getElementById('modalDetalleEvaluacion')
        );

      },

    async cargarOrdenes(){

        const res = await this.getAction({

            url:'/sgm/gestion-recursos/orden-servicio-evaluacion-proveedores/data'

        });

        if(res.success){

            this.ordenes = res.data;

        }

    },

    openNuevo(){

        this.resetForm();
        this.modo='create';
        this.modalNuevo.show();
    },

    resetForm(){

    this.form={
        id:null,
        folio:null,
        descripcion:'',
        justificacion:''
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

        if(!this.form.descripcion){
            this.errors.descripcion=true;
            valid=false;
        }

        if(!this.form.justificacion){
            this.errors.justificacion=true;
            valid=false;
        }

        return valid;

    },

    async openEditar(id){

    this.resetForm();

    this.modo='edit';

    const res=await this.getAction({

        url:'/sgm/gestion-recursos/orden-servicio-evaluacion-proveedores/detalle/'+id

    });

    if(!res.success){

        return;

    }

    this.form=res.data;

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

    const res=await this.createAction({

        url:'/sgm/gestion-recursos/orden-servicio-evaluacion-proveedores/create',

        data:this.form

    });

    if(res.success){

        this.modalNuevo.hide();

        this.cargarOrdenes();

    }

    },  

    async actualizar(){

      const res=await this.createAction({

          url:'/sgm/gestion-recursos/orden-servicio-evaluacion-proveedores/update',

          data:this.form

      });

      if(res.success){

          this.modalNuevo.hide();

          this.cargarOrdenes();

      }

    },

    async eliminar(id){

        const res = await this.deleteAction({
            url:'/sgm/gestion-recursos/orden-servicio-evaluacion-proveedores/delete',
            id,
            name:'Orden'
        });

        if(res.success){
            await this.cargarOrdenes();
        }

    },

    async detalleOrden(id){

    const res = await this.getAction({

        url:'/sgm/gestion-recursos/orden-servicio-evaluacion-proveedores/detalle-completo/' + id

    });

    if(!res.success){

        return;

    }

    this.detalle = res.data;

    this.modalDetalle.show();

    },

    async openEvaluacion(id){

    const res = await this.getAction({

        url:'/sgm/gestion-recursos/orden-servicio-evaluacion-proveedores/evaluacion/detalle/'+id

    });

    if(!res.success){
        return;
    }

    this.evaluacion = res.data.evaluacion;
    this.usuarios = res.data.usuarios;

    this.modalEvaluacion.show();

},

async guardarEvaluacion(){

    const res = await this.createAction({

        url:'/sgm/gestion-recursos/orden-servicio-evaluacion-proveedores/evaluacion/update',

        data:this.evaluacion

    });

    if(res.success){

        this.modalEvaluacion.hide();
        this.cargarOrdenes();

    }

},

async detalleEvaluacion(id){

    const res = await this.getAction({
        url: '/sgm/gestion-recursos/orden-servicio-evaluacion-proveedores/evaluacion/detalle-completo/' + id
    });

    if(!res.success){
        return;
    }

    this.detalle = res.data;

    this.modalDetalleEvaluacion.show();

}

   }));
});