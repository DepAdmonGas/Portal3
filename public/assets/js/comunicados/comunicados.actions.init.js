// ============================================================
// Comunicados — Componente Alpine: alta, edición, detalle, acuse y baja.
// ============================================================

document.addEventListener('alpine:init', () => {

    Alpine.data('comunicadosComponent', () => ({

        guardando: false,
        cargandoDetalle: false,

        modo: 'nuevo', // 'nuevo' | 'editar'

        detalle: null,

        puedeAgregar: false,
        puedeEditar: false,
        puedeEliminar: false,
        puedeDescargar: false,
        puedeAcusar: false,
        esAdmin: false,

        campos: ['fecha', 'titulo'],

        form: {},
        errores: {},

        get tablaId() {
            return '#tabla-comunicados';
        },

        init() {
            const c = document.getElementById('comunicados');
            if (!c) return;

            this.puedeAgregar = c.dataset.puedeAgregar === 'true';
            this.puedeEditar = c.dataset.puedeEditar === 'true';
            this.puedeEliminar = c.dataset.puedeEliminar === 'true';
            this.puedeDescargar = c.dataset.puedeDescargar === 'true';
            this.puedeAcusar = c.dataset.puedeAcusar === 'true';
            this.esAdmin = c.dataset.esAdmin === 'true';

            this.limpiarFormulario();

            document.addEventListener('comunicados:detalle', (e) => this.abrirDetalle(e.detail.id));
            document.addEventListener('comunicados:editar', (e) => this.abrirEditar(e.detail.id));
            document.addEventListener('comunicados:eliminar', (e) => this.eliminar(e.detail.id, e.detail.titulo));
            document.addEventListener('comunicados:acuse', (e) => this.confirmarAcuse(e.detail.id));
        },

        limpiarFormulario() {
            const f = { id: '', fecha: '', titulo: '', archivo_actual: '', archivo_url: '' };
            this.campos.forEach((campo) => { f[campo] = ''; });
            this.form = f;
            this.errores = {};
            if (this.$refs && this.$refs.inputArchivo) {
                this.$refs.inputArchivo.value = '';
            }
        },

        limpiarErrores() {
            const e = {};
            this.campos.forEach((campo) => { e[campo] = false; });
            e.archivo = false;
            this.errores = e;
        },

        validar() {
            this.limpiarErrores();

            let hayError = false;

            this.campos.forEach((campo) => {
                if (String(this.form[campo] ?? '').trim() === '') {
                    this.errores[campo] = true;
                    hayError = true;
                }
            });

            // El documento sólo es obligatorio en el alta (en edición se conserva).
            const archivo = this.$refs.inputArchivo && this.$refs.inputArchivo.files[0];
            if (this.modo === 'nuevo' && !archivo) {
                this.errores.archivo = true;
                hayError = true;
            }

            return hayError;
        },

        formData() {
            const fd = new FormData();

            if (this.modo === 'editar') {
                fd.append('id', String(this.form.id));
            }

            this.campos.forEach((campo) => {
                fd.append(campo, String(this.form[campo] ?? '').trim());
            });

            const archivo = this.$refs.inputArchivo && this.$refs.inputArchivo.files[0];
            if (archivo) {
                fd.append('archivo', archivo);
            }

            return fd;
        },

        abrirModalAgregar() {
            if (!this.puedeAgregar) {
                this.notify('error', 'No tienes permiso para agregar comunicados.');
                return;
            }

            this.modo = 'nuevo';
            this.limpiarFormulario();
            this.limpiarErrores();

            bootstrap.Modal.getOrCreateInstance(document.getElementById('modalComunicado')).show();
        },

        async abrirEditar(id) {
            if (!this.puedeEditar) {
                this.notify('error', 'No tienes permiso para editar comunicados.');
                return;
            }

            this.modo = 'editar';
            this.limpiarFormulario();
            this.limpiarErrores();

            bootstrap.Modal.getOrCreateInstance(document.getElementById('modalComunicado')).show();

            try {
                const resp = await axios.get('/comunicados/show', { params: { id: id } });
                const data = resp.data || {};

                if (data.success === true && data.data) {
                    const d = data.data;
                    this.form.id = d.id;
                    this.form.fecha = d.fecha || '';
                    this.form.titulo = d.titulo || '';
                    this.form.archivo_actual = d.existe_archivo ? (d.archivo || '') : '';
                    this.form.archivo_url = d.archivo_url || '';
                } else {
                    bootstrap.Modal.getOrCreateInstance(document.getElementById('modalComunicado')).hide();
                    this.notify('error', data.message || 'No se pudo cargar el comunicado.');
                }
            } catch (e) {
                bootstrap.Modal.getOrCreateInstance(document.getElementById('modalComunicado')).hide();
                this.notify('error', 'Error al consultar el comunicado.');
            }
        },

        async guardar() {
            if (this.guardando) return;

            if (this.validar()) {
                this.notify('error', 'Completa los campos obligatorios.');
                return;
            }

            const esEdicion = this.modo === 'editar';
            const url = esEdicion ? '/comunicados/update' : '/comunicados/store';

            this.guardando = true;

            try {
                const resp = await axios.post(url, this.formData(), {
                    headers: { 'Content-Type': 'multipart/form-data' }
                });

                const data = resp.data || {};

                if (data.success === true) {
                    bootstrap.Modal.getOrCreateInstance(document.getElementById('modalComunicado')).hide();
                    this.showAlert('success', 'Correcto', data.message);
                    this.notify('success', data.message);
                    this.cargarTabla();
                } else {
                    this.showAlert('error', 'Error', data.message || 'No fue posible guardar.');
                    this.notify('error', data.message || 'No fue posible guardar.');
                }
            } catch (e) {
                const msg = (e.response && e.response.data && e.response.data.message)
                    ? e.response.data.message
                    : 'Error al guardar el comunicado.';
                this.showAlert('error', 'Error', msg);
                this.notify('error', msg);
            } finally {
                this.guardando = false;
            }
        },

        async abrirDetalle(id) {
            this.detalle = null;
            this.cargandoDetalle = true;

            bootstrap.Modal.getOrCreateInstance(document.getElementById('modalDetalleComunicado')).show();

            try {
                const resp = await axios.get('/comunicados/show', { params: { id: id } });
                const data = resp.data || {};

                if (data.success === true && data.data) {
                    this.detalle = data.data;
                } else {
                    this.notify('error', data.message || 'No se pudo cargar el detalle.');
                }
            } catch (e) {
                this.notify('error', 'Error al consultar el comunicado.');
            } finally {
                this.cargandoDetalle = false;
            }
        },

        async confirmarAcuse(id) {
            if (!this.puedeAcusar) {
                this.notify('error', 'No tienes permiso para confirmar comunicados.');
                return;
            }

            if (id === undefined) {
                id = this.detalle ? this.detalle.id : 0;
            }

            if (!id) {
                this.notify('error', 'Comunicado no válido.');
                return;
            }

            if (this.guardando) return;
            this.guardando = true;

            if (window.loader) window.loader.show();

            try {
                const resp = await axios.post('/comunicados/acuse', { id });
                const data = resp.data || {};

                if (data.success === true) {
                    this.notify('success', data.message);
                    this.cargarTabla();
                    // Refresca el detalle abierto para reflejar el acuse.
                    if (this.detalle && this.detalle.id === id) {
                        await this.recargarDetalle(id);
                    }
                } else {
                    this.notify('error', data.message || 'No fue posible confirmar el comunicado.');
                }
            } catch (e) {
                const msg = (e.response && e.response.data && e.response.data.message)
                    ? e.response.data.message
                    : 'Error al confirmar el comunicado.';
                this.notify('error', msg);
            } finally {
                this.guardando = false;
                if (window.loader) window.loader.hide();
            }
        },

        async recargarDetalle(id) {
            try {
                const resp = await axios.get('/comunicados/show', { params: { id } });
                const data = resp.data || {};
                if (data.success === true && data.data) {
                    this.detalle = data.data;
                }
            } catch (e) {
                // Silencioso: el acuse ya se registró.
            }
        },

        /** Borrado con confirmación propia y loader de admongas. */
        async eliminar(id, titulo) {
            if (!this.puedeEliminar) {
                this.notify('error', 'No tienes permiso para eliminar comunicados.');
                return;
            }

            if (this.guardando) return;

            const nombre = '#' + id;

            const result = await Swal.fire({
                title: '¿Eliminar Registro?',
                text: 'El registro: ' + nombre + ' será eliminado',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#d33'
            });

            if (!result.isConfirmed) return;

            this.guardando = true;

            if (window.loader) window.loader.show();

            try {
                const resp = await axios.post('/comunicados/delete', { id });
                const data = resp.data || {};

                if (data.success === true) {
                    this.showAlert('success', 'Correcto', data.message);
                    this.notify('success', data.message);
                    this.cargarTabla();
                } else {
                    this.showAlert('error', 'Error', data.message || 'No fue posible eliminar.');
                    this.notify('error', data.message || 'No fue posible eliminar.');
                }
            } catch (e) {
                const msg = (e.response && e.response.data && e.response.data.message)
                    ? e.response.data.message
                    : 'Error al eliminar el comunicado.';
                this.showAlert('error', 'Error', msg);
                this.notify('error', msg);
            } finally {
                this.guardando = false;
                if (window.loader) window.loader.hide();
            }
        },

        /** Refresca la tabla desde el endpoint de datos. */
        cargarTabla() {
            const t = $(this.tablaId);
            if (t.length && $.fn.DataTable.isDataTable(this.tablaId)) {
                t.DataTable().ajax.reload(null, false);
            }
        }

    }));

});
