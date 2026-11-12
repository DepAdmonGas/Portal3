function reportesIndex() {

    return {

        cargando: false,
        cargandoAutolavado: false,
        cargandoDiaAutolavado: false,
        descargandoArchivo: false,

        reportes: [],

        reporte: {
            nombre: '',
            tipo: 'anual',
            estacion: '',
        },

        filtros: {
            idEstacion: 0,
            idDepartamento: 0,
            year: new Date().getFullYear(),
            mes: 0,
        },

        detalleAutolavado: {
            tipo: '',
            year: null,
            meses: [],
            total: 0,
        },

        modalAutolavado: null,

        consultaDia: {
            fecha: '',
        },
        resultadoDiaAutolavado: null,
        fechaHoy: (() => {
            const fecha = new Date();
            const mes = String(fecha.getMonth() + 1).padStart(2, '0');
            const dia = String(fecha.getDate()).padStart(2, '0');
            return `${fecha.getFullYear()}-${mes}-${dia}`;
        })(),

        async init() {

            const navegacion = performance.getEntriesByType('navigation')[0];
            const recarga = navegacion?.type === 'reload';
            let periodoGuardado = null;
            try {
                if (recarga) {
                    periodoGuardado = JSON.parse(sessionStorage.getItem('reportes.periodo') || 'null');
                } else {
                    sessionStorage.removeItem('reportes.periodo');
                }
            } catch (_) {
                periodoGuardado = null;
            }

            if (
                periodoGuardado
                && Number(periodoGuardado.year) >= 2021
                && Number(periodoGuardado.year) <= new Date().getFullYear()
                && Number(periodoGuardado.mes) >= 0
                && Number(periodoGuardado.mes) <= 12
            ) {
                this.filtros.year = Number(periodoGuardado.year);
                this.filtros.mes = Number(periodoGuardado.mes);
            }

            this.sincronizarBreadcrumb();

            document.addEventListener('click', (event) => {
                const enlace = event.target.closest('a[href], [data-action="logout"]');
                if (!enlace) return;

                if (enlace.matches('[data-action="logout"]')) {
                    sessionStorage.removeItem('reportes.periodo');
                    return;
                }

                const href = enlace.getAttribute('href');
                if (!href || href === '#') return;

                try {
                    const destino = new URL(href, window.location.href);
                    if (
                        destino.origin !== window.location.origin
                        || !/^\/reportes(?:\/|$)/.test(destino.pathname)
                    ) {
                        sessionStorage.removeItem('reportes.periodo');
                    }
                } catch (_) {
                    sessionStorage.removeItem('reportes.periodo');
                }
            }, true);

            window.addEventListener('pageshow', (event) => {
                if (!event.persisted) return;

                sessionStorage.removeItem('reportes.periodo');
                this.filtros.year = new Date().getFullYear();
                this.filtros.mes = 0;
                this.sincronizarBreadcrumb();
                this.cargarReportes();
            });

            document.addEventListener('click', (event) => {
                const opcion = event.target.closest('[data-reportes-year], [data-reportes-month]');
                if (!opcion || !document.getElementById('container')) return;
                event.preventDefault();

                const detalle = {};
                if (opcion.dataset.reportesYear !== undefined) {
                    detalle.year = Number(opcion.dataset.reportesYear);
                }
                if (opcion.dataset.reportesMonth !== undefined) {
                    detalle.mes = Number(opcion.dataset.reportesMonth);
                }
                window.dispatchEvent(new CustomEvent('reportes-periodo', { detail: detalle }));
            });

            const modalAutolavado =
                document.getElementById(
                    'modalAutolavado'
                );

            if (modalAutolavado) {

                this.modalAutolavado =
                    new bootstrap.Modal(
                        modalAutolavado
                    );
            }

            const selector = typeof ModuleStationSelector !== 'undefined'
                ? ModuleStationSelector.init('reportes', {
                    customReload: async (instance) => {
                        const context = instance.getValue();
                        this.filtros.idEstacion = Number(context.id_estacion) || 0;
                        this.filtros.idDepartamento = Number(context.id_depto) || 0;
                        await this.cargarReportes();
                    }
                })
                : null;

            const context = selector?.getValue();
            this.filtros.idEstacion = Number(context?.id_estacion) || 0;
            this.filtros.idDepartamento = Number(context?.id_depto) || 0;
            await this.cargarReportes();
        },

        get etiquetaReporte() {
            const year = this.filtros.year || new Date().getFullYear();
            const mes = Number(this.filtros.mes);
            if (mes > 0) {
                return `Reporte Mensual (${this.nombresMeses[mes - 1]} ${year})`;
            }
            return `Reporte Anual ${year}`;
        },

        columnaAccion(total) {
            const cantidad = Math.max(1, Number(total) || 1);
            if (cantidad === 1) return 'col-12';
            if (cantidad === 2) return 'col-6';
            if (cantidad === 3) return 'col-4';
            if (cantidad === 4) return 'col-3';
            return 'col';
        },


        get tituloAutolavado() {

            if (
                this.detalleAutolavado.tipo
                === 'anual'
            ) {

                return (
                    'Reporte anual '
                    + this.detalleAutolavado.year
                );
            }

            return '';
        },


        async cambiarPeriodo(periodo = {}) {
            if (periodo.year !== undefined) {
                this.filtros.year = String(periodo.year);
            }
            if (periodo.mes !== undefined) {
                this.filtros.mes = Number(periodo.mes) || 0;
            }
            if (
                Number(this.filtros.year) === new Date().getFullYear()
                && Number(this.filtros.mes) > new Date().getMonth() + 1
            ) {
                this.filtros.mes = 0;
            }

            try {
                sessionStorage.setItem('reportes.periodo', JSON.stringify({
                    year: Number(this.filtros.year),
                    mes: Number(this.filtros.mes),
                }));
            } catch (_) {}

            this.sincronizarBreadcrumb();
            await this.cargarReportes();
        },

        get nombresMeses() {
            return ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
        },

        sincronizarBreadcrumb() {
            const mesLabel = document.getElementById('reportes-mes-label');
            const yearLabel = document.getElementById('reportes-year-label');
            if (mesLabel) mesLabel.textContent = this.filtros.mes > 0 ? this.nombresMeses[this.filtros.mes - 1] : 'Todo el año';
            if (yearLabel) yearLabel.textContent = String(this.filtros.year || new Date().getFullYear());

            const yearActual = new Date().getFullYear();
            document.querySelectorAll('[data-reportes-month-option]').forEach((opcion) => {
                const mes = Number(opcion.dataset.reportesMonthOption);
                opcion.classList.toggle('d-none', Number(this.filtros.year) === yearActual && mes > new Date().getMonth() + 1);
            });
            this.actualizarTituloPagina();
        },

        actualizarTituloPagina() {
            const titulo = document.getElementById('reportes-page-title');
            if (titulo) titulo.textContent = this.etiquetaReporte;
        },


        /*
        |--------------------------------------------------------------------------
        | Reportes
        |--------------------------------------------------------------------------
        */

        async cargarReportes() {

            try {

                this.cargando = true;

                const response =
                    await axios.get(
                        '/reportes/data',
                        {
                            params: {
                                idEstacion:
                                    this.filtros
                                        .idEstacion,

                                idDepartamento:
                                    this.filtros.idDepartamento,

                                year:
                                    this.filtros
                                        .year,

                                mes:
                                    this.filtros
                                        .mes,
                            }
                        }
                    );

                if (
                    response.data?.success
                ) {

                    const data =
                        response.data?.data
                        ?? {};

                    this.reportes =
                        data.reportes
                        ?? [];

                    this.reporte =
                        data.reporte
                        ?? {
                            nombre: '',
                            tipo: 'anual',
                            estacion: '',
                        };

                    return;
                }

                this.reportes = [];

                this.notificar(
                    'error',
                    response.data?.message
                    ?? 'No fue posible cargar los reportes.'
                );

            } catch (error) {

                console.error(
                    'Error al cargar reportes:',
                    error
                );

                this.reportes = [];

                this.notificar(
                    'error',
                    error.response?.data?.message
                    ?? 'No fue posible cargar los reportes.'
                );

            } finally {

                this.cargando = false;
            }
        },


        /*
        |--------------------------------------------------------------------------
        | Modal buscador
        |--------------------------------------------------------------------------
        */

        async ejecutarAccion(
            reporte,
            accion
        ) {

            if (
                accion.tipo
                === 'detalle'
                && reporte.id
                === 'autolavado'
            ) {

                this.verDetalleAutolavado();

                return;
            }

            if (accion.url) {
                await this.descargarArchivo(reporte, accion);
            }
        },

        async descargarArchivo(reporte, accion) {
            if (this.descargandoArchivo) return;

            const inicioDescarga = performance.now();
            this.descargandoArchivo = true;
            if (window.loader) window.loader.show();
            window.addEventListener('load', () => {
                if (this.descargandoArchivo && window.loader) window.loader.show();
            }, { once: true });

            try {
                // Permite que el navegador pinte el GIF antes de iniciar la petición.
                await new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve)));

                const response = await fetch(accion.url, {
                    method: 'GET',
                    credentials: 'same-origin',
                });
                const tipoContenido = (response.headers.get('content-type') || '').toLowerCase();

                if (!response.ok || tipoContenido.includes('application/json') || tipoContenido.startsWith('text/')) {
                    const cuerpo = await response.text();
                    let mensaje = cuerpo;
                    try {
                        mensaje = JSON.parse(cuerpo).message || cuerpo;
                    } catch (_) {}
                    throw new Error(mensaje || 'No fue posible generar el archivo.');
                }

                const archivo = await response.blob();
                if (!archivo.size) throw new Error('El archivo descargado está vacío.');

                const disposition = response.headers.get('content-disposition') || '';
                const nombreUtf8 = disposition.match(/filename\*=UTF-8''([^;]+)/i);
                const nombreNormal = disposition.match(/filename="?([^";]+)"?/i);
                let nombreArchivo = '';

                if (nombreUtf8) {
                    try {
                        nombreArchivo = decodeURIComponent(nombreUtf8[1].trim());
                    } catch (_) {
                        nombreArchivo = nombreUtf8[1].trim();
                    }
                } else if (nombreNormal) {
                    nombreArchivo = nombreNormal[1].trim();
                }

                if (!nombreArchivo) {
                    const extension = tipoContenido.includes('pdf') || accion.nombre?.toLowerCase() === 'pdf'
                        ? 'pdf'
                        : 'xlsx';
                    nombreArchivo = `${reporte.nombre || 'Reporte'}.${extension}`;
                }

                const urlArchivo = URL.createObjectURL(archivo);
                const enlace = document.createElement('a');
                enlace.href = urlArchivo;
                enlace.download = nombreArchivo;
                document.body.appendChild(enlace);
                enlace.click();
                enlace.remove();
                window.setTimeout(() => URL.revokeObjectURL(urlArchivo), 1000);
            } catch (error) {
                this.notificar(
                    'error',
                    error.message || 'No fue posible descargar el archivo.'
                );
            } finally {
                const tiempoMinimo = 450;
                const esperaRestante = tiempoMinimo - (performance.now() - inicioDescarga);
                if (esperaRestante > 0) {
                    await new Promise(resolve => window.setTimeout(resolve, esperaRestante));
                }
                if (window.loader) window.loader.hide();
                this.descargandoArchivo = false;
            }
        },


        /*
        |--------------------------------------------------------------------------
        | Autolavado
        |--------------------------------------------------------------------------
        */

        async verDetalleAutolavado() {

            this.consultaDia.fecha = '';
            this.resultadoDiaAutolavado = null;
            this.cargandoDiaAutolavado = false;

            this.detalleAutolavado = {
                tipo: '',
                year: null,
                meses: [],
                total: 0,
            };

            this.modalAutolavado?.show();

            try {

                this.cargandoAutolavado =
                    true;


                const response =
                    await axios.get(
                        '/reportes/autolavado/anual',
                        {
                            params: {
                                idEstacion:
                                    this.filtros.idEstacion,

                                year:
                                    this.filtros.year,
                            }
                        }
                    );

                if (
                    !response.data?.success
                ) {

                    throw new Error(
                        response.data?.message
                        ?? 'No fue posible cargar el detalle.'
                    );
                }

                const data =
                    response.data?.data
                    ?? {};

                this.detalleAutolavado = {

                    tipo: 'anual',

                    year:
                        data.year
                        ?? null,

                    meses: data.meses ?? [],

                    total: Number(data.total ?? 0),
                };

            } catch (error) {

                console.error(
                    'Error al cargar autolavado:',
                    error
                );

                this.modalAutolavado?.hide();

                this.notificar(
                    'error',
                    error.response?.data?.message
                    ?? error.message
                    ?? 'No fue posible cargar el reporte de autolavado.'
                );

            } finally {

                this.cargandoAutolavado =
                    false;
            }
        },

        async buscarDiaAutolavado() {
            if (!this.consultaDia.fecha || this.cargandoDiaAutolavado) return;

            this.cargandoDiaAutolavado = true;
            this.resultadoDiaAutolavado = null;

            try {
                const response = await axios.get('/reportes/autolavado/diario', {
                    params: { dia: this.consultaDia.fecha },
                });

                if (!response.data?.success) {
                    throw new Error(response.data?.message ?? 'No fue posible cargar el concentrado diario.');
                }

                this.resultadoDiaAutolavado = response.data?.data ?? null;
            } catch (error) {
                this.notificar(
                    'error',
                    error.response?.data?.message
                        ?? error.message
                        ?? 'No fue posible cargar el concentrado diario de autolavado.'
                );
            } finally {
                this.cargandoDiaAutolavado = false;
            }
        },


        /*
        |--------------------------------------------------------------------------
        | Helpers
        |--------------------------------------------------------------------------
        */

        moneda(valor) {

            return new Intl.NumberFormat(
                'es-MX',
                {
                    style: 'currency',
                    currency: 'MXN',
                }
            ).format(
                Number(valor || 0)
            );
        },


        notificar(
            tipo,
            mensaje
        ) {

            if (
                typeof Swal
                !== 'undefined'
            ) {

                Swal.fire({
                    icon: tipo,
                    text: mensaje,
                    confirmButtonText: 'Aceptar',
                });

                return;
            }

            console.log(
                tipo,
                mensaje
            );
        },

    };
}
