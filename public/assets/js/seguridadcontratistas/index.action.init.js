document.addEventListener('alpine:init', () => {
    Alpine.data('seguridadContratistas', () => ({

        mode: 'create',
        editId: null,

        modalRequisicion: null,
        requisicion: {       
        fecha: '',
        descripcion: '',
        justificacion: '',
        proveedor: ''
    },

    errors: {

        fecha: false,
        descripcion: false,
        justificacion: false
    },

    //----------------------------------------
    //  Formato 12

    modalFormato12: null,
    formato12Id: null,
    formato12: {
    id: null,
    id_requisicion: null,

    municipio: '',
    estado: '',
    dia: '',
    mes: '',
    year: '',

    trabajo_realizar: '',
    descripcion: '',
    area: '',

    fecha_inicio: '',
    fecha_termino: '',
    hora_inicio: '',
    hora_termino: '',

    cprtp: 0,
    cteppc: 0,

    nombre_empresa: '',
    nombre_responsable: '',

    procedimientos: [],
    trabajadores: [],
    encargados: [],
    encargadosList: []
    },

    trabajador: {
        nombre: '',
        puesto: '',
        no_seguro: ''
    },

    encargado: {
        id_personal: ''
    },

    procedimientos: [],

    trabajadores: [],

    encargados: [],

    //----------------------------------------------
    //-------- Formato 14

    formato14Modal : null,
    formato14: {
        id_requisicion: '',
        folio: '',
        fecha: '',
        nombre_solicitante: '',
        empresa: '',
        descripcion: '',
        justificacion: '',
        archivo: '',
        archivo_url: ''
    },


    //----------------------------------------------
    //-------- Formato 15

    formato15Modal: null,

    formato15: {
        id_requisicion: null,
        fecha_lv: '',
        hora_lv: '',
        pregunta1: 1,
        pregunta2: 1,
        pregunta3: 1,
        pregunta4: 1,
        pregunta5: 1,
        id_usuario: ''
    },

    supervisores: [],

    //---------------------------------------------------
    //-------- Carta Responsiva

    modalCartaResponsiva: null,
    cartaResponsiva: {

        id: null,
        id_requisicion: null,

        municipio: '',
        estado: '',

        dia: '',
        mes: '',
        year: '',

        representante_legal: '',
        razon_social: '',
        domicilio: '',

        apoderado_legal: ''
    },

    init(){

        window.seguridadContratistas = this;
        this.modalRequisicion = new bootstrap.Modal(document.getElementById('ModalRequisicionObra'));
        this.modalFormato12 = new bootstrap.Modal(document.getElementById('ModalFormato12'));
        this.formato14Modal = new bootstrap.Modal(document.getElementById('modalFormato14'));
        this.formato15Modal = new bootstrap.Modal(document.getElementById('modalFormato15'));
        this.modalCartaResponsiva = new bootstrap.Modal(document.getElementById('ModalCartaResponsiva'));

    },

    openModalRequisicion() {

    this.mode = 'create';

    this.editId = null;

    this.limpiarRequisicion();

    this.modalRequisicion.show();
    },

    openModalEditar(row) {

        this.mode = 'edit';

        this.editId = row.id;

        this.requisicion = {

            fecha: row.fecha,
            descripcion: row.descripcion,
            justificacion: row.justificacion,
            proveedor: row.proveedor
        };

        this.modalRequisicion.show();
    },

    validateRequisicion() {

        Object.keys(this.errors).forEach(
            key => this.errors[key] = false
        );

        let valid = true;

        if (!this.requisicion.fecha) {

            this.errors.fecha = true;
            valid = false;
        }

        if (!this.requisicion.descripcion) {

            this.errors.descripcion = true;
            valid = false;
        }

        if (!this.requisicion.justificacion) {

            this.errors.justificacion = true;
            valid = false;
        }

        return valid;
    },

    limpiarRequisicion() {

        this.mode = 'create';

        this.editId = null;

        Object.keys(this.errors)
            .forEach(
                key => this.errors[key] = false
            );

        this.requisicion = {
            fecha: '',
            descripcion: '',
            justificacion: '',
            proveedor: ''
        };
    },

    async guardarRequisicion() {

        if (!this.validateRequisicion()) {

            this.notify('error','Completa todos los campos obligatorios'
            );

            return;
        }

        const payload = {

            fecha: this.requisicion.fecha,
            descripcion: this.requisicion.descripcion,
            justificacion: this.requisicion.justificacion,
            proveedor: this.requisicion.proveedor
        };

        let url =
            '/sasisopa/seguridad-contratistas/create';

        if (this.mode === 'edit') {
            payload.id = this.editId;

            url = '/sasisopa/seguridad-contratistas/update';
        }

        try {

            const res = await this.createAction({
                    url,
                    data: payload,
                    table: '#table-seguridad-contratista'
                });

            if (!res?.success) {
                return;
            }

            this.limpiarRequisicion();
            this.modalRequisicion.hide();

        } catch (error) {

            this.notify('error','Error al guardar');
        }
    },

    async eliminar(id, name) {
            const res = await this.deleteAction({
                url: '/sasisopa/seguridad-contratistas/delete',
                id,
                name,
                table: '#table-seguridad-contratista'
            });

    },

    //----------------------------------------------------------------
    //----- Formato12 ----------------------

    async openModalFormato12(row) {
        try {
            const res = await fetch(`/sasisopa/seguridad-contratistas/formato12/${row.id}`);
            const json = await res.json();

            if (!json.success) {
                this.notify('error', json.message);
                return;
            }

            this.formato12 = {
                ...json.data,
                procedimientos: json.data.procedimientos ?? [],
                trabajadores: json.data.trabajadores ?? [],
                encargados: json.data.encargados ?? []
            };

            this.modalFormato12.show();

        } catch (e) {
            this.notify('error', 'Error cargando formato 12');
        }
    },

    async toggleProcedimiento(p, e) {
        const valor = e.target.checked ? 1 : 0;

        const old = p.valor;
        p.valor = valor;

        const res = await this.createAction({
            url: '/sasisopa/seguridad-contratistas/formato12/procedimiento/update',
            data: {
                id: p.id,
                valor
            }
        });

        if (!res?.success) {
            p.valor = old;
        }
    },

    async agregarTrabajador() {

        const payload = {
            id_formato: this.formato12.id,
            nombre: this.trabajador.nombre,
            puesto: this.trabajador.puesto,
            no_seguro: this.trabajador.no_seguro,
            categoria: 1
        };

        const res = await this.createAction({
            url: '/sasisopa/seguridad-contratistas/formato12/trabajador/create',
            data: payload
        });

        if (!res?.success) return;

        this.formato12.trabajadores.push(res.data);

        this.trabajador = {
            nombre: '',
            puesto: '',
            no_seguro: ''
        };
    },


    async agregarEncargado() {

        const payload = {
            id_formato: this.formato12.id,
            id_personal: this.encargado.id_personal,
            categoria: 2
        };

        const res = await this.createAction({
            url: '/sasisopa/seguridad-contratistas/formato12/encargado/create',
            data: payload
        });

        if (!res?.success) return;

        this.formato12.encargados.push(res.data);

        this.encargado.id_personal = '';
    },

    async eliminarTrabajador(id, name) {

        await this.deleteAction({
            url: '/sasisopa/seguridad-contratistas/formato12/trabajador/delete',
            id,
            name
        });

        this.formato12.trabajadores = this.formato12.trabajadores.filter(t => t.id !== id);
    },

    async guardarFormato12() {
        try {
            const res = await this.createAction({
                url: '/sasisopa/seguridad-contratistas/formato12/update',
                data: this.formato12
            });

            if (!res || !res.success) return;

            this.modalFormato12.hide();

        } catch (e) {
            this.notify('error', 'Error al guardar formato 12');
        }
    },

    async eliminarEncargado(id, name) {

        const ok = await this.deleteAction({
            url: '/sasisopa/seguridad-contratistas/formato12/encargado/delete',
            id,
            name
        });

        if (!ok) return;

        this.formato12.encargados = this.formato12.encargados.filter(t => t.id !== id);
    },

    //-----------------------------------------------------
    //------------- Formato 14

    async modalFormato14(id)
    {
        try {

            const response = await fetch(
                `/sasisopa/seguridad-contratistas/formato14/${id}`
            );

            const data = await response.json();

            if (!data.success) {
                return;
            }

            this.formato14 = data.formato;
            this.formato14.id_requisicion = id;
            this.formato14Archivo = null;
            this.formato14Modal.show();

        } catch (error) {

            this.notify('error', error?.message ??
            'Error al buscar');
        }
    },

    async guardarFormato14()
    {
        try {

            const formData = new FormData();

            formData.append('id_requisicion',this.formato14.id_requisicion);

            if (this.formato14Archivo) {

                formData.append('archivo',this.formato14Archivo);
            }

            const res =
                await this.createAction({
                    url: '/sasisopa/seguridad-contratistas/formato14/update',
                    data: formData,
                    table: '#table-seguridad-contratista'
                });

            if (!res?.success) {
                return;
            }

            this.formato14Archivo = null;
            this.formato14Modal.hide();


        } catch (error) {

            this.notify(
                'error',
                error?.message ??
                'Error al guardar archivo'
            );
        }
    },

    //-----------------------------------------------------
    //------------- Formato 15

    async modalFormato15(id)
    {
        try {

            const response = await fetch(
                `/sasisopa/seguridad-contratistas/formato15/${id}`
            );

            const data = await response.json();

            if (!data.success) {
                return;
            }

            this.supervisores = data.supervisores ?? [];

            this.formato15 = {
                ...data.formato,

                fecha_lv:
                    data.formato.fecha_lv
                        ? data.formato.fecha_lv.substring(0,10)
                        : '',

                id_usuario: null
            };

            await this.$nextTick();
            this.formato15.id_usuario = Number(data.formato.id_usuario);
            this.formato15Modal.show();

        } catch (error) {

            this.notify(
                'error',
                error.message
            );
        }
    },

    async guardarFormato15()
    {
        try {

            const res =
                await this.createAction({
                    url: '/sasisopa/seguridad-contratistas/formato15/update',
                    data: {
                        id_requisicion: this.formato15.id_requisicion,
                        fecha_lv: this.formato15.fecha_lv,
                        hora_lv: this.formato15.hora_lv,
                        pregunta1: this.formato15.pregunta1,
                        pregunta2: this.formato15.pregunta2,
                        pregunta3: this.formato15.pregunta3,
                        pregunta4: this.formato15.pregunta4,
                        pregunta5: this.formato15.pregunta5,
                        id_usuario: this.formato15.id_usuario
                    },
                    table: '#table-seguridad-contratista'
                });

            if (!res?.success) {
                return;
            }

            this.formato15Modal.hide();


        } catch (error) {

            this.notify(
                'error',
                error?.message ??
                'Error al guardar'
            );
        }
    },

    //-----------------------------------------------------
    //------------ Carta Responsiva

    async openCartaResponsiva(id) {

        try {

            const response = await fetch(`/sasisopa/seguridad-contratistas/carta-responsiva/id/${id}`);

            const data = await response.json();
            this.cartaResponsiva = data;
            this.modalCartaResponsiva.show();

        } catch (e) {

            this.notify(
                'error',
                'No fue posible cargar la información'
            );
        }
    },

    async guardarCartaResponsiva() {

        try {

            const res = await this.createAction({
                url: '/sasisopa/seguridad-contratistas/carta-responsiva/update',
                    data: this.cartaResponsiva,
                    table: '#table-seguridad-contratista'
                });

            if (!res?.success) {
                return;
            }

            this.modalCartaResponsiva.hide();

        } catch (error) {

            this.notify(
                'error',
                'Error al guardar'
            );
        }
    },

    }));
});