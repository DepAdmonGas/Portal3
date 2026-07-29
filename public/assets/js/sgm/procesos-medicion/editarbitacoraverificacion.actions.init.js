document.addEventListener('alpine:init',()=>{
  Alpine.data('editarBitacoraVerificacion',(id)=>({
    
    id,

    tipo: '',

    programa: {
      equipo: {}
    },

        bitacora: {},

        resultados: [],

        detalles: [],

        modalDetalle: null,

        nuevo: {

            lado: '',

            producto: '',

            medida_comparar: '',

            medicion_jarra_patron: ''

        },

        diferenciaManguera: 0,

        resultadoManguera: '',

       init(){

          this.modalDetalle = new bootstrap.Modal(
              document.getElementById('modalAgregarManguera')
          );


          this.$watch(
              'nuevo.medida_comparar',
              ()=>this.calcularManguera()
          );


          this.$watch(
              'nuevo.medicion_jarra_patron',
              ()=>this.calcularManguera()
          );


          this.obtener(this.id);

      },

        calcularManguera(){

            let comparar = Number(this.nuevo.medida_comparar) || 0;

            let medicion = Number(this.nuevo.medicion_jarra_patron) || 0;


            this.diferenciaManguera = comparar - medicion;


            this.resultadoManguera =
                Math.abs(this.diferenciaManguera) <= 100
                    ? 'Favorable'
                    : 'No favorable';

        },        

        async obtener(id) {

          
            const respuesta = await fetch(
                `/sgm/procesos-medicion/bitacora-verificacion-equipo-medicion/detalle/${id}`
            );

            const json = await respuesta.json();
            
            this.tipo = json.tipo;
            this.programa = json.programa;

            this.bitacora = json.bitacora;

            this.resultados = json.categorias ?? [];

            this.detalles = json.detalles ?? [];
        },

        async guardar(campo) {
       
           await this.createAction({
            url:'/sgm/procesos-medicion/bitacora-verificacion-equipo-medicion/actualizar-campo',
            data:{
                tipo: this.tipo,
                id: this.bitacora.id,
                campo,
                valor: this.bitacora[campo]
            },
            notify: false
        });

        },

        async guardarResultado(resultado) {

          await this.createAction({

            url:'/sgm/procesos-medicion/bitacora-verificacion-equipo-medicion/actualizar-resultado',

            data:{

                id: resultado.id,
                resultado: resultado.resultado

            },
            notify: false

        });

        },

        async agregarDetalle() {

            const respuesta = await fetch(

                '/sgm/procesos-medicion/bitacora-verificacion/agregar-detalle',

                {
                    method: 'POST',

                    headers: {

                        'Content-Type': 'application/json'

                    },

                    body: JSON.stringify({

                        id_verificacion: this.bitacora.id,

                        lado: this.nuevo.lado,

                        producto: this.nuevo.producto,

                        medida_comparar: this.nuevo.medida_comparar,

                        medicion_jarra_patron: this.nuevo.medicion_jarra_patron

                    })

                }

            );

            const json = await respuesta.json();

            if (json.status) {

                this.detalles.push(json.detalle);

                this.nuevo = {

                    lado: '',

                    producto: '',

                    medida_comparar: '',

                    medicion_jarra_patron: ''

                };

                bootstrap.Modal.getInstance(
                    document.getElementById('modalAgregarDetalle')
                ).hide();
            }

        },

        async eliminarDetalle(id) {

          const res = await this.deleteAction({
              url: "/sgm/procesos-medicion/bitacora-verificacion-equipo-medicion/delete-manguera",
              id,
              name: 'Manguera'
          });

          if (res.success) {
              this.detalles = this.detalles.filter(
                  item => item.id !== id
              );
          }
      },

        async finalizar() {

          const res=await this.createAction({

            url:`/sgm/procesos-medicion/bitacora-verificacion-equipo-medicion/finalizar`,
             data:{

                id: this.id
            },

        });

        if(res.success){

            history.back();

        }

        },

        abrirModalDetalle() {

            this.modalDetalle.show();

        },

        cerrarModalDetalle() {

            this.modalDetalle.hide();

        },

        async guardarManguera() {

        if (!this.nuevo.lado) {
            return this.notify?.warning('Seleccione el lado.');
        }

        if (!this.nuevo.producto) {
            return this.notify?.warning('Seleccione el producto.');
        }

        if (!this.nuevo.medida_comparar) {
            return this.notify?.warning('Capture la medida a comparar.');
        }

        if (!this.nuevo.medicion_jarra_patron) {
            return this.notify?.warning('Capture la medición de la jarra patrón.');
        }

        const res = await this.createAction({

            url: '/sgm/procesos-medicion/bitacora-verificacion-equipo-medicion/create-manguera',

            data: {

                id_verificacion: this.bitacora.id,

                lado: this.nuevo.lado,

                producto: this.nuevo.producto,

                medida_comparar: this.nuevo.medida_comparar,

                medicion_jarra_patron: this.nuevo.medicion_jarra_patron

            }

        });

  

        if (res.success) {

          this.detalles.push(res.detalle);

          this.resetModal();

          this.cerrarModalDetalle();

      }

    },

    resetModal() {

      this.nuevo = {

          lado: '',
          producto: '',
          medida_comparar: '',
          medicion_jarra_patron: ''

      };

      this.diferenciaManguera = 0;

      this.resultadoManguera = '';

  }

        

  }));
});