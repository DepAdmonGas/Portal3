document.addEventListener('alpine:init', () => {

    Alpine.data('listaNegraComponent', () => ({

        puedeCrear: false,
        puedeEliminar: false,
        puedeDescargar: false,
        idUsuario: 0,

        agregarForm: { idPersonal: '', motivo: '', detalle: '' },
        guardando: false,

        buscarForm: { fechaInicio: '', fechaFin: '' },

        comentarios: [],
        nuevoComentario: '',
        guardandoComentario: false,
        comentarioIdActual: null,

        archivos: [],
        archivoForm: { descripcion: '' },
        subiendoArchivo: false,
        pruebasIdActual: null,

        init() {
            const c = document.getElementById('container');
            if (c) {
                this.puedeCrear = c.dataset.puedeCrear === 'true';
                this.puedeEliminar = c.dataset.puedeEliminar === 'true';
                this.puedeDescargar = c.dataset.puedeDescargar === 'true';
                this.idUsuario = parseInt(c.dataset.idUsuario) || 0;
            }

            if (window.jQuery && window.jQuery.fn && window.jQuery.fn.select2) {
                this.bindModalSelect2({
                    modalRef: 'modalAgregar',
                    selectRef: 'personalSelect',
                    wrapperRef: 'personalWrapper',
                    model: 'agregarForm.idPersonal',
                    options: { placeholder: 'Selecciona un colaborador...' },
                    namespace: 'lnAgregarPersonal'
                });
            }

            document.addEventListener('ver-comentarios-lista', (e) => { this.abrirComentarios(e.detail.id); });
            document.addEventListener('ver-pruebas-lista', (e) => { this.abrirPruebas(e.detail.id); });
            document.addEventListener('eliminar-lista-negra', (e) => { this.confirmarEliminar(e.detail.id, e.detail.name); });
        },

        formatearFecha(fecha) {
            if (!fecha) return '';
            var d = new Date(fecha);
            var meses = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
            return d.getDate() + ' de ' + meses[d.getMonth()] + ' del ' + d.getFullYear();
        },

        scrollChatToBottom() {
            this.$nextTick(() => {
                const el = this.$refs.chatContainer;
                if (el) el.scrollTop = el.scrollHeight;
            });
        },

        // ---------- AGREGAR ----------
        async abrirAgregar() {
            this.agregarForm = { idPersonal: '', motivo: '', detalle: '' };

            try {
                const resp = await fetch('/departamento-operativo/recursos-humanos/lista-negra/get-personal');
                const json = await resp.json();
                const sel = this.$refs.personalSelect;

                if (json.success && sel) {
                    sel.innerHTML = '<option value="">Selecciona un colaborador...</option>';
                    (json.data || []).forEach(p => {
                        const opt = document.createElement('option');
                        opt.value = p.id;
                        opt.textContent = p.nombre;
                        sel.appendChild(opt);
                    });
                }
            } catch (e) {
                console.error('Error cargando personal:', e);
            }

            new bootstrap.Modal(document.getElementById('modalAgregar')).show();
        },

        async guardarAgregar() {
            if (!this.agregarForm.idPersonal) {
                this.notify('error', 'Selecciona un colaborador.');
                return;
            }
            if (!this.agregarForm.motivo.trim()) {
                this.notify('error', 'El motivo es obligatorio.');
                return;
            }
            if (!this.agregarForm.detalle.trim()) {
                this.notify('error', 'La descripción es obligatoria.');
                return;
            }

            this.guardando = true;

            try {
                const res = await this.createAction({
                    url: '/departamento-operativo/recursos-humanos/lista-negra/add',
                    data: {
                        id_personal: this.agregarForm.idPersonal,
                        motivo: this.agregarForm.motivo,
                        detalle: this.agregarForm.detalle
                    },
                    table: '#tabla-lista-negra',
                    notify: true
                });

                if (res && res.success) {
                    bootstrap.Modal.getInstance(document.getElementById('modalAgregar'))?.hide();
                }
            } finally {
                this.guardando = false;
            }
        },

        // ---------- BUSCAR ----------
        abrirBuscar() {
            const c = document.getElementById('container');
            this.buscarForm.fechaInicio = c ? (c.dataset.fechaInicio || '') : '';
            this.buscarForm.fechaFin = c ? (c.dataset.fechaFin || '') : '';
            new bootstrap.Modal(document.getElementById('modalBuscar')).show();
        },

        buscar() {
            var fi = this.buscarForm.fechaInicio;
            var ff = this.buscarForm.fechaFin;

            if (!fi || !ff) {
                this.notify('error', 'Las fechas de inicio y fin son obligatorias.');
                return;
            }

            const c = document.getElementById('container');
            if (c) {
                c.dataset.fechaInicio = fi;
                c.dataset.fechaFin = ff;
            }

            bootstrap.Modal.getInstance(document.getElementById('modalBuscar'))?.hide();

            const dt = window.tablaListaNegra;
            if (dt) {
                dt.ajax.reload(null, false);
            }
        },

        // ---------- COMENTARIOS ----------
        async abrirComentarios(id) {
            this.comentarioIdActual = id;
            this.nuevoComentario = '';
            this.comentarios = [];

            try {
                const resp = await fetch('/departamento-operativo/recursos-humanos/lista-negra/get-comentarios?id=' + id);
                const json = await resp.json();
                if (json.success) {
                    this.comentarios = (json.data || []).map(c => ({
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

            bootstrap.Offcanvas.getOrCreateInstance(document.getElementById('offcanvasComentarios')).show();
        },

        async agregarComentario() {
            if (this.guardandoComentario) return;
            if (!this.nuevoComentario.trim()) return;
            if (!this.comentarioIdActual) return;

            this.guardandoComentario = true;
            const listaId = this.comentarioIdActual;

            try {
                const resp = await fetch('/departamento-operativo/recursos-humanos/lista-negra/add-comentario', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: listaId, comentario: this.nuevoComentario })
                });
                const json = await resp.json();

                if (json.success) {
                    this.nuevoComentario = '';
                    const resp2 = await fetch('/departamento-operativo/recursos-humanos/lista-negra/get-comentarios?id=' + listaId);
                    const json2 = await resp2.json();
                    if (json2.success) {
                        this.comentarios = (json2.data || []).map(c => ({
                            ...c,
                            esMio: c.esPropio,
                            usuario_nombre: c.usuario_nombre || 'Sistema',
                            fecha_formateada: c.fecha_hora || ''
                        }));
                        this.scrollChatToBottom();
                    }
                    const dt = window.tablaListaNegra;
                    if (dt) {
                        dt.rows().every(function () {
                            const d = this.data();
                            if (d.id === listaId) {
                                d.total_comentarios = (d.total_comentarios || 0) + 1;
                                this.invalidate();
                                dt.draw(false);
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

        // ---------- PRUEBAS (ARCHIVOS) ----------
        async abrirPruebas(id) {
            this.pruebasIdActual = id;
            this.archivos = [];
            this.archivoForm = { descripcion: '' };

            try {
                const res = await axios.get('/departamento-operativo/recursos-humanos/lista-negra/get-archivos', { params: { id } });
                if (res.data.success) {
                    this.archivos = res.data.data;
                }
            } catch (e) {
                this.archivos = [];
            }

            new bootstrap.Modal(document.getElementById('modalPruebas')).show();
        },

        downloadArchivo(archivo) {
            this.download('lista-negra', archivo);
        },

        async subirArchivo() {
            if (!this.pruebasIdActual) return;
            if (!this.archivoForm.descripcion.trim()) {
                this.notify('error', 'La descripción es obligatoria.');
                return;
            }

            const fileInput = document.getElementById('archivoInput');
            if (!fileInput || !fileInput.files[0]) {
                this.notify('error', 'Debe seleccionar un archivo.');
                return;
            }

            this.subiendoArchivo = true;
            const formData = new FormData();
            formData.append('id', this.pruebasIdActual);
            formData.append('descripcion', this.archivoForm.descripcion);
            formData.append('archivo', fileInput.files[0]);

            try {
                const res = await axios.post('/departamento-operativo/recursos-humanos/lista-negra/upload-archivo', formData);

                if (res.data.success) {
                    fileInput.value = '';
                    this.archivoForm = { descripcion: '' };
                    const res2 = await axios.get('/departamento-operativo/recursos-humanos/lista-negra/get-archivos', { params: { id: this.pruebasIdActual } });
                    if (res2.data.success) {
                        this.archivos = res2.data.data;
                    }
                    this.notify('success', 'Archivo agregado exitosamente.');
                } else {
                    this.notify('error', res.data.message || 'Error al agregar el archivo.');
                }
            } catch (e) {
                this.notify('error', 'Error al subir el archivo.');
            } finally {
                this.subiendoArchivo = false;
            }
        },

        async eliminarArchivo(id) {
            const res = await this.deleteAction({
                url: '/departamento-operativo/recursos-humanos/lista-negra/delete-archivo',
                id: id,
                name: 'Archivo #' + id
            });
            if (res && res.success && this.pruebasIdActual) {
                const res2 = await axios.get('/departamento-operativo/recursos-humanos/lista-negra/get-archivos', { params: { id: this.pruebasIdActual } });
                if (res2.data.success) {
                    this.archivos = res2.data.data;
                }
            }
        },

        // ---------- ELIMINAR ----------
        confirmarEliminar(id, name) {
            this.deleteAction({
                url: '/departamento-operativo/recursos-humanos/lista-negra/delete',
                id: id,
                name: name,
                table: '#tabla-lista-negra'
            });
        },
    }));
});