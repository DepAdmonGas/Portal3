// ============================================================
// Fallas de Terminales Punto de Venta — Almacén
// Componente Alpine: alta, edición, detalle, anexos y comentarios.
// Réplica de `4-almacen/tpv` del legacy.
// ============================================================

document.addEventListener('alpine:init', () => {

    Alpine.data('terminalesPuntoVentaFallasComponent', () => ({

        BASE_URL: '/departamento-operativo/almacen/terminales-punto-venta-fallas',
        TIPO_DESCARGA: 'tpv',

        guardando: false,
        cargandoDetalle: false,
        cargandoRegistro: false,
        cargandoAnexos: false,
        cargandoComentarios: false,
        guardandoAnexo: false,
        subiendoDocs: false,
        guardandoComentario: false,
        guardandoFinalizacion: false,

        idTpv: 0,
        terminal: null,
        puedeOperar: false,
        puedeReportar: false,

        catalogos: { fallas: [], controlux: [] },

        form: {},
        detalle: null,
        detalleRegistro: null,
        detalleAnexo: null,

        anexos: [],
        comentarios: [],
        comentarioId: 0,

        docs: { cotizacion: null, factura: null },
        formAnexo: { descripcion: '', archivo: null },
        formComentario: '',

        errores: { falla: false, vida_util: false, atiende: false, no_reporte: false },
        erroresAnexo: { descripcion: false, archivo: false },
        erroresComentario: false,

        init() {
            const c = document.getElementById('container');

            this.limpiarFormulario();
            this.limpiarErrores();

            if (c) {
                this.BASE_URL = c.dataset.baseUrl || this.BASE_URL;
                this.idTpv = parseInt(c.dataset.idTpv || '0', 10);
                this.puedeOperar = c.dataset.puedeOperar === 'true';
                this.puedeReportar = c.dataset.puedeReportar === 'true';

                // La vista por estación no manda terminal: el atributo viene vacío.
                try {
                    this.terminal = c.dataset.terminal ? JSON.parse(c.dataset.terminal) : null;
                } catch (e) {
                    console.error(e);
                    this.terminal = null;
                }

                try {
                    this.catalogos = Object.assign(this.catalogos, JSON.parse(c.dataset.catalogos || '{}') || {});
                } catch (e) {
                    console.error(e);
                }
            }

            document.addEventListener('falla:detalle',    (e) => this.abrirDetalle(e.detail.id));
            document.addEventListener('falla:editar',     (e) => this.abrirEditar(e.detail.id));
            document.addEventListener('falla:finalizar',  (e) => this.finalizarFalla(e.detail.id));
            document.addEventListener('falla:anexos',     (e) => this.abrirAnexos(e.detail.id));
            document.addEventListener('falla:eliminar',   (e) => this.eliminar(e.detail.id, e.detail.nombre));
            document.addEventListener('falla:comentarios', (e) => this.abrirComentarios(e.detail.id));
        },

        limpiarFormulario() {
            this.form = {
                falla: '',
                vida_util: '',
                no_reporte: '',
                observaciones: '',
                atiende: '',
                atiende_controlux: 0,
                cotizacion_file: null,
                factura_file: null
            };
            this.detalle = null;
            this.limpiarErrores();
        },

        limpiarErrores() {
            this.errores = { falla: false, vida_util: false, atiende: false, no_reporte: false };
        },

        esOpcionFalla(v) {
            return this.catalogos.fallas.includes(v);
        },

        /* --------------------------- ALTA --------------------------- */

        abrirNuevaFalla() {
            this.limpiarFormulario();
            bootstrap.Modal.getOrCreateInstance(document.getElementById('modalNuevaFalla')).show();

            // select2 se monta cuando el modal ya está visible; si se hiciera
            // antes, el wrapper seguiría oculto y el campo no se vería.
            this.alMostrarModal('modalNuevaFalla', 'selectFallaNueva', 'fallaWrapperNueva');
        },

        /** Monta el select2 del campo Falla en el siguiente ciclo de render. */
        alMostrarModal(modalId, selectRef, wrapperRef) {
            const montar = () => {
                this.configurarSelectFalla(selectRef, wrapperRef, modalId);
                this.sincronizarSelect('#' + modalId, '[x-ref="' + selectRef + '"]');
            };

            if (typeof this.$nextTick === 'function') {
                this.$nextTick(() => requestAnimationFrame(montar));
            } else {
                requestAnimationFrame(montar);
            }
        },

        validarNueva() {
            this.limpiarErrores();

            let hayError = false;

            if (!String(this.form.falla || '').trim()) {
                this.errores.falla = true;
                hayError = true;
            }

            return hayError;
        },

        async guardarNuevaFalla() {
            if (this.guardando) return;

            if (this.validarNueva()) {
                this.notify('error', 'Selecciona la falla de la terminal.');
                return;
            }

            this.guardando = true;

            try {
                const resp = await axios.post(this.BASE_URL + '/store', {
                    id_tpv: this.idTpv,
                    falla: this.form.falla
                });

                if (resp.data && resp.data.success) {
                    this.notify('success', resp.data.message);
                    bootstrap.Modal.getOrCreateInstance(document.getElementById('modalNuevaFalla')).hide();
                    this.reloadTable();
                } else {
                    this.notify('error', (resp.data && resp.data.message) || 'No se pudo registrar la falla.');
                }
            } catch (err) {
                console.error(err);
                this.notify('error', (err.response && err.response.data && err.response.data.message) || 'Error al registrar la falla.');
            } finally {
                this.guardando = false;
            }
        },

        /* --------------------- EDITAR / FINALIZAR --------------------- */

        /** true cuando el nombre se escribió a mano: el select queda bloqueado. */
        atiendeEscrito() {
            return String(this.form.atiende || '').trim() !== '';
        },

        onCambiaAtiende() {
            // Catálogo elegido: se limpia el nombre escrito.
            if (Number(this.form.atiende_controlux) > 0) {
                this.form.atiende = '';
            }
            this.errores.atiende = false;
        },

        onEscribeAtiende() {
            // Nombre escrito: se limpia el catálogo.
            if (String(this.form.atiende || '').trim() !== '') {
                this.form.atiende_controlux = 0;
            }
            this.errores.atiende = false;
        },

        async abrirEditar(id) {
            this.limpiarFormulario();
            this.cargandoDetalle = true;

            bootstrap.Modal.getOrCreateInstance(document.getElementById('modalEditarFalla')).show();

            try {
                const resp = await axios.post(this.BASE_URL + '/detalle', { id: id });

                if (resp.data && resp.data.success) {
                    const d = resp.data.data || {};
                    this.detalle = d;

                    this.form.falla            = d.falla || '';
                    this.form.vida_util        = d.vida_util || '';
                    this.form.no_reporte       = d.no_reporte || '';
                    this.form.observaciones    = d.observaciones || '';
                    this.form.atiende          = d.atiende || '';
                    this.form.atiende_controlux = Number(d.atiende_controlux) || 0;
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
            }

            this.sincronizarSelect('#modalEditarFalla', '[x-ref="selectFallaEditar"]');
            this.alMostrarModal('modalEditarFalla', 'selectFallaEditar', 'fallaWrapperEditar');
        },

        /** Etiqueta del botón que avanza el estatus, segun el estado actual. */
        etiquetaSiguienteStatus() {
            if (this.guardando) return 'Guardando...';
            if (!this.detalle) return 'Finalizar';
            return Number(this.detalle.status) === 0 ? 'Finalizar' : 'Marcar finalizado';
        },

        validarEdicion() {
            this.limpiarErrores();

            let hayError = false;

            if (!String(this.form.falla || '').trim()) {
                this.errores.falla = true;
                hayError = true;
            }

            if (!String(this.form.vida_util || '').trim()) {
                this.errores.vida_util = true;
                hayError = true;
            }

            const atiendeCatalogo = Number(this.form.atiende_controlux) > 0;
            const atiendeTexto = String(this.form.atiende || '').trim();

            if (!atiendeCatalogo && atiendeTexto === '') {
                this.errores.atiende = true;
                hayError = true;
            }

            if (!String(this.form.no_reporte || '').trim()) {
                this.errores.no_reporte = true;
                hayError = true;
            }

            return hayError;
        },

        /**
         * Confirma y marca como finalizada una falla que ya está "En reparación".
         * No reabre el modal: los datos se capturaron cuando estaba Pendiente.
         */
        async finalizarFalla(id) {
            if (this.guardandoFinalizacion) return;

            if (typeof Swal === 'undefined') {
                await this.enviarFinalizacion(id);
                return;
            }

            const confirmacion = await Swal.fire({
                title: '¿Marcar la falla como finalizada?',
                text: 'Se registrará el día de solución de hoy y la falla ya no podrá actualizarse.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Sí, finalizar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#206bc4'
            });

            if (!confirmacion.isConfirmed) return;

            await this.enviarFinalizacion(id);
        },

        async enviarFinalizacion(id) {
            this.guardandoFinalizacion = true;

            try {
                const resp = await axios.post(this.BASE_URL + '/finalizar', { id: id });

                if (resp.data && resp.data.success) {
                    this.notify('success', resp.data.message);
                    this.reloadTable();
                } else {
                    this.notify('error', (resp.data && resp.data.message) || 'No se pudo actualizar el estatus.');
                }
            } catch (err) {
                console.error(err);
                this.notify('error', (err.response && err.response.data && err.response.data.message) || 'Error al actualizar el estatus.');
            } finally {
                this.guardandoFinalizacion = false;
            }
        },

        async guardarFalla(accion) {
            if (this.guardando || this.cargandoDetalle || !this.detalle) return;

            // El permiso real viene del backend (puede_actualizar), no del puesto.
            if (!this.detalle.puede_actualizar) {
                this.notify('error', 'No tienes permiso para actualizar el estatus de esta falla.');
                return;
            }

            if (this.validarEdicion()) {
                this.notify('error', 'Completa los campos obligatorios.');
                return;
            }

            this.guardando = true;

            try {
                const fd = new FormData();
                fd.append('id', String(this.detalle.id));
                fd.append('accion', accion);
                fd.append('falla', String(this.form.falla || '').trim());
                fd.append('vida_util', String(this.form.vida_util || '').trim());
                fd.append('no_reporte', String(this.form.no_reporte || '').trim());
                fd.append('observaciones', String(this.form.observaciones || '').trim());
                fd.append('atiende', String(this.form.atiende || '').trim());
                fd.append('atiende_controlux', String(Number(this.form.atiende_controlux) || 0));

                if (this.form.cotizacion_file) fd.append('cotizacion', this.form.cotizacion_file);
                if (this.form.factura_file) fd.append('factura', this.form.factura_file);

                const resp = await axios.post(this.BASE_URL + '/update', fd);

                if (resp.data && resp.data.success) {
                    this.notify('success', resp.data.message);
                    bootstrap.Modal.getOrCreateInstance(document.getElementById('modalEditarFalla')).hide();
                    this.reloadTable();
                } else {
                    this.notify('error', (resp.data && resp.data.message) || 'No se pudo guardar el registro.');
                }
            } catch (err) {
                console.error(err);
                this.notify('error', (err.response && err.response.data && err.response.data.message) || 'Error al guardar el registro.');
            } finally {
                this.guardando = false;
            }
        },

        /* ------------------------- DETALLE ------------------------- */

        async abrirDetalle(id) {
            this.detalleRegistro = null;
            this.cargandoRegistro = true;

            bootstrap.Modal.getOrCreateInstance(document.getElementById('modalDetalleFalla')).show();

            try {
                const resp = await axios.post(this.BASE_URL + '/detalle', { id: id });

                if (resp.data && resp.data.success) {
                    this.detalleRegistro = resp.data.data;
                } else {
                    this.notify('error', (resp.data && resp.data.message) || 'No se pudo obtener el detalle.');
                }
            } catch (err) {
                console.error(err);
                this.notify('error', 'Error al consultar el registro.');
            } finally {
                this.cargandoRegistro = false;
            }
        },

        /* -------------------------- ANEXOS -------------------------- */

        async abrirAnexos(id) {
            this.detalleAnexo = null;
            this.anexos = [];
            this.docs = { cotizacion: null, factura: null };
            this.formAnexo = { descripcion: '', archivo: null };
            this.erroresAnexo = { descripcion: false, archivo: false };
            this.cargandoAnexos = true;

            bootstrap.Modal.getOrCreateInstance(document.getElementById('modalAnexos')).show();

            await this.cargarAnexos(id);
        },

        async cargarAnexos(id) {
            try {
                const [resDetalle, resAnexos] = await Promise.all([
                    axios.post(this.BASE_URL + '/detalle', { id: id }),
                    axios.post(this.BASE_URL + '/anexos', { id: id })
                ]);

                if (resDetalle.data && resDetalle.data.success) {
                    this.detalleAnexo = resDetalle.data.data;
                }

                if (resAnexos.data && resAnexos.data.success) {
                    this.anexos = resAnexos.data.data || [];
                }
            } catch (err) {
                console.error(err);
                this.notify('error', 'Error al consultar los anexos.');
            } finally {
                this.cargandoAnexos = false;
            }
        },

        async actualizarDocumentacion() {
            if (!this.detalleAnexo) return;

            if (!this.docs.cotizacion && !this.docs.factura) {
                this.notify('error', 'Debes subir al menos un archivo para actualizar la documentación.');
                return;
            }

            this.subiendoDocs = true;

            try {
                const fd = new FormData();
                fd.append('id', String(this.detalleAnexo.id));

                if (this.docs.cotizacion) fd.append('cotizacion', this.docs.cotizacion);
                if (this.docs.factura) fd.append('factura', this.docs.factura);

                const resp = await axios.post(this.BASE_URL + '/documentacion', fd);

                if (resp.data && resp.data.success) {
                    this.notify('success', resp.data.message);
                    this.docs = { cotizacion: null, factura: null };
                    ['inputCotizacionAnexo', 'inputFacturaAnexo'].forEach((i) => {
                        const el = document.getElementById(i);
                        if (el) el.value = '';
                    });
                    await this.cargarAnexos(this.detalleAnexo.id);
                    this.reloadTable();
                } else {
                    this.notify('error', (resp.data && resp.data.message) || 'No se pudo actualizar la documentación.');
                }
            } catch (err) {
                console.error(err);
                this.notify('error', (err.response && err.response.data && err.response.data.message) || 'Error al actualizar la documentación.');
            } finally {
                this.subiendoDocs = false;
            }
        },

        async guardarAnexo() {
            if (this.guardandoAnexo || !this.detalleAnexo) return;

            this.erroresAnexo = { descripcion: false, archivo: false };

            let hayError = false;

            if (!String(this.formAnexo.descripcion || '').trim()) {
                this.erroresAnexo.descripcion = true;
                hayError = true;
            }

            if (!this.formAnexo.archivo) {
                this.erroresAnexo.archivo = true;
                hayError = true;
            }

            if (hayError) {
                this.notify('error', 'Captura la descripción y selecciona el archivo del anexo.');
                return;
            }

            this.guardandoAnexo = true;

            try {
                const fd = new FormData();
                fd.append('id', String(this.detalleAnexo.id));
                fd.append('descripcion', String(this.formAnexo.descripcion).trim());
                fd.append('archivo', this.formAnexo.archivo);

                const resp = await axios.post(this.BASE_URL + '/anexos/guardar', fd);

                if (resp.data && resp.data.success) {
                    this.notify('success', resp.data.message);
                    this.formAnexo = { descripcion: '', archivo: null };
                    const el = document.getElementById('inputArchivoAnexo');
                    if (el) el.value = '';
                    await this.cargarAnexos(this.detalleAnexo.id);
                    this.reloadTable();
                } else {
                    this.notify('error', (resp.data && resp.data.message) || 'No se pudo agregar el anexo.');
                }
            } catch (err) {
                console.error(err);
                this.notify('error', (err.response && err.response.data && err.response.data.message) || 'Error al agregar el anexo.');
            } finally {
                this.guardandoAnexo = false;
            }
        },

        async eliminarAnexo(id) {
            const resp = await this.deleteAction({
                url: this.BASE_URL + '/anexos/eliminar',
                id: id,
                name: 'el anexo seleccionado'
            });

            if (resp && resp.success) {
                await this.cargarAnexos(this.detalleAnexo.id);
                this.reloadTable();
            }
        },

        /* ------------------------ COMENTARIOS ------------------------ */

        async abrirComentarios(id) {
            this.comentarioId = id;
            this.comentarios = [];
            this.formComentario = '';
            this.erroresComentario = false;
            this.cargandoComentarios = true;

            const el = document.getElementById('offcanvasComentarios');
            bootstrap.Offcanvas.getOrCreateInstance(el).show();

            try {
                const resp = await axios.post(this.BASE_URL + '/comentarios', { id: id });

                if (resp.data && resp.data.success) {
                    this.comentarios = resp.data.data || [];
                } else {
                    this.notify('error', (resp.data && resp.data.message) || 'No se pudieron obtener los comentarios.');
                }
            } catch (err) {
                console.error(err);
                this.notify('error', 'Error al consultar los comentarios.');
            } finally {
                this.cargandoComentarios = false;
                this.desplazarChat();
            }
        },

        desplazarChat() {
            const cont = this.$refs ? this.$refs.chatContainer : null;
            if (cont) cont.scrollTop = cont.scrollHeight;
        },

        async guardarComentario() {
            if (this.guardandoComentario) return;

            if (!String(this.formComentario || '').trim()) {
                this.erroresComentario = true;
                this.notify('error', 'Escribe el comentario.');
                return;
            }

            this.erroresComentario = false;
            this.guardandoComentario = true;

            try {
                const resp = await axios.post(this.BASE_URL + '/comentarios/guardar', {
                    id: this.comentarioId,
                    comentario: String(this.formComentario).trim()
                });

                if (resp.data && resp.data.success) {
                    this.notify('success', resp.data.message);
                    this.formComentario = '';
                    await this.cargarComentarios();
                    this.reloadTable();
                } else {
                    this.notify('error', (resp.data && resp.data.message) || 'No se pudo guardar el comentario.');
                }
            } catch (err) {
                console.error(err);
                this.notify('error', (err.response && err.response.data && err.response.data.message) || 'Error al guardar el comentario.');
            } finally {
                this.guardandoComentario = false;
            }
        },

        async cargarComentarios() {
            try {
                const resp = await axios.post(this.BASE_URL + '/comentarios', { id: this.comentarioId });
                if (resp.data && resp.data.success) {
                    this.comentarios = resp.data.data || [];
                }
            } catch (e) {
                console.error(e);
            }
            this.desplazarChat();
        },

        /* --------------------------- VARIOS --------------------------- */

        async eliminar(id, nombre) {
            await this.deleteAction({
                url: this.BASE_URL + '/destroy',
                id: id,
                name: nombre || 'la falla seleccionada',
                table: '#tabla-fallas-tpv'
            });
            this.reloadTable();
        },

        descargarDocumento(archivo, esAnexo = false) {
            if (!archivo) {
                this.notify('error', 'Archivo no disponible');
                return;
            }

            const tipo = esAnexo ? 'tpv-anexos' : this.TIPO_DESCARGA;

            window.open('/download?tipo=' + encodeURIComponent(tipo)
                       + '&file=' + encodeURIComponent(archivo), '_blank');
        },

        /**
         * Inicializa el <select> de Falla como select2 con tags: true, que es
         * el patrón del proyecto para poder elegir del listado o capturar una
         * falla nueva que todavía no existe.
         *
         * El wrapper trae .is-select2-pending, cuyas reglas lo ocultan con
         * visibility:hidden, así que hay que quitarlo en cuanto el plugin queda
         * montado; de lo contrario el usuario sólo ve el label.
         */
        configurarSelectFalla(selectRef, wrapperRef, modalRef) {
            const selectEl = this.$refs[selectRef];
            const wrapperEl = this.$refs[wrapperRef];
            const modalEl = document.getElementById(modalRef);

            if (!selectEl || typeof $ === 'undefined' || !$.fn.select2) {
                this.revelarCampoSelect(wrapperEl);
                return;
            }

            const $select = $(selectEl);
            const $parent = $(wrapperEl || modalEl || selectEl.parentElement);

            // Reconstruye las opciones: catálogo vigente + el valor que ya
            // tenía la falla (si no está en el catálogo se agrega), para que
            // al abrir el modal el select2 muestre el dato guardado.
            const actuales = Array.isArray(this.catalogos.fallas) ? this.catalogos.fallas.slice() : [];
            const valorActual = String(this.form.falla || '').trim();

            if (valorActual !== '' && !actuales.includes(valorActual)) {
                actuales.push(valorActual);
            }

            $select.empty();
            $select.append($('<option></option>'));

            actuales.forEach((o) => {
                const opcion = $('<option></option>').val(o).text(o);
                if (o === valorActual) {
                    opcion.attr('selected', 'selected');
                }
                $select.append(opcion);
            });

            $select.select2({
                dropdownParent: $parent,
                width: '100%',
                placeholder: 'Selecciona o escribe una falla...',
                tags: true,
                allowClear: false
            });

            $select.off('change.select2tpv').on('change.select2tpv', (e) => {
                this.form.falla = $(e.target).val() || '';
                this.errores.falla = false;
            });

            $select.closest('.select2-modal-field').removeClass('is-select2-pending');
            this.revelarCampoSelect(wrapperEl);

            // Tras montar el plugin hay que fijar el valor y disparar el
            // change: select2 dibuja su etiqueta a partir de ese evento, y
            // sin él el combo se vería vacío aunque el valor esté asignado.
            if (valorActual !== '') {
                $select.val(valorActual).trigger('change.select2');
            } else {
                $select.trigger('change.select2');
            }

            // Libera el plugin al cerrar el modal para no dejar nodos huérfanos.
            if (modalEl && !modalEl._select2FallaCleanup) {
                modalEl._select2FallaCleanup = true;
                modalEl.addEventListener('hidden.bs.modal', () => {
                    const $s = $(selectEl);
                    if ($s.hasClass('select2-hidden-accessible')) {
                        $s.select2('destroy');
                    }
                    if (wrapperEl) wrapperEl.classList.add('is-select2-pending');
                });
            }
        },

        /**
         * Red de seguridad: si select2 no está disponible, el <select> nativo se
         * muestra igual. Nunca debe quedar un campo invisible tras el label.
         */
        revelarCampoSelect(wrapperEl) {
            if (wrapperEl) {
                wrapperEl.classList.remove('is-select2-pending');
                wrapperEl.style.visibility = 'visible';
            }
        },

        /**
         * Refuerza el valor del <select> de Falla. Con select2 ya montado hay
         * que avisarle con change para que refresque la etiqueta visible.
         */
        sincronizarSelect(modalId, selector) {
            const aplicar = () => {
                const modal = document.getElementById(modalId);
                if (!modal) return;

                const select = selector ? modal.querySelector(selector) : modal.querySelector('select');
                if (!select) return;

                const valor = String(this.form.falla || '');
                const existe = Array.prototype.some.call(select.options, (o) => o.value === valor);

                // El valor puede no estar en el catálogo (falla capturada a mano).
                if (!existe && valor !== '') {
                    const nueva = document.createElement('option');
                    nueva.value = valor;
                    nueva.textContent = valor;
                    select.appendChild(nueva);
                }

                if (valor === '') {
                    select.value = '';
                } else {
                    select.value = valor;
                }

                if (typeof $ !== 'undefined' && $.fn && select.classList) {
                    const $select = $(select);
                    $select.val(valor);
                    $select.trigger('change.select2');
                }
            };

            if (typeof this.$nextTick === 'function') {
                this.$nextTick(() => requestAnimationFrame(aplicar));
            } else {
                requestAnimationFrame(aplicar);
            }
        },

        reloadTable() {
            if (typeof window.reloadFallasTPV === 'function') {
                window.reloadFallasTPV();
            } else {
                const $t = $('#tabla-fallas-tpv');
                if ($t.length && $.fn.DataTable.isDataTable($t)) {
                    $t.DataTable().ajax.reload(null, false);
                }
            }
        }

    }));
});