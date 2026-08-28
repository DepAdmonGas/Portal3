document.addEventListener('alpine:init', () => {
    Alpine.data('auditoriaInterna', () => ({

        registros: [],
        loading: false,

        modalAgregar: null,
        auditor: '',

        errors: {
            auditor: false,
            archivo024: false,
            archivo025: false,
            documentoAnexo: false,
            archivoAnexo: false
        },

        //-----------

        modal024: null,
        archivo024: null,
        auditoriaSeleccionada: null,

        //-----------

        modal025: null,
        archivo025: null,
        hallazgoSeleccionada: null,

        //------------------------------

        modalAnexos: null,
        anexos: [],
        anexoAuditoriaId: null,
        anexoFormato: null,

        documentoAnexo: '',
        archivoAnexo: null,

    init() {

        this.modalAgregar = new bootstrap.Modal(document.getElementById('modalAgregar'));
        this.modal024 = new bootstrap.Modal(document.getElementById('modal024'));
        this.modal025 = new bootstrap.Modal(document.getElementById('modal025'));
        this.modalAnexos = new bootstrap.Modal(document.getElementById('modalAnexos'));

        window.auditoriasInterna = this;

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

        let url = '/sasisopa/auditorias/interna/create';

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
            url: '/sasisopa/auditorias/interna/delete',
            id: id,
            name: 'Auditoria Interna'
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
        url: '/sasisopa/auditorias/interna/formato024',
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
        url: '/sasisopa/auditorias/interna/formato025',
        data: payload
    });

    if (res.success) {

        this.modal025.hide();

        await this.listar();
    }
    },

    //-------------------------------------

    abrirAnexos(id, formato) {
    this.anexoAuditoriaId = id;
    this.anexoFormato = formato;

    this.documentoAnexo = '';
    this.archivoAnexo = null;

    this.errors.documentoAnexo = false;
    this.errors.archivoAnexo = false;

    document.getElementById('archivoAnexo').value = '';

    this.cargarAnexos();

    this.modalAnexos.show();
    },

    async cargarAnexos() {

        try {

            const { data } = await axios.get(
                `/sasisopa/auditorias/interna/anexos?id=${this.anexoAuditoriaId}&formato=${this.anexoFormato}`
            );

            if (data.success) {
                this.anexos = data.data;
            }

        } catch (error) {
            this.notify('error', 'Error al cargar anexos');
        }
    },

    validateAnexo() {

        let valid = true;

        this.errors.documentoAnexo = !this.documentoAnexo;
        this.errors.archivoAnexo = !this.archivoAnexo;

        if (this.errors.documentoAnexo || this.errors.archivoAnexo) {
            valid = false;
        }

        return valid;
    },

    async guardarAnexo() {

        if (!this.validateAnexo()) {
            this.notify('error', 'Completa los campos obligatorios');
            return;
        }

        try {

            const payload = new FormData();

            payload.append('id', this.anexoAuditoriaId);
            payload.append('formato', this.anexoFormato);
            payload.append('documento', this.documentoAnexo);
            payload.append('archivo', this.archivoAnexo);

            const res = await this.createAction({
                url: '/sasisopa/auditorias/interna/anexos/create',
                data: payload
            });

            if (res.success) {

                this.documentoAnexo = '';
                this.archivoAnexo = null;

                document.getElementById('archivoAnexo').value = '';

                await this.cargarAnexos();
                await this.listar();
            }

        } catch (error) {
            this.notify('error', 'Error al guardar anexo');
        }
    },

    }));
});