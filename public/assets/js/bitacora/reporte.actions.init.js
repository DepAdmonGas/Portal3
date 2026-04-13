document.addEventListener('alpine:init', () => {

    Alpine.data('reporteForm', () => ({

        fecha: '',
        documento: null,
        loading: false,

        errors: {
            fecha: false,
            documento: false
        },

        // CAPTURAR ARCHIVO
        handleFile(e) {
            this.documento = e.target.files[0];
            this.errors.documento = false;
        },

        // VALIDACIÓN
        validate() {
            this.errors.fecha = !this.fecha;
            this.errors.documento = !this.documento;

            if (this.errors.fecha || this.errors.documento) {
                this.notify('error', 'Completa los campos obligatorios');
                return false;
            }

            return true;
        },

        // RESET
        resetForm() {
        this.fecha = '';
        this.documento = null;

        this.errors = {
            fecha: false,
            documento: false
        };

        // LIMPIAR INPUT FILE REAL
        if (this.$refs.documento) {
            this.$refs.documento.value = null;
        }
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

        // SUBMIT
        async submit() {

        if (!this.validate()) return;

        let formData = new FormData();
        formData.append('fecha', this.fecha);
        formData.append('documento', this.documento);
        
        try {
        const res = await this.createAction({
            url: '/bitacora-aditivo/create-reporte',
            data: formData,
            table: '#table-aditivo-reporte'
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