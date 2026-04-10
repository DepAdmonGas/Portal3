document.addEventListener('alpine:init', () => {
    Alpine.data('requisitosLegalesForm', () => ({
        permisos: [],
        permiso: '',
        vigencia: '',
        fechaemision: '',
        fechavencimiento: '',
        acusePDF: null,
        requisitoPDF: null,
        loading: false,

        errors: {
            permiso: false,
            vigencia: false,
            fechaemision: false
        },

        init() {
            window.requisitosInstance = this;
            this.bindModalSelect2({
                modalRef: 'modalNuevo',
                selectRef: 'selectPermiso',
                wrapperRef: 'permisoWrapper',
                model: 'permiso',
                namespace: 'requisitosLegales',
                options: {
                    placeholder: 'Seleccione'
                },
                onShown() {
                    if (!this.permisos.length) {
                        this.getPermisos();
                        return false;
                    }

                    return true;
                }
            });

            this.$watch('permiso', value => {
                if (value) {
                    this.errors.permiso = false;
                }
            });

            this.$watch('vigencia', value => {
                if (value) {
                    this.errors.vigencia = false;
                }

                this.updateFechaVencimiento();
            });

            this.$watch('fechaemision', () => {
                this.updateFechaVencimiento();
            });
        },

        parseIsoDate(value) {
            if (!value) {
                return null;
            }

            const parts = value.split('-').map(Number);

            if (parts.length !== 3 || parts.some(Number.isNaN)) {
                return null;
            }

            return new Date(parts[0], parts[1] - 1, parts[2]);
        },

        formatIsoDate(date) {
            if (!(date instanceof Date) || Number.isNaN(date.getTime())) {
                return '';
            }

            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');

            return `${year}-${month}-${day}`;
        },

        updateFechaVencimiento() {
            const baseDate = this.parseIsoDate(this.fechaemision);

            if (!baseDate || !this.vigencia) {
                this.fechavencimiento = '';
                return;
            }

            const nextDate = new Date(baseDate.getTime());

            switch (this.vigencia) {
                case 'Diario':
                    nextDate.setDate(nextDate.getDate() + 1);
                    break;
                case 'Trimestral':
                    nextDate.setMonth(nextDate.getMonth() + 3);
                    break;
                case 'Semestral':
                    nextDate.setMonth(nextDate.getMonth() + 6);
                    break;
                case 'Anual':
                    nextDate.setFullYear(nextDate.getFullYear() + 1);
                    break;
                case 'Bianual':
                    nextDate.setFullYear(nextDate.getFullYear() + 2);
                    break;
                case '3 años':
                    nextDate.setFullYear(nextDate.getFullYear() + 3);
                    break;
                case '5 años':
                    nextDate.setFullYear(nextDate.getFullYear() + 5);
                    break;
                case '10 años':
                    nextDate.setFullYear(nextDate.getFullYear() + 10);
                    break;
                case '30 años':
                    nextDate.setFullYear(nextDate.getFullYear() + 30);
                    break;
                case 'Permanente':
                case 'Cuando se realice cambio':
                case 'Mejora continua':
                    this.fechavencimiento = '';
                    return;
                default:
                    this.fechavencimiento = '';
                    return;
            }

            this.fechavencimiento = this.formatIsoDate(nextDate);
        },

        validate() {
            Object.keys(this.errors).forEach(k => this.errors[k] = false);
            let valid = true;

            if (!this.permiso) {
                this.errors.permiso = true;
                valid = false;
            }

            if (!this.vigencia) {
                this.errors.vigencia = true;
                valid = false;
            }

            if (!this.fechaemision) {
                this.errors.fechaemision = true;
                valid = false;
            }

            return valid;
        },

        resetForm() {
            this.permiso = '';
            this.vigencia = '';
            this.fechaemision = '';
            this.fechavencimiento = '';
            this.acusePDF = null;
            this.requisitoPDF = null;
            Object.keys(this.errors).forEach(k => this.errors[k] = false);
        },

        resetModal() {
            const modalEl = document.getElementById('nuevo');

            modalEl.addEventListener('hidden.bs.modal', () => {
                this.resetForm();
                document.body.focus();
            }, { once: true });

            if (document.activeElement) {
                document.activeElement.blur();
            }

            const modal = bootstrap.Modal.getInstance(modalEl);

            if (modal) {
                modal.hide();
            }
        },

        async getPermisos() {
            const ngobierno = document
                .getElementById('container')
                .dataset.ngobierno;

            try {
                const res = await fetch('/sasisopa/requisitos-legales/permisos/' + ngobierno + '/0');
                this.permisos = await res.json();

                this.$nextTick(() => {
                    const modalEl = this.$refs.modalNuevo || document.getElementById('nuevo');

                    if (modalEl && modalEl.classList.contains('show')) {
                        this.initModalSelect2({
                            modalRef: 'modalNuevo',
                            selectRef: 'selectPermiso',
                            wrapperRef: 'permisoWrapper',
                            model: 'permiso',
                            namespace: 'requisitosLegales',
                            options: {
                                placeholder: 'Seleccione'
                            }
                        });
                    }
                });
            } catch (e) {
                console.error(e);
            }
        },

        async submit() {
            if (!this.validate()) {
                this.notify('error', 'Completa todos los campos obligatorios');
                return;
            }
        }
    }));
});
