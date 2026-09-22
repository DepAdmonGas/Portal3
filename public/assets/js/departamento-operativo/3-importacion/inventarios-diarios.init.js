document.addEventListener('alpine:init', function () {
    Alpine.data('inventariosDiariosInitComponent', () => ({
        base: '/departamento-operativo/importacion/inventarios-diarios',
        reportes: [],
        cargado: false,
        colores: {
            oct87: '#76bd1d',
            oct91: '#e21683',
            diesel: '#5e0f8e'
        },
        puedeCrear: false,
        puedeEditar: false,
        puedeEliminar: false,
        tituloLista: 'Reportes',
        tituloVacio: 'No hay información disponible para el período seleccionado.',

        init() {
            const el = this.$el;
            this.puedeCrear = el.dataset.puedeCrear === 'true';
            this.puedeEditar = el.dataset.puedeEditar === 'true';
            this.puedeEliminar = el.dataset.puedeEliminar === 'true';

            const template = el.dataset.yearMesTemplate || '';
            const sufijo = template ? ' ' + template : '';
            this.tituloLista = 'Reportes' + sufijo;
            this.tituloVacio = 'No hay información disponible para el período seleccionado.';

            try {
                this.reportes = JSON.parse(el.dataset.reportes || '[]') || [];
            } catch (e) {
                this.reportes = [];
            }
            this.cargado = true;
        },

        celdaTexto(valor) {
            const n = Number(valor);
            return n > 0 ? n : '';
        },

        colorCelda(valor, colorBase) {
            const n = Number(valor);
            if (!(n > 0)) return '';
            return n < 11
                ? 'background: #FFC300; color: #fff;'
                : 'background: ' + colorBase + '; color: #fff;';
        },

        nuevoReporte() {
            if (this.loading) return;
            this.loading = true;

            axios.post(this.base + '/crear', {}, {
                headers: { 'X-CSRF-TOKEN': this.csrf() }
            })
            .then(function (r) {
                this.loading = false;
                if (r.data.success) {
                    window.location.href = '/departamento-operativo/importacion/inventarios-diarios-reporte/' + r.data.id;
                } else {
                    Notify.error(r.data.message || 'Error al crear el reporte');
                }
            }.bind(this))
            .catch(function (err) {
                this.loading = false;
                Notify.error(this.errorMessage(err));
            }.bind(this));
        },

        eliminarReporte(id, fechaLabel) {
            if (this.loading) return;

            Swal.fire({
                title: '¿Eliminar Inventario?',
                text: 'El reporte del ' + fechaLabel + ' será eliminado',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#d33'
            }).then(function (result) {
                if (!result.isConfirmed) return;

                this.loading = true;
                axios.post(this.base + '/eliminar', { id }, {
                    headers: { 'X-CSRF-TOKEN': this.csrf() }
                })
                .then(function (r) {
                    this.loading = false;
                    if (r.data.success) {
                        this.reportes = this.reportes.filter(function (rep) {
                            return Number(rep.id) !== Number(id);
                        });
                        Notify.success(r.data.message || 'Inventario eliminado exitosamente');
                    } else {
                        Notify.error(r.data.message || 'Error al eliminar');
                    }
                }.bind(this))
                .catch(function (err) {
                    this.loading = false;
                    Notify.error(this.errorMessage(err));
                }.bind(this));
            }.bind(this));
        },

        csrf() {
            var meta = document.querySelector('meta[name="csrf-token"]');
            return meta ? meta.getAttribute('content') : '';
        },

        errorMessage(err) {
            return err.response && err.response.data && err.response.data.message
                ? err.response.data.message
                : 'Error de conexión';
        }
    }));
});

window.addEventListener('pageshow', function (event) {
    if (event.persisted) {
        window.location.reload();
    }
});