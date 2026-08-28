document.addEventListener('alpine:init', () => {
    Alpine.data('auditoriaExterna', () => ({

         registros: [],
        loading: false,

        modalAgregar: null,
        auditor: '',

        errors: {
            auditor: false,
            archivo024: false,
            archivo025: false,
            comentarioAsea: false,
            archivoAsea: false
        },

        modalAsea: null,
        aseas: [],
        aseaAuditoriaId: null,
        aseaFormato: null,

        comentarioAsea: '',
        archivoAsea: null,

        init() {

        this.modalAgregar = new bootstrap.Modal(document.getElementById('modalAgregar'));
        this.modal024 = new bootstrap.Modal(document.getElementById('modal024'));
        this.modal025 = new bootstrap.Modal(document.getElementById('modal025'));
        this.modalAsea = new bootstrap.Modal(document.getElementById('modalAsea'));

        window.auditoriasExterna = this;

    },

    async listar() {

        if (window.table1) {

            window.table1.ajax.reload(null, false);
        }
    },

    abrirModalAgregar() {
        this.auditor = '';
        this.modalAgregar.show();
    },

    validate() {

        let valid = true;

        Object.keys(this.errors)
        .forEach(key => this.errors[key] = false);

        if (!this.auditor) {
        this.errors.auditor = true;
        valid = false;
        }

        return valid;
    },

    async guardarAuditoria() {

         if (!this.validate()) {

                this.notify(
                    'error',
                    'Debes de agregar los datos obligatorios'
                );
                return;
            }

    try {

        const payload = {
                auditor: this.auditor

            };

        let url = '/sasisopa/auditorias/externa/create';

        const res = await this.createAction({
                    url,
                    data: payload

                });

        if (res && res.success) {

            this.modalAgregar.hide();

            this.auditor = '';

            await this.listar();
        }

    } catch (error) {

        this.notify(
        'error',
        'Error al guardar'
        );
    }
    },

    async eliminar(id){

         const res = await this.deleteAction({
            url: '/sasisopa/auditorias/externa/delete',
            id: id,
            name: 'Auditoria Externa'
        });

        if (res && res.success) {
            await this.listar();
        }

    },

     //-------------------------------------

    subir024(id) {
    this.auditoriaSeleccionada = id;
    this.archivo024 = null;
    this.errors.archivo024 = false;
    document.getElementById('archivo024').value = '';
    this.modal024.show();
    },

    validate024() {
    this.errors.archivo024 = !this.archivo024;
    return !this.errors.archivo024;
    },

    async guardar024() {

  
    if (!this.validate024()) {
        return;
    }

    const payload = new FormData();

    payload.append(
        'id_auditoria',
        this.auditoriaSeleccionada
    );

    payload.append(
        'archivo',
        this.archivo024
    );

    const res = await this.createAction({
        url: '/sasisopa/auditorias/externa/formato024',
        data: payload
    });

    if (res.success) {

        this.modal024.hide();

        await this.listar();
    }
    },

     //-------------------------------------

    subir025(id) {
    this.hallazgoSeleccionada = id;
    this.archivo025 = null;
    this.errors.archivo025 = false;
    document.getElementById('archivo025').value = '';
    this.modal025.show();
    },

    validate025() {
    this.errors.archivo025 = !this.archivo025;
    return !this.errors.archivo025;
    },

    async guardar025() {

  
    if (!this.validate025()) {
        return;
    }

    const payload = new FormData();

    payload.append(
        'id_auditoria',
        this.hallazgoSeleccionada
    );

    payload.append(
        'archivo',
        this.archivo025
    );

    const res = await this.createAction({
        url: '/sasisopa/auditorias/externa/formato025',
        data: payload
    });

    if (res.success) {

        this.modal025.hide();

        await this.listar();
    }
    },

    //-------------------------------------

    abrirAsea(id, formato) {
    this.aseaAuditoriaId = id;
    this.aseaFormato = formato;

    this.comentarioAsea = '';
    this.archivoAsea = null;

    this.errors.comentarioAsea = false;
    this.errors.archivoAsea = false;

    document.getElementById('archivoAsea').value = '';

    this.cargarAsea();

    this.modalAsea.show();
    },

    async cargarAsea() {

        try {

            const { data } = await axios.get(
                `/sasisopa/auditorias/externa/asea?id=${this.aseaAuditoriaId}`
            );

            if (data.success) {
                this.aseas = data.data;
            }

        } catch (error) {
            this.notify('error', 'Error al cargar asea');
        }
    },

    validateAsea() {

        let valid = true;

        this.errors.comentarioAsea = !this.comentarioAsea;
        this.errors.archivoAsea = !this.archivoAsea;

        if (this.errors.comentarioAsea || this.errors.comentarioAsea) {
            valid = false;
        }

        return valid;
    },

    async guardarAsea() {

        if (!this.validateAsea()) {
            this.notify('error', 'Completa los campos obligatorios');
            return;
        }

        try {

            const payload = new FormData();

            payload.append('id', this.aseaAuditoriaId);
            payload.append('comentario', this.comentarioAsea);
            payload.append('archivo', this.archivoAsea);

            const res = await this.createAction({
                url: '/sasisopa/auditorias/externa/asea/create',
                data: payload
            });

            if (res.success) {

                this.comentarioAsea = '';
                this.archivoAsea = null;

                document.getElementById('archivoAsea').value = '';

                await this.cargarAsea();
                await this.listar();
            }

        } catch (error) {
            this.notify('error', 'Error al guardar');
        }
    },



    }));
});