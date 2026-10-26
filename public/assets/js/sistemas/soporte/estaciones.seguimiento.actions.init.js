// ============================================================
// Soporte (Estaciones) — Seguimiento del ticket:
// fechas comprometidas (ticket + actividades), estatus de las
// actividades y cierre del trabajo.
// Reutiliza el patrón de /sistemas/soporte (Alpine, acciones
// globales, notificaciones y Telegram del backend).
// ============================================================

document.addEventListener('alpine:init', () => {

    Alpine.data('soporteEstacionesSeguimientoComponent', () => {

        /* El ticket NO se imprime en el HTML (evita exponer los datos del
           registro en el código de la página): se carga con AJAX desde
           /show al iniciar el componente. */

        const c = document.getElementById('soporte-estaciones-seguimiento');

        const idTicket = Number((c && c.dataset.idTicket) || 0);
        const puedeSeguimiento = !!(c && c.dataset.puedeSeguimiento === 'true');
        const puedeDescargar = !!(c && c.dataset.puedeDescargar === 'true');
        const endpoint = (c && c.dataset.endpoint) || '/sistemas/estaciones';

        return {

        ticket: null,
        actividades: [],
        cargando: true,

        diasHabiles: 2,
        fechaInicio: '',
        fechaTermino: '',

        puedeSeguimiento: puedeSeguimiento,
        puedeDescargar: puedeDescargar,

        guardando: false,

        endpoint: endpoint,
        idTicket: idTicket,

        /* Propiedades simples (nunca getters): el x-data hace
           {...acciones(), ...componente()} y el operador spread copia el
           VALOR de un getter, con lo que quedaría congelado. */
        fechaRegistro: false,
        trabajoFinalizado: false,
        puedeEditarActividades: false,
        puedeFinalizar: false,

        // Estados de los atributos :disabled.
        // Cuando el trabajo ya está cerrado (ticket en "En proceso" o
        // finalizado) la pantalla queda en modo sólo lectura: no se pueden
        // guardar fechas ni editar actividades.
        seguimientoDeshabilitado: true,
        guardarDeshabilitado: true,
        cierreDeshabilitado: true,
        actividadesDeshabilitadas: true,

        /* -------------------------------------------------------------- */
        /* Carga del ticket                                                 */
        /* -------------------------------------------------------------- */

        async init() {
            await this.cargarTicket();
        },

        /** Descarga el ticket del servidor y recalcula los permisos de la pantalla. */
        async cargarTicket() {
            this.cargando = true;

            try {
                const resp = await axios.get(this.endpoint + '/show', {
                    params: { id_ticket: this.idTicket }
                });

                const data = resp.data || {};

                if (data.success === true && data.data) {
                    this.ticket = data.data;
                    this.actividades = data.data.actividades || [];
                    this.fechaInicio = data.data.fecha_inicio_iso || '';
                    this.fechaTermino = data.data.fecha_termino_iso || '';
                } else {
                    this.notify('error', data.message || 'No se pudo cargar el ticket.');
                }

                this.aplicarEstado();
            } catch (e) {
                this.notify('error', 'Error al cargar el ticket.');
            } finally {
                this.cargando = false;
            }
        },

        /** Habilita o deshabilita los campos según el estado del ticket. */
        aplicarEstado() {
            this.fechaRegistro = !!(this.ticket && this.ticket.fecha_inicio_iso && this.ticket.fecha_termino_iso);
            this.trabajoFinalizado = this.ticket ? Number(this.ticket.estado) !== 1 : false;
            this.puedeEditarActividades = this.puedeSeguimiento && this.fechaRegistro;
            this.puedeFinalizar = this.puedeSeguimiento && this.fechaRegistro && !this.trabajoFinalizado;

            this.seguimientoDeshabilitado = !this.puedeSeguimiento || this.trabajoFinalizado;
            this.guardarDeshabilitado = !this.puedeSeguimiento || this.trabajoFinalizado;
            this.cierreDeshabilitado = !this.puedeFinalizar;
            this.actividadesDeshabilitadas = !this.puedeEditarActividades || this.trabajoFinalizado;
        },


        /* -------------------------------------------------------------- */
        /* Utilidades                                                      */
        /* -------------------------------------------------------------- */

        /** Devuelve el valor o "S/I" cuando el campo viene vacío. */
        valorCampo(valor) {
            const texto = (valor === null || valor === undefined) ? '' : String(valor).trim();

            return texto === '' ? 'S/I' : texto;
        },

        /** Clase del badge de prioridad: Baja (verde), Media (amarillo), Alta (rojo). */
        clasePrioridad(prioridad) {
            const valor = String(prioridad || '').trim();

            if (valor === 'Baja') return 'badge bg-success text-white';
            if (valor === 'Media') return 'badge bg-warning text-white';
            if (valor === 'Alta') return 'badge bg-danger text-white';

            return 'badge bg-secondary text-white';
        },

        /** Clase del badge de estatus: Finalizado (verde), Creando (amarillo), demás (rojo). */
        claseEstado(estado) {
            const valor = Number(estado);

            if (valor === 3) return 'badge bg-success text-white';
             if (valor === 2) return 'badge bg-warning text-white';
            if (valor === 0) return 'badge bg-warning text-white';

            return 'badge bg-danger text-white';
        },

        /* -------------------------------------------------------------- */
        /* Fechas                                                          */
        /* -------------------------------------------------------------- */

        /**
         * Calcula la fecha de inicio (mañana, saltando el fin de semana) y
         * la fecha de término según los días hábiles indicados. Es la misma
         * regla del sistema anterior.
         *
         * El número de días se limita a un rango razonable: sin ese límite
         * un valor captura equivocado (por ejemplo 170000) movía la fecha de
         * término cientos de años adelante.
         */
        calcularFechas() {
            const dias = Math.min(60, Math.max(1, parseInt(this.diasHabiles || 2, 10) || 2));

            this.diasHabiles = dias;

            const inicio = new Date();
            inicio.setHours(0, 0, 0, 0);
            inicio.setDate(inicio.getDate() + 1);

            // 6 = sábado, 0 = domingo: se recorre al lunes.
            if (inicio.getDay() === 6) {
                inicio.setDate(inicio.getDate() + 2);
            } else if (inicio.getDay() === 0) {
                inicio.setDate(inicio.getDate() + 1);
            }

            const fin = new Date(inicio.getTime());
            let contador = 0;

            while (contador < dias) {
                const dia = fin.getDay();
                if (dia >= 1 && dia <= 5) {
                    contador++;
                }
                fin.setDate(fin.getDate() + 1);
            }

            this.fechaInicio = this.toIso(inicio);
            this.fechaTermino = this.toIso(fin);
        },

        toIso(fecha) {
            const mes = String(fecha.getMonth() + 1).padStart(2, '0');
            const dia = String(fecha.getDate()).padStart(2, '0');

            return fecha.getFullYear() + '-' + mes + '-' + dia;
        },

        /**
         * Guarda las fechas del ticket y de sus actividades y notifica al
         * solicitante.
         */
        async guardarSeguimiento() {
            if (this.guardando) return;

            if (!this.fechaInicio || !this.fechaTermino) {
                this.notify('error', 'Indica la fecha de inicio y la fecha de término.');
                return;
            }

            this.guardando = true;
            this.guardarDeshabilitado = true;

            try {
                const actividades = this.actividades.map((a) => ({
                    id: a.id,
                    fecha_inicio: a.fecha_inicio_iso || '',
                    fecha_termino: a.fecha_termino_iso || ''
                }));

                const resp = await this.createAction({
                    url: this.endpoint + '/guardar-seguimiento',
                    data: {
                        id_ticket: this.ticket.id_ticket,
                        fecha_inicio: this.fechaInicio,
                        fecha_termino: this.fechaTermino,
                        actividades: actividades
                    }
                });

                if (resp && resp.success) {
                    await this.recargarTicket();
                }
            } catch (e) {
                this.notify('error', 'Error al guardar el seguimiento.');
            } finally {
                this.guardando = false;
                this.guardarDeshabilitado = !this.puedeSeguimiento;
            }
        },

        /**
         * Cambia el estatus de una actividad. Cada cambio notifica al
         * solicitante.
         */
        async guardarActividad(actividad, opcion) {
            const detalle = opcion === 3 ? String(actividad.estado_valor) : (actividad[opcion === 1 ? 'fecha_inicio_iso' : 'fecha_termino_iso'] || '');

            const resp = await this.createAction({
                url: this.endpoint + '/editar-actividad',
                data: { id_actividad: actividad.id, detalle: detalle, opcion: opcion }
            });

                if (resp && resp.success) {
                    await this.recargarTicket();
                } else {
                    // Se restauran las fechas que están registradas en el servidor.
                    this.fechaInicio = this.ticket ? (this.ticket.fecha_inicio_iso || '') : '';
                    this.fechaTermino = this.ticket ? (this.ticket.fecha_termino_iso || '') : '';
                }
        },

        /* -------------------------------------------------------------- */
        /* Cierre                                                          */
        /* -------------------------------------------------------------- */

        /**
         * Cierra el trabajo de Sistemas: las actividades quedan finalizadas y
         * el solicitante tiene 3 días para validar.
         */
        async finalizarTrabajo() {
            if (this.guardando) return;

            if (!this.puedeFinalizar) {
                this.notify('error', 'El trabajo ya fue cerrado.');
                return;
            }

            if (!this.fechaRegistro) {
                this.notify('error', 'Registra primero las fechas comprometidas del ticket.');
                return;
            }

            const result = await Swal.fire({
                title: '¿Finalizar el trabajo de este ticket?',
                html: 'Las actividades quedarán como <b>Finalizadas</b> con la fecha de término de hoy.<br>'
                    + 'El solicitante tendrá <b>3 días</b> para validar la atención.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Sí, finalizar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#28a745',
                reverseButtons: true
            });

            if (!result.isConfirmed) return;

            this.guardando = true;
            this.cierreDeshabilitado = true;

            try {
                const resp = await this.createAction({
                    url: this.endpoint + '/finalizar-soporte',
                    data: { id_ticket: this.ticket.id_ticket, finalizar: 2 }
                });

                if (resp && resp.success) {
                    await this.recargarTicket();
                }
            } catch (e) {
                this.notify('error', 'Error al finalizar el trabajo.');
            } finally {
                this.guardando = false;
                this.cierreDeshabilitado = !this.puedeFinalizar;
            }
        },

        async recargarTicket() {
            await this.cargarTicket();
        }


    };

    });
});
