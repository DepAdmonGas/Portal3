document.addEventListener('alpine:init', () => {
    Alpine.data('recibosNominaComponent', () => ({
        idYear: 0,
        idEstacionActual: 0,

        esSemanal: false,
        estacionResuelta: false,
        esDirector: false,
        esMexdesa: false,
        puedeCrear: false,
        puedeEditar: false,
        puedeEliminar: false,
        puedeDescargar: false,
        periodos: [],
        periodoActual: 1,
        periodoActualInfo: null,
        resumenInfo: null,

        colaboradorEditando: null,
        formEdicion: {
            importe: 0,
            original: 0,
            prima_vacacional: 0
        },
        guardandoEdicion: false,

        comentarioIdActual: 0,
        comentarios: [],
        nuevoComentario: '',
        guardandoComentario: false,

        personalFaltante: [],
        personalFaltanteCargando: false,
        guardandoPersonal: false,

        listaAcuses: [],
        acusesCargando: false,
        subiendoAcusePeriodo: false,

        subiendoAcuseMexdesa: false,
        subiendoAguinaldo: false,

        get baseUrl() {
            return window.location.pathname.includes('/departamento-operativo')
                ? '/departamento-operativo/recursos-humanos/recibos-nomina'
                : '/recursos-humanos/recibos-nomina';
        },

        // ---------- PERSISTENCIA DEL PERIODO ----------
        // El periodo elegido se guarda en sessionStorage para que sobreviva a
        // una recarga del navegador (F5). La clave incluye el modulo, el anio y
        // la estacion, de modo que al cambiar de estacion o de anio no se
        // arrastre la seleccion de otro contexto.
        //
        // El requisito es "conserva al recargar, se reinicia si salgo del
        // modulo y regreso". sessionStorage por si solo sobrevive a la
        // navegacion dentro de la pestana, asi que se distingue el tipo de
        // navegacion con la Navigation Timing API: en "reload" (F5 o recargar)
        // se restaura lo guardado; en "navigate" (entrar al modulo desde el
        // menu) y en "back_forward" (boton atras) se descarta.
        esRecargaDePagina() {
            try {
                const nav = performance.getEntriesByType('navigation')[0];
                return nav ? nav.type === 'reload' : false;
            } catch (e) {
                return false;
            }
        },

        clavePeriodo() {
            return `rn_periodo_${this.idYear}_${this.idEstacionActual}`;
        },

        leerPeriodoGuardado() {
            // Solo tiene sentido leer en una recarga real de la pagina.
            if (!this.esRecargaDePagina()) return 0;
            try {
                const v = sessionStorage.getItem(this.clavePeriodo());
                return v ? parseInt(v, 10) || 0 : 0;
            } catch (e) {
                return 0;
            }
        },

        guardarPeriodo() {
            if (!this.idEstacionActual || !this.periodoActual) return;
            try {
                sessionStorage.setItem(this.clavePeriodo(), String(this.periodoActual));
            } catch (e) {
                // Modo privado o cuota llena: se sigue sin persistir.
            }
        },

        // Legacy modal-editar-info-nomina.php: el bloque de prima solo aparece
        // cuando hay aviso pendiente (prima 0 + alerta 0) o cuando ya se pagó
        // y el aviso se cerró (prima 2 + alerta 1). El valor 1 lo fija el
        // sistema al vencer el plazo, nunca el usuario.
        get puedeEditarPrima() {
            const row = this.colaboradorEditando;
            if (!row) return false;
            const prima = parseInt(row.prima_vacacional, 10) || 0;
            const alerta = row.alerta_bd === undefined ? 1 : parseInt(row.alerta_bd, 10);
            return (prima === 0 && alerta === 0) || (prima === 2 && alerta === 1);
        },


        init() {
            const c = document.getElementById('container');
            if (!c) return;

            this.idYear = parseInt(c.dataset.idYear);
            this.esDirector = c.dataset.esDirector === 'true';
            this.esMexdesa = c.dataset.esMexdesa === 'true';
            this.puedeCrear = c.dataset.puedeCrear === 'true';
            this.puedeEditar = c.dataset.puedeEditar === 'true';
            this.puedeEliminar = c.dataset.puedeEliminar === 'true';
            this.puedeDescargar = c.dataset.puedeDescargar === 'true';

            // El DELETE GLOBAL vive en Alpine.data('actions'), que solo existe
            // dentro de un ambito x-data="actions()". Alpine no expone una forma
            // publica de leer ese factory por nombre (Alpine.raw() es de Vue y no
            // existe en Alpine), asi que no se intenta inyectarlo aqui: los
            // botones de eliminar declaran x-data="actions()" en el markup y
            // llaman a deleteAction() directamente, igual que el resto del
            // proyecto (rol-comodines, biometricos-config-*).

            window.rnComponentInstance = this;

            // Patron estandar de Portal3: customReload se invoca desde
            // ModuleStationSelector._notify() despues de guardar el contexto en
            // sesion, de modo que el backend ya resuelve la misma localidad.
            // Asi no depende del orden de carga entre Alpine y el selector.
            this.registrarCustomReload();
        },

// El enganche se reintenta porque Alpine arranca con defer y
        // ModuleStationSelector se inicializa en DOMContentLoaded. El registro
        // ocurre una sola vez para no duplicar callbacks, y el reintento esta
        // acotado para no dejar un temporizador vivo si el selector no existe.
        registrarCustomReload(intento = 0) {
            const c = document.getElementById('container');
            if (!c || typeof ModuleStationSelector === 'undefined') return;

            const key = c.dataset.moduleStationKey;

            if (this._reloadRegistrado) return;

            const inst = ModuleStationSelector._instances
                && ModuleStationSelector._instances[key];

            if (!inst) {
                if (intento < 60) {
                    setTimeout(() => this.registrarCustomReload(intento + 1), 50);
                }
                return;
            }

            this._reloadRegistrado = true;

            const previo = inst._customReload;
            inst._customReload = (ms) => {
                if (typeof previo === 'function') previo(ms);
                this.refrescarEstacion();
            };

            // Resuelve el valor inicial que el servidor ya dejo puesto.
            this.refrescarEstacion(true);
        },

        refrescarEstacion(forzar = false) {
            const idPrevio = this.idEstacionActual;

            this.idEstacionActual = this.obtenerEstacionActual();
            this.estacionResuelta = this.idEstacionActual > 0;

            // Ignora recargas duplicadas: el enganche inicial y el cambio de
            // estación pueden coincidir con el mismo valor ya resuelto.
            if (!forzar && this.idEstacionActual === idPrevio) {
                return;
            }

            this.actualizarPeriodos();
        },

        // El selector usa "estacion_<id>" o "depto_<id>". Ambos son ids de
        // op_rh_localidades, asi que la regla semanal/quincenal del legacy se
        // aplica igual en los dos casos.
        parsearSelector(valor) {
            const v = String(valor || '');
            const id = parseInt(v.replace('depto_', '').replace('estacion_', ''), 10) || 0;
            return id;
        },

        obtenerEstacionActual() {
            const c = document.getElementById('container');
            if (!c) return 0;

            // Se consulta la instancia estandar, que ya expone getValue().
            if (typeof ModuleStationSelector !== 'undefined' && ModuleStationSelector._instances) {
                const inst = ModuleStationSelector._instances[c.dataset.moduleStationKey];
                if (inst) {
                    const v = inst.getValue();
                    const id = parseInt(v.id_depto || v.id_estacion || 0, 10);
                    if (id > 0) return id;
                }
            }

            const sel = document.getElementById('module-station-selector-' + c.dataset.moduleStationKey);
            if (sel && sel.value) {
                const id = this.parsearSelector(sel.value);
                if (id > 0) return id;
            }

            return parseInt(c.dataset.idEstacion || '0');
        },

        async actualizarPeriodos() {
            if (!this.estacionResuelta || !this.idEstacionActual) {
                this.periodos = [];
                this.esSemanal = false;
                this.estacionResuelta = false;
                this.periodoActual = 0;
                this.resumenInfo = null;
                this.pintarPeriodos();
                return;
            }

            try {
                const resp = await axios.post(`${this.baseUrl}/periodos/${this.idYear}`, {
                    id_estacion: this.idEstacionActual
                });
                if (!resp.data.success) {
                    this.periodos = [];
                    this.periodoActual = 0;
                    this.pintarPeriodos();
                    return;
                }

                this.esSemanal = resp.data.es_semanal;
                this.periodos = resp.data.periodos || [];
                this.aplicarPeriodo(resp.data.periodo_actual || 1);
            } catch (e) {
                console.error(e);
            }
        },

        // El <select> de periodos lo administra únicamente jQuery + select2,
        // igual que el resto de los módulos. Las opciones se rellenan aquí y
        // el evento change se conecta una sola vez con un namespace, para no
        // duplicar listeners en cada recarga.
        pintarPeriodos() {
            const $sel = $('#select-periodo');
            if (!$sel.length || !window.jQuery || !jQuery.fn.select2) return;

            if ($sel.hasClass('select2-hidden-accessible')) {
                $sel.select2('destroy');
            }

            $sel.empty();

            if (!this.periodos.length) {
                $sel.append(new Option('Selecciona...', ''));
                $sel.trigger('change');
                return;
            }

            this.periodos.forEach(p => {
                $sel.append(new Option(p.label, p.numero));
            });

            $sel.off('change.rn').on('change.rn', () => this.cambiarPeriodo());
            this.initSelect2Periodo($sel);
            $sel.val(this.periodoActual).trigger('change.select2');
        },

        // select2 mide el ancho al crearse. Si el panel aún está oculto
        // (x-show) queda con width 0 y se ve deformado, por eso se reintenta
        // hasta que el select sea visible.
        initSelect2Periodo($sel) {
            if ($sel.hasClass('select2-hidden-accessible')) {
                $sel.select2('destroy');
            }

            const crear = () => $sel.select2({
                width: 'auto',
                dropdownParent: $('#container')
            });

            if ($sel.is(':visible')) {
                crear();
                return;
            }

            let intentos = 0;
            const timer = setInterval(() => {
                if ($sel.is(':visible') || ++intentos > 40) {
                    clearInterval(timer);
                    crear();
                }
            }, 50);
        },

        cambiarPeriodo() {
            const $sel = $('#select-periodo');
            this.periodoActual = parseInt($sel.val(), 10) || 0;
            this.actualizarPeriodoActualInfo();
            this.guardarPeriodo();
            this.recargarTabla();
        },

        // Muestra el numero y el rango de fechas del periodo elegido, igual que
        // el encabezado del legacy ("Semana N dd/mm/yyyy al dd/mm/yyyy").
        actualizarPeriodoActualInfo() {
            const encontrado = this.periodos.find(p => p.numero === this.periodoActual);
            if (!encontrado) {
                this.periodoActualInfo = null;
                return;
            }
            this.periodoActualInfo = {
                numero: encontrado.numero,
                inicio: encontrado.inicio_fmt || encontrado.inicio,
                fin: encontrado.fin_fmt || encontrado.fin
            };
        },

        // Prioridad de la seleccion: primero lo que el usuario dejo guardado en este
        // contexto (persiste al recargar), despues el periodo que sugiere el
        // servidor y por ultimo el ultimo habilitado.
        aplicarPeriodo(actualServido) {
            if (!this.periodos.length) {
                this.periodoActual = 0;
                this.periodoActualInfo = null;
                this.resumenInfo = null;
                this.pintarPeriodos();
                this.recargarTabla();
                return;
            }

            const disponibles = this.periodos.map(p => p.numero);
            const guardado = this.leerPeriodoGuardado();

            if (guardado && disponibles.includes(guardado)) {
                this.periodoActual = guardado;
            } else {
                const habilitados = this.periodos.filter(p => p.habilitado);
                const esAnioActual = this.idYear === new Date().getFullYear();

                if (esAnioActual && habilitados.some(p => p.numero === actualServido)) {
                    this.periodoActual = actualServido;
                } else if (habilitados.length) {
                    this.periodoActual = habilitados[habilitados.length - 1].numero;
                } else {
                    this.periodoActual = this.periodos.length ? this.periodos[0].numero : 1;
                }
            }

            this.actualizarPeriodoActualInfo();
            this.pintarPeriodos();
            this.guardarPeriodo();
            this.recargarTabla();
        },


        actualizarTotalFooter(total) {
            const el = document.getElementById('tfoot-total-importe');
            if (el) {
                el.textContent = '$' + parseFloat(total || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            }
        },

        recargarTabla() {
            if (!window.tablaRecibosNomina) return;

            if (!this.idEstacionActual) {
                window.tablaRecibosNomina.clear().draw(false);
                this.resumenInfo = null;
                return;
            }

            // No se limpia la tabla antes de pedir los datos: al vaciar y
            // redibujar primero, la tabla se queda en blanco un instante y se
            // ve un parpadeo en cada edicion, alta o baja. ajax.reload()
            // reemplaza las filas cuando llega la respuesta y además conserva
            // la paginación y el orden.
            window.tablaRecibosNomina.ajax.reload(null, false);
        },

        abrirModalEditar(row) {
            this.colaboradorEditando = row;
            this.formEdicion.importe = row.importe_total;
            this.formEdicion.original = row.nomina_original;
            this.formEdicion.prima_vacacional = row.prima_vacacional;

            if (this.$refs.docNominaFile) this.$refs.docNominaFile.value = '';
            if (this.$refs.docNominaFirmaFile) this.$refs.docNominaFirmaFile.value = '';
            if (this.$refs.docAguinaldoFile) this.$refs.docAguinaldoFile.value = '';

            bootstrap.Modal.getOrCreateInstance(document.getElementById('modalEditarNomina')).show();
        },

        async guardarEdicion() {
            this.guardandoEdicion = true;
            try {
                const fd = new FormData();
                fd.append('idReporte', this.colaboradorEditando.id);

                // Solo se envían los campos que el rol puede modificar
                // (matriz de modal-editar-info-nomina.php del legacy).
                if (!this.esMexdesa) {
                    fd.append('importe', this.formEdicion.importe);

                    if (this.$refs.docNominaFile && this.$refs.docNominaFile.files[0]) {
                        fd.append('doc_nomina', this.$refs.docNominaFile.files[0]);
                    }
                    if (this.$refs.docNominaFirmaFile && this.$refs.docNominaFirmaFile.files[0]) {
                        fd.append('doc_nomina_firma', this.$refs.docNominaFirmaFile.files[0]);
                    }
                    if (this.$refs.docAguinaldoFile && this.$refs.docAguinaldoFile.files[0]) {
                        fd.append('doc_nomina_aguinaldo', this.$refs.docAguinaldoFile.files[0]);
                    }
                }

                if (!this.esDirector) {
                    fd.append('original', this.formEdicion.original);
                }

                if (this.esDirector && this.puedeEditarPrima) {
                    fd.append('prima_vacacional', this.formEdicion.prima_vacacional);
                }


                const resp = await axios.post(`${this.baseUrl}/editar`, fd);
                if (resp.data.success) {
                    window.alerts.success(resp.data.message || 'Registro editado exitosamente');
                    bootstrap.Modal.getInstance(document.getElementById('modalEditarNomina'))?.hide();
                    this.recargarTabla();
                } else {
                    window.alerts.error(resp.data.message || 'No se pudo guardar el registro');
                }
            } catch (e) {
                console.error(e);
            } finally {
                this.guardandoEdicion = false;
            }
        },

        async abrirModalComentarios(idReporte) {
            this.comentarioIdActual = idReporte;
            this.nuevoComentario = '';
            this.comentarios = [];

            bootstrap.Offcanvas.getOrCreateInstance(document.getElementById('modalComentarios')).show();

            try {
                const resp = await axios.post(`${this.baseUrl}/comentarios`, { idReporte });
                if (!resp.data.success) {
                    window.alerts.error(resp.data.message || 'No se pudieron cargar los comentarios');
                    return;
                }
                this.comentarios = (resp.data.data || []).map(c => ({
                    ...c,
                    esMio: c.es_propio === true,
                    fecha_formateada: this.formatoFechaHora(c.fecha_hora)
                }));
                this.scrollChatToBottom();
            } catch (e) {
                console.error(e);
            }
        },


        scrollChatToBottom() {
            this.$nextTick(() => {
                if (this.$refs.chatContainer) {
                    this.$refs.chatContainer.scrollTop = this.$refs.chatContainer.scrollHeight;
                }
            });
        },

        formatoFechaHora(v) {
            if (!v) return '';
            const m = String(v).match(/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})/);
            if (!m) return String(v);
            const [, y, mo, d, h, mi] = m;
            const hora = parseInt(h, 10);
            const ampm = hora >= 12 ? 'p.m.' : 'a.m.';
            const h12 = hora % 12 === 0 ? 12 : hora % 12;
            return `${d}/${mo}/${y} ${h12}:${mi} ${ampm}`;
        },

        async agregarComentario() {
            if (!this.nuevoComentario.trim()) return;
            this.guardandoComentario = true;
            try {
                const resp = await axios.post(`${this.baseUrl}/comentarios/guardar`, {
                    idReporte: this.comentarioIdActual,
                    comentario: this.nuevoComentario.trim()
                });
                if (!resp.data.success) {
                    window.alerts.error(resp.data.message || 'No se pudo guardar el comentario');
                    return;
                }
                this.nuevoComentario = '';
                const respC = await axios.post(`${this.baseUrl}/comentarios`, { idReporte: this.comentarioIdActual });
                this.comentarios = (respC.data.data || []).map(c => ({
                    ...c,
                    esMio: c.es_propio === true,
                    fecha_formateada: this.formatoFechaHora(c.fecha_hora)
                }));
                this.scrollChatToBottom();
                this.recargarTabla();
            } catch (e) {
                console.error(e);
            } finally {
                this.guardandoComentario = false;
            }
        },

        // El borrado de colaboradores no vive aqui: el boton "Eliminar" de la
        // tabla invoca al DELETE GLOBAL (deleteAction) desde su propio ambito
        // x-data="actions()", que es el patron del proyecto.

        finalizarActividad() {
            if (!this.puedeEditar) {
                window.alerts.error('No tienes permiso para finalizar la actividad');
                return;
            }
            if (!this.resumenInfo || !this.resumenInfo.puede_finalizar_estacion) {
                window.alerts.error('No es posible finalizar la actividad, se debe de agregar toda la información.');
                return;
            }
            window.alerts.confirm('Finalizar actividad', '¿Deseas finalizar la actividad de recibos de nómina?', async () => {
                try {
                    const resp = await axios.post(`${this.baseUrl}/finalizar`, {
                        idEstacion: this.idEstacionActual,

                        year: this.idYear,
                        periodo: this.periodoActual,
                        descripcion: this.esSemanal ? 'Semana' : 'Quincena',
                        idResponsable: 2
                    });
                    if (resp.data.success) {
                        window.alerts.success(resp.data.message || 'Actividad finalizada correctamente');
                        this.recargarTabla();
                    } else {
                        window.alerts.error(resp.data.message || 'No se pudo finalizar la actividad');
                    }
                } catch (e) {
                    console.error(e);
                }
            });
        },

        // ---------- AGREGAR PERSONAL ----------
        async abrirModalAgregarPersonal() {
            if (!this.puedeCrear) {
                window.alerts.error('No tienes permiso para agregar personal');
                return;
            }
            this.personalFaltante = [];
            this.personalFaltanteCargando = true;
            try {
                const resp = await axios.post(`${this.baseUrl}/personal-faltantes`, {
                    idEstacion: this.idEstacionActual,

                    year: this.idYear,
                    periodo: this.periodoActual,
                    descripcion: this.esSemanal ? 'Semana' : 'Quincena'
                });
                this.personalFaltante = resp.data.success ? (resp.data.data || []) : [];
            } catch (e) {
                console.error(e);
            } finally {
                this.personalFaltanteCargando = false;
                this.$nextTick(() => {
                    const $sel = $('#select-personal-agregar');
                    if ($sel.length && window.jQuery && jQuery.fn.select2) {
                        if ($sel.hasClass('select2-hidden-accessible')) $sel.select2('destroy');
                        $sel.empty();
           this.personalFaltante.forEach(p => {
    const etiqueta = `${p.nombre_completo} (${p.puesto})`;
    $sel.append(new Option(etiqueta, p.id, false, false));
});
                        // El dropdown debe colgarse del propio modal: si se
                        // cuelga de #container queda fuera del modal y se ve
                        // recortado o por detrás del backdrop.
                        $sel.select2({
                            placeholder: 'Selecciona el personal...',
                            allowClear: true,
                            width: '100%',
                            dropdownParent: $('#modalAgregarPersonal .modal-content')
                        }).val(null).trigger('change');
                    }
                });
                bootstrap.Modal.getOrCreateInstance(document.getElementById('modalAgregarPersonal')).show();
            }
        },

        async guardarPersonal() {
            const $sel = $('#select-personal-agregar');
            const ids = $sel.length ? ($sel.val() || []) : [];
            if (!ids.length) {
                window.alerts.error('Selecciona al menos un colaborador');
                return;
            }

            this.guardandoPersonal = true;
            try {
                const resp = await axios.post(`${this.baseUrl}/personal/guardar`, {
                    idEstacion: this.idEstacionActual,

                    year: this.idYear,
                    periodo: this.periodoActual,
                    descripcion: this.esSemanal ? 'Semana' : 'Quincena',
                    personal: ids
                });
                if (resp.data.success) {
                    window.alerts.success(resp.data.message || 'Personal agregado exitosamente');
                    bootstrap.Modal.getInstance(document.getElementById('modalAgregarPersonal'))?.hide();
                    this.recargarTabla();
                } else {
                    window.alerts.error(resp.data.message || 'No se pudo agregar el personal');
                }
            } catch (e) {
                console.error(e);
            } finally {
                this.guardandoPersonal = false;
            }
        },

        // ---------- ACUSE DE NÓMINA (PERIODO) ----------
        async abrirModalAcusePeriodo() {
            this.listaAcuses = [];
            this.acusesCargando = true;
            try {
                const resp = await axios.post(`${this.baseUrl}/acuses`, {
                    idEstacion: this.idEstacionActual,

                    year: this.idYear,
                    periodo: this.periodoActual,
                    descripcion: this.esSemanal ? 'Semana' : 'Quincena'
                });
                this.listaAcuses = resp.data.success ? (resp.data.data || []) : [];
            } catch (e) {
                console.error(e);
            } finally {
                this.acusesCargando = false;
                if (this.$refs.archivoAcusePeriodo) this.$refs.archivoAcusePeriodo.value = '';
                bootstrap.Modal.getOrCreateInstance(document.getElementById('modalAcusePeriodo')).show();
            }
        },

        async guardarAcusePeriodo() {
            const file = this.$refs.archivoAcusePeriodo && this.$refs.archivoAcusePeriodo.files[0];
            if (!file) {
                window.alerts.error('Selecciona un archivo para subir');
                return;
            }

            this.subiendoAcusePeriodo = true;
            try {
                const fd = new FormData();
                fd.append('archivo', file);
                fd.append('idEstacion', this.idEstacionActual);

                fd.append('year', this.idYear);
                fd.append('periodo', this.periodoActual);
                fd.append('descripcion', this.esSemanal ? 'Semana' : 'Quincena');

                const resp = await axios.post(`${this.baseUrl}/acuses/guardar`, fd);
                if (resp.data.success) {
                    window.alerts.success(resp.data.message || 'Documento agregado exitosamente');
                    if (this.$refs.archivoAcusePeriodo) this.$refs.archivoAcusePeriodo.value = '';
                    this.abrirModalAcusePeriodo(false);
                    this.recargarTabla();
                } else {
                    window.alerts.error(resp.data.message || 'No se pudo subir el documento');
                }
            } catch (e) {
                console.error(e);
            } finally {
                this.subiendoAcusePeriodo = false;
            }
        },

        // El borrado de acuses se resuelve con el DELETE GLOBAL desde el markup
        // (x-data mezcla "actions()" con este componente), por eso no hay un
        // metodo propio: el icono llama a deleteAction() directamente y luego
        // refresca la lista del modal.

        // ---------- RECIBOS DE NÓMINA (MEXDESA) ----------
        abrirModalMexdesa() {
            if (!this.esMexdesa) {
                window.alerts.error('No tienes permiso para subir los recibos de nómina');
                return;
            }
            if (this.$refs.archivoAcuseMexdesa) this.$refs.archivoAcuseMexdesa.value = '';
            bootstrap.Modal.getOrCreateInstance(document.getElementById('modalMexdesa')).show();
        },

        async guardarAcuseMexdesa() {
            if (!this.esMexdesa) {
                window.alerts.error('No tienes permiso para subir los recibos de nómina');
                return;
            }
            const file = this.$refs.archivoAcuseMexdesa && this.$refs.archivoAcuseMexdesa.files[0];
            if (!file) {
                window.alerts.error('Selecciona un archivo para subir');
                return;
            }

            this.subiendoAcuseMexdesa = true;
            try {
                const fd = new FormData();
                fd.append('doc_nomina_acuse', file);
                fd.append('idEstacion', this.idEstacionActual);

                fd.append('year', this.idYear);
                fd.append('periodo', this.periodoActual);
                fd.append('descripcion', this.esSemanal ? 'Semana' : 'Quincena');
                fd.append('idAcuse', this.resumenInfo ? this.resumenInfo.id_acuse_mexdesa : 0);

                const resp = await axios.post(`${this.baseUrl}/acuse-mexdesa/guardar`, fd);
                if (resp.data.success) {
                    window.alerts.success(resp.data.message || 'Recibos de nómina subidos');
                    if (this.$refs.archivoAcuseMexdesa) this.$refs.archivoAcuseMexdesa.value = '';
                    this.recargarTabla();
                } else {
                    window.alerts.error(resp.data.message || 'No se pudieron subir los recibos');
                }
            } catch (e) {
                console.error(e);
            } finally {
                this.subiendoAcuseMexdesa = false;
            }
        },

        finalizarMexdesa() {
            if (!this.esMexdesa) {
                window.alerts.error('No tienes permiso para finalizar la actividad');
                return;
            }
            window.alerts.confirm('Finalizar actividad', '¿Deseas finalizar la actividad de recibos de nómina?', async () => {
                try {
                    const resp = await axios.post(`${this.baseUrl}/finalizar`, {
                        idEstacion: this.idEstacionActual,

                        year: this.idYear,
                        periodo: this.periodoActual,
                        descripcion: this.esSemanal ? 'Semana' : 'Quincena',
                        idResponsable: 1
                    });
                    if (resp.data.success) {
                        window.alerts.success(resp.data.message || 'Actividad finalizada correctamente');
                        this.recargarTabla();
                    } else {
                        window.alerts.error(resp.data.message || 'No se pudo finalizar la actividad');
                    }
                } catch (e) {
                    console.error(e);
                }
            });
        },

        // ---------- AGUINALDOS ----------
        abrirModalAguinaldo() {
            if (!this.esMexdesa) {
                window.alerts.error('No tienes permiso para subir los recibos de aguinaldo');
                return;
            }
            if (this.$refs.archivoAguinaldo) this.$refs.archivoAguinaldo.value = '';
            bootstrap.Modal.getOrCreateInstance(document.getElementById('modalAguinaldo')).show();
        },

        async guardarAguinaldo() {
            if (!this.esMexdesa) {
                window.alerts.error('No tienes permiso para subir los recibos de aguinaldo');
                return;
            }
            const file = this.$refs.archivoAguinaldo && this.$refs.archivoAguinaldo.files[0];
            if (!file) {
                window.alerts.error('Selecciona un archivo para subir');
                return;
            }

            this.subiendoAguinaldo = true;
            try {
                const fd = new FormData();
                fd.append('doc_aguinaldo', file);
                fd.append('idEstacion', this.idEstacionActual);

                fd.append('year', this.idYear);
                fd.append('periodo', this.periodoActual);
                fd.append('descripcion', this.esSemanal ? 'Semana' : 'Quincena');
                fd.append('idAguinaldo', this.resumenInfo && this.resumenInfo.aguinaldo ? this.resumenInfo.aguinaldo.id : 0);

                const resp = await axios.post(`${this.baseUrl}/aguinaldo/guardar`, fd);
                if (resp.data.success) {
                    window.alerts.success(resp.data.message || 'Recibos de aguinaldo subidos');
                    if (this.$refs.archivoAguinaldo) this.$refs.archivoAguinaldo.value = '';
                    this.recargarTabla();
                } else {
                    window.alerts.error(resp.data.message || 'No se pudieron subir los recibos de aguinaldo');
                }
            } catch (e) {
                console.error(e);
            } finally {
                this.subiendoAguinaldo = false;
            }
        },

        finalizarAguinaldoOp() {
            if (!this.esMexdesa) {
                window.alerts.error('No tienes permiso para finalizar la actividad');
                return;
            }
            window.alerts.confirm('Finalizar aguinaldos', '¿Deseas finalizar la actividad de recibos de aguinaldo?', async () => {
                try {
                    const resp = await axios.post(`${this.baseUrl}/aguinaldo/finalizar`, {
                        idAguinaldo: this.resumenInfo.aguinaldo.id
                    });
                    if (resp.data.success) {
                        window.alerts.success(resp.data.message || 'Actividad finalizada correctamente');
                        this.recargarTabla();
                    } else {
                        window.alerts.error(resp.data.message || 'No se pudo finalizar la actividad');
                    }
                } catch (e) {
                    console.error(e);
                }
            });
        }
    }));
});
