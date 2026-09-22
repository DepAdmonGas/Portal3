document.addEventListener('alpine:init', function () {
    Alpine.data('inventariosReporteComponent', () => ({
        base: '/departamento-operativo/importacion/inventarios-diarios',
        idReporte: 0,
        fecha: '',
        sucursales: [],
        guardando: false,
        _saveTimers: {},
        puedeCrear: false,
        puedeEditar: false,
        form: {
            Sucursal: '',
            Destino1: '',
            Oct871: '',
            Oct911: '',
            Diesel1: '',
            Destino2: '',
            Oct872: '',
            Oct912: '',
            Diesel2: ''
        },
        init() {
            const el = this.$el;
            this.idReporte = parseInt(el.dataset.idReporte || '0', 10) || 0;
            this.puedeCrear = el.dataset.puedeCrear === 'true';
            this.puedeEditar = el.dataset.puedeEditar === 'true';

            let detalle = {};
            try {
                detalle = JSON.parse(el.dataset.reporte || '{}') || {};
            } catch (e) {
                detalle = {};
            }

            this.fecha = (detalle.fecha && detalle.fecha !== '0000-00-00') ? detalle.fecha : '';
            this.sucursales = Array.isArray(detalle.sucursales) ? detalle.sucursales : [];
        },
        celdaTexto(valor) {
            const n = Number(valor);
            return n > 0 ? n : '';
        },
        editarDestino(e, id, tipo) {
            var valor = e.target ? e.target.value : '';
            var campos = { 1: 'destino', 2: 'oct87', 3: 'oct91', 4: 'diesel' };
            var campo = campos[tipo];
            if (!campo) return;

            var key = id + '-' + tipo;
            clearTimeout(this._saveTimers[key]);

            this._saveTimers[key] = setTimeout(function () {
                axios.post(this.base + '/update', { id, tipo, valor }, {
                    headers: { 'X-CSRF-TOKEN': this.csrf() }
                })
                .then(function (r) {
                    if (r.data.success) {
                        var n = Number(valor);
                        this.sucursales = this.sucursales.map(function (s) {
                            if (Number(s.id) === Number(id)) {
                                s[campo] = n > 0 ? n : 0;
                            }
                            return s;
                        });
                    } else {
                        Notify.error(r.data.message || 'Error al editar');
                    }
                }.bind(this))
                .catch(function (err) {
                    Notify.error(this.errorMessage(err));
                }.bind(this));
            }.bind(this), 700);
        },
        abrirModal() {
            var modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('modalAgregarSucursal'));
            modal.show();
        },
        guardarSucursal() {
            if (this.guardando) return;

            var input = document.getElementById('inputSucursal');
            if (!this.form.Sucursal.trim()) {
                if (input) {
                    input.style.border = '2px solid #A52525';
                    input.focus();
                }
                return;
            }
            if (input) input.style.border = '';

            this.guardando = true;

            axios.post(this.base + '/agregar-sucursal', {
                idReporte: this.idReporte,
                Sucursal: this.form.Sucursal,
                Destino1: this.form.Destino1,
                Oct871: this.form.Oct871,
                Oct911: this.form.Oct911,
                Diesel1: this.form.Diesel1,
                Destino2: this.form.Destino2,
                Oct872: this.form.Oct872,
                Oct912: this.form.Oct912,
                Diesel2: this.form.Diesel2
            }, {
                headers: { 'X-CSRF-TOKEN': this.csrf() }
            })
            .then(function (r) {
                this.guardando = false;
                if (r.data.success) {
                    var modal = bootstrap.Modal.getInstance(document.getElementById('modalAgregarSucursal'));
                    if (modal) modal.hide();
                    Notify.success(r.data.message || 'Sucursal agregada');
                    window.location.reload();
                } else {
                    Notify.error(r.data.message || 'Error al agregar');
                }
            }.bind(this))
            .catch(function (err) {
                this.guardando = false;
                Notify.error(this.errorMessage(err));
            }.bind(this));
        },
        finalizar() {
            if (this.guardando) return;

            if (!this.fecha) {
                var fechaInput = document.getElementById('Fecha');
                if (fechaInput) fechaInput.style.border = '2px solid #A52525';
                Notify.error('Selecciona una fecha para el inventario');
                return;
            }
            var fechaInput = document.getElementById('Fecha');
            if (fechaInput) fechaInput.style.border = '';

            this.guardando = true;

            axios.post(this.base + '/finalizar', { id: this.idReporte, fecha: this.fecha }, {
                headers: { 'X-CSRF-TOKEN': this.csrf() }
            })
            .then(function (r) {
                this.guardando = false;
                if (r.data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Finalizado',
                        text: r.data.message || 'El inventario fue finalizado exitosamente.',
                        timer: 2000,
                        showConfirmButton: false
                    }).then(function () {
                        window.history.back();
                    });
                } else {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Aviso',
                        text: r.data.message || 'Error al finalizar.'
                    });
                }
            }.bind(this))
            .catch(function (err) {
                this.guardando = false;
                Notify.error(this.errorMessage(err));
            }.bind(this));
        },
        eliminarDetalle(id) {
            if (this.guardando) return;

            for (var key in this._saveTimers) {
                clearTimeout(this._saveTimers[key]);
            }
            this._saveTimers = {};

            Swal.fire({
                title: '¿Desea eliminar la siguiente información?',
                text: 'Se eliminará la sucursal del reporte.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#d33'
            }).then(function (result) {
                if (!result.isConfirmed) return;

                this.guardando = true;
                axios.post(this.base + '/eliminar-destino', { id }, {
                    headers: { 'X-CSRF-TOKEN': this.csrf() }
                })
                .then(function (r) {
                    this.guardando = false;
                    if (r.data.success) {
                        this.sucursales = this.sucursales.filter(function (s) {
                            return Number(s.id) !== Number(id);
                        });
                        Notify.success(r.data.message || 'Destino eliminado');
                    } else {
                        Notify.error(r.data.message || 'Error al eliminar');
                    }
                }.bind(this))
                .catch(function (err) {
                    this.guardando = false;
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