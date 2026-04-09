document.addEventListener('alpine:init', () => {
    Alpine.data('requisitosLegalesForm', () => ({
        dependencias: [],
        dependencia: '',
        gobierno: '',
        permiso: '',
        fundamento: '',
        loading: false,

        errors: {
            gobierno: false,
            dependencia: false,
            permiso: false,
            fundamento: false
        },

        init() {
            window.requisitosInstance = this;
            this.bindModalSelect2({
                modalRef: 'modalNuevo',
                selectRef: 'selectDependencia',
                wrapperRef: 'dependenciaWrapper',
                model: 'dependencia',
                namespace: 'requisitosLegales',
                options: {
                    placeholder: 'Seleccione'
                },
                onShown() {
                    if (!this.dependencias.length) {
                        this.getDependencias();
                        return false;
                    }

                    return true;
                }
            });

            this.$watch('dependencia', value => {
                if (value) {
                    this.errors.dependencia = false;
                }
            });
        },

        validate() {

             Object.keys(this.errors).forEach(k => this.errors[k] = false);
             let valid = true;

            if (!this.gobierno) {
            this.errors.gobierno = true;
            valid = false;
            }

            if (!this.dependencia) {
            this.errors.dependencia = true;
            valid = false;
            }

            if (!this.permiso) {
            this.errors.permiso = true;
            valid = false;
            }

            if (!this.fundamento) {
            this.errors.fundamento = true;
            valid = false;
            }

             return valid;
        },

        resetForm() {

            this.gobierno = '';            
            this.dependencia = '';
            this.permiso = '';
            this.fundamento = '';
            Object.keys(this.errors).forEach(k => this.errors[k] = false);

        },

        resetModal(){

            const modalEl = document.getElementById('nuevo');

                    // evento al cerrar completamente
                    modalEl.addEventListener('hidden.bs.modal', () => {
                        this.resetForm();
                        document.body.focus(); // opcional (mejora accesibilidad)
                    }, { once: true });

                    // quitar foco ANTES de cerrar
                    if (document.activeElement) {
                        document.activeElement.blur();
                    }

                    const modal = bootstrap.Modal.getInstance(modalEl);

                    if (modal) {
                        modal.hide();
                    }

        },

        async getDependencias() {
            try {
                const res = await fetch('/sasisopa/requisitos-legales/dependencias');
                this.dependencias = await res.json();

                this.$nextTick(() => {
                    const modalEl = this.$refs.modalNuevo || document.getElementById('nuevo');

                    if (modalEl && modalEl.classList.contains('show')) {
                        this.initModalSelect2({
                            modalRef: 'modalNuevo',
                            selectRef: 'selectDependencia',
                            wrapperRef: 'dependenciaWrapper',
                            model: 'dependencia',
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

             try {
             const res = await this.createAction({
                url: '/sasisopa/requisitos-legales/create-configuracion',
                data: {
                    gobierno: this.gobierno,
                    dependencia: this.dependencia,
                    permiso: this.permiso,
                    fundamento: this.fundamento
                },
                    table: '#table-lista-requisitos-legales-configuracion'
            });

            if (res && res.success) {

                    this.resetModal();
                }

            
            } catch (error) {
                this.notify('error', 'Error al guardar');
            }


        }
    }));
});
