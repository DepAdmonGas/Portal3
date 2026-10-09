// ============================================================
// Mantenimiento Preventivo — Almacén
// Componente Alpine: alta, edición, estatus y prueba de eficiencia.
// ============================================================

document.addEventListener('alpine:init', () => {

    Alpine.data('mantenimientoPreventivoComponent', () => ({

        BASE_URL: '/departamento-operativo/almacen/mantenimiento-preventivo',
        YEAR: '',
        estacionFija: false,
        esUsuarioEstacion: false,
        puedeCrear: false,
        puedeEditar: false,
        puedeEliminar: false,
        puedeDescargar: false,
        tipos: [],

        guardando: false,
        cargandoDetalle: false,
        cargandoEncargados: false,
        detalle: null,
        encargados: [],
        pendientesActual: 0,

        form: {
            id_encargado: '',
            tipo: '',
            fecha: '',
            fecha2: '',
            costo: '',
            observaciones: '',
            archivo: null
        },

        errores: {
            id_encargado: false,
            tipo: false,
            fecha: false,
            fecha2: false,
            costo: false,
            archivo: false
        },

        // Prueba de Eficiencia
        cargandoDoc: false,
        documentos: [],
        formDoc: { fecha: '', archivo: null },
        erroresDoc: { fecha: false, archivo: false },

        init() {
            const c = document.getElementById('container');

            if (c) {
                this.BASE_URL = c.dataset.baseUrl || this.BASE_URL;
                this.YEAR = c.dataset.idYear || '';
                this.estacionFija = c.dataset.estacionFija === 'true';
                this.esUsuarioEstacion = c.dataset.esUsuarioEstacion === 'true';
                this.puedeCrear = c.dataset.puedeCrear === 'true';
                this.puedeEditar = c.dataset.puedeEditar === 'true';
                this.puedeEliminar = c.dataset.puedeEliminar === 'true';
                this.puedeDescargar = c.dataset.puedeDescargar === 'true';
                this.pendientesActual = parseInt(c.dataset.pendientesActual || '0', 10);

                try {
                    this.tipos = JSON.parse(c.dataset.tipos || '[]');
                } catch (e) {
                    console.error(e);
                    this.tipos = [];
                }
            }

            document.addEventListener('mp:status', (e) => this.actualizarEstatus(e.detail.id, e.detail.folio, e.detail.status));
            document.addEventListener('mp:editar', (e) => this.abrirEditar(e.detail.id));
            document.addEventListener('mp:eliminar', (e) => this.eliminar(e.detail.id, e.detail.nombre));
            document.addEventListener('mp:pendientes', (e) => {
                this.pendientesActual = (e.detail && e.detail.total) || 0;
            });
        },

        idEstacionActual() {
            const c = document.getElementById('container');
            const key = c ? (c.dataset.moduleStationKey || '') : '';
            if (!key) return 0;

            if (typeof ModuleStationSelector !== 'undefined' && ModuleStationSelector._instances[key]) {
                const v = ModuleStationSelector._instances[key].getValue();
                if (v.id_estacion) return parseInt(v.id_estacion, 10);
            }

            // Respaldo: leer la estación directamente del <select> del layout.
            const sel = document.getElementById('module-station-selector-' + key);
            if (sel && sel.value && sel.value.startsWith('estacion_')) {
                return parseInt(sel.value.replace('estacion_', ''), 10);
            }

            // Fuente autoritativa del servidor: la estación del contexto del módulo.
            // Cubre el caso de usuario de estación única, donde el layout no pinta
            // el <select> (sólo la insignia) y el selector devuelve null.
            const server = parseInt(c.dataset.estacionActual || '0', 10);
            if (server > 0) return server;

            return 0;
        },

        recargarTabla() {
            const tabla = $('#tabla-mantenimiento-preventivo');
            if (tabla.length && $.fn.DataTable.isDataTable(tabla)) {
                tabla.DataTable().ajax.reload(null, false);
            }
        },

        limpiarFormulario() {
            this.form = {
                id_encargado: '',
                tipo: '',
                fecha: '',
                fecha2: '',
                costo: '',
                observaciones: '',
                archivo: null
            };
            this.errores = { id_encargado: false, tipo: false, fecha: false, fecha2: false, costo: false, archivo: false };
            this.encargados = [];
            this.detalle = null;

            const nuevo = document.getElementById('inputArchivoNuevo');
            if (nuevo) nuevo.value = '';

            const editar = document.getElementById('inputArchivoEditar');
            if (editar) editar.value = '';
        },

        async cargarEncargados(idEstacion) {
            this.cargandoEncargados = true;

            try {
                const resp = await axios.post(this.BASE_URL + '/encargados', { id_estacion: idEstacion });

                if (resp.data && resp.data.success) {
                    this.encargados = resp.data.data || [];
                } else {
                    this.encargados = [];
                    this.notify('error', (resp.data && resp.data.message) || 'No se pudieron cargar los encargados.');
                }
            } catch (err) {
                console.error(err);
                this.encargados = [];
                this.notify('error', 'Error al cargar los encargados.');
            } finally {
                this.cargandoEncargados = false;
            }
        },

        /** Inicializa los selects del modal indicado como select2 y sincroniza
         *  los valores con el estado del componente. 'nuevo' | 'editar' */
        configurarSelect2(modo) {
            const modalEl = document.getElementById(modo === 'nuevo' ? 'modalNuevo' : 'modalEditar');
            const idEnc = modo === 'nuevo' ? 'selectEncargadoNuevo' : 'selectEncargadoEditar';
            const idTip = modo === 'nuevo' ? 'selectTipoNuevo' : 'selectTipoEditar';
            const $enc = $('#' + idEnc);
            const $tip = $('#' + idTip);
            const $contenido = $(modalEl).find('.modal-content');

            if ($enc.length) {
                $enc.val(this.form.id_encargado || '');
                if (!$enc.hasClass('select2-hidden-accessible')) {
                    $enc.select2({
                        dropdownParent: $contenido,
                        width: '100%',
                        placeholder: 'Seleccione una opción...',
                        allowClear: false
                    });
                    $enc.off('change.select2modal').on('change.select2modal', (e) => {
                        this.form.id_encargado = $(e.target).val() || '';
                        this.errores.id_encargado = false;
                    });
                }
                $enc.trigger('change.select2modal');
                $enc.closest('.select2-modal-field').removeClass('is-select2-pending');
            }

            if ($tip.length) {
                $tip.val(this.form.tipo || '');
                if (!$tip.hasClass('select2-hidden-accessible')) {
                    $tip.select2({
                        dropdownParent: $contenido,
                        width: '100%',
                        placeholder: 'Selecciona o escribe un tipo...',
                        tags: true,
                        allowClear: false
                    });
                    $tip.off('change.select2modal').on('change.select2modal', (e) => {
                        this.form.tipo = $(e.target).val() || '';
                        this.errores.tipo = false;
                    });
                }
                $tip.trigger('change.select2modal');
                $tip.closest('.select2-modal-field').removeClass('is-select2-pending');
            }

            if (modalEl && !modalEl._select2Cleanup) {
                modalEl._select2Cleanup = true;
                modalEl.addEventListener('hidden.bs.modal', () => {
                    [$enc, $tip].forEach(($s) => {
                        if ($s.length && $s.hasClass('select2-hidden-accessible')) {
                            $s.select2('destroy');
                        }
                        const wr = $s.closest('.select2-modal-field');
                        if (wr.length) wr.addClass('is-select2-pending');
                    });
                });
            }
        },

        async abrirNuevo() {
            this.limpiarFormulario();

            const idEstacion = this.idEstacionActual();
            if (!idEstacion) {
                this.notify('error', 'Selecciona una estación en el filtro superior.');
                return;
            }

            await this.cargarEncargados(idEstacion);
            bootstrap.Modal.getOrCreateInstance(document.getElementById('modalNuevo')).show();
            this.$nextTick(() => this.configurarSelect2('nuevo'));
        },

        async abrirEditar(id) {
            this.limpiarFormulario();
            this.cargandoDetalle = true;

            bootstrap.Modal.getOrCreateInstance(document.getElementById('modalEditar')).show();

            try {
                const resp = await axios.post(this.BASE_URL + '/detalle', { id: id });

                if (resp.data && resp.data.success) {
                    this.detalle = resp.data.data;
                    this.form.id_encargado = this.detalle.id_encargado;
                    this.form.tipo = this.detalle.tipo_descripcion || '';
                    this.form.fecha = this.detalle.fecha === '0000-00-00' ? '' : this.detalle.fecha;
                    this.form.fecha2 = this.detalle.fecha2 === '0000-00-00' ? '' : this.detalle.fecha2;
                    this.form.costo = this.detalle.costo;
                    this.form.observaciones = this.detalle.observaciones;

                    await this.cargarEncargados(this.detalle.id_estacion);

                    // El encargado registrado siempre debe quedar seleccionado y visible,
                    // aunque no aparezca en el catálogo de la estación (estación cambiada,
                    // usuario dado de baja o fuera de catálogo).
                    const idEnc = this.detalle.id_encargado;
                    if (idEnc && !this.encargados.some(e => String(e.id) === String(idEnc))) {
                        this.encargados.unshift({
                            id: idEnc,
                            nombre: this.detalle.encargado || ('Usuario #' + idEnc)
                        });
                    }
                    this.form.id_encargado = idEnc;

                    // Igual criterio para el tipo de mantenimiento: si el tipo registrado
                    // no está en el catálogo vigente, se agrega para que quede visible
                    // y seleccionable en el modal.
                    const tipoStr = String(this.detalle.tipo_descripcion || '').trim();
                    if (tipoStr && !this.tipos.some(t => String(t.descripcion || '').trim().toLowerCase() === tipoStr.toLowerCase())) {
                        this.tipos.unshift({ id: 0, descripcion: this.detalle.tipo_descripcion });
                    }
                    this.form.tipo = this.detalle.tipo_descripcion || '';
                } else {
                    this.detalle = null;
                    this.notify('error', (resp.data && resp.data.message) || 'No se pudo obtener el registro.');
                }
            } catch (err) {
                console.error(err);
                this.detalle = null;
                this.notify('error', 'Error al consultar el registro.');
            } finally {
                this.cargandoDetalle = false;
                // El contenido del modal (incluidas las <option>) se pinta cuando
                // cargandoDetalle pasa a false; rAF asegura que ya esté en el DOM
                // antes de inicializar select2 y fijar el valor seleccionado.
                requestAnimationFrame(() => this.configurarSelect2('editar'));
            }
        },

        /**
         * @returns {boolean} true si hay errores
         */
        validar(opciones = {}) {
            this.errores = { id_encargado: false, tipo: false, fecha: false, fecha2: false, costo: false, archivo: false };
            let hayError = false;

            if (!this.form.id_encargado) {
                this.errores.id_encargado = true;
                hayError = true;
            }

            if (!String(this.form.tipo).trim()) {
                this.errores.tipo = true;
                hayError = true;
            }

            if (!this.form.fecha) {
                this.errores.fecha = true;
                hayError = true;
            }

            if (this.form.costo === '' || isNaN(parseFloat(this.form.costo)) || parseFloat(this.form.costo) < 0) {
                this.errores.costo = true;
                hayError = true;
            }

            if (opciones.archivoObligatorio && !this.form.archivo) {
                this.errores.archivo = true;
                hayError = true;
            }

            return hayError;
        },

        formDataBase() {
            const fd = new FormData();
            fd.append('id_encargado', this.form.id_encargado);
            fd.append('tipo_mantenimiento', String(this.form.tipo).trim());
            fd.append('fecha', this.form.fecha);
            fd.append('fecha2', this.form.fecha2 || '');
            fd.append('costo', this.form.costo);
            fd.append('observaciones', this.form.observaciones || '');
            return fd;
        },

        async guardarNuevo() {
            if (this.validar({ archivoObligatorio: true }) || !this.form.archivo) {
                this.notify('error', 'Completa los campos obligatorios.');
                return;
            }

            const fd = this.formDataBase();
            fd.append('Archivo_file', this.form.archivo);

            await this.enviar(this.BASE_URL + '/store', fd, 'modalNuevo');
        },

        async guardarEdicion() {
            if (this.validar() || this.cargandoDetalle) {
                this.notify('error', 'Completa los campos obligatorios.');
                return;
            }

            const fd = this.formDataBase();
            fd.append('id', this.detalle.id);

            if (this.form.archivo) {
                fd.append('Archivo_file', this.form.archivo);
            }

            await this.enviar(this.BASE_URL + '/update', fd, 'modalEditar');
        },

        async enviar(url, fd, modalId) {
            this.guardando = true;

            try {
                const resp = await axios.post(url, fd);

                if (resp.data && resp.data.success) {
                    this.notify('success', resp.data.message);

                    const modal = document.getElementById(modalId);
                    if (modal) {
                        bootstrap.Modal.getOrCreateInstance(modal).hide();
                    }

                    this.limpiarFormulario();
                    this.recargarTabla();
                } else {
                    this.notify('error', (resp.data && resp.data.message) || 'No se pudo guardar el registro.');
                }
            } catch (err) {
                console.error(err);
                const mensaje = (err.response && err.response.data && err.response.data.message)
                    || 'Error en el servidor al guardar el registro.';
                this.notify('error', mensaje);
            } finally {
                this.guardando = false;
            }
        },
 
        async actualizarEstatus(id, folio, status) {
            // Transición legible del estatus: 0 Pendiente → 1 En proceso → 2 Finalizado.
            const transiciones = {
                0: ['Pendiente', 'En proceso'],
                1: ['En proceso', 'Finalizado']
            };

            const [actual, siguiente] = transiciones[status] || [];
            const texto = (actual && siguiente)
                ? 'El registro ' + (folio || '') + ' cambiará de ' + actual + ' a ' + siguiente + '.'
                : 'El registro ' + (folio || '') + ' avanzará al siguiente estatus.';

            const result = await Swal.fire({
                title: '¿Actualizar estatus?',
                text: texto,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Sí, actualizar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#3085d6'
            });

            if (!result.isConfirmed) return;
window.loader.show();

            try {
                const resp = await axios.post(this.BASE_URL + '/actualizar-status', { id: id });
 window.loader.hide();
                if (resp.data && resp.data.success) {
                    this.notify('success', resp.data.message);
                    this.recargarTabla();
                } else {
                    this.notify('error', (resp.data && resp.data.message) || 'No se pudo actualizar el estatus.');
                }
            } catch (err) {
                 window.loader.hide();
                console.error(err);
                const mensaje = (err.response && err.response.data && err.response.data.message)
                    || 'Error al actualizar el estatus.';
                this.notify('error', mensaje);
            }
        },

        async eliminar(id, nombre) {
            await this.deleteAction({
                url: this.BASE_URL + '/destroy',
                id: id,
                name: nombre || 'Mantenimiento',
                table: '#tabla-mantenimiento-preventivo'
            });
        },

        descargarOrden(archivo) {
            this.download('mantenimiento-preventivo', archivo);
        },

        /* ============================================================
           PRUEBA DE EFICIENCIA
           ============================================================ */

        async abrirArchivos() {
            this.formDoc = { fecha: '', archivo: null };
            this.erroresDoc = { fecha: false, archivo: false };

            const inputPrueba = document.getElementById('inputArchivoPrueba');
            if (inputPrueba) inputPrueba.value = '';

            bootstrap.Modal.getOrCreateInstance(document.getElementById('modalArchivos')).show();
            await this.cargarDocumentos();
        },

        async cargarDocumentos() {
            this.cargandoDoc = true;

            try {
                const resp = await axios.post(this.BASE_URL + '/archivo-prueba/data/' + this.YEAR);

                if (resp.data && resp.data.success) {
                    this.documentos = resp.data.data || [];
                } else {
                    this.documentos = [];
                }
            } catch (err) {
                console.error(err);
                this.documentos = [];
            } finally {
                this.cargandoDoc = false;
            }
        },

        async guardarArchivoPrueba() {
            this.erroresDoc = { fecha: false, archivo: false };
            let hayError = false;

            if (!this.formDoc.fecha) {
                this.erroresDoc.fecha = true;
                hayError = true;
            }

            if (!this.formDoc.archivo) {
                this.erroresDoc.archivo = true;
                hayError = true;
            }

            if (hayError) {
                this.notify('error', 'Completa los campos obligatorios.');
                return;
            }

            const fd = new FormData();
            fd.append('fecha', this.formDoc.fecha);
            fd.append('Archivo_file', this.formDoc.archivo);

            this.guardando = true;

            try {
                const resp = await axios.post(this.BASE_URL + '/archivo-prueba/' + this.YEAR, fd);

                if (resp.data && resp.data.success) {
                    this.notify('success', resp.data.message);

                    this.formDoc = { fecha: '', archivo: null };
                    this.erroresDoc = { fecha: false, archivo: false };

                    const inputPrueba = document.getElementById('inputArchivoPrueba');
                    if (inputPrueba) inputPrueba.value = '';

                    await this.cargarDocumentos();
                } else {
                    this.notify('error', (resp.data && resp.data.message) || 'No se pudo guardar el archivo.');
                }
            } catch (err) {
                console.error(err);
                const mensaje = (err.response && err.response.data && err.response.data.message)
                    || 'Error al guardar el archivo.';
                this.notify('error', mensaje);
            } finally {
                this.guardando = false;
            }
        },

        async eliminarDocumento(id) {
            // Reutiliza el "delete global": confirmación + manejo de respuesta uniforme.
            const r = await this.deleteAction({
                url: this.BASE_URL + '/archivo-prueba/eliminar',
                id: id,
                name: 'Prueba de eficiencia',
                table: null
            });

            // Con table: null el delete global no recarga; refrescamos la lista aquí.
            if (r && r.success) {
                await this.cargarDocumentos();
            }
        },

        descargar(archivo) {
            this.download('prueba-eficiencia', archivo);
        }

    }));
});