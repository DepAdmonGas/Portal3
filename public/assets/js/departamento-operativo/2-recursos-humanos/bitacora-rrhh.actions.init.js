document.addEventListener('alpine:init', () => {

    Alpine.data('bitacoraRrhhComponent', () => ({

        puedeCrear: false,
        puedeEliminar: false,
        puedeDescargar: false,
        puedeFinalizar: false,
        puedeEliminarDoc: false,
        puedeVerVisualizaciones: false,
        idUsuario: 0,
        idYear: 0,
        idMes: 0,
        esMultiestacion: false,
        contextoEspecifico: false,

        crearForm: { idEstacion: 0, contextoNombre: '', descripcion: '' },
        guardando: false,

        comentarios: [],
        nuevoComentario: '',
        guardandoComentario: false,
        comentarioIdActual: null,

        documentos: [],
        docForm: { nombre: '' },
        subiendoDoc: false,
        docIdActual: null,

        visualizaciones: [],
        visIdActual: null,

        init() {
            const c = document.getElementById('container');
            if (c) {
                this.puedeCrear = c.dataset.puedeCrear === 'true';
                this.puedeEliminar = c.dataset.puedeEliminar === 'true';
                this.puedeDescargar = c.dataset.puedeDescargar === 'true';
                this.puedeFinalizar = c.dataset.puedeFinalizar === 'true';
                this.puedeEliminarDoc = c.dataset.puedeEliminarDoc === 'true';
                this.puedeVerVisualizaciones = c.dataset.puedeVerVisualizaciones === 'true';
                this.idUsuario = parseInt(c.dataset.idUsuario) || 0;
                this.idYear = parseInt(c.dataset.idYear) || 0;
                this.idMes = parseInt(c.dataset.idMes) || 0;
                this.esMultiestacion = c.dataset.multiestacion === 'true';
            }

            this.actualizarContextoUI();

            document.addEventListener('ver-comentarios-bitacora', (e) => { this.abrirComentarios(e.detail.id); });
            document.addEventListener('ver-detalle-bitacora', (e) => { this.abrirDetalle(e.detail.id); });
            document.addEventListener('ver-visualizaciones-bitacora', (e) => { this.abrirVisualizaciones(e.detail.id); });
            document.addEventListener('finalizar-bitacora', (e) => { this.confirmarFinalizar(e.detail.id); });
            document.addEventListener('eliminar-bitacora', (e) => { this.confirmarEliminar(e.detail.id, e.detail.name); });
            document.addEventListener('tabla-recargada', () => {
                this.actualizarContextoUI();
                this.refrescarPendientes();
            });
        },

        getContextoId() {
            const c = document.getElementById('container');
            if (!c) return 0;
            if (this.esMultiestacion) {
                const sel = document.getElementById('module-station-selector-bitacora-rrhh');
                if (sel && sel.value) {
                    return parseInt(sel.value.replace(/^(estacion_|depto_)/, '')) || 0;
                }
                return 0;
            }
            return parseInt(c.dataset.idEstacion) || 0;
        },

        getContextoNombre() {
            const sel = document.getElementById('module-station-selector-bitacora-rrhh');
            if (sel && sel.value && sel.options[sel.selectedIndex]) {
                return sel.options[sel.selectedIndex].textContent.replace(/\s*\(\d+\)\s*$/, '').trim();
            }
            const c = document.getElementById('container');
            if (c && c.dataset.contextoNombre) {
                return c.dataset.contextoNombre;
            }
            return '';
        },

        actualizarContextoUI() {
            const c = document.getElementById('container');
            if (!c) { this.contextoEspecifico = false; return; }
            if (this.esMultiestacion) {
                const sel = document.getElementById('module-station-selector-bitacora-rrhh');
                this.contextoEspecifico = !!(sel && sel.value);
            } else {
                this.contextoEspecifico = !!(parseInt(c.dataset.idEstacion) > 0);
            }
        },

        scrollChatToBottom() {
            this.$nextTick(() => {
                const el = this.$refs.chatContainer;
                if (el) el.scrollTop = el.scrollHeight;
            });
        },

        // ---------- CREAR ----------
        async abrirCrear() {
            if (!this.contextoEspecifico) {
                this.notify('error', 'Selecciona una estación o departamento para crear un registro.');
                return;
            }

            this.crearForm = {
                idEstacion: this.getContextoId(),
                contextoNombre: this.getContextoNombre() || '—',
                descripcion: ''
            };

            new bootstrap.Modal(document.getElementById('modalCrear')).show();
        },

        async guardarCrear() {
            if (!this.crearForm.idEstacion) {
                this.notify('error', 'Selecciona una estación o departamento.');
                return;
            }
            if (!this.crearForm.descripcion.trim()) {
                this.notify('error', 'La descripción es obligatoria.');
                return;
            }

            this.guardando = true;

            try {
                const res = await this.createAction({
                    url: '/departamento-operativo/recursos-humanos/bitacora-rrhh/crear',
                    data: {
                        id_estacion: this.crearForm.idEstacion,
                        id_year: this.idYear,
                        id_mes: this.idMes,
                        descripcion: this.crearForm.descripcion
                    },
                    table: '#tabla-bitacora-rrhh',
                    notify: true
                });

                if (res && res.success) {
                    bootstrap.Modal.getInstance(document.getElementById('modalCrear'))?.hide();
                    this.refrescarPendientes();
                }
            } finally {
                this.guardando = false;
            }
        },

        actualizarBadges(json) {
            const sel = document.getElementById('module-station-selector-bitacora-rrhh');
            if (!sel) return;
            const map = {};
            Object.keys(json).forEach(k => {
                if (k.indexOf('estacion_') === 0) map['estacion_' + k.replace('estacion_', '')] = json[k];
                if (k.indexOf('depto_') === 0) map['depto_' + k.replace('depto_', '')] = json[k];
            });
            Array.from(sel.options).forEach(opt => {
                const v = opt.value;
                if (!v) return;
                const key = v.startsWith('depto_') ? v : v;
                const count = map[key] || 0;
                const label = opt.textContent.replace(/\s*\(\d+\)\s*$/, '').trim();
                opt.textContent = count > 0 ? label + ' (' + count + ')' : label;
            });
            const first = sel.options[0];
            if (first && !first.value && typeof json.total !== 'undefined') {
                const base = first.textContent.replace(/\s*\(\d+\)\s*$/, '').trim();
                first.textContent = json.total > 0 ? base + ' (' + json.total + ')' : base;
            }
        },

        async refrescarPendientes() {
            try {
                const resp = await fetch('/departamento-operativo/recursos-humanos/bitacora-rrhh/pendientes?idYear=' + this.idYear + '&idMes=' + this.idMes);
                const json = await resp.json();
                if (json.success) {
                    this.actualizarBadges(json);
                    const badge = document.getElementById('br-pending-count');
                    if (badge) badge.textContent = json.contexto || 0;
                }
            } catch (e) {}
        },

        // ---------- COMENTARIOS ----------
        async abrirComentarios(id) {
            this.comentarioIdActual = id;
            this.nuevoComentario = '';
            this.comentarios = [];

            try {
                const resp = await fetch('/departamento-operativo/recursos-humanos/bitacora-rrhh/comentarios?id=' + id);
                const json = await resp.json();
                if (json.success) {
                    this.comentarios = (json.comentarios || []).map(c => ({
                        ...c,
                        esMio: c.esPropio,
                        usuario_nombre: c.usuario_nombre || 'Sistema',
                        fecha_formateada: c.fecha_hora || ''
                    }));
                    this.scrollChatToBottom();
                }
            } catch (e) {
                console.error('Error cargando comentarios:', e);
            }

            // Registrar visualización si el rol la ve
            if (this.puedeVerVisualizaciones) {
                try {
                    await axios.post('/departamento-operativo/recursos-humanos/bitacora-rrhh/registrar-visualizacion', { id });
                } catch (e) {}
            }

            bootstrap.Offcanvas.getOrCreateInstance(document.getElementById('offcanvasComentarios')).show();
        },

        async agregarComentario() {
            if (this.guardandoComentario) return;
            if (!this.nuevoComentario.trim()) return;
            if (!this.comentarioIdActual) return;

            this.guardandoComentario = true;
            const id = this.comentarioIdActual;

            try {
                const resp = await fetch('/departamento-operativo/recursos-humanos/bitacora-rrhh/add-comentario', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id, comentario: this.nuevoComentario })
                });
                const json = await resp.json();

                if (json.success) {
                    this.nuevoComentario = '';
                    const resp2 = await fetch('/departamento-operativo/recursos-humanos/bitacora-rrhh/comentarios?id=' + id);
                    const json2 = await resp2.json();
                    if (json2.success) {
                        this.comentarios = (json2.comentarios || []).map(c => ({
                            ...c,
                            esMio: c.esPropio,
                            usuario_nombre: c.usuario_nombre || 'Sistema',
                            fecha_formateada: c.fecha_hora || ''
                        }));
                        this.scrollChatToBottom();
                    }
                    if (window.tablaBitacoraRrhh) {
                        window.tablaBitacoraRrhh.rows().every(function () {
                            const d = this.data();
                            if (d.id === id) {
                                d.total_comentarios = (d.total_comentarios || 0) + 1;
                                this.invalidate();
                                window.tablaBitacoraRrhh.draw(false);
                                return false;
                            }
                        });
                    }
                    if (window.Notify) Notify.success('Comentario agregado');
                } else {
                    if (window.Notify) Notify.error(json.message || 'Error al agregar comentario');
                }
            } catch (e) {
                console.error('Error al agregar comentario:', e);
                if (window.Notify) Notify.error('Error al agregar comentario');
            } finally {
                this.guardandoComentario = false;
            }
        },

        // ---------- DETALLE ----------
        async abrirDetalle(id) {
            this.docIdActual = id;
            this.detalle = null;
            this.documentos = [];
            this.docForm = { nombre: '' };

            try {
                const res = await axios.get('/departamento-operativo/recursos-humanos/bitacora-rrhh/documentos', { params: { id } });
                if (res.data.success) {
                    this.detalle = res.data.detalle || null;
                    this.documentos = res.data.documentos;
                }
            } catch (e) {
                this.documentos = [];
            }

            new bootstrap.Modal(document.getElementById('modalDetalle')).show();
        },

        downloadDocumento(archivo) {
            if (!this.puedeDescargar) {
                this.notify('error', 'No tienes permiso para descargar documentos.');
                return;
            }
            this.download('bitacora-rrhh', archivo);
        },

        async subirDocumento() {
            if (!this.docIdActual) return;
            if (!this.docForm.nombre.trim()) {
                this.notify('error', 'El nombre es obligatorio.');
                return;
            }

            const fileInput = document.getElementById('docInput');
            if (!fileInput || !fileInput.files[0]) {
                this.notify('error', 'Debe seleccionar un archivo.');
                return;
            }

            this.subiendoDoc = true;
            const formData = new FormData();
            formData.append('id', this.docIdActual);
            formData.append('nombre', this.docForm.nombre);
            formData.append('archivo', fileInput.files[0]);

            try {
                const res = await axios.post('/departamento-operativo/recursos-humanos/bitacora-rrhh/add-documento', formData);
                if (res.data.success) {
                    fileInput.value = '';
                    this.docForm = { nombre: '' };
                    const res2 = await axios.get('/departamento-operativo/recursos-humanos/bitacora-rrhh/documentos', { params: { id: this.docIdActual } });
                    if (res2.data.success) {
                        this.documentos = res2.data.documentos;
                    }
                    this.notify('success', 'Documento agregado exitosamente.');
                } else {
                    this.notify('error', res.data.message || 'Error al agregar el documento.');
                }
            } catch (e) {
                this.notify('error', 'Error al subir el documento.');
            } finally {
                this.subiendoDoc = false;
            }
        },

        async eliminarDocumento(id) {
            const res = await this.deleteAction({
                url: '/departamento-operativo/recursos-humanos/bitacora-rrhh/delete-documento',
                id: id,
                name: 'Documento #0' + id
            });
            if (res && res.success && this.docIdActual) {
                const res2 = await axios.get('/departamento-operativo/recursos-humanos/bitacora-rrhh/documentos', { params: { id: this.docIdActual } });
                if (res2.data.success) {
                    this.documentos = res2.data.documentos;
                }
            }
        },

        // ---------- VISUALIZACIONES ----------
        async abrirVisualizaciones(id) {
            this.visIdActual = id;
            this.visualizaciones = [];

            try {
                const res = await axios.get('/departamento-operativo/recursos-humanos/bitacora-rrhh/registros', { params: { id } });
                if (res.data.success) {
                    this.visualizaciones = res.data.registros;
                }
            } catch (e) {
                this.visualizaciones = [];
            }

            new bootstrap.Modal(document.getElementById('modalVisualizaciones')).show();
        },

        // ---------- FINALIZAR ----------
        async confirmarFinalizar(id) {
            const result = await Swal.fire({
                title: '¿Finalizar registro?',
                text: 'El registro #' + id + ' será marcado como finalizado.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Sí, finalizar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#28a745'
            });

            if (!result.isConfirmed) return;

            try {
                const response = await axios.post('/departamento-operativo/recursos-humanos/bitacora-rrhh/finalizar', { id });
                this.handleResponse(response, '#tabla-bitacora-rrhh');
                if (response.data.success) this.refrescarPendientes();
            } catch (err) {
                const mensaje = err.response?.data?.message || 'Error al finalizar';
                this.showAlert('error', 'Error', mensaje);
                this.notify('error', mensaje);
            }
        },

        // ---------- ELIMINAR ----------
        async confirmarEliminar(id, name) {
            const res = await this.deleteAction({
                url: '/departamento-operativo/recursos-humanos/bitacora-rrhh/delete',
                id: id,
                name: name,
                table: '#tabla-bitacora-rrhh'
            });
            if (res && res.success) this.refrescarPendientes();
        }
    }));

    Alpine.data('bitacoraRrhhFormularioComponent', () => ({

        id: 0,
        idYear: 0,
        idMes: 0,
        estatus: 0,
        puedeFinalizar: false,
        puedeEliminarDoc: false,
        puedeDescargar: false,
        puedeVerVisualizaciones: false,
        finalizando: false,

        documentos: [],
        docForm: { nombre: '' },
        subiendoDoc: false,

        comentarios: [],
        nuevoComentario: '',
        guardandoComentario: false,

        init() {
            const el = this.$el;
            if (!el) return;
            this.id = parseInt(el.dataset.idBitacora) || 0;
            this.idYear = parseInt(el.dataset.idYear) || 0;
            this.idMes = parseInt(el.dataset.idMes) || 0;
            this.estatus = parseInt(el.dataset.estatus) || 0;
            this.puedeFinalizar = el.dataset.puedeFinalizar === 'true';
            this.puedeEliminarDoc = el.dataset.puedeEliminarDoc === 'true';
            this.puedeDescargar = el.dataset.puedeDescargar === 'true';
            this.puedeVerVisualizaciones = el.dataset.puedeVerVisualizaciones === 'true';

            this.cargarDocumentos();
            this.cargarComentarios();

            if (this.puedeVerVisualizaciones) {
                try {
                    axios.post('/departamento-operativo/recursos-humanos/bitacora-rrhh/registrar-visualizacion', { id: this.id });
                } catch (e) {}
            }
        },

        scrollChatToBottom() {
            this.$nextTick(() => {
                const el = this.$refs.chatContainer;
                if (el) el.scrollTop = el.scrollHeight;
            });
        },

        async cargarDocumentos() {
            if (!this.id) return;
            try {
                const res = await axios.get('/departamento-operativo/recursos-humanos/bitacora-rrhh/documentos', { params: { id: this.id } });
                if (res.data.success) {
                    this.documentos = res.data.documentos || [];
                }
            } catch (e) {
                this.documentos = [];
            }
        },

        async cargarComentarios() {
            if (!this.id) return;
            try {
                const resp = await fetch('/departamento-operativo/recursos-humanos/bitacora-rrhh/comentarios?id=' + this.id);
                const json = await resp.json();
                if (json.success) {
                    this.comentarios = (json.comentarios || []).map(c => ({
                        ...c,
                        esMio: c.esPropio,
                        usuario_nombre: c.usuario_nombre || 'Sistema',
                        fecha_formateada: c.fecha_hora || ''
                    }));
                    this.scrollChatToBottom();
                }
            } catch (e) {
                this.comentarios = [];
            }
        },

        downloadDocumento(archivo) {
            if (!this.puedeDescargar) {
                this.notify('error', 'No tienes permiso para descargar documentos.');
                return;
            }
            this.download('bitacora-rrhh', archivo);
        },

        async subirDocumento() {
            if (!this.id) return;
            if (!this.docForm.nombre.trim()) {
                this.notify('error', 'El nombre es obligatorio.');
                return;
            }

            const fileInput = this.$refs.docInput;
            if (!fileInput || !fileInput.files[0]) {
                this.notify('error', 'Debe seleccionar un archivo.');
                return;
            }

            this.subiendoDoc = true;
            const formData = new FormData();
            formData.append('id', this.id);
            formData.append('nombre', this.docForm.nombre);
            formData.append('archivo', fileInput.files[0]);

            try {
                const res = await axios.post('/departamento-operativo/recursos-humanos/bitacora-rrhh/add-documento', formData);
                if (res.data.success) {
                    fileInput.value = '';
                    this.docForm = { nombre: '' };
                    await this.cargarDocumentos();
                    this.notify('success', 'Documento agregado exitosamente.');
                } else {
                    this.notify('error', res.data.message || 'Error al agregar el documento.');
                }
            } catch (e) {
                this.notify('error', 'Error al subir el documento.');
            } finally {
                this.subiendoDoc = false;
            }
        },

        async eliminarDocumento(id) {
            const res = await this.deleteAction({
                url: '/departamento-operativo/recursos-humanos/bitacora-rrhh/delete-documento',
                id: id,
                name: 'Documento #' + id
            });
            if (res && res.success) {
                await this.cargarDocumentos();
            }
        },

        async agregarComentario() {
            if (this.guardandoComentario) return;
            if (!this.nuevoComentario.trim()) return;

            this.guardandoComentario = true;

            try {
                const resp = await fetch('/departamento-operativo/recursos-humanos/bitacora-rrhh/add-comentario', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: this.id, comentario: this.nuevoComentario })
                });
                const json = await resp.json();

                if (json.success) {
                    this.nuevoComentario = '';
                    await this.cargarComentarios();
                    if (window.Notify) Notify.success('Comentario agregado');
                } else {
                    if (window.Notify) Notify.error(json.message || 'Error al agregar comentario');
                }
            } catch (e) {
                if (window.Notify) Notify.error('Error al agregar comentario');
            } finally {
                this.guardandoComentario = false;
            }
        },

        async finalizar() {
            if (!this.puedeFinalizar) {
                this.notify('error', 'No tienes permiso para finalizar este registro.');
                return;
            }

            const result = await Swal.fire({
                title: '¿Finalizar registro?',
                text: 'El registro será marcado como finalizado.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Sí, finalizar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#28a745'
            });

            if (!result.isConfirmed) return;

            this.finalizando = true;
            try {
                const response = await axios.post('/departamento-operativo/recursos-humanos/bitacora-rrhh/finalizar', { id: this.id });
                if (response.data.success) {
                    if (window.Notify) Notify.success(response.data.message || 'Registro finalizado');
                    window.location.href = '/departamento-operativo/recursos-humanos/bitacora-rrhh/' + this.idYear + '/' + this.idMes;
                } else {
                    this.notify('error', response.data.message || 'Error al finalizar');
                }
            } catch (err) {
                const mensaje = err.response?.data?.message || 'Error al finalizar';
                this.showAlert('error', 'Error', mensaje);
                this.notify('error', mensaje);
            } finally {
                this.finalizando = false;
            }
        }
    }));
});
