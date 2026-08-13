document.addEventListener('alpine:init', () => {

    Alpine.data('planauditoria', (idAuditoria) => ({

        idAuditoria,

        plan: {},

        responsables: [],

        usuarios: [],
        usuariosDisponibles: [],

        auditores: [],

        auxiliares: [],

        modalTipo: '',

        usuarioResponsable: '',

        actualizarUsuariosDisponibles() {

            const idsResponsables = this.responsables.map(
                responsable => Number(responsable.id_responsable)
            );

            this.usuariosDisponibles = this.usuarios.filter(
                usuario => !idsResponsables.includes(Number(usuario.id))
            );

        },

        formAuditor: {
            id_plan: '',
            id_usuario: '',
            categoria: '',
            nombre: '',
            area_actividad: '',
            auditorInterno: '',
        },

        

        get tieneNombreAuditor() {
            return this.formAuditor.nombre.trim() !== '';
        },

        get tieneAuditorInterno() {
            return this.formAuditor.auditorInterno !== '';
        },

        limpiarNombreAuditor() {

            if (this.formAuditor.auditorInterno) {
                this.formAuditor.nombre = '';
            }

        },

        limpiarAuditorInterno() {

            if (this.formAuditor.nombre.trim() !== '') {
                this.formAuditor.auditorInterno = '';
            }

        },

        formAuxiliar: {
            id_plan: '',
            categoria: '',
            nombre: ''
        },

        errors:{
          categoria: false,
          nombre: false,
          area_actividad: false,

          hora_inicio: false,
          hora_termino: false,
          proceso: false,
          elemento_sistema: false,
          nombre_rol: false
        },

        elementos: [],

        agenda: [],

        formAgenda: {
            hora_inicio: '',
            hora_termino: '',
            proceso: '',
            elemento_sistema: '',
            nombre_rol: '',
            guia: '',
        },


        async init() {

             this.cargar();

        },

        async cargar() {

            try {

                const response = await fetch(
                    '/sgm/auditorias-internas-externas-atencion-hallazgos/plan-auditoria/' +
                    this.idAuditoria +
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

                this.responsables = res.data.responsables || [];

                this.usuarios = res.data.usuarios || [];

                this.auditores = res.data.auditores || [];

                this.auxiliares = res.data.auxiliares || [];

                this.elementos = res.data.elementos || [];

                this.agenda = res.data.agenda || [];

                this.actualizarUsuariosDisponibles();

            } catch (error) {

                this.notify('error', 'No se incontro información');

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
                      '/sgm/auditorias-internas-externas-atencion-hallazgos/plan-auditoria/responsable/create',

                  data: {
                      id: this.idAuditoria,
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

                url: '/sgm/auditorias-internas-externas-atencion-hallazgos/plan-auditoria/responsable/delete',
                id,
                name: 'Responsable',

            });

            this.responsables = this.responsables.filter(
                responsable => Number(responsable.id) !== Number(id)
            );
            this.actualizarUsuariosDisponibles();

        },

        async editar(campo) {

          
            try {

            const res = await this.createAction({
                url: `/sgm/auditorias-internas-externas-atencion-hallazgos/plan-auditoria/editar`,
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

        abrirAuditor() {

            if (!this.plan.id) {
                console.error('No existe ID del plan');
                return;
            }

            this.formAuditor = {
                id_plan: this.plan.id,
                categoria: '',
                nombre: '',
                id_usuario: '',
                area_actividad: ''
            };

            this.modalTipo = 'auditor';
            this.resetErrors();

            const elemento = document.getElementById('modalPrincipal');
            const modal = bootstrap.Modal.getOrCreateInstance(elemento);

            modal.show();

        },

        async guardarAuditor() {

        try {

            if (!this.validaFormAuditor()) {

                this.notify(
                    'error',
                    'Completa todos los campos obligatorios'
                );

                return;
            }

            const res = await this.createAction({

                url:
                    '/sgm/auditorias-internas-externas-atencion-hallazgos/plan-auditoria/auditor/create',

                data: {

                    id: this.idAuditoria,

                    equipo: this.formAuditor.categoria,

                    auditor: this.formAuditor.nombre,

                    auditor_interno: this.formAuditor.auditorInterno,

                    area_actividad:
                        this.formAuditor.area_actividad

                },

                notify: false

            });

            if (res.success) {

                // Agregar a la tabla
                this.auditores.push(res.data);

                // Limpiar formulario
                this.formAuditor = {
                    id_plan: '',
                    id_usuario: '',
                    categoria: '',
                    nombre: '',
                    area_actividad: '',
                    auditorInterno: '',
                };
              
              const elemento = document.getElementById('modalPrincipal');
              const modal = bootstrap.Modal.getOrCreateInstance(elemento);
              modal.hide();

            }

        } catch (error) {

            console.error(error);

            this.notify(
                'error',
                'Error al agregar auditor'
            );

        }

        },

        validaFormAuditor() {

            this.resetErrors();
            
            let valid = true;

            if (!this.formAuditor.categoria) {
                this.errors.categoria = true;
                valid = false;
            }

            if (!this.formAuditor.area_actividad) {
                this.errors.area_actividad = true;
                valid = false;
            }
          
            return valid;

        },

        async eliminarAuditor(id) {

            await this.deleteAction({

                url: '/sgm/auditorias-internas-externas-atencion-hallazgos/plan-auditoria/auditor/delete',
                id,
                name: 'Auditor',

            });

             this.auditores = this.auditores.filter(
            auditor => Number(auditor.id) !== Number(id)
        );

        },

       
        abrirAuxiliar() {


            this.formAuxiliar = {
                id_plan: this.plan.id,
                categoria: '',
                nombre: ''
            };
            
            this.modalTipo = 'auxiliar';
            this.resetErrors();

            const elemento = document.getElementById('modalPrincipal');
            const modal = bootstrap.Modal.getOrCreateInstance(elemento);
            modal.show();

        },

        async guardarAuxiliar() {

        try {

         if (!this.validaFormAudxiliar()) {

                this.notify(
                    'error',
                    'Completa todos los campos obligatorios'
                );

                return;
            }

        const res = await this.createAction({

            url:
                '/sgm/auditorias-internas-externas-atencion-hallazgos/plan-auditoria/auxiliar/create',

            data: {

                id: this.idAuditoria,

                categoria:
                    this.formAuxiliar.categoria,

                nombre:
                    this.formAuxiliar.nombre.trim()

            },

            notify: false

        });


        if (res.success) {

            // Actualizar tabla inmediatamente
            this.auxiliares.push(res.data);

            // Limpiar formulario
            this.formAuxiliar = {
                id_plan: '',
                categoria: '',
                nombre: ''
            };

            const elemento = document.getElementById('modalPrincipal');
            const modal = bootstrap.Modal.getOrCreateInstance(elemento);
            modal.hide();


        }

    } catch (error) {


        this.notify(
            'error',
            'Error al agregar auxiliar'
        );

    }

        },

        validaFormAudxiliar() {

            this.resetErrors();
            
            let valid = true;

            if (!this.formAuxiliar.categoria) {
                this.errors.categoria = true;
                valid = false;
            }

            if (!this.formAuxiliar.nombre) {
                this.errors.nombre = true;
                valid = false;
            }
          
            return valid;

        },

        async eliminarAuxiliar(id) {

            try {

                await this.deleteAction({

                    url:
                        '/sgm/auditorias-internas-externas-atencion-hallazgos/plan-auditoria/auxiliar/delete',

                    id,

                    name: 'Auxiliar',

                });

                // Actualizar tabla inmediatamente
                this.auxiliares = this.auxiliares.filter(
                    auxiliar =>
                        Number(auxiliar.id) !== Number(id)
                );

            } catch (error) {

                console.error(error);

                this.notify(
                    'error',
                    'Error al eliminar auxiliar'
                );

            }

        },

        abrirAgenda() {

          this.formAgenda = {
                hora_inicio: '',
                hora_termino: '',
                proceso: '',
                elemento_sistema: '',
                nombre_rol: '',
                guia: '',
            };

            this.resetErrors();
            const elemento = document.getElementById('modalAgenda');
            const modal = bootstrap.Modal.getOrCreateInstance(elemento);
            modal.show();

        },

        async guardarAgenda() {

            try {

               if (!this.validaFormAgenda()) {

                this.notify(
                    'error',
                    'Completa todos los campos obligatorios'
                );

                return;
            }

                const res = await this.createAction({

                    url: '/sgm/auditorias-internas-externas-atencion-hallazgos/plan-auditoria/agenda/create',

                    data: {
                        id: this.idAuditoria,

                        hora_inicio:
                            this.formAgenda.hora_inicio,

                        hora_termino:
                            this.formAgenda.hora_termino,

                        proceso:
                            this.formAgenda.proceso,

                        elemento_sistema:
                            this.formAgenda.elemento_sistema,

                        nombre_rol:
                            this.formAgenda.nombre_rol,

                        guia:
                            this.formAgenda.guia,
                    },

                    notify: false

                });

                if (res.success) {

                    this.agenda.push(res.data);

                    this.formAgenda = {
                        hora_inicio: '',
                        hora_termino: '',
                        proceso: '',
                        elemento_sistema: '',
                        nombre_rol: '',
                        guia: '',
                    };

                    const elemento = document.getElementById('modalAgenda');
                    const modal = bootstrap.Modal.getOrCreateInstance(elemento);
                    modal.hide();


                }

            } catch (error) {

                console.log(error);

                this.notify(
                    'error',
                    'Error al agregar agenda'
                );

            }
        },

        validaFormAgenda() {

            this.resetErrors();
            
            let valid = true;

            if (!this.formAgenda.hora_inicio) {
                this.errors.hora_inicio = true;
                valid = false;
            }

            if (!this.formAgenda.hora_termino) {
                this.errors.hora_termino = true;
                valid = false;
            }

            if (!this.formAgenda.proceso) {
                this.errors.proceso = true;
                valid = false;
            }

            if (!this.formAgenda.elemento_sistema) {
                this.errors.elemento_sistema = true;
                valid = false;
            }

            if (!this.formAgenda.nombre_rol) {
                this.errors.nombre_rol = true;
                valid = false;
            }
          
            return valid;

        },

        async eliminarAgenda(id) {

            try {

                await this.deleteAction({

                    url:
                        '/sgm/auditorias-internas-externas-atencion-hallazgos/plan-auditoria/agenda/delete',

                    id,

                    name: 'Agenda',

                });


                this.agenda = this.agenda.filter(
                    agenda =>
                        Number(agenda.id) !== Number(id)
                );

            } catch (error) {

                console.error(error);

                this.notify(
                    'error',
                    'Error al eliminar agenda'
                );

            }

        },

        finalizar(){
           window.history.back();
        },        

        resetErrors() {

            Object.keys(this.errors).forEach(key => {
                this.errors[key] = false;
            });

        },

    }));

});
