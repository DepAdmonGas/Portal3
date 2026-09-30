document.addEventListener('alpine:init', () => {
    Alpine.data('proveedorCrearComponent', () => ({
        guardando: false,
        form: {
            Fecha: new Date().toISOString().slice(0, 10),
            RazonSocial: '',
            ActividadEco: '',
            Email: '',
            RFC: '',
            Ciudad: '',
            Telefono1: '',
            Telefono2: '',
            Direccion: '',
            Beneficiario: '',
            Banco: '',
            Metodopago: '',
            CFDI: '',
            Moneda: 'MXN',
            FormaPago: '',
            Descripcion: '',
            FechaConstancia: new Date().toISOString().slice(0, 10),
            FechaCaratula: new Date().toISOString().slice(0, 10),
            ConstanciaS: null,
            CaratulaB: null
        },
        errors: {
            Fecha: false,
            RazonSocial: false,
            ActividadEco: false,
            Email: false,
            RFC: false,
            Ciudad: false,
            Telefono1: false,
            Direccion: false,
            Beneficiario: false,
            Banco: false,
            Metodopago: false,
            CFDI: false,
            FormaPago: false,
            Descripcion: false,
            FechaConstancia: false,
            FechaCaratula: false,
            ConstanciaS: false,
            CaratulaB: false
        },

        validar() {
            let hasError = false;
            const fields = [
                'Fecha', 'RazonSocial', 'ActividadEco', 'Email',
                'RFC', 'Ciudad', 'Telefono1', 'Direccion',
                'Beneficiario', 'Banco', 'Metodopago', 'CFDI',
                'FormaPago', 'Descripcion', 'FechaConstancia', 'FechaCaratula'
            ];

            fields.forEach((field) => {
                if (!this.form[field] || !String(this.form[field]).trim()) {
                    this.errors[field] = true;
                    hasError = true;
                } else {
                    this.errors[field] = false;
                }
            });

            if (!this.form.ConstanciaS) {
                this.errors.ConstanciaS = true;
                hasError = true;
            } else {
                this.errors.ConstanciaS = false;
            }

            if (!this.form.CaratulaB) {
                this.errors.CaratulaB = true;
                hasError = true;
            } else {
                this.errors.CaratulaB = false;
            }

            if (hasError) {
                this.notify('error', 'Completa los campos obligatorios marcados en rojo.');
                return false;
            }

            return true;
        },

        async guardarProveedor() {
            if (!this.validar()) return;

            this.guardando = true;
            const fd = new FormData();
            for (const key in this.form) {
                if (key !== 'ConstanciaS' && key !== 'CaratulaB') {
                    fd.append(key, this.form[key]);
                }
            }

            fd.append('ConstanciaS_file', this.form.ConstanciaS);
            fd.append('CaratulaB_file', this.form.CaratulaB);

            try {
                const resp = await axios.post('/departamento-operativo/almacen/proveedores/guardar', fd);
                if (resp.data?.success) {
                    this.notify('success', resp.data.message);
                    setTimeout(() => {
                        window.location.href = '/departamento-operativo/almacen/proveedores';
                    }, 1200);
                } else {
                    this.notify('error', resp.data?.message || 'Error al guardar el proveedor.');
                    this.guardando = false;
                }
            } catch (err) {
                console.error(err);
                this.notify('error', 'Error en el servidor al intentar registrar el proveedor.');
                this.guardando = false;
            }
        }
    }));
});