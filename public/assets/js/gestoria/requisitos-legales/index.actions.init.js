document.addEventListener('alpine:init', () => {
    Alpine.data('requisitoLegal', () => ({

      modalNivelGobierno: null,
      modalMunicipioAlcaldiaEstado: null,
      modalDependencias: null, 
      modalRequisitoLegal: null,

      nivelgobierno: '',
      municipioalcaldiaestado: '',
      dependencias: '',

      errors:{
        nivelgobierno: false,
        municipioalcaldiaestado: false,
        dependencias: false
      },

      form: {
            id: null,

            mode: 'create',

            nivelGobierno: '',
            municipioAlcaldiaEstado: '',
            dependencia: '',
            permiso: '',
            fundamento: '',
            idPersonal: '',
            responsable: '',
            sgm: '0'
        },

        errors: {
            nivelgobierno: false,
            municipioalcaldiaestado: false,
            dependencias: false,

            nivelGobierno: false,
            municipioAlcaldiaEstado: false,
            dependencia: false,
            permiso: false,
            fundamento: false,
            idPersonal: false
        },      

        init(){
            window.requisitoLegal = this;

            this.modalNivelGobierno = new bootstrap.Modal(
              document.getElementById('modalNivelGobierno')
          );

          this.modalMunicipioAlcaldiaEstado = new bootstrap.Modal(
              document.getElementById('modalMunicipioAlcaldiaEstado')
          );

          this.modalDependencias = new bootstrap.Modal(
              document.getElementById('modalDependencias')
          );

          this.modalRequisitoLegal = new bootstrap.Modal(
              document.getElementById('modalRequisitoLegal')
          );
          
        },

        resetErrors(){
        Object.keys(this.errors).forEach(key=>{
            this.errors[key]=false;
        });
      },

        openNivelGobierno(){
          this.modalNivelGobierno.show();
        },

        async guardarNivelGobierno(){

          if(!this.validaNivelGobierno()){
            this.notify(
                'error',
                'Completa los campos obligatorios.'
            );
            return;
          }

          try{

            const res = await this.createAction({

                url:'/gestoria/requisitos-legales/create-nivel-gobierno',

                data:{
                  detalle : this.nivelgobierno
                },

                table:'#table-nivel-gobierno'

            });

            if(res.success){

              this.modalNivelGobierno.hide();

            }

          }catch(e){

            this.notify(
                'error',
                'Error al guardar.'
            );

          }

        },

        validaNivelGobierno(){

          this.resetErrors();

          let valid=true;

          if(!this.nivelgobierno){
              this.errors.nivelgobierno=true;
              valid=false;
          }        
          return valid;

        },

        async eliminarNivelGobierno(id){

            const res = await this.deleteAction({
                  url: "/gestoria/requisitos-legales/delete-nivel-gobierno",
                  id: id,
                  name: 'Nivel de Gobierno',
                  table: "#table-nivel-gobierno"
              });

        },

        //---------------------------------------------------

        openMunicipioAlcaldiaEstado(){
          this.modalMunicipioAlcaldiaEstado.show();
        },

        async guardarMunicipalAlcaldiaEstado(){

           if(!this.validaMunicipalAlcaldiaEstado()){
            this.notify(
                'error',
                'Completa los campos obligatorios.'
            );
            return;
          }

          try{

            const res = await this.createAction({

                url:'/gestoria/requisitos-legales/create-municipio-alcaldia-estado',

                data:{
                  detalle : this.municipioalcaldiaestado
                },

                table:'#table-municipio-alcaldia-estado'

            });

            if(res.success){

              this.modalMunicipioAlcaldiaEstado.hide();

            }

          }catch(e){

            this.notify(
                'error',
                'Error al guardar.'
            );

          }

        },

        validaMunicipalAlcaldiaEstado(){

          this.resetErrors();

          let valid=true;

          if(!this.municipioalcaldiaestado){
              this.errors.municipioalcaldiaestado=true;
              valid=false;
          }        
          return valid;

        },

        async eliminarMunicipioAlcaldiaEstado(id){
        
          const res = await this.deleteAction({
                  url: "/gestoria/requisitos-legales/delete-municipio-alcaldia-estado",
                  id: id,
                  name: 'Municipio, Alcaldía y Estado',
                  table: "#table-municipio-alcaldia-estado"
              });

        },

        //----------------------------------------------------

        openDependencias(){
          this.modalDependencias.show();
        },

        async guardarDependencias(){

          if(!this.validaDependencia()){
            this.notify(
                'error',
                'Completa los campos obligatorios.'
            );
            return;
          }

          try{

            const res = await this.createAction({

                url:'/gestoria/requisitos-legales/create-dependencias',

                data:{
                  detalle : this.dependencias
                },

                table:'#table-dependencias'

            });

            if(res.success){

              this.modalDependencias.hide();

            }

          }catch(e){

            this.notify(
                'error',
                'Error al guardar.'
            );

          }

        },

        validaDependencia(){

          this.resetErrors();

          let valid=true;

          if(!this.dependencias){
              this.errors.dependencias=true;
              valid=false;
          }        
          return valid;

        },

        async eliminarDependencia(id){
        
          const res = await this.deleteAction({
                  url: "/gestoria/requisitos-legales/delete-dependencias",
                  id: id,
                  name: 'Dependencias',
                  table: "#table-dependencias"
              });

        },

        //--------------------------------------------------------


        openRequisitoLegal() {

            this.resetErrors();
            this.resetForm();
            this.form.mode = 'create';
            this.modalRequisitoLegal.show();

        },

         resetForm() {

            this.form = {
                id: null,
                mode: 'create',
                nivelGobierno: '',
                municipioAlcaldiaEstado: '',
                dependencia: '',
                permiso: '',
                fundamento: '',
                idPersonal: '',
                sgm: '0'
            };
        },

        async editarRequisitoLegal(json) {

            this.resetErrors();

            this.resetForm();

            this.form.mode = 'edit';

            console.log(json)

           
            this.form.id = json.id;
            this.form.nivelGobierno = json.nivel_gobierno;
            this.form.municipioAlcaldiaEstado = json.mun_alc_est;
            this.form.dependencia = json.dependencia;
            this.form.permiso = json.permiso;
            this.form.fundamento = json.fundamento;
            this.form.idPersonal = json.id_usuario;
            this.form.responsable = json.responsable;
            this.form.sgm = json.sgm;

            this.modalRequisitoLegal.show();
        },

        async guardarRequisitoLegal() {

            if (!this.validaRequisitoLegal()) {

                this.notify(
                    'error',
                    'Completa los campos obligatorios.'
                );

                return;
            }

            try {

                const url =
                    this.form.mode === 'edit'
                        ? '/gestoria/requisitos-legales/update-requisito-legal'
                        : '/gestoria/requisitos-legales/create-requisito-legal';


                const data = {

                    idRequisito: this.form.id,

                    NivelG: this.form.nivelGobierno,

                    MuAlEs: this.form.municipioAlcaldiaEstado,

                    Dependencia: this.form.dependencia,

                    Permiso: this.form.permiso,

                    Fundamento: this.form.fundamento,

                    IdPersonal: this.form.idPersonal,

                    sgmValor: this.form.sgm
                };

            const res = await this.createAction({
                url: url,
                data: data,
                table:'#table-requisito-legal'

            });

            if(res.success){

              this.modalRequisitoLegal.hide();
              this.resetForm();

            }

            } catch (error) {

                console.error(error);

                this.notify(
                    'error',
                    'Ocurrió un error al guardar el requisito legal.'
                );

            } finally {

               
              
            }
        },


        validaRequisitoLegal() {

            this.resetErrors();
            let valid = true;

            if (!this.form.nivelGobierno) {
                this.errors.nivelGobierno = true;
                valid = false;
            }

            if (!this.form.permiso?.trim()) {
                this.errors.permiso = true;
                valid = false;
            }


            if (!this.form.fundamento?.trim()) {
                this.errors.fundamento = true;
                valid = false;
            }


            return valid;
        },


         async eliminarRequisitoLegal(id){
        
          const res = await this.deleteAction({
                  url: "/gestoria/requisitos-legales/delete-requisito-legal",
                  id: id,
                  name: 'Requisitos Legales',
                  table: "#table-requisito-legal"
              });

        },

    }));
});