document.addEventListener('alpine:init', () => {
   Alpine.data('fichaPersonalForm', () => ({

    // ======================
    // DATOS GENERALES
    // ======================
    id: null,
    nombre: '',
    domicilio: '',
    fechaNacimiento: '',
    estadoCivil: '',
    seguroSocial: '',
    telefono: '',
    email: '',

    errors: {
        nombre: false,
        domicilio: false,
        fechaNacimiento: false,
        estadoCivil: false,
        seguroSocial: false,
        telefono: false,
        email: false
    },

    // ======================
    // MODALES
    // ======================
    modals: {},

    // ======================
    // FAMILIARES
    // ======================
    familiares: [],
    familiar: {
        nombrecompleto: '',
        parentesco: '',
        domicilio: '',
        telefono: ''
    },

    errorsFamiliar: {
        nombrecompleto: false,
        parentesco: false,
        domicilio: false,
        telefono: false
    },

    // ======================
    // FORMACIÓN
    // ======================
    formaciones: [],
    formacion: {
        nivel: '',
        detalle: ''
    },

    errorsFormacion: {
        nivel: false,
        detalle: false
    },

    // EXPERIENCIA LABORAL
    editandoEmpresa: false,
    empresaId: null,
    experiencias: [],

    experiencia: {
        detalle: ''
    },

    errorsExperiencia: {
        detalle: false
    },

    empresas: [],

    empresa: {
        razon_social: '',
        puesto: '',
        periodo_inicio: '',
        periodo_fin: ''
    },

    errorsEmpresa: {
        razon_social: false,
        puesto: false,
        periodo_inicio: false
    },

    modalEmpresa: null,

    //FIRMA
    firmaPad: null,
    firmaBase64: '',
    firmaPreview: '',
    firmaError: false,

    // ======================
    // INIT
    // ======================
    init() {

        this.id = document.getElementById('container').dataset.id;

        this.$nextTick(() => {
            this.initFirma();
        });

        this.modals.familiar = new bootstrap.Modal(document.getElementById('modalFamiliar'));
        this.modals.formacion = new bootstrap.Modal(document.getElementById('modalFormacion'));
        this.modals.experiencia = new bootstrap.Modal(document.getElementById('modalExperiencia'));
        this.modals.empresa = new bootstrap.Modal(document.getElementById('modalEmpresa'));

        this.getFamiliares();
        this.getFormaciones();
        this.getExperiencias();
        this.getEmpresas();
    },

    // ======================
    // MODALES HELPERS
    // ======================
    openModal(nombre) {
        this.modals[nombre].show();
    },

    closeModal(nombre) {
        this.modals[nombre].hide();
    },

    // ======================
    // VALIDACIÓN GENERAL
    // ======================
    validar() {

        let valid = true;
        Object.keys(this.errors).forEach(k => this.errors[k] = false);

        if (!this.nombre.trim()) {
            this.errors.nombre = true;
            valid = false;
        }

        if (!this.domicilio.trim()) {
            this.errors.domicilio = true;
            valid = false;
        }

        if (!this.fechaNacimiento) {
            this.errors.fechaNacimiento = true;
            valid = false;
        }

        if (!this.estadoCivil) {
            this.errors.estadoCivil = true;
            valid = false;
        }

        if (!this.seguroSocial.trim()) {
            this.errors.seguroSocial = true;
            valid = false;
        }

        if (this.email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(this.email)) {
            this.errors.email = true;
            valid = false;
        }

        return valid;
    },

    // ======================
    // UPDATE USUARIO
    // ======================
    async actualizar() {

        if (!this.validar()) return;

        try {

            await this.createAction({
                url: '/sasisopa/competencia-personal-capacitacion-entrenamiento/update-ficha-personal',
                data: {
                    id: this.id,
                    nombre: this.nombre,
                    domicilio: this.domicilio,
                    fecha_nacimiento: this.fechaNacimiento,
                    estado_civil: this.estadoCivil,
                    seguro_social: this.seguroSocial,
                    telefono: this.telefono,
                    email: this.email
                }
            });

        } catch (e) {
            this.notify('error', 'Error al guardar');
        }
    },

    // ======================
    // FAMILIARES
    // ======================
    validarFamiliar() {

        let valid = true;
        Object.keys(this.errorsFamiliar).forEach(k => this.errorsFamiliar[k] = false);

        if (!this.familiar.nombrecompleto.trim()) {
            this.errorsFamiliar.nombrecompleto = true;
            valid = false;
        }

        if (!this.familiar.parentesco.trim()) {
            this.errorsFamiliar.parentesco = true;
            valid = false;
        }

        if (!this.familiar.domicilio.trim()) {
            this.errorsFamiliar.domicilio = true;
            valid = false;
        }

        if (!this.familiar.telefono.trim()) {
            this.errorsFamiliar.telefono = true;
            valid = false;
        }

        return valid;
    },

    async getFamiliares() {
        try {
            const res = await fetch(`/sasisopa/competencia-personal-capacitacion-entrenamiento/get-falimiares/${this.id}`);
            this.familiares = await res.json();
        } catch (e) {
            this.notify('error', 'Completa el campo');
        }
    },

    openModalFamiliar() {
        this.familiar = {
            nombrecompleto: '',
            parentesco: '',
            domicilio: '',
            telefono: ''
        };
        this.openModal('familiar');
    },

    async guardarFamiliar() {

        if (!this.validarFamiliar()) {
            this.notify('error', 'Completa los campos obligatorios');
            return;
        }

        try {

            const res = await this.createAction({
                url: '/sasisopa/competencia-personal-capacitacion-entrenamiento/create-dato-familiar',
                data: {
                    ...this.familiar,
                    id_usuario: this.id
                }
            });

            if (res.success) {
                await this.getFamiliares();
                this.closeModal('familiar');
            }

        } catch (e) {
            this.notify('error', 'Error al guardar');
        }
    },

    async eliminarFamiliar(id, name) {

        const res = await this.deleteAction({
            url: '/sasisopa/competencia-personal-capacitacion-entrenamiento/delete-dato-familiar',
            id: id,
            name: name
        });

        if (res && res.success) {
            await this.getFamiliares();
        }
    },

    // ======================
    // FORMACIÓN
    // ======================
    validarFormacion() {

        let valid = true;
        Object.keys(this.errorsFormacion).forEach(k => this.errorsFormacion[k] = false);

        if (!this.formacion.nivel.trim()) {
            this.errorsFormacion.nivel = true;
            valid = false;
        }

        if (!this.formacion.detalle.trim()) {
            this.errorsFormacion.detalle = true;
            valid = false;
        }

        return valid;
    },

    async getFormaciones() {
        try {
            const res = await fetch(`/sasisopa/competencia-personal-capacitacion-entrenamiento/get-formacion-academica/${this.id}`);
            this.formaciones = await res.json();
        } catch (e) {
            this.notify('error', 'Error al cargar formación académica');
        }
    },

    openModalFormacion() {
        this.formacion = { nivel: '', detalle: '' };
        this.openModal('formacion');
    },

    async guardarFormacion() {

        if (!this.validarFormacion()) {
            this.notify('error', 'Completa los campos');
            return;
        }

        try {

            const res = await this.createAction({
                url: '/sasisopa/competencia-personal-capacitacion-entrenamiento/create-formacion',
                data: {
                    ...this.formacion,
                    id_usuario: this.id
                }
            });

            if (res.success) {
                await this.getFormaciones();
                this.closeModal('formacion');
            }

        } catch (e) {
            this.notify('error', 'Error al guardar');
        }
    },

    async eliminarFormacion(id, name) {

        const res = await this.deleteAction({
            url: '/sasisopa/competencia-personal-capacitacion-entrenamiento/delete-formacion',
            id: id,
            name: name
        });

        if (res && res.success) {
            await this.getFormaciones();
        }
    },

    // ======================
    // EXPERIENCIA LABORAL
    // ======================

    validarExperiencia() {

        let valid = true;

        Object.keys(this.errorsExperiencia).forEach(k => this.errorsExperiencia[k] = false);

        if (!this.experiencia.detalle.trim()) {
            this.errorsExperiencia.detalle = true;
            valid = false;
        }

        return valid;
    },

    async getExperiencias() {

        try {
            const res = await fetch(`/sasisopa/competencia-personal-capacitacion-entrenamiento/get-experiencia/${this.id}`);
            this.experiencias = await res.json();
        } catch (e) {
            this.notify('error', 'Error al cargar experiencias');
        }
    },

    openModalExperiencia() {

        this.experiencia = { detalle: '' };

        this.openModal('experiencia');
    },

    async guardarExperiencia() {

        if (!this.validarExperiencia()) {
            this.notify('error', 'Completa el campo');
            return;
        }

        try {

            const res = await this.createAction({
                url: '/sasisopa/competencia-personal-capacitacion-entrenamiento/create-experiencia',
                data: {
                    ...this.experiencia,
                    id_usuario: this.id
                }
            });

            if (res.success) {
                await this.getExperiencias();
                this.closeModal('experiencia');
            }

        } catch (e) {
            this.notify('error', 'Error al guardar');
        }
    },

    async eliminarExperiencia(id, name) {

        const res = await this.deleteAction({
            url: '/sasisopa/competencia-personal-capacitacion-entrenamiento/delete-experiencia',
            id: id,
            name: name
        });

        if (res && res.success) {
            await this.getExperiencias();
        }
    },

    // ======================
    // EXPERIENCIA EMPRESA
    // ======================

    async getEmpresas() {
        try {
            const res = await fetch(`/sasisopa/competencia-personal-capacitacion-entrenamiento/get-experiencia-empresa/${this.id}`);
            this.empresas = await res.json();
        } catch (e) {
            this.notify('error', 'Error al cargar empresas');
        }
    },

    openModalEmpresa() {
        this.editandoEmpresa = false;
        this.empresaId = null;

        this.empresa = {
            razon_social: '',
            puesto: '',
            periodo_inicio: '',
            periodo_fin: ''
        };

        Object.keys(this.errorsEmpresa).forEach(k => this.errorsEmpresa[k] = false);

        this.openModal('empresa');
    },

    closeModalEmpresa() {
        this.closeModal('empresa');
    },

    validarEmpresa() {

        let valid = true;

        Object.keys(this.errorsEmpresa).forEach(k => this.errorsEmpresa[k] = false);

        if (!this.empresa.razon_social.trim()) {
            this.errorsEmpresa.razon_social = true;
            valid = false;
        }

        if (!this.empresa.puesto.trim()) {
            this.errorsEmpresa.puesto = true;
            valid = false;
        }

        if (!this.empresa.periodo_inicio) {
            this.errorsEmpresa.periodo_inicio = true;
            valid = false;
        }

        return valid;
    },

    async guardarEmpresa() {

        if (!this.validarEmpresa()) return;

        try {

            let url = this.editandoEmpresa
                ? '/sasisopa/competencia-personal-capacitacion-entrenamiento/update-experiencia-empresa'
                : '/sasisopa/competencia-personal-capacitacion-entrenamiento/create-experiencia-empresa';

            let data = {
                ...this.empresa,
                id_usuario: this.id
            };

            if (this.editandoEmpresa) {
                data.id = this.empresaId;
            }

            const res = await this.createAction({
                url,
                data
            });

            if (res.success) {
                await this.getEmpresas();
                this.closeModal('empresa');
            }

        } catch (e) {
            this.notify('error', 'Completa el campo');
        }
    },

    async eliminarEmpresa(id, name) {

        const res = await this.deleteAction({
            url: '/sasisopa/competencia-personal-capacitacion-entrenamiento/delete-experiencia-empresa',
            id: id,
            name: name
        });

        if (res && res.success) {
            await this.getEmpresas();
        }
    },

    formatearFecha(fecha) {

        if (
            !fecha ||
            fecha === '0000-00-00' ||
            fecha === '0000-00-00 00:00:00' ||
            fecha.includes('-000001') ||
            fecha.startsWith('0000')
        ) {
            return 'S/I';
        }

        let limpia = fecha.split('T')[0];

        const partes = limpia.split('-');

        if (partes.length !== 3) return 'S/I';

        const year = parseInt(partes[0]);
        const month = parseInt(partes[1]);
        const day = parseInt(partes[2]);

        if (!year || year < 1900 || month < 1 || month > 12 || day < 1 || day > 31) {
            return 'S/I';
        }

        const f = new Date(year, month - 1, day);

        if (isNaN(f.getTime())) return 'S/I';

        return f.toLocaleDateString('es-MX', {
            day: 'numeric',
            month: 'long',
            year: 'numeric'
        });
    },

    editarEmpresa(id) {

        const emp = this.empresas.find(e => e.id === id);

        if (!emp) return;

        this.empresaId = id;
        this.editandoEmpresa = true;

        this.empresa = {
            razon_social: emp.razon_social || '',
            puesto: emp.puesto || '',
            
            periodo_inicio: emp.periodo_inicio ? emp.periodo_inicio.split('T')[0] : '',
            periodo_fin: emp.periodo_fin ? emp.periodo_fin.split('T')[0] : ''
        };

        Object.keys(this.errorsEmpresa).forEach(k => this.errorsEmpresa[k] = false);

        this.openModal('empresa');
    },

    // ======================
    // FIRMA
    // ======================

        initFirma() {

        const canvas = this.$refs.canvas;

        const ratio = window.devicePixelRatio || 1;
        canvas.width = canvas.offsetWidth * ratio;
        canvas.height = canvas.offsetHeight * ratio;
        canvas.getContext("2d").scale(ratio, ratio);

        this.firmaPad = new SignaturePad(canvas, {
            minWidth: 1,
            maxWidth: 2.5,
            penColor: "black"
        });

    },

    limpiarFirma() {
        if (this.firmaPad) {
            this.firmaPad.clear();
        }
    },

    guardarFirma() {

        if (!this.firmaPad || this.firmaPad.isEmpty()) {
            this.notify('error', 'Firma vacía');
            return;
        }

        this.firmaBase64 = this.firmaPad.toDataURL('image/png');

        

        this.subirFirma();
    },

    async subirFirma() {

        try {

            const res = await this.createAction({
                url: '/sasisopa/competencia-personal-capacitacion-entrenamiento/update-firma',
                data: {
                    id: this.id,
                    firma: this.firmaBase64
                }
            });

            if (res.success) {
                this.limpiarFirma();
                this.firmaError = false;
                this.firmaPreview = this.firmaBase64;
            }

        } catch (e) {
            this.notify('error', 'Error al guardar firma');
        }
    }

   }));
});