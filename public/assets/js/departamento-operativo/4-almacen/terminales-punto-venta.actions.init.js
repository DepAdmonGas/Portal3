// ============================================================
// Terminales Punto de Venta — Almacén
// Componente Alpine: alta, edición, detalle y eliminación.
// ============================================================

document.addEventListener('alpine:init', () => {

    Alpine.data('terminalesPuntoVentaComponent', () => ({

        BASE_URL: '/departamento-operativo/almacen/terminales-punto-venta',

        guardando: false,
        cargandoDetalle: false,
        cargandoRegistro: false,

        // Estación: la fija el contexto del módulo (ModuleStationService); los
        // usuarios que no tienen selector nunca la cambian desde el front.
        moduleStationKey: '',
        estacionFija: false,
        estacionActual: 0,

        puedeCrear: false,
        puedeEditar: false,
        puedeEliminar: false,
        puedeUsarSelector: false,

        // Campos del formulario, en el mismo orden del legacy. Esta lista es la
        // única fuente de verdad: el alta, la validación y el llenado del modal
        // de editar se derivan de aquí, de modo que ningún campo puede quedar
        // fuera al agregar o al editar el módulo.
        campos: [
            'tpv', 'no_serie', 'modelo', 'no_lote', 'tipo_conexion',
            'no_afiliacion', 'telefono', 'estado', 'rollos', 'cargadores',
            'pedestales', 'estatus_tpv', 'no_impresiones', 'tipo_tpv'
        ],

        // Campos que el legacy dejaba sin llenar; se conservan como opcionales.
        camposOpcionales: ['no_lote', 'estatus_tpv', 'tipo_tpv'],

        form: {},
        detalle: null,
        detalleRegistro: null,

        // Opciones vigentes de los dos campos tipo lista (pintadas por el servidor).
        catalogos: { estatus_tpv: [], tipo_tpv: [] },

        errores: {},

        init() {
            const c = document.getElementById('container');

            this.limpiarFormulario();
            this.limpiarErrores();

            if (c) {
                this.BASE_URL = c.dataset.baseUrl || this.BASE_URL;
                this.moduleStationKey = c.dataset.moduleStationKey || '';
                this.estacionFija = c.dataset.estacionFija === 'true';
                this.estacionActual = parseInt(c.dataset.estacionActual || '0', 10);
                this.puedeCrear = c.dataset.puedeCrear === 'true';
                this.puedeEditar = c.dataset.puedeEditar === 'true';
                this.puedeEliminar = c.dataset.puedeEliminar === 'true';
                this.puedeUsarSelector = c.dataset.puedeUsarSelector === 'true';

                try {
                    this.catalogos = Object.assign(this.catalogos, JSON.parse(c.dataset.catalogos || '{}') || {});
                } catch (e) {
                    console.error(e);
                }
            }

            document.addEventListener('tpv:detalle', (e) => this.abrirDetalle(e.detail.id));
            document.addEventListener('tpv:editar',  (e) => this.abrirEditar(e.detail.id));
            document.addEventListener('tpv:fallas',  (e) => this.irAFallasTPV(e.detail.id));
            document.addEventListener('tpv:eliminar', (e) => this.eliminar(e.detail.id, e.detail.nombre));
        },

        /** Objeto de formulario vacío con todos los campos del legacy. */
        formularioVacio() {
            const f = {};
            this.campos.forEach((c) => { f[c] = ''; });
            f.id_estacion = '';
            return f;
        },

        limpiarFormulario() {
            this.form = this.formularioVacio();
            this.detalle = null;
            this.limpiarErrores();
        },

        limpiarErrores() {
            const e = {};
            this.campos.forEach((c) => { e[c] = false; });
            this.errores = e;
        },

        esOpcionEstatusTpv(v) {
            return this.catalogos.estatus_tpv.includes(v);
        },

        esOpcionTipoTpv(v) {
            return this.catalogos.tipo_tpv.includes(v);
        },

        abrirNuevo() {
            this.limpiarFormulario();
            this.form.id_estacion = this.estacionContexto();
            bootstrap.Modal.getOrCreateInstance(document.getElementById('modalNuevo')).show();
            this.sincronizarControles('modalNuevo');
        },

        /**
         * Estación que aplica al registro: la del selector de contexto si está
         * visible, o la fija del usuario cuando no tiene selector.
         */
        estacionContexto() {
            if (this.moduleStationKey && typeof ModuleStationSelector !== 'undefined') {
                const instancia = ModuleStationSelector._instances[this.moduleStationKey];
                if (instancia) {
                    const v = instancia.getValue();
                    if (v.id_estacion) return String(v.id_estacion);
                }
            }
            if (this.estacionActual > 0) return String(this.estacionActual);
            return '';
        },

        /**
         * El botón de fallas sólo existe para los usuarios multiestación, es
         * decir quienes pueden cambiar de estación con el selector. Si viene un
         * id se abre el historial de esa terminal; sin id, el de la estación del
         * contexto.
         */
        irAFallasTPV(id = null) {
            let url = '/departamento-operativo/almacen/terminales-punto-venta-fallas';
            if (id) {
                url += '/' + encodeURIComponent(id);
            }
            window.location.href = url;
        },

        /* ------------------------- VALIDACIÓN ------------------------- */

        /**
         * Idéntica en agregar y editar: marca en rojo todos los campos
         * obligatorios y avisa arriba a la derecha.
         *
         * @returns {boolean} true si hay errores
         */
        validar() {
            this.limpiarErrores();

            let hayError = false;

            this.campos.forEach((campo) => {
                if (this.camposOpcionales.includes(campo)) return;

                const valor = this.form[campo];
                if (String(valor === undefined || valor === null ? '' : valor).trim() === '') {
                    this.errores[campo] = true;
                    hayError = true;
                }
            });

            return hayError;
        },

        formData() {
            const fd = new FormData();
            fd.append('id_estacion', String(this.form.id_estacion || '').trim());
            this.campos.forEach((c) => {
                fd.append(c, String(this.form[c] === undefined || this.form[c] === null ? '' : this.form[c]).trim());
            });
            return fd;
        },

        async guardarNuevo() {
            if (this.guardando) return;

            if (this.validar()) {
                this.notify('error', 'Completa los campos obligatorios.');
                return;
            }

            this.guardando = true;

            try {
                const resp = await axios.post(this.BASE_URL + '/store', this.formData());

                if (resp.data && resp.data.success) {
                    this.notify('success', resp.data.message);
                    bootstrap.Modal.getOrCreateInstance(document.getElementById('modalNuevo')).hide();
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

        async abrirEditar(id) {
            this.limpiarFormulario();
            this.cargandoDetalle = true;

            bootstrap.Modal.getOrCreateInstance(document.getElementById('modalEditar')).show();

            try {
                const resp = await axios.post(this.BASE_URL + '/detalle', { id: id });

                if (resp.data && resp.data.success) {
                    const d = resp.data.data || {};
                    this.detalle = d;

                    // Se llenan TODOS los campos recorriendo la lista canónica,
                    // así que ningún campo del legacy puede quedar sin cargar.
                    this.campos.forEach((campo) => {
                        this.form[campo] = d[campo] === undefined || d[campo] === null ? '' : String(d[campo]);
                    });

                    // El número de impresiones viaja crudo para el input numérico
                    // (el detalle lo devuelve formateado con separador de miles).
                    this.form.no_impresiones = d.no_impresiones_raw !== undefined
                        ? d.no_impresiones_raw
                        : (d.no_impresiones === undefined ? '' : String(d.no_impresiones).replace(/,/g, ''));

                    this.form.id_estacion = String(d.estacion_id || '');
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

            // Los <select> sólo existen después de que Alpine resuelve el x-if del
            // formulario; rAF garantiza que ya estén en el DOM para fijarles el
            // valor (mismo patrón que el resto de los módulos de Almacén).
            this.sincronizarControles('modalEditar');
        },

        /**
         * Fuerza el valor de los <select> del modal una vez que Alpine ya pintó
         * el formulario. Sin esto el x-model puede quedar apuntando al
         * placeholder cuando el <option> se agrega después del binding.
         */
        sincronizarControles(modalId) {
            const aplicar = () => {
                const modal = document.getElementById(modalId);
                if (!modal) return;

                const pares = [
                    ['[x-ref="selEstatusTpv"]', this.form.estatus_tpv],
                    ['[x-ref="selTipoTpv"]',   this.form.tipo_tpv]
                ];

                pares.forEach(([sel, valor]) => {
                    const el = modal.querySelector(sel);
                    if (!el) return;

                    const valorFinal = valor === undefined || valor === null ? '' : String(valor);

                    // Si el valor no está entre las opciones, se agrega una opción
                    // de respaldo para no perder datos históricos del legacy.
                    const existe = Array.prototype.some.call(el.options, (o) => o.value === valorFinal);
                    const respaldo = el.querySelector('option[value="__LEGACY__"]');

                    if (!existe && respaldo) {
                        respaldo.value = valorFinal;
                        respaldo.textContent = valorFinal;
                        respaldo.style.display = '';
                    }

                    el.value = valorFinal;
                });
            };

            if (typeof this.$nextTick === 'function') {
                this.$nextTick(() => requestAnimationFrame(aplicar));
            } else {
                requestAnimationFrame(aplicar);
            }
        },

        async guardarEdicion() {
            if (this.guardando || this.cargandoDetalle || !this.detalle) return;

            if (this.validar()) {
                this.notify('error', 'Completa los campos obligatorios.');
                return;
            }

            this.guardando = true;

            try {
                const fd = this.formData();
                fd.append('id', String(this.detalle.id));

                const resp = await axios.post(this.BASE_URL + '/update', fd);

                if (resp.data && resp.data.success) {
                    this.notify('success', resp.data.message);
                    bootstrap.Modal.getOrCreateInstance(document.getElementById('modalEditar')).hide();
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

        async abrirDetalle(id) {
            this.detalleRegistro = null;
            this.cargandoRegistro = true;

            bootstrap.Modal.getOrCreateInstance(document.getElementById('modalDetalle')).show();

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

        async eliminar(id, nombre) {
            await this.deleteAction({
                url: this.BASE_URL + '/destroy',
                id: id,
                name: nombre || 'terminal',
                table: '#tabla-terminales-punto-venta'
            });
        },

        reloadTable() {
            if (typeof window.reloadTerminalesTPV === 'function') {
                window.reloadTerminalesTPV();
            } else {
                const $t = $('#tabla-terminales-punto-venta');
                if ($t.length && $.fn.DataTable.isDataTable($t)) {
                    $t.DataTable().ajax.reload(null, false);
                }
            }
        }

    }));
});