document.addEventListener('alpine:init', () => {

    Alpine.data('pivoteoComponent', () => ({

        puedeCrear: false,
        puedeEditar: false,
        puedeEliminar: false,
        estacionEspecifica: false,
        idUsuario: 0,
        idEstacion: 0,
        moduleStationKey: 'pivoteo',
        correoDefault: 'cambiosdedestinovdm@g500network.com',
        guardando: false,

        detalleData: {},
        detalleLoading: false,

        gmailId: 0,
        gmailHistorial: [],
        gmailForm: { correo: '', asunto: '', contenido: '' },

        init() {
            const c = document.getElementById('container');
            if (c) {
                this.puedeCrear = c.dataset.puedeCrear === 'true';
                this.puedeEditar = c.dataset.puedeEditar === 'true';
                this.puedeEliminar = c.dataset.puedeEliminar === 'true';
                this.idUsuario = parseInt(c.dataset.idUsuario) || 0;
                this.idEstacion = parseInt(c.dataset.idEstacion) || 0;
                this.moduleStationKey = c.dataset.moduleStationKey || 'pivoteo';
                this.correoDefault = c.dataset.correoDefault || 'cambiosdedestinovdm@g500network.com';
            }

            this.estacionEspecifica = this.contextoEspecifico();
            document.addEventListener('pivoteo-estacion-change', () => {
                this.estacionEspecifica = this.contextoEspecifico();
            });

            document.addEventListener('pivoteo-eliminar', (e) => {
                this.confirmarEliminar(e.detail.id, e.detail.name);
            });

            document.addEventListener('pivoteo-detalle', (e) => {
                this.abrirDetalle(e.detail.id);
            });

            document.addEventListener('pivoteo-gmail', (e) => {
                this.abrirGmail(e.detail.id, e.detail.nocontrol);
            });
        },

        getSelector() {
            return document.getElementById('module-station-selector-' + this.moduleStationKey);
        },

        contextoEspecifico() {
            const sel = this.getSelector();
            if (sel) {
                return !!sel.value;
            }
            return this.idEstacion > 0;
        },

        abrirNuevo() {
            if (!this.puedeCrear) return;
            if (!this.contextoEspecifico()) {
                this.notify('error', 'Selecciona una estación para registrar el pivoteo.');
                return;
            }
            this.guardando = true;
            this.createAction({
                url: '/departamento-operativo/importacion/pivoteo/crear',
                data: {},
                notify: true,
                onSuccess: (r) => {
                    if (r && r.id) {
                        window.location.href = '/departamento-operativo/importacion/pivoteo/' + r.id;
                    }
                }
            }).finally(() => { this.guardando = false; });
        },

        async confirmarEliminar(id, name) {
            if (!id) return;
            if (!this.puedeEliminar) {
                this.notify('error', 'No tienes permisos para eliminar registros.');
                return;
            }
            await this.deleteAction({
                url: '/departamento-operativo/importacion/pivoteo/eliminar',
                id: id,
                name: name || 'Registro',
                table: '#tabla-pivoteo'
            });
        },

        async abrirDetalle(id) {
            if (!id) return;
            this.detalleData = {};
            this.detalleLoading = true;
            bootstrap.Modal.getOrCreateInstance(document.getElementById('modalDetalle')).show();

            const res = await this.getAction({
                url: '/departamento-operativo/importacion/pivoteo-editar/' + id
            });

            if (res && res.success) {
                this.detalleData = res.data || {};
            } else {
                this.notify('error', (res && res.message) || 'No se pudo cargar el detalle.');
            }
            this.detalleLoading = false;
        },

        async abrirGmail(id, nocontrol) {
            if (!id) return;
            this.gmailId = id;
            this.gmailNocontrol = nocontrol || '';
            this.gmailHistorial = [];
            this.gmailForm.correo = this.correoDefault;
            this.gmailForm.asunto = 'Formato de Pivoteo';
            this.gmailForm.contenido = 'Envió formato de Pivoteo con número de folio: ' + this.gmailNocontrol;
            bootstrap.Modal.getOrCreateInstance(document.getElementById('modalGmail')).show();

            const res = await this.getAction({
                url: '/departamento-operativo/importacion/pivoteo-editar/' + id
            });

            if (res && res.success && res.data) {
                this.gmailHistorial = res.data.historial_correos || [];
            }
        },

        async guardarGmail() {
            if (this.guardando) return;
            if (!this.gmailForm.correo) {
                this.notify('error', 'El correo es obligatorio.');
                return;
            }
            if (!this.gmailForm.asunto) {
                this.notify('error', 'El asunto es obligatorio.');
                return;
            }

            this.guardando = true;
            try {
                const res = await this.createAction({
                    url: '/departamento-operativo/importacion/pivoteo/enviar-correo',
                    data: {
                        id: this.gmailId,
                        correo: this.gmailForm.correo,
                        asunto: this.gmailForm.asunto,
                        contenido: this.gmailForm.contenido
                    },
                    notify: true
                });

                if (res && res.success) {
                    if (Array.isArray(res.historial)) this.gmailHistorial = res.historial;
                    this.gmailForm.correo = this.correoDefault;
                    this.gmailForm.asunto = 'Formato de Pivoteo';
                    this.gmailForm.contenido = 'Envió formato de Pivoteo con número de folio: ' + this.gmailNocontrol;
                    if (window.tablaPivoteo) {
                        window.tablaPivoteo.ajax.reload(null, false);
                    }
                }
            } finally {
                this.guardando = false;
            }
        },

        formatoLitros(valor) {
            if (typeof window.formatNum === 'function') {
                return window.formatNum(valor, 2);
            }
            return parseFloat(valor || 0).toFixed(2);
        },

        tipoFirmaTexto(tipo) {
            if (tipo === 'A') return 'NOMBRE Y FIRMA DEL ENCARGADO';
            if (tipo === 'C') return 'NOMBRE Y FIRMA DE AUTORIZACIÓN';
            return 'Depto Operativo';
        }
    }));
});
