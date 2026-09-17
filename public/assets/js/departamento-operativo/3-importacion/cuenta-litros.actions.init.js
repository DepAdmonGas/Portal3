document.addEventListener('alpine:init', () => {

    Alpine.data('cuentaLitrosComponent', () => ({

        puedeCrear: false,
        puedeEditar: false,
        estacionEspecifica: false,
        idUsuario: 0,
        idEstacion: 0,
        moduleStationKey: 'cuenta-litros',
        guardando: false,
        nuevoForm: { fecha: '' },

        init() {
            const c = document.getElementById('container');
            if (c) {
                this.puedeCrear = c.dataset.puedeCrear === 'true';
                this.puedeEditar = c.dataset.puedeEditar === 'true';
                this.idUsuario = parseInt(c.dataset.idUsuario) || 0;
                this.idEstacion = parseInt(c.dataset.idEstacion) || 0;
                this.moduleStationKey = c.dataset.moduleStationKey || 'cuenta-litros';
            }

            this.estacionEspecifica = this.contextoEspecifico();
            document.addEventListener('cuenta-litros-estacion-change', () => {
                this.estacionEspecifica = this.contextoEspecifico();
            });

            document.addEventListener('cuenta-litros-habilitar', (e) => {
                this.confirmarHabilitar(e.detail.id, e.detail.name);
            });
            document.addEventListener('cuenta-litros-eliminar', (e) => {
                this.confirmarEliminar(e.detail.id, e.detail.name);
            });
        },

        getSelector() {
            return document.getElementById('module-station-selector-' + this.moduleStationKey);
        },

        contextoEspecifico() {
            const sel = this.getSelector();
            if (sel) {
                return !!sel.value;
            }
            return this.idEstacion > 0;
        },

        hoyISO() {
            const d = new Date();
            return d.getFullYear() + '-' +
                String(d.getMonth() + 1).padStart(2, '0') + '-' +
                String(d.getDate()).padStart(2, '0');
        },

        abrirNuevo() {
            if (!this.puedeCrear) return;
            if (!this.contextoEspecifico()) {
                this.notify('error', 'Selecciona una estación para registrar el formato.');
                return;
            }
            this.nuevoForm = { fecha: this.hoyISO() };
            new bootstrap.Modal(document.getElementById('modalNuevo')).show();
        },

        async guardarNuevo() {
            if (!this.contextoEspecifico()) {
                this.notify('error', 'Selecciona una estación para registrar el formato.');
                return;
            }
            if (!this.nuevoForm.fecha) {
                this.notify('error', 'La fecha es obligatoria.');
                return;
            }

            this.guardando = true;

            try {
                const res = await this.createAction({
                    url: '/departamento-operativo/importacion/cuenta-litros/crear',
                    data: { fecha: this.nuevoForm.fecha },
                    notify: true,
                    onSuccess: (r) => {
                        if (r && r.id) {
                            const modal = bootstrap.Modal.getInstance(document.getElementById('modalNuevo'));
                            if (modal) modal.hide();
                            window.location.href = '/departamento-operativo/importacion/cuenta-litros-formato/' + r.id;
                        }
                    }
                });
            } finally {
                this.guardando = false;
            }
        },

async confirmarHabilitar(id, name) {
            if (!id) return;

            const result = await Swal.fire({
                title: '¿Habilitar registro?',
                text: 'El registro: ' + (name || '') + ' será habilitado para edición.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sí, habilitar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#28a745'
            });

            if (!result.isConfirmed) return;

            // 1. Mostramos el loader antes de la petición
            window.loader.show();

            try {
                await this.createAction({
                    url: '/departamento-operativo/importacion/cuenta-litros/habilitar',
                    data: { id: id },
                    table: '#tabla-cuenta-litros'
                });
            } finally {
                // 2. El bloque finally se ejecuta pase lo que pase (éxito o error)
                window.loader.hide();
            }
        },

        async confirmarEliminar(id, name) {
            if (!id) return;

            await this.deleteAction({
                url: '/departamento-operativo/importacion/cuenta-litros/eliminar',
                id: id,
                name: name || 'Registro',
                table: '#tabla-cuenta-litros'
            });
        }
    }));
});