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
        },

        init() {
            window.objetivosMetasIndicadores = this;
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

                console.log(data.objetivos)

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

            const modal = new bootstrap.Modal(document.getElementById('ObjetivosMetas'));
            modal.show();

        }

    }));
});