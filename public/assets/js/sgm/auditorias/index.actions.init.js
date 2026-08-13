document.addEventListener('alpine:init', () => {

    Alpine.data('auditorias', () => ({

        auditorias: [],
        error: null,

        init(){

            window.auditorias = this;

            this.cargarAuditorias();

        },

        async cargarAuditorias(){

            const res = await this.getAction({

                url: '/sgm/auditorias-internas-externas-atencion-hallazgos/table'

            });

              if(res.success){

                this.auditorias = res.data;

            }

        },

        editar(id, tipo){

           if(tipo == 18){
              window.location = "/sgm/auditorias-internas-externas-atencion-hallazgos/plan-auditoria/" + id; 
            }else if(tipo == 19){
              window.location = "/sgm/auditorias-internas-externas-atencion-hallazgos/reporte-hallazgos-auditoria/" + id;
            }else if(tipo == 20){
              window.location = "/sgm/auditorias-internas-externas-atencion-hallazgos/plan-atencion-hallazgos/" + id;
            }
        },

        descargar(id, tipo){

          if(tipo == 18){

            window.open(
                `/sgm/auditorias-internas-externas-atencion-hallazgos/plan-auditoria/pdf/${id}`,
                '_blank'
            );

          }else if(tipo == 19){

            window.open(
                `/sgm/auditorias-internas-externas-atencion-hallazgos/reporte-hallazgos-auditoria/pdf/${id}`,
                '_blank'
            );

          }else if(tipo == 20){

            window.open(
                `/sgm/auditorias-internas-externas-atencion-hallazgos/plan-atencion-hallazgos/pdf/${id}`,
                '_blank'
            );

          }

            

        }

    }));

});