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

                const modalEl = document.getElementById('nuevo');

                //quitar foco (error aria-hidden)
                document.activeElement.blur();

                //IMPORTANTE: esperar a que cierre
                modalEl.addEventListener('hidden.bs.modal', () => {

                    this.resetForm();

                }, { once: true });

                const modal = bootstrap.Modal.getInstance(modalEl);

                if (modal) {
                    modal.hide();
                }

            }

        } catch (error) {
                this.notify('error', 'Error al guardar');
            }
    }

    }));

});