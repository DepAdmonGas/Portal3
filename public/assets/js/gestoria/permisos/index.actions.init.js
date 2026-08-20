document.addEventListener('alpine:init', () => {

    Alpine.data('permisos', (idUsuarioInicial) => ({

        idUsuario: localStorage.getItem('gestoria_permisos_usuario')
            || idUsuarioInicial,

        vigencia: '',
        personal: [],

        usuario: null,

        estaciones: [],
        idEstacion: '',
        nombreEstacion: '',

        loading: false,

        error: null,

         historialForm: {
            id: null,
            fecha_emision: '',
            fecha_vencimiento: ''
        },
        historialErrors: {
            fecha_emision: false
        },

        historialTitle: '',

        showHistorialForm: false,
        historialRows: [],

        errors: {
            vigencia: false,
            fechaemision: false
        },

        init() {

            this.cargar();

            this.$watch('vigencia', value => {
                if (value) {
                    this.errors.vigencia = false;
                }

                this.updateFechaVencimiento();
            });

            this.$watch('historialForm.fecha_emision', value => {
                if (value) {
                    this.errors.vigencia = false;
                }

                this.updateHistorialFechaVencimiento();
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

        updateHistorialFechaVencimiento() {
            const baseDate = this.parseIsoDate(this.historialForm.fecha_emision);

            if (!baseDate || !this.historialVigencia) {
                this.historialForm.fecha_vencimiento = '';
                return;
            }

            const nextDate = new Date(baseDate.getTime());

            switch (this.historialVigencia) {
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
                    this.historialForm.fecha_vencimiento = '';
                    return;
                default:
                    this.historialForm.fecha_vencimiento = '';
                    return;
            }

            this.historialForm.fecha_vencimiento = this.formatIsoDate(nextDate);
        },


        async cargar() {

            this.loading = true;

            this.error = null;

            try {

                const params = new URLSearchParams({
                    idUsuario: this.idUsuario
                });

                const response = await fetch(
                    `/gestoria/permisos/table?${params.toString()}`
                );

                const json = await response.json();

                if (!response.ok || !json.success) {

                    throw new Error(
                        json.message ||
                        'No fue posible cargar la información.'
                    );

                }

                this.usuario = json.data.usuario;

                this.estaciones = json.data.estaciones;

                this.personal = json.data.personal;

            } catch (error) {

                console.error(error);

                this.error = error.message ||
                    'Ocurrió un error al cargar la información.';

            } finally {

                this.loading = false;

            }

        },


        async cambiarUsuario() {

            // Persistimos inmediatamente la selección
            localStorage.setItem(
                'gestoria_permisos_usuario',
                this.idUsuario
            );

            await this.cargar();

        },


        async openHistorial(id, title, vigencia, idEstacion, nombreEstacion) {
            this.historialCalendarioId = id;
            this.historialTitle = title || 'Historial';
            this.historialVigencia = vigencia;
            this.resetHistorialForm();

            this.nombreEstacion = nombreEstacion;
            this.idEstacion = idEstacion;

            try {
                const res = await fetch(`/sasisopa/requisitos-legales/historial/${id}?idEstacion=${this.idEstacion}`);
                const data = await res.json();

                if (!data.success) {
                    this.notify('error', data.message || 'No se pudo cargar el historial');
                    return;
                }

                this.historialRows = data.rows || [];

                const modal = new bootstrap.Modal(document.getElementById('modalHistorial'));
                modal.show();
            } catch (e) {
                this.notify('error', 'Error al cargar historial');
            }
        },

          resetHistorialForm() {
            this.historialForm = {
                id: null,
                fecha_emision: '',
                fecha_vencimiento: ''
            };

            this.historialErrors = {
                fecha_emision: false
            };

            if (this.$refs?.historialAcusePDF) {
                this.$refs.historialAcusePDF.value = '';
            }

            if (this.$refs?.historialRequisitoPDF) {
                this.$refs.historialRequisitoPDF.value = '';
            }
        },

        async submitHistorial() {
            if (!this.validateHistorialForm()) {
                this.notify('error', 'Completa los campos obligatorios');
                return;
            }

            if (!this.historialCalendarioId) {
                this.notify('error', 'No se encontró información');
                return;
            }

            const formData = new FormData();
            const acuseFile = this.$refs?.historialAcusePDF?.files?.[0] || null;
            const requisitoFile = this.$refs?.historialRequisitoPDF?.files?.[0] || null;

            formData.append('fecha_emision', this.historialForm.fecha_emision);
            formData.append('fecha_vencimiento', this.historialForm.fecha_vencimiento || '');
            formData.append('idEstacion', this.idEstacion || '');

            if (acuseFile) {
                formData.append('acuse_pdf', acuseFile);
            }

            if (requisitoFile) {
                formData.append('requisito_pdf', requisitoFile);
            }

            let url = `/sasisopa/requisitos-legales/historial/create/${this.historialCalendarioId}`;

            if (this.historialForm.id) {
                url = `/sasisopa/requisitos-legales/historial/update/${this.historialForm.id}`;
            }

            const res = await this.createAction({
                url,
                data: formData,
                table: null
            });

            if (res && res.success) {
                 this.showHistorialForm = false;
                this.historialRows = res.rows || [];
                this.reloadDetalleTable();
                this.updateCumplimientoProgress(res.cumplimiento);
                this.resetHistorialForm();
            }
        },

        editHistorialRow(row) {

            this.historialForm = { ...row };
            this.showHistorialForm = true;

            this.historialForm = {
                id: row.id,
                fecha_emision: row.fecha_emision_raw || '',
                fecha_vencimiento: row.fecha_vencimiento_raw || ''
            };

            this.historialErrors.fecha_emision = false;

            if (this.$refs?.historialAcusePDF) {
                this.$refs.historialAcusePDF.value = '';
            }

            if (this.$refs?.historialRequisitoPDF) {
                this.$refs.historialRequisitoPDF.value = '';
            }
        },

         async deleteHistorialRow(row) {
            const res = await this.deleteAction({
                url: '/sasisopa/requisitos-legales/historial/delete?idEstacion=${this.idEstacion}',
                id: row.id,
                name: this.historialTitle,
                table: null
            });

            if (res && res.success) {
                this.historialRows = res.rows || [];
                this.reloadDetalleTable();
                this.updateCumplimientoProgress(res.cumplimiento);
            }
        },

        validateHistorialForm() {
            this.historialErrors.fecha_emision = false;

            if (!this.historialForm.fecha_emision) {
                this.historialErrors.fecha_emision = true;
                return false;
            }

            return true;
        },

    }));

});