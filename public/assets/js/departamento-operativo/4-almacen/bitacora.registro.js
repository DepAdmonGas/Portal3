// ============================================================
// Bitácora de Maquinaria y Equipos — Almacén
// Pantallas del registro (una vista, cuatro rutas):
//   detalle       → solo lectura (checklist de consulta + firmas)
//   editar        → costo del registro (bloqueos del legacy)
//   mantenimiento → checklist editable + evidencias
//   firma         → checklist de consulta + firmas (pad A / B / tokens)
// El payload del registro lo pinta el servidor; este componente
// sólo aplica permisos y acciones sobre él.
// ============================================================

document.addEventListener('alpine:init', () => {

    Alpine.data('bitacoraRegistro', () => ({

        BASE: '/departamento-operativo/almacen/maquinaria-equipos-bitacora',
        BASE_RAIZ: '/departamento-operativo/almacen/maquinaria-equipos-bitacora',
        pdfBase: '/departamento-operativo/almacen/maquinaria-equipos-bitacora/pdf',
        volver: '/departamento-operativo/almacen/maquinaria-equipos-bitacora',
        modo: 'detalle',

        titulos: {
            detalle: 'Detalle Mantenimiento',
            editar: 'Editar registro',
            mantenimiento: 'Mantenimiento',
            firma: 'Firmar Mantenimiento'
        },

        reg: {},
        revisadoPor: {},
        costoEdicion: 0,
        guardando: false,

        // Firmas: un pad por tarjeta (A, B) y estado único por tipo.
        pads: {},
        padCanvas: {},
        token: '',
        tokenOcupado: false,

        // Evidencias (mantenimiento)
        subiendo: false,

        /* ------------------------------------------------------------ */
        /* Init                                                          */
        /* ------------------------------------------------------------ */

        initRegistro() {
            const c = document.getElementById('container');

            if (c) {
                this.BASE = c.dataset.baseUrl || this.BASE;
                this.pdfBase = c.dataset.pdfBase || this.pdfBase;
                this.volver = c.dataset.volver || this.volver;
                this.modo = c.dataset.modo || 'detalle';

                try {
                    this.reg = JSON.parse(c.dataset.registro || '{}');
                } catch (e) {
                    this.reg = {};
                }
            }

            this.costoEdicion = this.reg.costo ?? 0;
            this.revisadoPor = {};

            (this.reg.actividades || []).forEach((a) => {
                this.revisadoPor[a.id] = a.revisado_por || '';
            });
        },

        tituloModo() {
            return (this.titulos[this.modo] || 'Registro');
        },

        // Columnas del checklist de consulta, mismas reglas que el legacy:
        // "Revisado por" sólo si el formato no es Diario y existe alguna
        // actividad revisada; "Cambiado por" si hay select/texto.
        tieneRevisado() {
            if ((this.reg.frecuencia || 0) === 1) {
                return false;
            }

            return (this.reg.actividades || []).some((a) => a.revision_tipo === 'revisado');
        },

        tieneCambio() {
            return (this.reg.actividades || []).some((a) => ['select', 'texto'].includes(a.revision_tipo));
        },

        actividadesVisible() {
            return (this.reg.actividades || []).filter((a) => !a.es_fila_seccion);
        },

        // Numeración del legacy: sólo cuentan las actividades (no las secciones).
        numeroActividad(index) {
            const acts = this.reg.actividades || [];
            let n = 0;

            for (let i = 0; i <= index && i < acts.length; i++) {
                if (!acts[i].es_fila_seccion) {
                    n++;
                }
            }

            return n;
        },

        // Estilo de la fila de sección, igual que el detalle/PDF del legacy.
        claseSeccion(tipo) {
            if (tipo === 'titulo') {
                return 'fw-bold';
            }

            if (tipo === 'subtitulo') {
                return 'fw-bold text-secondary';
            }

            return 'fst-italic text-muted';
        },

        urlFirma(id) {
            return this.BASE_RAIZ + '-firma/' + id;
        },

        /* ------------------------------------------------------------ */
        /* Checklist de consulta (detalle / firma)                       */
        /* ------------------------------------------------------------ */

        valorSiNo(a) {
            if (a.revision_tipo === 'checkbox') {
                return (a.resultado == 1);
            }

            return !!(a.revisado_por || a.cambiado_por_texto);
        },

        nombreUsuario(id) {
            if (!id) {
                return 'Sin informacion';
            }

            const u = (this.reg.usuarios_estacion || []).find((x) => x.id === id);

            return u ? u.nombre : 'Sin informacion';
        },

        /* ------------------------------------------------------------ */
        /* Firmas                                                        */
        /* ------------------------------------------------------------ */

        // Estado único de cada tarjeta: firmada | pad | token | pendiente.
        // detalle = sólo lectura; en firma puede firmar quien corresponda;
        // en mantenimiento sólo la firma A (quien elabora).
        estadoFirma(tipo) {
            if (this.firmaDe(tipo)) {
                return 'firmada';
            }
            if (this.modo === 'detalle') {
                return 'pendiente';
            }
            if (!this.disponibleFirma(tipo)) {
                return 'pendiente';
            }
            if (!(this.modo === 'firma' || tipo === 'A')) {
                return 'pendiente';
            }
            return this.viaDibujo(tipo) ? 'pad' : 'token';
        },

        firmaDe(tipo) {
            const f = (this.reg.firmas_rows || []).filter((x) => x.tipo_firma === tipo);

            return f.length ? f[0] : null;
        },

        fechaFirma(f) {
            return (f && f.fecha_label) ? f.fecha_label : '';
        },

        // Medio de firma, igual que el legado: pad (imagen) = digital, token = electrónico.
        mensajeMedioFirma(f) {
            if (!f) {
                return '';
            }

            return f.es_imagen
                ? 'El mantenimiento se firmó por un medio digital.'
                : 'El mantenimiento se firmó por un medio electrónico.';
        },

        // ¿Toca firmar este tipo ahora? (el backend manda disponibilidad)
        disponibleFirma(tipo) {
            const f = this.reg.firmas && this.reg.firmas[tipo];

            return !!(f && f.disponible);
        },

        viaDibujo(tipo) {
            const f = this.reg.firmas && this.reg.firmas[tipo];

            return !!(f && f.via_dibujo);
        },

        tituloFirmaAccion(tipo) {
            if (tipo === 'A') {
                return 'FIRMA DE QUIEN ELABORA';
            }

            return tipo === 'B' ? 'FIRMA DE VO.BO.' : 'FIRMA DE AUTORIZACIÓN';
        },

        // Mensajes idénticos a los de solicitud-cheque-firmar.
        mensajeFaltaFirma(tipo) {
            if (tipo === 'A') {
                return 'Sin firma registrada';
            }

            if (tipo === 'B') {
                return this.firmaDe('A') ? '¡Falta la firma de Vo.Bo.!' : 'Falta la firma de quien elabora';
            }

            return (this.firmaDe('A') && this.firmaDe('B')) ? '¡Falta la firma de Autorización!' : 'Faltan las firmas anteriores';
        },

        /* ------------------------------------------------------------ */
        /* Firmas: pad inline por tarjeta                                */
        /* ------------------------------------------------------------ */

        // Mismo patrón que los demás módulos (solicitud-cheque/crear):
        // se dimensiona el canvas y se crea el pad. Con x-if el bloque se
        // re-inserta al cambiar de estado, así que se recrea si el canvas cambió.
        prepararPad(tipo) {
            this.$nextTick(() => {
                const canvas = document.getElementById('canvas-' + tipo);

                if (!canvas || typeof SignaturePad === 'undefined') {
                    return;
                }

                if (this.pads[tipo] && this.padCanvas[tipo] === canvas) {
                    return;
                }

                const ratio = Math.max(window.devicePixelRatio || 1, 1);

                canvas.width = canvas.offsetWidth * ratio;
                canvas.height = canvas.offsetHeight * ratio;
                canvas.getContext('2d').scale(ratio, ratio);

                this.pads[tipo] = new SignaturePad(canvas, {
                    backgroundColor: 'rgb(255, 255, 255)'
                });
                this.padCanvas[tipo] = canvas;
            });
        },

        limpiarPad(tipo) {
            if (this.pads[tipo]) {
                this.pads[tipo].clear();
            }
        },

        async guardarFirmaPad(tipo) {
            const pad = this.pads[tipo];

            if (!pad || pad.isEmpty()) {
                this.showAlert('warning', 'Firma vacía', 'Dibuja tu firma antes de guardar.');
                return;
            }

            const imagen = pad.toDataURL('image/png');
            const url = tipo === 'B' ? '/firmar-vobo' : '/firmar-elaboro';

            try {
                const { data } = await axios.post(this.BASE + url, {
                    id: this.reg.id,
                    firma: imagen
                });

                if (!data.success) {
                    throw { response: { data: data } };
                }

                this.ok(data.message || 'Firma registrada.');
                this.pads[tipo] = null;
                await this.refrescarRegistro();
            } catch (e) {
                this.error(e);
            }
        },

        crearTokenTelegram(tipo) {
            this.crearToken('telegram', tipo);
        },

        crearTokenEmail(tipo) {
            this.crearToken('email', tipo);
        },

        async crearToken(via, tipoFirma) {
            if (this.tokenOcupado) {
                return;
            }

            this.tokenOcupado = true;

            try {
                const { data } = await axios.post(this.BASE + '/token/solicitar', {
                    id: this.reg.id,
                    via: via,
                    tipoFirma: tipoFirma
                });

                if (!data.success) {
                    throw { response: { data: data } };
                }

                this.ok(data.message || 'Token generado.');
            } catch (e) {
                this.error(e);
            } finally {
                this.tokenOcupado = false;
            }
        },

        // Firma con token (igual que solicitud-cheque: el token se captura en la tarjeta)
        async firmarSolicitud(tipoFirma) {
            const token = (this.token || '').trim();

            if (!token) {
                this.error({ response: { data: { message: 'Falta ingresar el token de seguridad' } } });
                return;
            }

            try {
                const { data } = await axios.post(this.BASE + '/token/validar', {
                    id: this.reg.id,
                    tipoFirma: tipoFirma,
                    token: token
                });

                if (!data.success) {
                    throw { response: { data: data } };
                }

                this.ok(data.message || 'Firma registrada.');
                this.token = '';
                await this.refrescarRegistro();
            } catch (e) {
                this.error(e);
            }
        },
        /* ------------------------------------------------------------ */
        /* Checklist editable (mantenimiento)                            */
        /* ------------------------------------------------------------ */

        async guardarActividad(actividad, extra) {
            try {
                const payload = {
                    idActividad: actividad.id,
                    completada: (extra.completada !== undefined) ? extra.completada : (actividad.resultado == 1 ? 1 : 0),
                    observacion: actividad.observacion || '',
                    revisadoPor: (extra.revisadoPor !== undefined)
                        ? extra.revisadoPor
                        : (this.revisadoPor[actividad.id] || '')
                };

                if (extra.cambiadoPorTexto !== undefined) {
                    payload.cambiadoPorTexto = extra.cambiadoPorTexto;
                }

                const { data } = await axios.post(this.BASE + '/actividad/guardar', payload);

                if (!data.success) {
                    throw { response: { data: data } };
                }

                await this.refrescarRegistro();
            } catch (e) {
                this.error(e);
                await this.refrescarRegistro();
            }
        },

        cambiarRevisado(actividad, ev) {
            const valor = ev.target.value;

            if (!valor) {
                this.error({ response: { data: { message: 'Selecciona a la persona.' } } });
                this.revisadoPor[actividad.id] = actividad.revisado_por || '';
                return;
            }

            this.guardarActividad(actividad, { completada: 1, revisadoPor: valor });
        },

        cambiarTexto(actividad) {
            const texto = String(actividad.cambiado_por_texto || '').trim();

            if (!texto) {
                this.error({ response: { data: { message: 'Escribe el nombre.' } } });
                return;
            }

            this.guardarActividad(actividad, { completada: 1, cambiadoPorTexto: texto });
        },

        async refrescarRegistro() {
            try {
                const { data } = await axios.post(this.BASE + '/detalle', { id: this.reg.id });
                const nuevo = data.data || {};

                (nuevo.actividades || []).forEach((a) => {
                    this.revisadoPor[a.id] = a.revisado_por || '';
                });

                this.reg = nuevo;
            } catch (e) {
                this.error(e);
            }
        },

        /* ------------------------------------------------------------ */
        /* Costo / flujo                                                 */
        /* ------------------------------------------------------------ */

        async eliminarRegistro(reg) {
            if (this.guardando) {
                return;
            }

            // Confirmación global de Portal3 (bajaAction).
            const res = await this.bajaAction({
                url: this.BASE + '/eliminar-registro',
                id: reg.id,
                name: 'Registro ' + (reg.orden_label || '') + ' y sus consecuentes',
                table: null
            });

            if (res && res.success) {
                window.location.href = this.volver;
            }
        },

        /* ------------------------------------------------------------ */
        /* Evidencias (mantenimiento)                                    */
        /* ------------------------------------------------------------ */

        async subirEvidencia() {
            const input = document.getElementById('inputEvidenciaBitacora');

            if (!input || !input.files || input.files.length === 0) {
                this.showAlert('warning', 'Sin archivo', 'Selecciona un archivo.');
                return;
            }

            const form = new FormData();
            form.append('id', this.reg.id);
            form.append('evidencia', input.files[0]);

            this.subiendo = true;

            try {
                const { data } = await axios.post(this.BASE + '/evidencias/subir', form, {
                    headers: { 'Content-Type': 'multipart/form-data' }
                });

                if (!data.success) {
                    throw { response: { data: data } };
                }

                this.ok(data.message || 'Evidencia subida.');
                input.value = '';
                await this.refrescarRegistro();
            } catch (e) {
                this.error(e);
            } finally {
                this.subiendo = false;
            }
        },

        async eliminarEvidencia(evidencia) {
            const res = await this.bajaAction({
                url: this.BASE + '/evidencias/eliminar',
                id: evidencia.id,
                name: evidencia.nombre_original || 'evidencia',
                table: null
            });

            if (res && res.success) {
                await this.refrescarRegistro;
            }
        },

        /* ------------------------------------------------------------ */
        /* Utilidades                                                    */
        /* ------------------------------------------------------------ */

        claseEstatus(estatus) {
            return ['bg-danger-subtle text-danger', 'bg-warning text-dark', 'bg-success-subtle text-success'][estatus] || 'bg-secondary';
        },

        ok(msg) {
            if (typeof this.showAlert === 'function') {
                this.showAlert('success', 'Correcto', msg);
            }
        },

        error(e) {
            const data = (e && e.response && e.response.data) || {};
            const msg = data.message || 'Ocurrió un problema, intenta de nuevo.';

            if (typeof this.showAlert === 'function') {
                this.showAlert('error', 'Error', msg);
            }
        }

    }));

});
