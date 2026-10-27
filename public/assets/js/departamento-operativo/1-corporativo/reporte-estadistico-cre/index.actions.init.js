/**
 * Reporte Estadístico de la CRE — Dpto. Operativo.
 *
 * Comportamiento heredado del módulo anterior del proyecto portal-sasisopa
 * (reporte-estadistico-index.php + lista-reporte-cre*.php + descargar-facturas.php).
 *
 * El año y el mes viven en el breadcrumb (cambiarYearMes del layout); este
 * componente sólo carga el contenido del reporte para la estación activa.
 */
function reporteEstadisticoCreComponent() {
    return {
        // Configuracion general
        baseUrl: '',
        year: 0,
        mes: 0,
        anios: [],
        productos: [],
        meses: [],
        puedeDescargar: false,

        estacionNombre: '',
        reporte: null,
        anuales: [],
        vistaAnual: false,

        cargando: false,
        descargando: false,

        // Comentarios
        comentarios: [],
        cargandoComentarios: false,
        guardandoComentario: false,
        formComentario: '',
        contextoMensaje: { idReporte: 0, idFecha: 0, etiqueta: '' },
        resumenDiaComentario: null,

        //------------------------------------------------------------------
        init() {
            const c = document.getElementById('container');
            if (!c) return;

            const config = this.leerJson(c.dataset.creConfig, {});

            this.baseUrl = config.baseUrl || c.dataset.baseUrl || '';
            this.year = parseInt(config.year || c.dataset.idYear, 10) || 0;
            this.mes = parseInt(config.month || c.dataset.idMes, 10) || 0;
            this.anios = Array.isArray(config.anios) ? config.anios : this.leerJson(c.dataset.anios, []);
            this.productos = Array.isArray(config.productos) ? config.productos : this.leerJson(c.dataset.productos, []);
            this.meses = Array.isArray(config.meses) ? config.meses : this.leerJson(c.dataset.meses, []);
            this.puedeDescargar = config.puedeDescargar === true || c.dataset.puedeDescargar === 'true';
            this.estacionNombre = config.estacion || c.dataset.estacion || '';

            const tieneEstacion = parseInt(config.estacionId || c.dataset.idEstacion, 10) > 0;

            // Sin estación el backend responde 403: se muestra el mensaje
            // obligatorio y no se consulta nada.
            const sinEstacion = document.getElementById('cre-sin-estacion');
            const contenido = document.getElementById('cre-contenido');

            if (sinEstacion) sinEstacion.style.display = tieneEstacion ? 'none' : '';
            if (contenido) contenido.style.display = tieneEstacion ? '' : 'none';

            if (tieneEstacion) {
                this.cargar();
            }

            // El cambio de estación desde el selector global recarga la vista.
            if (typeof ModuleStationSelector !== 'undefined') {
                ModuleStationSelector.init(config.moduleStationKey || c.dataset.moduleStationKey, {
                    customReload: () => window.location.reload()
                });
            }
        },

        //------------------------------------------------------------------
        // UTILIDADES
        //------------------------------------------------------------------
        leerJson(valor, porDefecto) {
            try {
                const v = JSON.parse(valor || 'null');
                return v === null ? porDefecto : v;
            } catch (e) {
                return porDefecto;
            }
        },

        /**
         * Etiqueta del periodo visible en la barra superior.
         *
         * ES UN METODO Y NO UN GETTER A PROPOSITO: el template usa
         * x-data="{ ...reporteEstadisticoCreComponent() }". El operador de
         * dispersion ({...objeto}) copia el VALOR de los getters, no la
         * funcion, asi que un getter quedaria congelado con el valor del
         * momento del arranque y nunca se actualizaria.
         */
        etiquetaPeriodo() {
            const m = this.meses.find((x) => x.mes === this.mes);
            return (m ? m.nombre : '') + ' ' + this.year;
        },

        /** Dia al que pertenece el comentario que se esta capturando. */
        etiquetaDiaComentarios() {
            return this.contextoMensaje.etiqueta || 'Selecciona un día';
        },

        /** Formato visual 1,234.50 sin tocar el valor almacenado. */
        /**
         * Formato visual con separador de miles y EXACTAMENTE dos decimales.
         *
         * No recibeparametro de decimales a proposito: el modulo exige dos
         * decimales en todas las cantidades, y asi ninguna llamada puede
         * volver a pedir mas. Solo afecta la presentacion; el valor guardado
         * en la base de datos no se toca.
         */
        formatearNumero(valor) {
            const n = Number(valor || 0);
            return n.toLocaleString('es-MX', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        },

        colorProducto(nombre) {
            const p = (this.productos || []).find((x) => x.nombre === nombre);
            return p ? p.color : 'secondary';
        },

        etiquetaPeriodoFactura(periodo) {
            return { 1: 'Factura', 2: 'Factura inicial', 3: 'Factura final' }[periodo] || 'Factura';
        },

        //------------------------------------------------------------------
        // CARGA
        //------------------------------------------------------------------
        async cargar() {
            this.cargando = true;

            const url = this.baseUrl + '/data';

            try {
                const resp = await axios.get(url, {
                    params: { year: this.year, mes: this.mes },
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });

                const cuerpo = resp.data || {};

                // JsonResponse::custom() responde sin 'success'; los errores
                // usan JsonResponse::error/forbidden.
                if (cuerpo.success === false) {
                    this.reporte = null;
                    this.anuales = [];
                    this.notify('error', cuerpo.message || 'No fue posible cargar el reporte.');
                    return;
                }

                this.reporte = cuerpo.reporte || null;
                this.anuales = cuerpo.anual || [];
            } catch (err) {
                this.reporte = null;
                this.anuales = [];

                const respuesta = err.response;
                const cuerpo = respuesta && respuesta.data ? respuesta.data : {};
                const estado = respuesta ? respuesta.status : 0;

                // El backend responde 403 con el mensaje de estación, que ya
                // se muestra en pantalla; no se duplica como notificación.
                if (estado === 403 && cuerpo.message) {
                    console.warn('[CRE] ' + url + ' -> 403: ' + cuerpo.message);
                    return;
                }

                console.error('[CRE] ' + url + ' -> ' + (estado || 'sin respuesta'), err);
                this.notify('error', cuerpo.message || ('No fue posible cargar el reporte. (HTTP ' + (estado || '0') + ')'));
            } finally {
                this.cargando = false;
            }
        },

        irAMes(mes) {
            // El año/mes viven en la URL (ver year-mes-template). Se navega
            // directamente porque cambiarYearMes() pertenece al scope Alpine
            // del layout, no a este componente.
            window.location.href = this.baseUrl + '/' + this.year + '/' + mes;
        },

        cambiarVistaAnual() {
            this.vistaAnual = !this.vistaAnual;

            if (this.vistaAnual && this.anuales.length === 0) {
                this.cargarAnual();
            }
        },

        async cargarAnual() {
            this.cargando = true;

            try {
                const resp = await axios.get(this.baseUrl + '/anual', {
                    params: { year: this.year }
                });

                this.anuales = (resp.data && resp.data.anual) || [];
            } catch (err) {
                this.anuales = [];
            } finally {
                this.cargando = false;
            }
        },

        //------------------------------------------------------------------
        // DESCARGA ANUAL
        //------------------------------------------------------------------
        descargarFacturas() {
            if (!this.puedeDescargar || this.descargando) return;

            this.descargando = true;

            const url = this.baseUrl + '/facturas?year=' + encodeURIComponent(this.year);

            // window.location mantiene la descarga aunque el servidor responda
            // con un error en texto plano (no se puede leer con axios binary).
            const ventana = window.open(url, '_blank');

            const restaurar = () => { this.descargando = false; };

            if (ventana) {
                ventana.addEventListener('afterunload', restaurar);
                setTimeout(restaurar, 4000);
            } else {
                window.location.href = url;
                setTimeout(restaurar, 4000);
            }
        },

        //------------------------------------------------------------------
        // COMENTARIOS
        //------------------------------------------------------------------
/**
         * Abre el panel de comentarios del dia seleccionado.
         * La etiqueta del encabezado se arma aqui para que el modal indique
         * de forma explicita a que dia pertenece lo que se capture.
         */
        async abrirComentarios(dia) {
            const m = this.meses.find((x) => x.mes === this.mes);

            this.contextoMensaje = {
                idReporte: dia.id_reporte,
                idFecha: dia.id_fecha,
                etiqueta: (dia.dia ? dia.dia : dia.fecha) + ' · '
                    + (dia.fecha_label || '') + ' · '
                    + ((m ? m.nombre : '') + ' ' + this.year)
            };
            this.resumenDiaComentario = {
                ...dia,
                idReporte: dia.id_reporte,
                fechaEtiqueta: dia.dia || dia.fecha || '',
                periodo: this.etiquetaPeriodo(),
                estacion: this.estacionNombre
            };
            this.formComentario = '';

            const offcanvas = bootstrap.Offcanvas.getOrCreateInstance(document.getElementById('offcanvasComentariosCre'));
            offcanvas.show();

            await this.cargarComentarios();
        },

        async cargarComentarios() {
            this.cargandoComentarios = true;
            this.comentarios = [];

            try {
                const resp = await axios.get(this.baseUrl + '/mensajes', {
                    params: {
                        id_reporte: this.contextoMensaje.idReporte,
                        id_fecha: this.contextoMensaje.idFecha
                    }
                });

                this.comentarios = (resp.data && resp.data.data) || [];
            } catch (err) {
                this.comentarios = [];
            } finally {
                this.cargandoComentarios = false;
                this.scrollChat();
            }
        },

        /**
         * Guarda el comentario.
         *
         * Sin bloqueo por permisos: el modulo anterior (reporte-estadistico-index.php
         * de portal-sasisopa) permitia comentar a cualquier usuario autenticado,
         * y asi se conserva.
         */
        async guardarComentario() {
            const texto = this.formComentario.trim();

            if (!texto || this.guardandoComentario) return;

            this.guardandoComentario = true;

            try {
                const resp = await axios.post(this.baseUrl + '/mensajes/create', {
                    id_reporte: this.contextoMensaje.idReporte,
                    id_fecha: this.contextoMensaje.idFecha,
                    mensaje: texto
                });

                const cuerpo = resp.data || {};

                /*
                 * El controller responde con JsonResponse::custom(['data' => ...]),
                 * que NO incluye la clave 'success'. Solo los errores usan
                 * JsonResponse::error/forbidden y si llevan 'success' => false.
                 *
                 * Por eso se compara contra false en lugar de pedir truthy:
                 * de lo contrario una respuesta correcta se tomaba como error.
                 */
                if (cuerpo.success === false) {
                    this.notify('error', cuerpo.message || 'No fue posible enviar el mensaje.');
                    return;
                }

                this.comentarios = cuerpo.data || [];
                this.formComentario = '';
                this.scrollChat();
                this.actualizarContadores();
            } catch (err) {
                const respuesta = err.response;
                const cuerpo = respuesta && respuesta.data ? respuesta.data : {};

                console.error('[CRE] error al enviar comentario', err);

                this.notify('error', cuerpo.message
                    || ('No fue posible enviar el mensaje. (HTTP ' + (respuesta ? respuesta.status : 0) + ')'));
            } finally {
                this.guardandoComentario = false;
            }
        },

        /** Refleja en las filas el nuevo conteo sin recargar todo el reporte. */
        actualizarContadores() {
            if (!this.reporte || !this.reporte.dias) return;

            const idFecha = this.contextoMensaje.idFecha;

            this.reporte.dias.forEach((dia) => {
                if (dia.id_fecha === idFecha) {
                    dia.total_mensajes = this.comentarios.length;
                }
            });

            this.reporte = Object.assign({}, this.reporte);
        },

        scrollChat() {
            this.$nextTick(() => {
                const cont = this.$refs.chatCre;
                if (cont) cont.scrollTop = cont.scrollHeight;
            });
        }
    };
}
