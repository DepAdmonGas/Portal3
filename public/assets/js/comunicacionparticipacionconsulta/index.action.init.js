document.addEventListener('alpine:init', () => {
    Alpine.data('comunicacionParticipacionConsulta', () => ({

        mode: 'create',
        modalComunicacion: null,
        modalBuscar: null,
        modalQuejas: null,
        modalEvidencia: null,
        modalDetalleComunicacion: null,

        idComunicacion: null,
        pdfUrl: '/sasisopa/comunicacion-participacion-consulta/pdf-registro-comunicacion',
        year: '',

        errors: {
        year: false
        },

        //----- Registro atencion
        comie: {
        tema: '',
        detalle: '',
        tipo_comunicacion: '',
        material: '',
        dirigidoa: [],
        seguimiento: ''
        },

        errorscomie: {
        tema: false,
        detalle: false,
        tipo_comunicacion: false,
        material: false,
        dirigidoa: false,
        seguimiento: false
        },

         puestos: [],

         evidencia: {
            id_comunicacion: null,
            tema: ''
        },

        evidencias: [],

        detalle: {
            id: null,
            fecha: '',
            tema: '',
            detalle: '',
            encargado_comunicacion: '',
            tipo_comunicacion: '',
            material: '',
            seguimiento: '',
            dirigidoa: '',
            puestos: [],
            url: '',
            asistencia_url: ''
        },

        //---- Quejas y sugerencias

        qs: {
        fecha: '',
        nombre: '',
        motivos: '',
        dirigido: '',
        contacto: '',
        nombre_puesto: '',
        efectos: '',
        solucion: '',
        plazo: '',
        confirmacion: ''
        },

        errorsqs: {
        fecha: false,
        nombre: false,
        motivos: false,
        dirigido: false,
        contacto: false,
        nombre_puesto: false,
        efectos: false,
        solucion: false,
        plazo: false,
        confirmacion: false
        },

        init(){

            window.comunicacionParticipacionConsulta = this;
            this.modalComunicacion = new bootstrap.Modal(document.getElementById('modalComunicacion'));
            this.modalEvidencia = new bootstrap.Modal(document.getElementById('modalEvidencia'));
            this.modalBuscar = new bootstrap.Modal(document.getElementById('modalBuscar'));
            this.modalQuejas = new bootstrap.Modal(document.getElementById('modalQS'));
            this.modalDetalleComunicacion = new bootstrap.Modal(document.getElementById('modalDetalleComunicacion'));
            
        },

       //---- Comunicacion Interna y Externa

       validateComie(){

        Object.keys(this.errorscomie).forEach(k => {
            this.errorscomie[k] = false;
        });

        let valid = true;

        if (!this.comie.tema) {
            this.errorscomie.tema = true;
            valid = false;
        }

        if (!this.comie.detalle) {
            this.errorscomie.detalle = true;
            valid = false;
        }

        if (!this.comie.tipo_comunicacion) {
            this.errorscomie.tipo_comunicacion = true;
            valid = false;
        }

        if (!this.comie.material) {
            this.errorscomie.material = true;
            valid = false;
        }

        // Interna requiere dirigidoa
        if (
            this.comie.tipo_comunicacion === 'Interna' &&
            this.comie.dirigidoa.length === 0
        ) {

            this.errorscomie.dirigidoa = true;
            valid = false;
        }

        // Externa requiere seguimiento
        if (
            this.comie.tipo_comunicacion === 'Externa' &&
            !this.comie.seguimiento
        ) {

            this.errorscomie.seguimiento = true;
            valid = false;
        }

        return valid;
    },

       
        limpiarModalComunicacion(){

            this.comie = {
                tema: '',
                detalle: '',
                tipo_comunicacion: '',
                material: '',
                dirigidoa: [],
                seguimiento: ''
            };

            Object.keys(this.errorscomie).forEach(key => {
                this.errorscomie[key] = false;
            });

            $('#dirigidoa')
                .val(null)
                .trigger('change');
        },

       openModalComunicacion(){
            this.mode = 'create';
            this.id = null;

            this.limpiarModalComunicacion();
            this.modalComunicacion.show();
            this.getPuestos();
       },

        async getPuestos() {

            try {

                const res = await axios.get(
                    '/puestos/get-puestos'
                );

                this.puestos = res.data;

                 this.$nextTick(() => {
                    this.initSelect2();
                });

            } catch (e) {

                this.notify('error', 'Error al obtener puestos');
            }
        },

       initSelect2() {

        const vm = this;

        $('#dirigidoa').select2({
            placeholder: 'Selecciona',
            width: '100%',
            dropdownParent: $('#modalComunicacion')
        });

        // iniciar bloqueado
        $('#dirigidoa').prop(
            'disabled',
            vm.comie.tipo_comunicacion !== 'Interna'
        );

        $('#dirigidoa').on('change', function () {

            vm.comie.dirigidoa = $(this).val();

        });

    },

        tipoComunicacion() {

            const disabled =
                this.comie.tipo_comunicacion !== 'Interna';

            // bloquear/desbloquear select2
            $('#dirigidoa')
                .prop('disabled', disabled);

            // refrescar select2
            $('#dirigidoa').trigger('change.select2');

            // limpiar cuando NO sea interna
            if (disabled) {

                this.comie.dirigidoa = [];

                $('#dirigidoa')
                    .val(null)
                    .trigger('change');
            }
        },

       async guardarComunicacion(){

        if (!this.validateComie()) {

            this.notify(
                'error',
                'Completa todos los campos obligatorios'
            );

            return;
        }

        try {

            const res = await this.createAction({

                url: '/sasisopa/comunicacion-participacion-consulta/create-registro-comunicacion',

                data: {
                    tema: this.comie.tema,
                    detalle: this.comie.detalle,
                    tipo_comunicacion: this.comie.tipo_comunicacion,
                    material: this.comie.material,
                    dirigidoa: this.comie.dirigidoa,
                    seguimiento: this.comie.seguimiento
                },

                table: '#table-registro-comunicacion'
            });

            if (res.success) {

                this.limpiarModalComunicacion();
                this.modalComunicacion.hide();
            }

        } catch (e) {

            this.notify(
                'error',
                'Error al guardar'
            );
        }
    },

    async editarComunicacion(row) {

        this.mode = 'edit';
        this.idComunicacion = row.id;

        await this.getPuestos();

        this.comie = {
            tema: row.tema ?? '',
            detalle: row.detalle ?? '',
            tipo_comunicacion: row.tipo_comunicacion ?? '',
            material: row.material ?? '',
            dirigidoa: row.dirigidoa
                ? row.dirigidoa.split(',')
                : [],
            seguimiento:
                row.seguimiento && row.seguimiento !== 'S/I'
                    ? row.seguimiento
                    : ''
        };

        this.modalComunicacion.show();

        this.$nextTick(() => {

            $('#dirigidoa')
                .val(this.comie.dirigidoa)
                .trigger('change');

            this.tipoComunicacion();
        });
    },

    async updateComunicacion() {

    if (!this.validateComie()) {

        this.notify(
            'error',
            'Completa todos los campos obligatorios'
        );

        return;
    }

    try {

        const res = await this.createAction({

            url: '/sasisopa/comunicacion-participacion-consulta/update-registro-comunicacion',

            data: {
                id: this.idComunicacion,
                tema: this.comie.tema,
                detalle: this.comie.detalle,
                tipo_comunicacion: this.comie.tipo_comunicacion,
                material: this.comie.material,
                dirigidoa: this.comie.dirigidoa,
                seguimiento: this.comie.seguimiento
            },

            table: '#table-registro-comunicacion'
        });

        if (res.success) {

            this.modalComunicacion.hide();
            this.limpiarModalComunicacion();
        }

    } catch (e) {

        this.notify(
            'error',
            'Error al guardar'
        );
    }
},

    async eliminarComunicacion(id, name) {

        const res = await this.deleteAction({

            url: '/sasisopa/comunicacion-participacion-consulta/delete-registro-comunicacion',

            id: id,
            name: name,

            table: '#table-registro-comunicacion'
        });

    },

    //------------ Evidencia

    async openModalEvidencia(id, tema) {

        this.evidencia.id_comunicacion = id;
        this.evidencia.tema = tema;

        this.modalEvidencia.show();

        await this.obtenerEvidencias();
    },

    async obtenerEvidencias() {

        try {

            const res = await axios.get(
                `/sasisopa/comunicacion-participacion-consulta/get-evidencias/${this.evidencia.id_comunicacion}`
            );

            this.evidencias = res.data;

        } catch (e) {

            this.notify(
                'error',
                'Error al obtener evidencias'
            );
        }
    },

    async guardarEvidencia() {

        const file =
            this.$refs?.fileEvidencia?.files?.[0] || null;

        if (!file) {

            this.notify(
                'error',
                'Selecciona una imagen'
            );

            return;
        }

        const formData = new FormData();

        formData.append(
            'id_comunicacion',
            this.evidencia.id_comunicacion
        );

        formData.append(
            'evidencia',
            file
        );

        try {

            const res = await this.createAction({

                url: '/sasisopa/comunicacion-participacion-consulta/create-evidencia',

                data: formData
            });

            if (res.success) {

                this.$refs.fileEvidencia.value = '';
                await this.obtenerEvidencias();
            }

        } catch (e) {

            this.notify(
                'error',
                'Error al guardar evidencia'
            );
        }
    },

    async eliminarEvidencia(id) {

         try {

            const res = await this.deleteAction({

                url: '/sasisopa/comunicacion-participacion-consulta/delete-evidencia',

                id: id,
                name: 'evidencia'
            });

            if (res.success) {

                await this.obtenerEvidencias();
            }

        } catch (e) {

            this.notify(
                'error',
                'Error al eliminar evidencia'
            );
        }
    },

    //----------------- Detalle

    formatFecha(fecha) {

    if (!fecha) {
        return 'S/I';
    }

    return new Date(fecha).toLocaleDateString('es-MX', {
        day: 'numeric',
        month: 'long',
        year: 'numeric'
    });
},

limpiarDetalle() {

    this.detalle = {
        id: null,
        fecha: '',
        tema: '',
        detalle: '',
        encargado_comunicacion: '',
        tipo_comunicacion: '',
        material: '',
        seguimiento: '',
        dirigidoa: '',
        puestos: [],
        url: '',
        asistencia_url: ''
    };
},

async openModalDetalle(id) {

    try {

        const res = await axios.get(
            `/sasisopa/comunicacion-participacion-consulta/get-detalle-comunicacion/${id}`
        );

        if (!res.data.success) {

            this.notify(
                'error',
                res.data.message || 'No se pudo obtener el detalle'
            );

            return;
        }

        this.detalle = res.data.data;

        this.modalDetalleComunicacion.show();

    } catch (error) {

        this.notify(
            'error',
            'Error al obtener detalle'
        );
    }
},

    //---------------- Buscar

       openModalBuscar(){
            this.mode = 'search';
            this.id = null;
            this.modalBuscar.show();
       },

       resetBusqueda(){

            this.year = '';

            table1.ajax.url(
                '/sasisopa/comunicacion-participacion-consulta/datatable-registro-comunicacion'
            ).load();

            this.pdfUrl = '/sasisopa/comunicacion-participacion-consulta/pdf-registro-comunicacion';

            this.limpiarBusqueda();
        },

       limpiarBusqueda(){
            this.year = '';
            Object.keys(this.errors).forEach(key => this.errors[key] = false);
        },

       validateBusqueda() {
            Object.keys(this.errors).forEach(k => this.errors[k] = false);
            let valid = true;

            if (!this.year) {
            this.errors.year = true;
            valid = false;
            }

            return valid;
        },

        buscarYear(){   

             if (!this.validateBusqueda()) {
                this.notify('error', 'Completa todos los campos obligatorios');
                return;
            }

            // RECARGAR DATATABLE
            table1.ajax.url(
                `/sasisopa/comunicacion-participacion-consulta/datatable-registro-comunicacion?year=${this.year}`
            ).load();

            this.pdfUrl = `/sasisopa/comunicacion-participacion-consulta/pdf-registro-comunicacion?year=${this.year}`;

            // CERRAR MODAL
            bootstrap.Modal
                .getInstance(document.getElementById('modalBuscar'))
                .hide();
            
        },


       //---- Quejas y Sugerenias

        openModalQS(){
            this.mode = 'create';
            this.id = null;

            this.limpiarQS();
            this.modalQuejas.show();
       },

       validateQS() {

            Object.keys(this.errorsqs).forEach(k => {
                this.errorsqs[k] = false;
            });

            let valid = true;

            if (!this.qs.fecha) {
                this.errorsqs.fecha = true;
                valid = false;
            }

            if (!this.qs.nombre) {
                this.errorsqs.nombre = true;
                valid = false;
            }

            if (!this.qs.motivos) {
                this.errorsqs.motivos = true;
                valid = false;
            }

            if (!this.qs.dirigido) {
                this.errorsqs.dirigido = true;
                valid = false;
            }

            if (!this.qs.contacto) {
                this.errorsqs.contacto = true;
                valid = false;
            }

            if (!this.qs.nombre_puesto) {
                this.errorsqs.nombre_puesto = true;
                valid = false;
            }

            if (!this.qs.efectos) {
                this.errorsqs.efectos = true;
                valid = false;
            }

            if (!this.qs.solucion) {
                this.errorsqs.solucion = true;
                valid = false;
            }

            if (!this.qs.plazo) {
                this.errorsqs.plazo = true;
                valid = false;
            }

            if (!this.qs.confirmacion) {
                this.errorsqs.confirmacion = true;
                valid = false;
            }

            return valid;
        },

        limpiarQS(){

            this.qs = {
                fecha: '',
                nombre: '',
                motivos: '',
                dirigido: '',
                contacto: '',
                nombre_puesto: '',
                efectos: '',
                solucion: '',
                plazo: '',
                confirmacion: ''
            };

            Object.keys(this.errorsqs)
                .forEach(key => this.errorsqs[key] = false);
        },

        async guardarQS(){

             if (!this.validateQS()) {
                this.notify('error', 'Completa todos los campos obligatorios');
                return;
            }

             try {

            const res = await this.createAction({
                url: '/sasisopa/comunicacion-participacion-consulta/create-quejas-sugerencias',
                data: {
                    fecha: this.qs.fecha,
                    nombre: this.qs.nombre,
                    motivos: this.qs.motivos,
                    dirigido: this.qs.dirigido,
                    contacto: this.qs.contacto,
                    nombre_puesto: this.qs.nombre_puesto,
                    efectos: this.qs.efectos,
                    solucion: this.qs.solucion,
                    plazo: this.qs.plazo,
                    confirmacion: this.qs.confirmacion
                },
                    table: '#table-quejas-sugerencia'
            });

           
              if (res.success) {
                    this.limpiarQS();
                    this.modalQuejas.hide();
             }  

        } catch (e) {

            this.notify(
                'error',
                'Error al guardar'
            );
        }

        },

        async eliminarQS(id, name) {

        const res = await this.deleteAction({
            url: '/sasisopa/comunicacion-participacion-consulta/delete-quejas-sugerencias',
            id: id,
            name: name,
            table: '#table-quejas-sugerencia'
        });
    }       

    }));
});