document.addEventListener('alpine:init', () => {
    Alpine.data('objetivosMetasIndicadoresForm', () => ({

        mode: 'create',
        editId: null,

        objetivosMetas: {
            // SATISFACCIÓN
            satisfaccion: {
                fecha: '',
                cumplimiento: '',
                accion: '',
                fecha_aplicacion: ''
            },

            // MANTENIMIENTO
            mantenimiento: {
                fecha: '',
                cumplimiento: '',
                accion: '',
                fecha_aplicacion: ''
            },

            // CAPACITACIÓN
            capacitacion: {
                fecha: '',
                cumplimiento: '',
                accion: '',
                fecha_aplicacion: ''
            },

            // QUEJAS
            quejas: {
                fecha: '',
                cumplimiento: '',
                accion: '',
                fecha_aplicacion: ''
            },

            // LEGISLACIÓN
            legislacion: {
                fecha: '',
                cumplimiento: '',
                accion: '',
                fecha_aplicacion: ''
            }
        },

        reporteIndicadores: {

            fecha: '',
            capacitacion: '',
            experiencia: '',
            ventas: '',
            medidas: '',
            fecha_aplicacion: ''
        },

        init() {
            window.objetivosMetasIndicadores = this;
        },

        errors: {
            reporteIndicadores: {
            fecha: false,
            capacitacion: false,
            experiencia: false,
            ventas: false,
            medidas: false,
            fecha_aplicacion: false
            }
        },

        validate() {
            Object.keys(this.errors.reporteIndicadores).forEach(k => this.errors.reporteIndicadores[k] = false);
            let valid = true;

            if (!this.reporteIndicadores.fecha) {
            this.errors.reporteIndicadores.fecha = true;
            valid = false;
            }

            if (!this.reporteIndicadores.capacitacion) {
            this.errors.reporteIndicadores.capacitacion = true;
            valid = false;
            }

            if (!this.reporteIndicadores.experiencia) {
            this.errors.reporteIndicadores.experiencia = true;
            valid = false;
            }

            if (!this.reporteIndicadores.ventas) {
            this.errors.reporteIndicadores.ventas = true;
            valid = false;
            }

            if (!this.reporteIndicadores.medidas) {
            this.errors.reporteIndicadores.medidas = true;
            valid = false;
            }

            if (!this.reporteIndicadores.fecha_aplicacion) {
            this.errors.reporteIndicadores.fecha_aplicacion = true;
            valid = false;
            }

            return valid;
        },

        resetObjetivosMetas() {
            this.objetivosMetas = {
                satisfaccion: { fecha:'', cumplimiento:'', accion:'', fecha_aplicacion:'' },
                mantenimiento: { fecha:'', cumplimiento:'', accion:'', fecha_aplicacion:'' },
                capacitacion: { fecha:'', cumplimiento:'', accion:'', fecha_aplicacion:'' },
                quejas: { fecha:'', cumplimiento:'', accion:'', fecha_aplicacion:'' },
                legislacion: { fecha:'', cumplimiento:'', accion:'', fecha_aplicacion:'' }
            };
        },

        resetReporteIndicadores(){
            this.reporteIndicadores = {

            fecha: '',
            capacitacion: '',
            experiencia: '',
            ventas: '',
            medidas: '',
            fecha_aplicacion: ''

            };

            Object.keys(this.errors.reporteIndicadores).forEach(key => {
            this.errors.reporteIndicadores[key] = false;
            });
        },

        openNuevoObjetivoMetas(){
            this.mode = 'create';
            this.editId = null;

            this.resetObjetivosMetas();

            const modal = new bootstrap.Modal(document.getElementById('ObjetivosMetas'));
            modal.show();
        },

        async openEditarObjetivoMetas(id){

                this.mode = 'edit';
                this.editId = id;

                const res = await fetch(`/sasisopa/objetivos-metas-indicadores/get-objetivos-metas/${id}`);
                const data = await res.json();

                if (!data.success) return;

                this.objetivosMetas = data.objetivos;

                const modal = new bootstrap.Modal(document.getElementById('ObjetivosMetas'));
                modal.show();
            },

        async submitObjetivosMetas(){

            let payload = {
                data: this.objetivosMetas
            };

            let url = '/sasisopa/objetivos-metas-indicadores/create-objetivos-metas';

            if (this.mode === 'edit') {
                url = `/sasisopa/objetivos-metas-indicadores/update-objetivos-metas/${this.editId}`;
            }

                try {

                const res = await this.createAction({
                    url,
                    data: payload,
                    table: '#table-seguimiento-objetivosmetas' 
                });

                if (res && res.success) {
                    this.resetObjetivosMetas();
                    bootstrap.Modal.getInstance(document.getElementById('ObjetivosMetas')).hide();
                }

            } catch (e) {
                 this.notify('error', 'Error al guardar');
            }
    
        },

        async openViewObjetivoMetas(id){
            this.mode = 'view';
            this.editId = id;

            const res = await fetch(`/sasisopa/objetivos-metas-indicadores/get-objetivos-metas/${id}`);
            const data = await res.json();

            if (!data.success) {
                this.notify('error', data.message);
                return;
            }

            this.objetivosMetas = data.objetivos;

            const modal = new bootstrap.Modal(document.getElementById('ObjetivosMetas'));
            modal.show();
        },

        async deleteObjetivoMetas(id){

            const res = await this.deleteAction({
                url: '/sasisopa/objetivos-metas-indicadores/delete-objetivos-metas',
                id,
                name: id,
                table: '#table-seguimiento-objetivosmetas'
            });

            if (res && res.success) {
                await this.refreshPermisosSelect();
                this.updateCumplimientoProgress(res.cumplimiento);
            }
            
        },

        openNuevoReporteIndicador(){

            this.mode = 'create';
            this.editId = null;
            this.resetReporteIndicadores();
            const modal = new bootstrap.Modal(document.getElementById('ReporteIndicadores'));
            modal.show();

        },

        async submitReporteIndicadores(){

            if (!this.validate()) {
                this.notify('error', 'Completa todos los campos obligatorios');
                return;
            }

            let payload = {
                data: this.reporteIndicadores
            };

            let url = '/sasisopa/objetivos-metas-indicadores/create-reporte-indicadores';

            if (this.mode === 'edit') {
                url = `/sasisopa/objetivos-metas-indicadores/update-reporte-indicadores/${this.editId}`;
            }

            try {

                const res = await this.createAction({
                    url,
                    data: payload,
                    table: '#table-seguimiento-indicadores'
                });

                if (res && res.success) {
                    this.resetReporteIndicadores();

                    bootstrap.Modal
                        .getInstance(document.getElementById('ReporteIndicadores'))
                        .hide();
                }

            } catch (e) {
                this.notify('error', 'Error al guardar');
            }

        },

        async openViewReporteIndicadores(id){

            this.mode = 'view';
            this.editId = id;

            const res = await fetch(`/sasisopa/objetivos-metas-indicadores/get-reporte-indicadores/${id}`);
            const data = await res.json();

            if (!data.success) {
                this.notify('error', data.message);
                return;
            }

            this.reporteIndicadores = data.data;

            const modal = new bootstrap.Modal(document.getElementById('ReporteIndicadores'));
            modal.show();
        },

        async openEditarReporteIndicadores(id){

             this.mode = 'edit';
            this.editId = id;

            const res = await fetch(`/sasisopa/objetivos-metas-indicadores/get-reporte-indicadores/${id}`);
            const data = await res.json();

            if (!data.success) {
                this.notify('error', data.message);
                return;
            }

            this.reporteIndicadores = data.data;

            const modal = new bootstrap.Modal(document.getElementById('ReporteIndicadores'));
            modal.show();

        },

        async deleteReporteIndicadores(id){

             const res = await this.deleteAction({
                url: '/sasisopa/objetivos-metas-indicadores/delete-reporte-indicadores',
                id,
                name: id,
                table: '#table-seguimiento-indicadores'
            });

            if (res && res.success) {
                await this.refreshPermisosSelect();
                this.updateCumplimientoProgress(res.cumplimiento);
            }

        }

    }));
});