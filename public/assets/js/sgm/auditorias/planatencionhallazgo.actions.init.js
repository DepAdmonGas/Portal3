document.addEventListener('alpine:init', () => {
    Alpine.data('planatencion', (idAtencion) => ({

      idAtencion,

      idAuditoria: null,

      plan: {},

      usuarios: [],

      responsables: [],

      loading: false,

      usuariosDisponibles: [],

      usuarioResponsable: '',

      actualizarUsuariosDisponibles() {

            const idsResponsables = this.responsables.map(
                responsable => Number(responsable.id_responsable)
            );

            this.usuariosDisponibles = this.usuarios.filter(
                usuario => !idsResponsables.includes(Number(usuario.id))
            );

        },

      init(){

        this.cargar();

      },

      async cargar() {

          try {

              const response = await fetch(
                  '/sgm/auditorias-internas-externas-atencion-hallazgos/plan-atencion-hallazgos/' +
                  this.idAtencion +
                  '/data',
                  {
                      method: 'GET',
                      headers: {
                          'Accept': 'application/json'
                      }
                  }
              );

              const res = await response.json();

              this.plan = res.data.plan || {};

              this.responsables =
                  res.data.responsables || [];

              this.usuarios =
                  res.data.usuarios || [];

              this.actualizarUsuariosDisponibles();

          } catch (error) {

            
              this.notify(
                  'error',
                  'No se encontró información'
              );
          }
      },

      async editar(campo) {

        try {

            const res = await this.createAction({
                url: `/sgm/auditorias-internas-externas-atencion-hallazgos/plan-atencion-hallazgos/editar`,
                data: {
                    id: this.plan.id,
                    campo: campo,
                    valor: this.plan[campo]
                  },
                  notify: false
            });

            } catch (error) {

            this.notify(
                  'error',
                  'Error al editar'
              );

            }
      },

      async agregarResponsable() {

          if (!this.usuarioResponsable) {
              return;
          }

          try {

              const idUsuario = this.usuarioResponsable;

              const res = await this.createAction({

                  url:
                      '/sgm/auditorias-internas-externas-atencion-hallazgos/plan-atencion-hallazgos/responsable/create',

                  data: {
                      id: this.idAtencion,
                      id_responsable: idUsuario
                  },
                  notify: false

              });

              console.log(res)

              if (res.success) {

                  this.responsables.push(res.data);

                  this.usuarioResponsable = '';

                  // Quita automáticamente el usuario del select
                  this.actualizarUsuariosDisponibles();

              }

          } catch (error) {

              console.error(error);

              this.notify(
                  'error',
                  'Error al agregar responsable'
              );

          }

        },

        async eliminarResponsable(id) {

            await this.deleteAction({

                url: '/sgm/auditorias-internas-externas-atencion-hallazgos/plan-atencion-hallazgos/responsable/delete',
                id,
                name: 'Responsable',

            });

            this.responsables = this.responsables.filter(
                responsable => Number(responsable.id) !== Number(id)
            );
            this.actualizarUsuariosDisponibles();

        },

        finalizar(){
           window.history.back();
        },  

    }));
});