document.addEventListener('alpine:init', () => {
    Alpine.data('proveedorEditarComponent', (proveedor) => ({
        guardando: false,
        idProveedor: proveedor?.id || 0,
        BASE_URL: '/departamento-operativo/almacen/proveedores',

        form: {
            folio: proveedor?.folio || '',
            Fecha: proveedor?.fecha_raw || '',
            RazonSocial: proveedor?.razon_social || '',
            ActividadEco: proveedor?.actividad_economica || '',
            Email: proveedor?.email || '',
            RFC: proveedor?.rfc || proveedor?.RFC || '',
            Ciudad: proveedor?.ciudad || '',
            Telefono1: proveedor?.telefono_1 || '',
            Telefono2: proveedor?.telefono_2 || '',
            Direccion: proveedor?.direccion || '',
            Beneficiario: proveedor?.beneficiario || '',
            Banco: proveedor?.banco || '',
            Metodopago: proveedor?.metodo_pago || '',
            CFDI: proveedor?.cfdi || '',
            Moneda: proveedor?.moneda || 'MXN',
            FormaPago: proveedor?.forma_pago || '',
            Descripcion: proveedor?.descripcion || ''
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
            Moneda: false,
            FormaPago: false,
            Descripcion: false
        },

        validar() {
            let hasError = false;
            const campos = [
                'Fecha', 'RazonSocial', 'ActividadEco', 'Email',
                'RFC', 'Ciudad', 'Telefono1', 'Direccion',
                'Beneficiario', 'Banco', 'Metodopago', 'CFDI',
                'Moneda', 'FormaPago', 'Descripcion'
            ];

            campos.forEach((campo) => {
                if (!this.form[campo] || !String(this.form[campo]).trim()) {
                    this.errors[campo] = true;
                    hasError = true;
                } else {
                    this.errors[campo] = false;
                }
            });

            if (hasError) {
                this.notify('error', 'Completa los campos obligatorios marcados en rojo.');
                return false;
            }

            return true;
        },

        async actualizarProveedor() {
            if (!this.validar()) return;

            this.guardando = true;
            const fd = new FormData();
            for (const key in this.form) {
                fd.append(key, this.form[key]);
            }

            try {
                const resp = await axios.post(`${this.BASE_URL}/editar-guardar/${this.idProveedor}`, fd);
                if (resp.data?.success) {
                    this.notify('success', resp.data.message || 'Proveedor actualizado exitosamente.');
                    setTimeout(() => {
                        window.location.href = this.BASE_URL;
                    }, 1200);
                } else {
                    this.notify('error', resp.data?.message || 'Error al actualizar el proveedor.');
                    this.guardando = false;
                }
            } catch (err) {
                console.error(err);
                this.notify('error', 'Error en el servidor al intentar actualizar el proveedor.');
                this.guardando = false;
            }
        }
    }));
});