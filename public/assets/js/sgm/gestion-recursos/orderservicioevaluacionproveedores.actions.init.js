document.addEventListener('alpine:init', () => {
    Alpine.data('evaluacion', () => ({

      ordenes: [],
      tablaOrdenes: null,
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

        if (!document.getElementById('sgm-content')) {
            return;
        }

        this.tablaOrdenes = $('#table-orden-servicio').DataTable({
            processing: true,
            serverSide: false,
            autoWidth: false,
            order: [],
            pageLength: 10,
            lengthMenu: [10, 25, 50, 100],
            language: {
                url: '/assets/libs/datatables.net/js/es-ES.json',
                emptyTable: 'No se encontró información para mostrar.'
            },
            columns: [
                { data: 'numero', width: '96px', className: 'text-center fw-bolder align-middle' },
                { data: 'fecha', className: 'text-center align-middle text-nowrap' },
                { data: 'hora', className: 'text-center align-middle text-nowrap' },
                { data: 'descripcion', className: 'align-middle', render: $.fn.dataTable.render.text() },
                {
                    data: null,
                    className: 'text-center align-middle',
                    orderable: false,
                    searchable: false,
                    render: (data, type, row) => `<a href="#" class="pointer" data-order-action="edit" data-order-id="${Number(row.id)}" aria-label="Editar orden"><i class="ti ti-edit fs-6 text-warning"></i></a>`
                },
                {
                    data: null,
                    className: 'text-center align-middle',
                    orderable: false,
                    searchable: false,
                    render: (data, type, row) => `<a href="#" class="pointer" data-order-action="detail" data-order-id="${Number(row.id)}" aria-label="Ver orden"><i class="ti ti-file-description fs-6 text-info"></i></a>`
                },
                {
                    data: null,
                    className: 'text-center align-middle',
                    orderable: false,
                    searchable: false,
                    render: (data, type, row) => `<a class="pointer" href="/sgm/gestion-recursos/orden-servicio-evaluacion-proveedores/pdf/${Number(row.id)}" data-action-loader="download" aria-label="Descargar PDF"><i class="ti ti-file-type-pdf fs-6 text-danger"></i></a>`
                },
                {
                    data: null,
                    className: 'text-center align-middle',
                    orderable: false,
                    searchable: false,
                    render: (data, type, row) => `<a href="#" class="pointer" data-order-action="edit-evaluation" data-order-id="${Number(row.id)}" aria-label="Editar evaluación"><i class="ti ti-edit fs-6 text-warning"></i></a>`
                },
                {
                    data: null,
                    className: 'text-center align-middle',
                    orderable: false,
                    searchable: false,
                    render: (data, type, row) => row.evaluacion
                        ? `<a href="#" class="pointer" data-order-action="detail-evaluation" data-order-id="${Number(row.id)}" aria-label="Ver evaluación"><i class="ti ti-file-description fs-6 text-info"></i></a>`
                        : '<i class="ti ti-file-off fs-6" aria-label="Sin evaluación"></i>'
                },
                {
                    data: null,
                    className: 'text-center align-middle',
                    orderable: false,
                    searchable: false,
                    render: (data, type, row) => row.evaluacion
                        ? `<a class="pointer" href="/sgm/gestion-recursos/orden-servicio-evaluacion-proveedores/evaluacion/pdf/${Number(row.id)}" data-action-loader="download" aria-label="Descargar PDF"><i class="ti ti-file-type-pdf fs-6 text-danger"></i></a>`
                        : '<i class="ti ti-file-off fs-6" aria-label="Sin evaluación"></i>'
                },
                {
                    data: null,
                    className: 'text-center align-middle',
                    orderable: false,
                    searchable: false,
                    render: (data, type, row) => `<a href="#" class="pointer" data-order-action="delete" data-order-id="${Number(row.id)}" aria-label="Eliminar orden"><i class="ti ti-trash fs-6 text-danger"></i></a>`
                }
            ]
        });

        document.querySelector('#table-orden-servicio tbody')?.addEventListener('click', event => {
            const action = event.target.closest('[data-order-action]');
            if (!action) return;
            event.preventDefault();
            const id = Number(action.dataset.orderId);
            switch (action.dataset.orderAction) {
                case 'edit': this.openEditar(id); break;
                case 'detail': this.detalleOrden(id); break;
                case 'edit-evaluation': this.openEvaluacion(id); break;
                case 'detail-evaluation': this.detalleEvaluacion(id); break;
                case 'delete': this.eliminar(id); break;
            }
        });

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
            this.tablaOrdenes.clear().rows.add(this.ordenes).draw();

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
