document.addEventListener('alpine:init', () => {
    Alpine.data('requisitosLegalesForm', () => ({
        permisos: [],
        permiso: '',
        vigencia: '',
        fechaemision: '',
        fechavencimiento: '',
        acusePDF: null,
        requisitoPDF: null,

        detalle: {},
        matriz: [],
        renovacion: [],
        historialRows: [],
        historialTitle: '',
        historialVigencia: '',
        historialCalendarioId: null,
        historialForm: {
            id: null,
            fecha_emision: '',
            fecha_vencimiento: ''
        },
        historialErrors: {
            fecha_emision: false
        },

        showHistorialForm: false,

        mode: 'create', // create | edit
        editId: null,

        loading: false,        

        errors: {
            permiso: false,
            vigencia: false,
            fechaemision: false
        },

        init() {

            if (!document.getElementById('table-lista-requisitos-legales-detalle')) {
                return;
            }

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


            this.$watch('historialForm.fecha_emision', value => {
                if (value) {
                    this.errors.vigencia = false;
                }

                this.updateHistorialFechaVencimiento();
            });

        },

        getContentContainer() {
            return document.getElementById('sgm-content') || document.getElementById('container');
        },

        getModuleKey() {
            return (document.getElementById('container')?.dataset?.moduleStationKey) || 'sasisopa';
        },

        getNgobierno() {
            return this.getContentContainer()?.dataset?.ngobierno;
        },

        getModulo() {
            return this.getContentContainer()?.dataset?.modulo;
        },

        buildUrl(path) {
            const sep = path.includes('?') ? '&' : '?';
            return `${path}${sep}module=${this.getModuleKey()}`;
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

        getMesesRenovacion() {
            return {
                enero: Boolean(this.$refs.ene?.checked),
                febrero: Boolean(this.$refs.feb?.checked),
                marzo: Boolean(this.$refs.mar?.checked),
                abril: Boolean(this.$refs.abr?.checked),
                mayo: Boolean(this.$refs.may?.checked),
                junio: Boolean(this.$refs.jun?.checked),
                julio: Boolean(this.$refs.jul?.checked),
                agosto: Boolean(this.$refs.ago?.checked),
                septiembre: Boolean(this.$refs.sep?.checked),
                octubre: Boolean(this.$refs.oct?.checked),
                noviembre: Boolean(this.$refs.nov?.checked),
                diciembre: Boolean(this.$refs.dic?.checked)
            };
        },

        updateCumplimientoProgress(cumplimiento) {
            const progressBar = document.getElementById('cumplimiento-progress-bar');
            const progressLabel = document.getElementById('cumplimiento-progress-label');

            if (!progressBar || !progressLabel) {
                return;
            }

            const value = Number(cumplimiento) || 0;

            progressBar.classList.remove('text-bg-success', 'text-bg-warning', 'text-bg-danger');

            if (value === 100) {
                progressBar.classList.add('text-bg-success');
            } else if (value >= 50) {
                progressBar.classList.add('text-bg-warning');
            } else {
                progressBar.classList.add('text-bg-danger');
            }

            progressBar.setAttribute('aria-valuenow', value);
            progressBar.style.width = `${value}%`;
            progressLabel.textContent = `Cumple ${value}%`;
        },

        async refreshPermisosSelect(currentId = null) {
            await this.getPermisos(currentId);
        },

        reloadDetalleTable() {
            if ($.fn.DataTable.isDataTable('#table-lista-requisitos-legales-detalle')) {
                $('#table-lista-requisitos-legales-detalle').DataTable().ajax.reload(null, false);
            }
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

            if (this.mode === 'create') {
                if (!this.fechaemision) {
                    this.errors.fechaemision = true;
                    valid = false;
                }
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
            
            ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'].forEach(ref => {
                if (this.$refs?.[ref]) {
                    this.$refs[ref].checked = false;
                }
            });

            if (this.$refs?.acusePDF) {
                this.$refs.acusePDF.value = '';
            }

            if (this.$refs?.requisitoPDF) {
                this.$refs.requisitoPDF.value = '';
            }

            this.mode = 'create';
            this.editId = null;

            Object.keys(this.errors).forEach(k => this.errors[k] = false);
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

        resetHistorialModal() {
            this.historialRows = [];
            this.historialTitle = '';
            this.historialCalendarioId = null;
            this.resetHistorialForm();
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

        async getPermisos(currentId = null) {

            const ngobierno = this.getNgobierno();
            const modulo = this.getModulo();

            let url = `/sasisopa/requisitos-legales/permisos/${ngobierno}/${modulo}`;

            if (currentId) {
                url += `/${currentId}`;
            }

            url = this.buildUrl(url);

            try {
                const res = await fetch(url);
                this.permisos = await res.json();

                this.$nextTick(() => {


                    if ($('#selectPermiso').hasClass("select2-hidden-accessible")) {
                        $('#selectPermiso').select2('destroy');
                    }

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


                $('#selectPermiso').on('change', (e) => {
                    this.permiso = e.target.value;
                    });

                    if (this.mode === 'edit') {
                        $('#selectPermiso')
                            .val(this.permiso)
                            .trigger('change');
                    }

                });

            } catch (e) {
                this.notify('error', 'Error al traer los permisos');
            }
        },

        async submit() {

            if (!this.validate()) {
                this.notify('error', 'Completa todos los campos obligatorios');
                return;
            }

            const ngobierno = this.getNgobierno();

            const formData = new FormData();

            const meses = this.getMesesRenovacion();
            const acuseFile = this.$refs?.acusePDF?.files?.[0] || null;
            const requisitoFile = this.$refs?.requisitoPDF?.files?.[0] || null;

            formData.append('nivel_gobierno', ngobierno);
            formData.append('permiso', this.permiso);
            formData.append('vigencia', this.vigencia);
            formData.append('fecha_emision', this.fechaemision);
            formData.append('fecha_vencimiento', this.fechavencimiento);

            if (acuseFile) {
                formData.append('acuse_pdf', acuseFile);
            }

            if (requisitoFile) {
                formData.append('requisito_pdf', requisitoFile);
            }

            Object.entries(meses).forEach(([mes, activo]) => {
                formData.append(mes, activo ? '1' : '0');
            });

            let url = '/sasisopa/requisitos-legales/create-permiso-detalle';

            if (this.mode === 'edit') {
                url = `/sasisopa/requisitos-legales/update-permiso-detalle/${this.editId}`;
            }

            url = this.buildUrl(url);

            try {
                const res = await this.createAction({
                    url,
                    data: formData,
                    table: '#table-lista-requisitos-legales-detalle'
                });

                if (res && res.success) {
                    await this.refreshPermisosSelect(this.mode === 'edit' ? this.permiso : null);
                    this.updateCumplimientoProgress(res.cumplimiento);
                    this.resetModal();
                }

            } catch (error) {
                this.notify('error', 'Error al guardar');
            }
        },

        async openDetalle(id) {
            try {
                const res = await fetch(this.buildUrl(`/sasisopa/requisitos-legales/detalle/${id}`));
                const data = await res.json();

                 if (!data.success) {
                    this.notify('error', 'No se encontró información');
                    return;
                }
               
                this.detalle = data.detalle;
                this.matriz = data.matriz;
                this.renovacion = data.renovacion || [];

                const modal = new bootstrap.Modal(document.getElementById('modalDetalle'));
                modal.show();

            } catch (e) {
                this.notify('error', 'Error al cargar detalle');
            }
        },

        openNuevo() {
            this.mode = 'create';
            this.editId = null;

            this.resetForm();
            this.refreshPermisosSelect();

            const modal = new bootstrap.Modal(document.getElementById('nuevo'));
            modal.show();
        },

        async handleDelete(id, name) {
            const res = await this.deleteAction({
                url: this.buildUrl('/sasisopa/requisitos-legales/delete-detalle'),
                id,
                name,
                table: '#table-lista-requisitos-legales-detalle'
            });

            if (res && res.success) {
                await this.refreshPermisosSelect();
                this.updateCumplimientoProgress(res.cumplimiento);
            }
        },

        async openHistorial(id, title, vigencia) {
            this.historialCalendarioId = id;
            this.historialTitle = title || 'Historial';
            this.historialVigencia = vigencia;
            this.resetHistorialForm();

            try {
                const res = await fetch(this.buildUrl(`/sasisopa/requisitos-legales/historial/${id}`));
                const data = await res.json();

                console.log(res)

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

        validateHistorialForm() {
            this.historialErrors.fecha_emision = false;

            if (!this.historialForm.fecha_emision) {
                this.historialErrors.fecha_emision = true;
                return false;
            }

            return true;
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

            url = this.buildUrl(url);

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

        async deleteHistorialRow(row) {
            const res = await this.deleteAction({
                url: this.buildUrl('/sasisopa/requisitos-legales/historial/delete'),
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

        async openEditar(id){
            try {
                const res = await fetch(this.buildUrl(`/sasisopa/requisitos-legales/detalle/${id}`));
                const data = await res.json();

                if (!data.success) {
                    this.notify('error', 'No se encontró información');
                    return;
                }

                this.mode = 'edit';
                this.editId = id;

                this.permiso = data.id_requisito_legal;
                this.vigencia = data.detalle.vigencia;
                this.fechaemision = data.detalle.fecha_emision || '';
                this.fechavencimiento = data.detalle.fecha_vencimiento || '';
                this.renovacion = data.renovacion || {};

                await this.getPermisos(this.permiso);

                this.$nextTick(() => {
                    Object.entries(this.renovacion).forEach(([mes, val]) => {
                        const ref = mes.substring(0,3);
                        if (this.$refs[ref]) {
                            this.$refs[ref].checked = val == 1;
                        }
                    });
                });

                const modal = new bootstrap.Modal(document.getElementById('nuevo'));
                modal.show();

            } catch (e) {
                this.notify('error', 'Error al cargar detalle');
            }
        }


    }));
});
