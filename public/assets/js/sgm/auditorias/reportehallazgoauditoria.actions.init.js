document.addEventListener('alpine:init', () => {

    Alpine.data('reporte', (idReporte) => ({

      idReporte,

      hallazgo: {},

      responsables: [],

      usuarios: [],

      responsables: [],

      usuariosDisponibles: [],
      
      usuarioResponsable: '',

      errors: {},

        actualizarUsuariosDisponibles() {

            const idsResponsables = this.responsables.map(
                responsable => Number(responsable.id_responsable)
            );

            this.usuariosDisponibles = this.usuarios.filter(
                usuario => !idsResponsables.includes(Number(usuario.id))
            );

        },

        formEntrevistador: {
            id_usuario: '',
            area_descripcion: '',
        },

        errorsEntrevistador: {
            id_usuario: false,
            area_descripcion: false,
        },

        entrevistados: [],

        equipoauditor: [],

        formEquipoAuditor: {
            id_usuario: '',
            nombre: '',
            rol: '',
        },

        errorsEquipoAuditor: {
            id_usuario: false,
            nombre: false,
            rol: false,
        },

        resultados: [],

        conformes: [],

        formConforme: {
            descripcion: '',
            evidencia: '',
            criterio: '',
        },

        errorsConforme: {
            descripcion: false,
            evidencia: false,
            criterio: false,
        },

        mejoras: [],

        formMejora: {
            descripcion: '',
        },

        errorsMejora: {
            descripcion: false,
        },

      init(){

        this.cargar();

      },

        async cargar() {

            try {

                const response = await fetch(
                    '/sgm/auditorias-internas-externas-atencion-hallazgos/reporte-hallazgos-auditoria/' +
                    this.idReporte +
                    '/data',
                    {
                        method: 'GET',
                        headers: {
                            'Accept': 'application/json'
                        }
                    }
                );

                const res = await response.json();

                this.hallazgo = res.data || {};

                this.responsables =
                    res.data?.responsables || [];

                this.entrevistados =
                    res.data?.entrevistados || [];

                this.usuarios =
                    res.data?.usuarios || [];

                this.equipoauditor =
                    res.data?.equipoauditor || [];

                this.resultados =
                    res.data?.resultados || [];

                this.conformes =
                    res.data?.conformes || [];

                this.mejoras =
                    res.data?.mejoras || [];


                this.actualizarUsuariosDisponibles();

            } catch (error) {

                console.error(error);

                this.notify(
                    'error',
                    'No se encontró información'
                );

            }

        },

        async editar(campo) {

          console.log(campo)

            try {

                const res = await this.createAction({

                    url:
                        '/sgm/auditorias-internas-externas-atencion-hallazgos/reporte-hallazgos-auditoria/editar',

                    data: {

                        id: this.hallazgo.id,

                        campo: campo,

                        valor: this.hallazgo[campo]

                    },

                    notify: false

                });

            } catch (error) {

                console.error(error);

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
                      '/sgm/auditorias-internas-externas-atencion-hallazgos/reporte-hallazgos-auditoria/responsable/create',

                  data: {
                      id: this.idReporte,
                      id_responsable: idUsuario
                  },
                  notify: false

              });

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

                url: '/sgm/auditorias-internas-externas-atencion-hallazgos/reporte-hallazgos-auditoria/responsable/delete',
                id,
                name: 'Responsable',
                notify: false

            });

            this.responsables = this.responsables.filter(
                responsable => Number(responsable.id) !== Number(id)
            );
            this.actualizarUsuariosDisponibles();

        },

        resetErrors() {

            Object.keys(this.errors).forEach(key => {
                this.errors[key] = false;
            });

        },

        //--------------------------------------

      abrirEntrevistador() {

          this.formEntrevistador = {
              id_usuario: '',
              area_descripcion: '',
          };

          this.errorsEntrevistador = {
              id_usuario: false,
              area_descripcion: false,
          };

          const elemento = document.getElementById(
              'modalPersonalEntrevistado'
          );

          const modal = bootstrap.Modal.getOrCreateInstance(elemento);

          modal.show();

      },

      async guardarEntrevistador() {

          if (!this.validaFormEntrevistador()) {

              this.notify(
                  'error',
                  'Completa todos los campos obligatorios'
              );

              return;

          }


          try {

              const res = await this.createAction({

                  url:
                      '/sgm/auditorias-internas-externas-atencion-hallazgos/reporte-hallazgos-auditoria/entrevistador/create',

                  data: {

                      id: this.hallazgo.id,

                      id_usuario:
                          this.formEntrevistador.id_usuario,

                      area_descripcion:
                          this.formEntrevistador.area_descripcion,

                  },

                  notify: false

              });


              if (res.success) {

                  this.entrevistados.push(res.data);


                  this.formEntrevistador = {
                      id_usuario: '',
                      area_descripcion: '',
                  };


                  this.errorsEntrevistador = {
                      id_usuario: false,
                      area_descripcion: false,
                  };


                  const elemento = document.getElementById(
                      'modalPersonalEntrevistado'
                  );

                  const modal =
                      bootstrap.Modal.getOrCreateInstance(elemento);

                  modal.hide();

              }

          } catch (error) {

              console.error(error);

              this.notify(
                  'error',
                  'Error al agregar personal entrevistado'
              );

          }

      },

      validaFormEntrevistador() {

          this.errorsEntrevistador = {
              id_usuario: false,
              area_descripcion: false,
          };

          let valid = true;


          if (!this.formEntrevistador.id_usuario) {

              this.errorsEntrevistador.id_usuario = true;

              valid = false;

          }


          if (
              !this.formEntrevistador.area_descripcion ||
              !this.formEntrevistador.area_descripcion.trim()
          ) {

              this.errorsEntrevistador.area_descripcion = true;

              valid = false;

          }


          return valid;

      },

      async eliminarEntrevistado(id) {

          try {

              await this.deleteAction({

                  url:
                      '/sgm/auditorias-internas-externas-atencion-hallazgos/reporte-hallazgos-auditoria/entrevistador/delete',

                  id,

                  name: 'Personal entrevistado',

              });

              this.entrevistados =
                  this.entrevistados.filter(
                      entrevistado =>
                          Number(entrevistado.id) !== Number(id)
                  );

          } catch (error) {

              console.error(error);

              this.notify(
                  'error',
                  'Error al eliminar personal entrevistado'
              );

          }

      },

      //-----------------------------------------

      abrirEquipoAuditor() {

          this.formEquipoAuditor = {
              id_usuario: '',
              nombre: '',
              rol: '',
          };

          this.errorsEquipoAuditor = {
              id_usuario: false,
              nombre: false,
              rol: false,
          };

          const elemento = document.getElementById(
              'modalEquipoAuditor'
          );

          const modal =
              bootstrap.Modal.getOrCreateInstance(elemento);

          modal.show();

      },

      validaFormAuditor() {

          this.errorsEquipoAuditor = {
              id_usuario: false,
              nombre: false,
              rol: false,
          };

          let valid = true;


          const idUsuario =
              this.formEquipoAuditor.id_usuario;

          const nombre =
              this.formEquipoAuditor.nombre?.trim() || '';

          const rol =
              this.formEquipoAuditor.rol?.trim() || '';


          if (idUsuario) {
              return true;
          }

          if (!nombre) {
              this.errorsEquipoAuditor.nombre = true;
              valid = false;
          }


          if (!rol) {
              this.errorsEquipoAuditor.rol = true;
              valid = false;
          }

          if (!nombre && !rol) {
              this.errorsEquipoAuditor.id_usuario = true;
          }

          return valid;

      },

      async guardarAuditor() {

    if (!this.validaFormAuditor()) {

        this.notify(
            'error',
            'Selecciona un usuario interno o completa los datos del personal externo'
        );

        return;

    }


    try {

        const res = await this.createAction({

            url:
                '/sgm/auditorias-internas-externas-atencion-hallazgos/reporte-hallazgos-auditoria/equipoauditor/create',

            data: {

                id:
                    this.hallazgo.id,

                id_usuario:
                    this.formEquipoAuditor.id_usuario,

                nombre:
                    this.formEquipoAuditor.nombre,

                rol:
                    this.formEquipoAuditor.rol,

            },

            notify: false

        });


        if (res.success) {

            this.equipoauditor.push(res.data);


            this.formEquipoAuditor = {

                id_usuario: '',

                nombre: '',

                rol: '',

            };


            this.errorsEquipoAuditor = {

                id_usuario: false,

                nombre: false,

                rol: false,

            };


            const elemento =
                document.getElementById(
                    'modalEquipoAuditor'
                );


            const modal =
                bootstrap.Modal.getOrCreateInstance(
                    elemento
                );


            modal.hide();

        }

    } catch (error) {

        console.error(error);

        this.notify(
            'error',
            'Error al agregar equipo auditor'
        );

    }

      },

      async eliminarAuditor(id) {

          try {

              await this.deleteAction({

                  url:
                      '/sgm/auditorias-internas-externas-atencion-hallazgos/reporte-hallazgos-auditoria/equipoauditor/delete',

                  id,

                  name: 'Auditor',

              });


              this.equipoauditor =
                  this.equipoauditor.filter(
                      auditor =>
                          Number(auditor.id) !== Number(id)
                  );

          } catch (error) {

              console.error(error);

              this.notify(
                  'error',
                  'Error al eliminar auditor'
              );

          }

      },

      //------------------------------------------
      async editarResultado(resultado){

        const res = await this.createAction({

        url:
            '/sgm/auditorias-internas-externas-atencion-hallazgos/reporte-hallazgos-auditoria/resultado/update',

        data: {

            id:
                this.resultado.id,

            resultado:
                this.resultado.resultado,

        },

        notify: false

    });
      
      },
      //------------------------------------------

      abrirConforme() {

          this.formConforme = {
              descripcion: '',
              evidencia: '',
              criterio: '',
          };

          this.errorsConforme = {
              descripcion: false,
              evidencia: false,
              criterio: false,
          };

          const elemento = document.getElementById(
              'modalConforme'
          );

          const modal = bootstrap.Modal.getOrCreateInstance(
              elemento
          );

          modal.show();

      },

      async guardarConforme() {

    if (!this.validaFormConforme()) {

        this.notify(
            'error',
            'Completa todos los campos obligatorios'
        );

        return;

    }


    try {

        const res = await this.createAction({

            url:
                '/sgm/auditorias-internas-externas-atencion-hallazgos/reporte-hallazgos-auditoria/conforme/create',

            data: {

                id:
                    this.hallazgo.id,

                descripcion:
                    this.formConforme.descripcion,

                evidencia:
                    this.formConforme.evidencia,

                criterio:
                    this.formConforme.criterio,

            },

            notify: false

        });


        if (res.success) {

            this.conformes.push(res.data);


            this.formConforme = {
                descripcion: '',
                evidencia: '',
                criterio: '',
            };


            this.errorsConforme = {
                descripcion: false,
                evidencia: false,
                criterio: false,
            };


            const elemento = document.getElementById(
                'modalConforme'
            );

            const modal =
                bootstrap.Modal.getOrCreateInstance(
                    elemento
                );

            modal.hide();

        }

    } catch (error) {

        console.error(error);

        this.notify(
            'error',
            'Error al agregar la documentación del hallazgo'
        );

    }

      },

      validaFormConforme() {

          this.errorsConforme = {
              descripcion: false,
              evidencia: false,
              criterio: false,
          };

          let valid = true;


          if (
              !this.formConforme.descripcion ||
              !this.formConforme.descripcion.trim()
          ) {

              this.errorsConforme.descripcion = true;

              valid = false;

          }


          if (
              !this.formConforme.evidencia ||
              !this.formConforme.evidencia.trim()
          ) {

              this.errorsConforme.evidencia = true;

              valid = false;

          }


          if (
              !this.formConforme.criterio ||
              !this.formConforme.criterio.trim()
          ) {

              this.errorsConforme.criterio = true;

              valid = false;

          }


          return valid;

      },

      async eliminarConforme(id) {

          try {

              await this.deleteAction({

                  url:
                      '/sgm/auditorias-internas-externas-atencion-hallazgos/reporte-hallazgos-auditoria/conforme/delete',

                  id,

                  name:
                      'Documentación del hallazgo',
                  notify: false

              });


              this.conformes =
                  this.conformes.filter(
                      conforme =>
                          Number(conforme.id) !== Number(id)
                  );

          } catch (error) {

                     this.notify(
                  'error',
                  'Error al eliminar la documentación del hallazgo'
              );

          }

      },

      //----------------------------

      abrirMejoras() {

    this.formMejora = {
        descripcion: '',
    };

    this.errorsMejora = {
        descripcion: false,
    };


    const elemento = document.getElementById(
        'modalMejora'
    );

    const modal = bootstrap.Modal.getOrCreateInstance(
        elemento
    );

    modal.show();

      },

async guardarMejora() {

    if (!this.validaFormMejora()) {

        this.notify(
            'error',
            'Completa todos los campos obligatorios'
        );

        return;

    }


    try {

        const res = await this.createAction({

            url:
                '/sgm/auditorias-internas-externas-atencion-hallazgos/reporte-hallazgos-auditoria/mejora/create',

            data: {

                id:
                    this.hallazgo.id,

                descripcion:
                    this.formMejora.descripcion,

            },

            notify: false

        });


        if (res.success) {

            this.mejoras.push(res.data);


            this.formMejora = {
                descripcion: '',
            };


            this.errorsMejora = {
                descripcion: false,
            };


            const elemento = document.getElementById(
                'modalMejora'
            );

            const modal =
                bootstrap.Modal.getOrCreateInstance(
                    elemento
                );

            modal.hide();

        }

    } catch (error) {

        console.error(error);

        this.notify(
            'error',
            'Error al agregar la oportunidad de mejora'
        );

    }

},

validaFormMejora() {

    this.errorsMejora = {
        descripcion: false,
    };

    let valid = true;


    if (
        !this.formMejora.descripcion ||
        !this.formMejora.descripcion.trim()
    ) {

        this.errorsMejora.descripcion = true;

        valid = false;

    }


    return valid;

},

async eliminarMejora(id) {

    try {

        await this.deleteAction({

            url:
                '/sgm/auditorias-internas-externas-atencion-hallazgos/reporte-hallazgos-auditoria/mejora/delete',

            id,

            name:
                'Oportunidad de mejora',

        });


        this.mejoras =
            this.mejoras.filter(
                mejora =>
                    Number(mejora.id) !== Number(id)
            );

    } catch (error) {

        console.error(error);

        this.notify(
            'error',
            'Error al eliminar la oportunidad de mejora'
        );

    }

},



    }));

});