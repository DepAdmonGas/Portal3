document.addEventListener('alpine:init', () => {

    Alpine.data('medicionesComponent', () => ({

        puedeCrear: false,
        puedeEliminar: false,
        idUsuario: 0,
        esMultiestacion: false,
        moduleStationKey: 'mediciones',
        guardando: false,
        errors: { fecha: false, factura: false, neto: false, bruto: false, cuentaLitros: false, proveedor: false },

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

        formatInputNumber(value) {
            if (value === null || value === undefined || value === '') return '';
            const normalized = String(value).replace(/,/g, '').replace(/[^0-9.]/g, '');
            const parts = normalized.split('.');
            const integer = parts.shift() || '';
            const decimals = parts.join('');
            const grouped = integer.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
            return normalized.includes('.') ? grouped + '.' + decimals : grouped;
        },

        formatNumericInput(event, field) {
            const input = event.target;
            const raw = input.value;
            const cursor = input.selectionStart ?? raw.length;
            const digitsBeforeCursor = raw.slice(0, cursor).replace(/,/g, '').replace(/[^0-9.]/g, '').length;
            const normalized = raw.replace(/,/g, '').replace(/[^0-9.]/g, '');
            const parts = normalized.split('.');
            const integer = parts.shift() || '';
            const decimals = parts.join('');
            const formatted = normalized.includes('.')
                ? integer.replace(/\B(?=(\d{3})+(?!\d))/g, ',') + '.' + decimals
                : integer.replace(/\B(?=(\d{3})+(?!\d))/g, ',');

            this.nuevoForm[field] = normalized;
            this.errors[field] = false;
            input.value = formatted;

            let nextCursor = 0;
            let numericChars = 0;
            while (nextCursor < formatted.length && numericChars < digitsBeforeCursor) {
                if (/[0-9.]/.test(formatted[nextCursor])) numericChars++;
                nextCursor++;
            }
            input.setSelectionRange(nextCursor, nextCursor);
        },

        abrirNuevo() {
            if (!this.puedeCrear) return;
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
            this.errors = { fecha: false, factura: false, neto: false, bruto: false, cuentaLitros: false, proveedor: false };

            new bootstrap.Modal(document.getElementById('modalNuevo')).show();
        },

        async guardarNuevo() {
            if (!this.puedeCrear) return;
            if (!this.getContextoId()) {
                this.notify('error', 'Selecciona una estación para registrar la medición.');
                return;
            }

            this.errors = {
                fecha: !this.nuevoForm.fecha,
                factura: !this.nuevoForm.factura.trim(),
                neto: this.nuevoForm.neto === '' || !Number.isFinite(Number(this.nuevoForm.neto)) || Number(this.nuevoForm.neto) < 0,
                bruto: this.nuevoForm.bruto === '' || !Number.isFinite(Number(this.nuevoForm.bruto)) || Number(this.nuevoForm.bruto) < 0,
                cuentaLitros: this.nuevoForm.cuentaLitros === '' || !Number.isFinite(Number(this.nuevoForm.cuentaLitros)) || Number(this.nuevoForm.cuentaLitros) < 0,
                proveedor: !this.nuevoForm.proveedor
            };

            if (Object.values(this.errors).some(Boolean)) {
                this.notify('error', 'Completa los campos obligatorios.');
                return;
            }

            this.guardando = true;
            window.loader?.show();

            try {
                const res = await this.createAction({
                    url: '/departamento-operativo/importacion/mediciones/store',
                    data: {
                        fecha: this.nuevoForm.fecha,
                        factura: this.nuevoForm.factura.trim(),
                        neto: this.nuevoForm.neto.replace(/,/g, ''),
                        bruto: this.nuevoForm.bruto.replace(/,/g, ''),
                        cuenta_litros: this.nuevoForm.cuentaLitros.replace(/,/g, ''),
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
                window.loader?.hide();
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
