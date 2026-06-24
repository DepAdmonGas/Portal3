document.addEventListener('alpine:init', () => {
    Alpine.data('incidentesAccidentes', () => ({

        incidentes: [],
        registros: [],

        modalInvestigacion: null,

        idInvestigacion: null,

        form: {
            fecha: '',
            descripcion: '',
            tipo_evento: '',

            muertes: 0,

            hubo_muertes: false,
            contratar_tercero: false,

            nombre_ta: '',
            numero_autorizacion: '',
            lider: ''
        },

        // Grupo

        modalGrupo: null,
        grupo_personal: [],
        grupo: {
            nombre: '',
            puesto: '',
            especialidad: ''
        },

        //Modal modal026

        modal026: null,
        archivo026: null,

        modalTercero: null,
        archivoTercer: null,
        
        idTercer: '',
        nombreTercer: '',
        numeroTercer: '',
        liderTercer: '',
        fechaTercer: '',
        uploadarchivoTercer: '',
        
        //Modal no accidentes
        modoModal: 'create',

        modalNoAccidentes: null,
        idNoAccidentes: null,
        fechaNoAccidentes: '',

        municipio: '',
        estado: '',
        nombre: '',
        puesto: '',
        razon_social: '',
        direccion: '',

        errors: {
            fechaNoAccidentes: false,

            form: {
            fecha: false,
            descripcion: false,
            tipo_evento: false,

            muertes: false,

            nombre_ta: false,
            numero_autorizacion: false,
            lider: false
        },

        grupo: {
            nombre: false,
            puesto: false,
            especialidad: false
        },

        archivo026: false,
        archivoTercero: false

        },

        init(){
           
           this.modalInvestigacion = new bootstrap.Modal(document.getElementById('modalInvestigacion'));
           this.modalGrupo = new bootstrap.Modal(document.getElementById('modalGrupo'));
           this.modal026 = new bootstrap.Modal(document.getElementById('modal026'));
           this.modalTercero = new bootstrap.Modal(document.getElementById('modalTercero'));

           this.modalNoAccidentes = new bootstrap.Modal(document.getElementById('modalNoAccidentes'));

           this.incidentesAccidentes();
           this.noAccidentes();

        },

    //-------------------------------
    //---- Crear investigación de incidentes y accidentes
    async incidentesAccidentes(){

    this.loading = true;

    try {

        const { data } = await axios.get(
            '/sasisopa/investigacion-incidentes-accidentes/datatable'
        );

        if(data.success){

            this.incidentes = data.data;
        }

    } finally {

        this.loading = false;
    }
},

    validateInvestigacion(){

        let valid = true;

        Object.keys(this.errors.form)
            .forEach(key => {
                this.errors.form[key] = false;
            });

        if(!this.form.fecha){

            this.errors.form.fecha = true;
            valid = false;
        }

        if(!this.form.descripcion){

            this.errors.form.descripcion = true;
            valid = false;
        }

        if(!this.form.tipo_evento){

            this.errors.form.tipo_evento = true;
            valid = false;
        }

        // Validaciones para tercero autorizado

        if(
            this.form.contratar_tercero ||
            this.form.tipo_evento == '3'
        ){

            if(!this.form.nombre_ta){

                this.errors.form.nombre_ta = true;
                valid = false;
            }

            if(!this.form.numero_autorizacion){

                this.errors.form.numero_autorizacion = true;
                valid = false;
            }

            if(!this.form.lider){

                this.errors.form.lider = true;
                valid = false;
            }
        }

        return valid;
    },

    limpiarInvestigacion(){

        this.form = {

            fecha: '',
            descripcion: '',
            tipo_evento: '',

            muertes: 0,

            hubo_muertes: false,
            contratar_tercero: false,

            nombre_ta: '',
            numero_autorizacion: '',
            lider: ''
        };

        Object.keys(this.errors.form)
            .forEach(key => {
                this.errors.form[key] = false;
            });

    },

    openModalInvestigacion(){
    this.limpiarInvestigacion();
    this.modalInvestigacion.show();   
    },

    cambioTipoEvento() {

        this.form.hubo_muertes = false;
        this.form.contratar_tercero = false;

        this.form.nombre_ta = '';
        this.form.numero_autorizacion = '';
        this.form.lider = '';

        this.form.muertes = 0;

        if(this.form.tipo_evento == '3') {

            this.form.contratar_tercero = true;
        }
    },

    toggleMuertes() {

        if(this.form.hubo_muertes) {

            this.form.contratar_tercero = true;

        } else {

            this.form.contratar_tercero = false;

            this.form.nombre_ta = '';
            this.form.numero_autorizacion = '';
            this.form.lider = '';
        }
    },

    async guardarInvestigacion(){

    if(!this.validateInvestigacion()){

        this.notify(
            'error',
            'Completa todos los campos obligatorios'
        );

        return;
    }

    const payload = {

        ...this.form,

        muertes: Number(
            this.form.muertes || 0
        ),

        tercer_autorizado:
            this.form.contratar_tercero
    };

    const res = await this.createAction({

        url: '/sasisopa/investigacion-incidentes-accidentes/create',
        data: payload
    });

    if(res.success){

        this.modalInvestigacion.hide();

        this.limpiarInvestigacion();

        await this.incidentesAccidentes();
    }
    },

    async eliminarInvestigacion(id){
        const res = await this.deleteAction({
            url: '/sasisopa/investigacion-incidentes-accidentes/delete',
            id: id,
            name: id
        });

        if (res && res.success) {
            this.incidentesAccidentes();
        }
    },

    grupoInterdisciplinario(id){
    this.limpiarGrupo();
    this.idInvestigacion = id;
    this.cargarGrupo();
    this.modalGrupo.show();
    },

    limpiarGrupo(){

    this.grupo = {
        nombre: '',
        puesto: '',
        especialidad: ''
        };

        Object.keys(this.errors.grupo)
            .forEach(key => {
                this.errors.grupo[key] = false;
            });

    },

    async cargarGrupo(){
          try {

            const { data } = await axios.get(
                `/sasisopa/investigacion-incidentes-accidentes/grupo?id=${this.idInvestigacion}`
            );

            if (data.success) {
                this.grupo_personal = data.data;
            }

        } catch (error) {
            this.notify('error', 'Error al cargar asea');
        }
    },

    validateGrupo(){

        let valid = true;

        Object.keys(this.errors.grupo)
            .forEach(key => {
                this.errors.grupo[key] = false;
            });

        if(!this.grupo.nombre){

            this.errors.grupo.nombre = true;
            valid = false;
        }

        if(!this.grupo.puesto){

            this.errors.grupo.puesto = true;
            valid = false;
        }

        if(!this.grupo.especialidad){

            this.errors.grupo.especialidad = true;
            valid = false;
        }

        return valid;
    },

    async guardarPersonal(){

    if(!this.validateGrupo()){

        this.notify(
            'error',
            'Completa todos los campos obligatorios'
        );

        return;
    }

        const payload = {
        id: this.idInvestigacion,    
        ...this.grupo,

    };

    try {

        const res = await this.createAction({
                url: '/sasisopa/investigacion-incidentes-accidentes/grupo/create',
                data: payload
            });

            if (res.success) {
                await this.cargarGrupo();
                await this.incidentesAccidentes();
                this.limpiarGrupo();
            }


    } catch (error) {
        this.notify('error', 'Error al guardar');
    }

    },

    subir026(id){
        this.idInvestigacion = id;
        this.archivo026 = null;
        this.errors.archivo026 = false;
        document.getElementById('archivo026').value = '';
        this.modal026.show();
    },

    validate026() {
    this.errors.archivo026 = !this.archivo026;
    return !this.errors.archivo026;
    },

    async guardar026() {
  
    if (!this.validate026()) {
        return;
    }

    const payload = new FormData();

    payload.append(
        'id_investigacion',
        this.idInvestigacion
    );

    payload.append(
        'archivo',
        this.archivo026
    );

    const res = await this.createAction({
        url: '/sasisopa/investigacion-incidentes-accidentes/formato026',
        data: payload
    });

    if (res.success) {

        this.modal026.hide();

        await this.incidentesAccidentes();
    }
    },

    openModalTercero(item){
    this.idInvestigacion = item.id;
    
    this.idTercer = item.tercer_autorizado_detalle.id;
    this.nombreTercer = item.tercer_autorizado_detalle.nombre;
    this.numeroTercer = item.tercer_autorizado_detalle.numero;
    this.liderTercer = item.tercer_autorizado_detalle.lider;
    this.fechaTercer = item.tercer_autorizado_detalle.fecha_larga;
    this.uploadarchivoTercer = item.tercer_autorizado_detalle.archivo;

    this.archivoTercer = null;
    this.errors.archivoTercer = false;
    document.getElementById('archivoTercer').value = '';
    this.modalTercero.show();

    },

    validateTercer() {
    this.errors.archivoTercer = !this.archivoTercer;
    return !this.errors.archivoTercer;
    },

    async guardarTercero(){

       if (!this.validateTercer()) {
        return;
    }

    const payload = new FormData();

    payload.append(
        'id',
        this.idTercer
    );

    payload.append(
        'archivo',
        this.archivoTercer
    );

    const res = await this.createAction({
        url: '/sasisopa/investigacion-incidentes-accidentes/formatoTercer',
        data: payload
    });

    if (res.success) {

        this.uploadarchivoTercer = res.archivo;
        this.fechaTercer = res.fecha_larga;

        this.archivoTercer = null;
        document.getElementById('archivoTercer').value = '';

  
    }

    },


    //-------------------------------
    //---- Crear investigación de incidentes y accidentes
    
    //-------------------------------
    //---- Sin accidentes a la fecha

    async noAccidentes() {

        this.loading = true;

        try {

            const { data } = await axios.get(
                '/sasisopa/investigacion-incidentes-accidentes/no/datatableNoAccidentes'
            );

            if (data.success) {
                this.registros = data.data;
            }

        } finally {

            this.loading = false;
        }
    },

    async eliminarNo(id){

         const res = await this.deleteAction({
            url: '/sasisopa/investigacion-incidentes-accidentes/no/delete',
            id: id,
            name: id
        });

        if (res && res.success) {
            this.noAccidentes();
        }

    },

    openModalNoAccidentes() {

    this.modoModal = 'create';

    this.idNoAccidentes = null;

    this.fechaNoAccidentes =
        new Date().toISOString().split('T')[0];

    this.errors.fechaNoAccidentes = false;

    this.modalNoAccidentes.show();
    },

    editarNoAccidentes(item) {

    this.modoModal = 'edit';

    this.idNoAccidentes = item.id;

    this.nombre = item.usuario;

    this.fechaNoAccidentes = item.fecha;

    this.errors.fechaNoAccidentes = false;

    this.modalNoAccidentes.show();
    },

    validateEditar() {

        this.errors.fechaNoAccidentes = !this.fechaNoAccidentes;
        return !this.errors.fechaNoAccidentes;

    },

    async guardarNoAccidentes() {

    if (!this.validateEditar()) {

        this.notify(
            'error',
            'La fecha es obligatoria'
        );

        return;
    }

    try {

        const payload = {

            id: this.idNoAccidentes,
            fecha: this.fechaNoAccidentes
        };

        const url = this.idNoAccidentes
            ? '/sasisopa/investigacion-incidentes-accidentes/no/update'
            : '/sasisopa/investigacion-incidentes-accidentes/no/create';

        const res = await this.createAction({

            url,
            data: payload
        });

        if (res.success) {

            this.modalNoAccidentes.hide();

            this.idNoAccidentes = null;
            this.fechaNoAccidentes = '';

            await this.noAccidentes();
        }

    } catch (error) {

        this.notify(
            'error',
            this.idNoAccidentes
                ? 'Error al actualizar'
                : 'Error al guardar'
        );
    }
    }

    //-------------------------------
    //---- Sin accidentes a la fecha

    }));
});