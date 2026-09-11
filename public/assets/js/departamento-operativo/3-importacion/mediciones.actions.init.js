document.addEventListener('alpine:init', () => {

    Alpine.data('medicionesComponent', () => ({

        puedeCrear: false,
        puedeEliminar: false,
        idUsuario: 0,
        esMultiestacion: false,
        moduleStationKey: 'mediciones',
        guardando: false,

        nuevoForm: { fecha: '', factura: '', neto: '', bruto: '', cuentaLitros: '', proveedor: '' },

        init() {
            const c = document.getElementById('container');
            if (c) {
                this.puedeCrear = c.dataset.puedeCrear === 'true';
                this.puedeEliminar = c.dataset.puedeEliminar === 'true';
                this.idUsuario = parseInt(c.dataset.idUsuario) || 0;
                this.esMultiestacion = c.dataset.multiestacion === 'true';
                this.moduleStationKey = c.dataset.moduleStationKey || 'mediciones';
            }

            document.addEventListener('eliminar-medicion', (e) => {
                this.confirmarEliminar(e.detail.id, e.detail.name);
            });
        },

        getSelector() {
            return document.getElementById('module-station-selector-' + this.moduleStationKey);
        },

        getContextoId() {
            if (this.esMultiestacion) {
                const sel = this.getSelector();
                if (sel && sel.value) {
                    return parseInt(sel.value.replace(/^(estacion_|depto_)/, '')) || 0;
                }
                return 0;
            }
            const c = document.getElementById('container');
            return parseInt(c.dataset.idEstacion || '0') || 0;
        },

        contextoEspecifico() {
            if (this.esMultiestacion) {
                const sel = this.getSelector();
                return !!(sel && sel.value);
            }
            const c = document.getElementById('container');
            return (parseInt(c.dataset.idEstacion || '0') || 0) > 0;
        },

        hoyISO() {
            const d = new Date();
            return d.getFullYear() + '-' +
                String(d.getMonth() + 1).padStart(2, '0') + '-' +
                String(d.getDate()).padStart(2, '0');
        },

        abrirNuevo() {
            if (!this.contextoEspecifico()) {
                this.notify('error', 'Selecciona una estación para registrar la medición.');
                return;
            }

            this.nuevoForm = {
                fecha: this.hoyISO(),
                factura: '',
                neto: '',
                bruto: '',
                cuentaLitros: '',
                proveedor: ''
            };

            new bootstrap.Modal(document.getElementById('modalNuevo')).show();
        },

        async guardarNuevo() {
            if (!this.getContextoId()) {
                this.notify('error', 'Selecciona una estación para registrar la medición.');
                return;
            }
            if (!this.nuevoForm.fecha) {
                this.notify('error', 'La fecha es obligatoria.');
                return;
            }
            if (!this.nuevoForm.factura.trim()) {
                this.notify('error', 'La factura es obligatoria.');
                return;
            }
            if (this.nuevoForm.neto === '' || isNaN(this.nuevoForm.neto)) {
                this.notify('error', 'El neto es obligatorio.');
                return;
            }
            if (this.nuevoForm.bruto === '' || isNaN(this.nuevoForm.bruto)) {
                this.notify('error', 'El bruto es obligatorio.');
                return;
            }
            if (this.nuevoForm.cuentaLitros === '' || isNaN(this.nuevoForm.cuentaLitros)) {
                this.notify('error', 'La cuenta de litros es obligatoria.');
                return;
            }
            if (!this.nuevoForm.proveedor) {
                this.notify('error', 'Selecciona un proveedor.');
                return;
            }

            this.guardando = true;

            try {
                const res = await this.createAction({
                    url: '/departamento-operativo/importacion/mediciones/store',
                    data: {
                        fecha: this.nuevoForm.fecha,
                        factura: this.nuevoForm.factura.trim(),
                        neto: this.nuevoForm.neto,
                        bruto: this.nuevoForm.bruto,
                        cuenta_litros: this.nuevoForm.cuentaLitros,
                        proveedor: this.nuevoForm.proveedor
                    },
                    table: '#tabla-mediciones',
                    notify: true
                });

                if (res && res.success) {
                    bootstrap.Modal.getInstance(document.getElementById('modalNuevo'))?.hide();
                    this.nuevoForm = { fecha: '', factura: '', neto: '', bruto: '', cuentaLitros: '', proveedor: '' };
                }
            } finally {
                this.guardando = false;
            }
        },

        async confirmarEliminar(id, name) {
            await this.deleteAction({
                url: '/departamento-operativo/importacion/mediciones/delete',
                id: id,
                name: name,
                table: '#tabla-mediciones'
            });
        }
    }));
});