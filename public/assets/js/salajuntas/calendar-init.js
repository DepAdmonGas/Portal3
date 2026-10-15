document.addEventListener('alpine:init', () => {

    Alpine.data('calendario', (modulo = 'sala-juntas') => ({

        /*
        |--------------------------------------------------------------------------
        | ESTADO GENERAL
        |--------------------------------------------------------------------------
        */

        modulo,

        calendar: null,

        fechaSeleccionada: '',

        actividadesDia: [],

        // Totales del rango visible en el calendario.
        totales: { programadas: 0, solicitudes: 0, total: 0 },

        juntaSeleccionada: null,

        juntaComentarios: null,

        /*
        |--------------------------------------------------------------------------
        | MODALES
        |--------------------------------------------------------------------------
        */

        modalDia: null,

        modalJunta: null,

        modalNuevaJunta: null,

        modalComentarios: null,

        // Modal de solicitud de uso
        modalSolicitud: null,

        // Modal con el que el creador acepta o deniega la solicitud
        modalRespuestaSolicitud: null,

        // Modal donde el creador elige la fecha y horario al autorizar
        modalAutorizacion: null,

        /*
        |--------------------------------------------------------------------------
        | NUEVA JUNTA
        |--------------------------------------------------------------------------
        */


        puestosDisponibles: [],

        personalDisponible: [],

        horasDisponibles: [],

        horasTermino: [],

        cargandoHoras: false,

        /*
         * Secuencia de las consultas de disponibilidad. Cada peticion toma
         * un numero y solo la ultima en llegar puede escribir el resultado:
         * asi un cambio rapido de fecha nunca se queda sin horas o muestra
         * las de otro dia.
         */
        peticionHoras: 0,

        guardandoJunta: false,

        errorPersonal: false,

        errorNuevaJunta: '',

        // Permisos que llegan desde el backend; la vista tambien los usa.
        permisos: { leer: false, crear: false, editar: false, eliminar: false, descargar: false },

        // Quien esta navigating: define "convoca" y los comentarios propios.
        idUsuarioActual: 0,
        nombreUsuarioActual: '',
        puestoUsuarioActual: '',

        // Datos del formulario (id_puesto lo usa el backend al guardar).
        idPuestoUsuario: 0,

        // Junta que se esta editando (0 = alta nueva).
        idJuntaEditando: 0,

        // Comentarios de la junta abierta.
        comentarios: [],
        comentariosCargados: false,
        volverAlDetalle: false,
        rememberedDetalle: null,
        guardandoComentario: false,
        nuevoComentario: '',

        // Solicitud de uso del horario de una junta ajena.
        solicitud: { id: 0, motivo: '', descripcion: '', departamentos: [], personal: [] },

        // Campos obligatorios del modal de solicitud (patron de los demas modulos)
        erroresSolicitud: { motivo: false, descripcion: false, destinatarios: false },

        // Campos obligatorios del formulario de juntas
        erroresJunta: {
            descripcion: false,
            fecha: false,
            hora_inicio: false,
            hora_termino: false,
            destinatarios: false
        },

        guardandoSolicitud: false,
        respondiendoSolicitud: false,
        respuestaSolicitud: null,
        idJuntaSolicitud: 0,

        // Autorizacion: fecha y horario elegidos por el creador
        autorizacion: { fecha: '', hora_inicio: '', hora_termino: '' },
        horasAutorizacion: [],
        horasTerminoAutorizacion: [],
        cargandoHorasAutorizacion: false,
        erroresAutorizacion: { fecha: false, hora_inicio: false, hora_termino: false },
        puestosSolicitud: [],
        personalSolicitud: [],

        nuevaJunta: {
            descripcion: '',
            idUsuario: '',
            id_puesto: 0,
            fecha: '',
            hora_inicio: '',
            hora_termino: '',
            departamentos: [],
            personal: []
        },


        /*
        |--------------------------------------------------------------------------
        | INIT
        |--------------------------------------------------------------------------
        */

        init() {

            /*
             * Permisos y contexto que entrega el backend.
             */
            const cont = document.getElementById('container');

              if (cont) {

                  this.permisos = this.leerJson(
                      cont.dataset.permisos,
                      this.permisos
                  );

                  /*
                   * Quien esta navegando. "Convoca" siempre es esta persona,
                   * asi que se necesita para marcar comentarios propios.
                   */
                  this.idUsuarioActual =
                      Number(cont.dataset.idUsuario) || 0;

                  this.nombreUsuarioActual =
                      cont.dataset.nombreUsuario || '';

                  this.puestoUsuarioActual =
                      cont.dataset.puestoUsuario || '';

              }

                /*
                 * No se usa $watch sobre la fecha: Alpine difiere los watchers,
                 * asi que se ejecutarian DESPUES de precargar los datos de
                 * la edicion y borrarian la hora de inicio y termino.
                 *
                 * La recarga se dispara en los dos puntos necesarios:
                 *   - al cambiar la fecha en el input (@change);
                 *   - al abrir el formulario (abrirModalNuevaJunta /
                 *     abrirModalEditar ya piden los horarios).
                 */

              /*
               * Modal día
               */
              const modalDiaElement =
                  document.getElementById(
                      'modalDia'
                  );

            if (modalDiaElement) {

                this.modalDia =
                    bootstrap.Modal.getOrCreateInstance(
                        modalDiaElement
                    );

            }


            /*
             * Modal detalle
             */
            const modalJuntaElement =
                document.getElementById(
                    'modalJunta'
                );

            if (modalJuntaElement) {

                this.modalJunta =
                    bootstrap.Modal.getOrCreateInstance(
                        modalJuntaElement
                    );

            }


            /*
             * Modal nueva junta
             */
            const modalNuevaJuntaElement =
                document.getElementById(
                    'modalNuevaJunta'
                );

                        if (modalNuevaJuntaElement) {

                this.modalNuevaJunta =
                    bootstrap.Modal.getOrCreateInstance(
                        modalNuevaJuntaElement
                    );

            }


            /*
              * Panel de comentarios (offcanvas, igual que solicitud-cheque)
              */
              const modalComentariosElement =
                  document.getElementById(
                      'modalComentariosJunta'
                  );

              if (modalComentariosElement) {

                  this.modalComentarios =
                      bootstrap.Offcanvas.getOrCreateInstance(
                          modalComentariosElement
                      );

              }

              /*
               * Modal de solicitud de uso
               */
              const modalSolicitudElement =
                  document.getElementById(
                      'modalSolicitud'
                  );

              if (modalSolicitudElement) {

                  this.modalSolicitud =
                      bootstrap.Modal.getOrCreateInstance(
                          modalSolicitudElement
                      );

              }

              /*
               * Modal con el que el creador acepta o deniega la solicitud
               */
              const modalRespuestaSolicitudElement =
                  document.getElementById(
                      'modalRespuestaSolicitud'
                  );

              if (modalRespuestaSolicitudElement) {

                  this.modalRespuestaSolicitud =
                      bootstrap.Modal.getOrCreateInstance(
                          modalRespuestaSolicitudElement
                      );

              }

              /*
               * Modal de autorizacion (el creador elige fecha y horario)
               */
              const modalAutorizacionElement =
                  document.getElementById(
                      'modalAutorizacion'
                  );

              if (modalAutorizacionElement) {

                  this.modalAutorizacion =
                      bootstrap.Modal.getOrCreateInstance(
                          modalAutorizacionElement
                      );

              }



            /*
             * FullCalendar
             */
             const calendarElement =
                document.getElementById(
                    'calendar'
                );

            if (!calendarElement) {

                console.error(
                    'No se encontró el elemento #calendar'
                );

                return;
            }


            const estado =
                this.obtenerEstadoCalendario();


            this.calendar =
                new FullCalendar.Calendar(
                    calendarElement,
                    {

                        locale: 'es',

                        initialView:
                            estado?.view
                            ?? (
                                this.checkWindowWidth()
                                    ? 'listWeek'
                                    : 'dayGridMonth'
                            ),

                        initialDate:
                            estado?.date
                            ?? this.fechaLocal(),

                        firstDay: 0,

                        fixedWeekCount: false,

                        height:
                            this.checkWindowWidth()
                                ? 900
                                : 1052,

                        dayMaxEvents: true,

                        moreLinkText(num) {
                            return `+${num} más`;
                        },

                        displayEventTime: false,

                        /*
                         * Mismo patron que /sasisopa/calendario y
                         * /sgm/calendario: event-fc-color da el estilo base
                         * (fondo, borde izquierdo y padding) y fc-bg-* aplica
                         * la variante de color. Sin esto los eventos salen
                         * con el estilo por defecto de FullCalendar.
                         */
                        eventClassNames: (arg) => {

                            const estatus =
                                arg.event.extendedProps?.estatus;

                            const color =
                                estatus === 'Horario Solicitado'
                                    ? 'warning'
                                    : estatus === 'Cancelada'
                                        ? 'danger'
                                        : 'success';

                            return [
                                'event-fc-color',
                                'fc-bg-' + color
                            ];

                        },

                        headerToolbar: {
                            left: 'prev,next today',
                            center: 'title',
                            right: ''
                        },

                        buttonText: {
                            today: 'Hoy'
                        },

                        dayHeaderFormat: {
                            weekday: 'long'
                        },


                        /*
                        |--------------------------------------------------------------------------
                        | CARGAR EVENTOS
                        |--------------------------------------------------------------------------
                        */

                        events: (
                            info,
                            successCallback,
                            failureCallback
                        ) => {

                            axios.get(
                                '/sala-juntas/calendario/data',
                                {
                                    params: {
                                        start:
                                            info.startStr,

                                        end:
                                            info.endStr
                                    }
                                }
                            )
                            .then(response => {

                                const eventos =
                                    response.data.eventos
                                    ?? [];

                                // Totales del rango visible.
                                this.totales = response.data.totales ?? this.totales;

                                successCallback(
                                    eventos
                                );

                            })
                            .catch(error => {

                                console.error(
                                    'Error al cargar las juntas:',
                                    error.response?.data
                                    ?? error
                                );

                                failureCallback(
                                    error
                                );

                            });

                        },


                        /*
                        |--------------------------------------------------------------------------
                        | CLICK DÍA
                        |--------------------------------------------------------------------------
                        */

                        dateClick: (info) => {

                            this.abrirDia(
                                info.dateStr
                            );

                        },


                        /*
                        |--------------------------------------------------------------------------
                        | CLICK EVENTO
                        |--------------------------------------------------------------------------
                        */

                        eventClick: (info) => {

                            /*
                             * El icono de comentarios abre directo el panel,
                             * sin pasar por el modal de detalle.
                             */
                              if (info.jsEvent && info.jsEvent.target
                                  && info.jsEvent.target.closest('.js-comentarios')) {

                                  // El badge es un <a href="#">: no debe navegar.
                                  info.jsEvent.preventDefault();

                                  this.abrirComentarios({
                                      id: info.event.id,
                                      descripcion: info.event.title,
                                      puedeComentar: true
                                  });

                                  return;

                              }

                            this.abrirJunta(
                                info.event.id
                            );

                        },


                        /*
                        |--------------------------------------------------------------------------
                        | EVENTO RENDERIZADO
                        |--------------------------------------------------------------------------
                        */

                        eventDidMount: (info) => {

                            info.el.title =
                                info.event.title;

                            info.el.style.cursor =
                                'pointer';

                              /*
                               * Boton de comentarios con el badge-historico,
                               * igual que las columnas de comentarios de los
                               * demas modulos:
                               *   .btn-badge-historico  (position:relative)
                               *     <i class="ti ti-message">
                               *     .badge-historico    (circulo rojo, contador)
                               */
                                const n = Number(
                                    info.event.extendedProps?.comentarios || 0
                                );

                                /*
                                 * El icono se dibuja siempre, tenga o no
                                 * comentarios; el contador rojo solo aparece
                                 * cuando hay al menos uno.
                                 */
                                const boton = document.createElement('a');

                                boton.href = '#';
                                boton.className =
                                    'js-comentarios btn-comentarios btn-badge-historico '
                                    + 'position-relative d-inline-flex align-items-center '
                                    + 'justify-content-center text-decoration-none';
                                boton.dataset.id = info.event.id;
                                boton.title =
                                    n === 0
                                        ? 'Comentarios'
                                        : n === 1
                                            ? '1 comentario'
                                            : n + ' comentarios';
                                boton.style.color = 'inherit';
                                boton.setAttribute('aria-label', boton.title);

                                boton.innerHTML =
                                    '<i class="ti ti-message fs-7"></i>'
                                    + (n > 0
                                        ? '<span class="badge-historico position-absolute '
                                          + 'top-0 start-100 translate-middle">'
                                          + n
                                          + '</span>'
                                        : '');

                                /*
                                 * FullCalendar 6 genera esta estructura:
                                 *   .fc-event-main-frame
                                 *     .fc-event-time            (opcional)
                                 *     .fc-event-title-container  <-- aqui va el boton
                                 *       .fc-event-title.fc-sticky
                                 *
                                 * El boton NO debe meterse dentro de
                                 * .fc-event-title porque ese elemento lleva
                                 * los estilos del texto del titulo.
                                 */
                                const anchor =
                                    info.el.querySelector('.fc-event-title-container')
                                    || info.el.querySelector('.fc-event-main-frame')
                                    || info.el;

                                anchor.appendChild(boton);

                            },


                        /*
                        |--------------------------------------------------------------------------
                        | CAMBIO DE MES / SEMANA
                        |--------------------------------------------------------------------------
                        */

                        datesSet: () => {

                            this.cambiarTamanoDias();

                            this.guardarEstadoCalendario();

                        },


                        /*
                        |--------------------------------------------------------------------------
                        | RESPONSIVE
                        |--------------------------------------------------------------------------
                        */

                        windowResize: () => {

                            const mobile =
                                this.checkWindowWidth();


                            if (mobile) {

                                if (
                                    this.calendar.view.type
                                    !== 'listWeek'
                                ) {

                                    this.calendar.changeView(
                                        'listWeek'
                                    );

                                }

                                this.calendar.setOption(
                                    'height',
                                    900
                                );

                                return;
                            }


                            if (
                                this.calendar.view.type
                                !== 'dayGridMonth'
                            ) {

                                this.calendar.changeView(
                                    'dayGridMonth'
                                );

                            }

                            this.calendar.setOption(
                                'height',
                                1052
                            );

                        }

                    }
                );


            this.calendar.render();

        },


        /*
        |--------------------------------------------------------------------------
        | RESPONSIVE
        |--------------------------------------------------------------------------
        */

        checkWindowWidth() {

            return window.innerWidth <= 1199;

        },


        /*
        |--------------------------------------------------------------------------
        | ABRIR DÍA
        |--------------------------------------------------------------------------
        */

        async abrirDia(fecha) {

            const [
                year,
                month,
                day
            ] = fecha.split('-');


            const meses = [
                'Enero',
                'Febrero',
                'Marzo',
                'Abril',
                'Mayo',
                'Junio',
                'Julio',
                'Agosto',
                'Septiembre',
                'Octubre',
                'Noviembre',
                'Diciembre'
            ];


            this.fechaSeleccionada =
                `${parseInt(day)} de ${
                    meses[
                        parseInt(month) - 1
                    ]
                } del ${year}`;


            try {

                const response =
                    await axios.get(
                        '/sala-juntas/calendario/dia',
                        {
                            params: {
                                fecha
                            }
                        }
                    );


                if (
                    response.data.success
                    === false
                ) {

                    this.actividadesDia = [];

                    return;
                }


                this.actividadesDia =
                    response.data.data
                    ?? [];


                if (this.modalDia) {

                    this.modalDia.show();

                }

            } catch (error) {

                console.error(
                    'Error al cargar las juntas del día:',
                    error.response?.data
                    ?? error
                );

                this.notify?.(
                    'error',
                    'No fue posible consultar las juntas del día.'
                );

            }

        },


        /*
        |--------------------------------------------------------------------------
        | ABRIR DETALLE
        |--------------------------------------------------------------------------
        */

        async abrirJunta(id) {

            if (!id) {
                return;
            }


            try {

                const response =
                    await axios.get(
                        `/sala-juntas/calendario/detalle/${id}`
                    );


                if (
                    !response.data.success
                ) {

                    this.notify?.(
                        'warning',
                        response.data.message
                        ?? 'No fue posible consultar la junta.'
                    );

                    return;
                }


                this.juntaSeleccionada =
                    response.data.data;

                /*
                 * Si hay una solicitud de uso pendiente:
                 *   - quien creo la junta va directo a resolverla;
                 *   - los demas solo ven el detalle, sin acciones.
                 */
                if (this.juntaSeleccionada?.tieneSolicitudPendiente
                    && this.juntaSeleccionada?.esCreador) {

                    this.abrirRespuestaSolicitud(
                        this.juntaSeleccionada.id
                    );

                    return;

                }

                /*
                 * Si modalDia está abierto,
                 * esperar a que termine de cerrar.
                 */
                const modalDiaElement =
                    document.getElementById(
                        'modalDia'
                    );


                if (
                    modalDiaElement
                    && modalDiaElement
                        .classList
                        .contains('show')
                ) {

                    modalDiaElement
                        .addEventListener(
                            'hidden.bs.modal',
                            () => {

                                if (
                                    this.modalJunta
                                ) {

                                    this.modalJunta.show();

                                }

                            },
                            {
                                once: true
                            }
                        );


                    this.modalDia.hide();

                    return;
                }


                /*
                 * Click directo desde FullCalendar
                 */
                if (this.modalJunta) {

                    this.modalJunta.show();

                }

            } catch (error) {

                console.error(
                    'Error al cargar el detalle de la junta:',
                    error.response?.data
                    ?? error
                );

                this.notify?.(
                    'error',
                    'No fue posible consultar el detalle de la junta.'
                );

            }

        },


        /*
        |--------------------------------------------------------------------------
        | NUEVA JUNTA
        |--------------------------------------------------------------------------
        */

        async abrirModalNuevaJunta(
            fecha = null
        ) {

            this.errorNuevaJunta = '';

            this.errorPersonal = false;

            this.horasDisponibles = [];

            this.horasTermino = [];


            this.nuevaJunta = {

                descripcion: '',

                /*
                 * "Convoca" no se elige: siempre es quien esta navegando.
                 * El backend lo ignora y usa el usuario de la sesion.
                 */
                idUsuario: String(this.idUsuarioActual || 0),

                id_puesto: this.idPuestoUsuario,

                fecha:
                    fecha
                    ?? this.fechaLocal(),

                hora_inicio: '',

                hora_termino: '',

                departamentos: [],

                personal: []

            };


            try {

                const response =
                    await axios.get(
                        '/sala-juntas/calendario/nuevo'
                    );


                this.puestosDisponibles =
                    response.data.puestos
                    ?? [];


                this.personalDisponible =
                    response.data.personal
                    ?? [];


                if (
                    response.data.usuario_actual
                ) {

                    this.idUsuarioActual =
                        Number(
                            response.data
                                .usuario_actual
                        ) || this.idUsuarioActual;

                    this.nuevaJunta.idUsuario =
                        String(this.idUsuarioActual);

                }


                /*
                 * "Convoca" es informativo: se fija al usuario en sesion y no
                 * se puede cambiar desde el formulario.
                 */
                if (response.data.convococa) {
                    this.nombreUsuarioActual =
                        response.data.convococa.nombre
                        || this.nombreUsuarioActual;
                }

                /*
                 * El backend devuelve el puesto de quien agenda; se usa al
                 * guardar porque la tabla juntas.idPuesto lo referencia.
                 */
                this.idPuestoUsuario = Number(
                    response.data.id_puesto
                ) || 0;

                this.nuevaJunta.id_puesto =
                    this.idPuestoUsuario;

                /*
                 * Los horarios se piden desde el $watch de la fecha, que
                 * tambien cubre los cambios posteriores del usuario.
                 */
                await this
                    .cargarHorasDisponibles();


                // Select2 se inicializa con el modal ya visible.
                setTimeout(() => this.refrescarSelectsJunta(), 150);

                // Si veniamos del detalle, se cierra antes de abrir el formulario.
                this.abrirModalEncima(this.modalNuevaJunta);

            } catch (error) {

                console.error(
                    'Error al preparar nueva junta:',
                    error.response?.data
                    ?? error
                );


                this.errorNuevaJunta =
                    'No fue posible cargar la información de la reunión.';

            }

        },


        /*
        |--------------------------------------------------------------------------
        | MODALES APILADOS
        |--------------------------------------------------------------------------
        */

        /**
         * Bootstrap no soporta dos modales visibles a la vez.
         *
         * Si el modal de detalle esta abierto, se espera a que termine de
         * cerrarse antes de mostrar el destino.
         */
        abrirModalEncima(modal) {

            if (!modal) return;

            const mostrar = () => modal.show();

            if (this.modalJunta) {

                const detalle = document.getElementById('modalJunta');

                if (detalle && detalle.classList.contains('show')) {

                    detalle.addEventListener('hidden.bs.modal', mostrar, { once: true });
                    this.modalJunta.hide();
                    return;

                }

            }

            mostrar();

        },

        /**
         * Cierra todos los modales abiertos (tras guardar o cancelar).
         */
        cerrarModalesJunta() {

              for (const modal of [
                  this.modalComentarios,
                  this.modalSolicitud,
                  this.modalJunta,
                  this.modalNuevaJunta
              ]) {
                  if (modal) {
                      modal.hide();
                  }
              }

        },


        /*
        |--------------------------------------------------------------------------
        | SELECT2 (departamentos / personal)
        |--------------------------------------------------------------------------
        */

        /**
         * Reconstruye los <option> del select de departamentos / estaciones.
         */
        _fillDepartamentos() {

            const el = document.getElementById('dirigidoa');

            if (!el) return;

            el.innerHTML = '';

            for (const puesto of (this.puestosDisponibles || [])) {
                const option = document.createElement('option');
                option.value = puesto.id;
                option.text = puesto.nombre;
                el.appendChild(option);
            }

        },

        /**
         * Reconstruye los <optgroup> del select de personal.
         *
         * El legacy agrupa al puesto 6 (Encargado) por estacion y al resto
         * por tipo de puesto; el Service ya devuelve esa estructura.
         */
        _fillPersonal() {

            const el = document.getElementById('personal');

            if (!el) return;

            el.innerHTML = '';

            const grupos = this.personalDisponible || [];

            for (const grupo of grupos) {

                const optgroup = document.createElement('optgroup');
                optgroup.label = grupo.label;

                for (const usuario of (grupo.usuarios || [])) {
                    const option = document.createElement('option');
                    option.value = usuario.id;
                    option.text = usuario.nombre;
                    optgroup.appendChild(option);
                }

                el.appendChild(optgroup);

            }

        },

        /**
         * (Re)inicializa los dos select2 y sincroniza con el estado Alpine.
         *
         * Se llama cada vez que se abre el formulario para reflejar altas,
         * ediciones y catálogos nuevos.
         */
        /**
         * Sincroniza un <select multiple> con el estado Alpine.
         *
         * Select2 gestiona su propio dropdown, asi que el cambio se escucha
         * en el elemento original. El fallback cubre el caso en que la
         * libreria no cargo: el select nativo sigue siendo usable.
         */
        /**
         * Contenedor donde se ancla el desplegable de Select2.
         *
         * Se usa document.body a proposito:
         *   - dentro de ".modal-content" el dropdown se recorta, porque
         *     .modal-dialog-scrollable .modal-content tiene overflow:hidden;
         *   - dentro de ".modal-dialog" no responde al clic, porque
         *     Bootstrap lo deja con pointer-events:none.
         *
         * En el body no hay recorte ni bloqueo de eventos; basta con subir
         * el z-index por encima del modal (1055) y su backdrop (1050), que
         * hace la regla de select2-modal.css.
         */
        _select2Parent(el) {

            return typeof document !== 'undefined' ? document.body : undefined;

        },

        _syncSelect(el, valores) {

            this._fillDepartamentos();
            this._fillPersonal();

            const esDept = el.id === 'dirigidoa';

            const leer = () => Array.from(el.selectedOptions).map((o) => o.value);

            const aplicar = () => {
                const ids = leer();
                if (esDept) {
                    this.nuevaJunta.departamentos = ids;
                } else {
                    this.nuevaJunta.personal = ids;
                }
                this.errorPersonal = false;
            };

              // Select2 disponible.
              if (typeof $ !== 'undefined' && typeof $.fn.select2 === 'function') {

                  const jq = $(el);

                  // Destruir la instancia previa para no duplicar el dropdown.
                  if (jq.data('select2')) {
                      jq.select2('destroy');
                  }

                  /*
                   * Por defecto Select2 mueve el desplegable a <body>, fuera
                   * del modal, y queda por detras de el (z-index). Con
                   * dropdownParent se ancla dentro del modal y asi aplica
                   * la regla ".modal .select2-dropdown { z-index: 2000 }"
                   * de select2-modal.css.
                   */
                  const padre = this._select2Parent(el);

                  jq.select2({
                      width: '100%',
                      placeholder: 'Seleccione...',
                      allowClear: true,
                      closeOnSelect: false,
                      dropdownParent: padre,
                      language: {
                          noResults: () => 'Sin resultados',
                          searching: () => 'Buscando...'
                      }
                  });

                jq.val(valores.map(String)).trigger('change.select2');

                // Listener con namespace para no apilarse en cada apertura.
                jq.off('change.sjSelects').on('change.sjSelects', aplicar);

            } else {

                // Fallback nativo: el formulario sigue funcionando sin select2.
                console.warn('Select2 no disponible; se usan los selects nativos.');

                el.value = '';

                for (const opt of el.options) {
                    opt.selected = valores.map(String).includes(opt.value);
                }

                el.onchange = aplicar;

            }

        },

        initSelectsJunta({ departamentos = [], personal = [] } = {}) {

            const dirEl = document.getElementById('dirigidoa');
            const perEl = document.getElementById('personal');

            if (!dirEl || !perEl) return;

            this._syncSelect(dirEl, departamentos);
            this._syncSelect(perEl, personal);

            if (typeof $ !== 'undefined' && typeof $.fn.select2 === 'function') {
                setTimeout(() => {
                    for (const el of [dirEl, perEl]) {
                        const inst = $(el).data('select2');
                        if (inst && inst.$container) {
                            inst.$container.css('width', '100%');
                        }
                    }
                }, 120);
            }

        },

        /*Alias corto usado al abrir el modal.*/
        refrescarSelectsJunta() {

            this.initSelectsJunta({
                departamentos: this.nuevaJunta?.departamentos || [],
                personal: this.nuevaJunta?.personal || []
            });

        },


        /*
        |--------------------------------------------------------------------------
        | HORARIOS DISPONIBLES
        |--------------------------------------------------------------------------
        */

        /*
        |--------------------------------------------------------------------------
        | CONSULTA DE DISPONIBILIDAD (compartida por todos los modales)
        |--------------------------------------------------------------------------
        */

        /**
         * Pide las horas libres de un dia al backend.
         *
         * @param {string} fecha     Fecha en formato Y-m-d.
         * @param {number} excluirId Junta que no debe bloquearse (0 = ninguna).
         *                          Se usa al editar, para que la junta no se
         *                          bloquee a si misma.
         *
         * @returns {Promise<{fecha: string, horas: string[]}>}
         */
        async consultarHorasLibres(fecha, excluirId = 0) {

            if (!fecha) {
                return { fecha: '', horas: [] };
            }

            const params = { fecha };

            if (excluirId > 0) {
                params.id = excluirId;
            }

            const response = await axios.get(
                '/sala-juntas/calendario/horas',
                { params }
            );

            return {
                fecha,
                horas: Array.isArray(response.data.horas)
                    ? response.data.horas
                    : []
            };

        },

        /**
         * Horas de termino posibles para un inicio dado: todos los finales
         * consecutivos a partir de 30 minutos despues del inicio mientras
         * el bloque siguiente siga libre.
         *
         * @param {string} inicio  Hora de inicio elegida.
         * @param {string[]} libres  Horas de inicio libres de ese dia.
         *
         * @returns {string[]}
         */
        calcularTerminosPosibles(inicio, libres) {

            if (!inicio) {
                return [];
            }

            const opciones = [];

            let cursor = this.horaMasMinutos(inicio, 30);

            while (cursor) {

                opciones.push(cursor);

                // Si en ese momento no se puede iniciar otra junta, la sala
                // se ocupa: aqui termina el bloque disponible.
                if (!libres.includes(cursor)) {
                    break;
                }

                cursor = this.horaMasMinutos(cursor, 30);

            }

            return [...new Set(opciones)];

        },

        /**
         * Descarta las horas elegidas que ya no sean validas para el dia
         * y el inicio actuales. Evita dejar seleccionadas horas que en
         * realidad estan ocupadas.
         *
         * @param {{hora_inicio: string, hora_termino: string}} destino
         * @param {string[]} libres        Horas de inicio libres del dia.
         * @param {string[]} terminos      Terminos posibles desde el inicio.
         */
        depurarHoras(destino, libres, terminos) {

            if (destino.hora_inicio && !libres.includes(destino.hora_inicio)) {
                destino.hora_inicio = '';
            }

            if (destino.hora_inicio && destino.hora_termino
                && !terminos.includes(destino.hora_termino)) {
                destino.hora_termino = '';
            }

            if (!destino.hora_inicio) {
                destino.hora_termino = '';
            }

        },

        /*
        |--------------------------------------------------------------------------
        | ALTA / EDICIÓN: CARGAR HORAS DEL DÍA ELEGIDO
        |--------------------------------------------------------------------------
        */

        async cargarHorasDisponibles() {

            const fecha = this.nuevaJunta.fecha;
            const token = ++this.peticionHoras;

            this.errorNuevaJunta = '';

            /*
             * Sin fecha no hay horas: se limpia todo para que nunca queden
             * disponibles las de un dia anterior.
             */
            if (!fecha) {

                this.horasDisponibles = [];
                this.horasTermino = [];
                this.nuevaJunta.hora_inicio = '';
                this.nuevaJunta.hora_termino = '';

                return;

            }

            this.cargandoHoras = true;

            // Se limpian de inmediato: mientras carga, el usuario no puede
            // confirmar un horario que pertenece a otro dia.
            this.horasDisponibles = [];
            this.horasTermino = [];
            this.nuevaJunta.hora_inicio = '';
            this.nuevaJunta.hora_termino = '';

            try {

                /*
                 * Al editar se envia el id de la junta para que el backend
                 * excluya su propio horario de la disponibilidad; de lo
                 * contrario el select de inicio quedaria vacio.
                 */
                const { horas } = await this.consultarHorasLibres(
                    fecha,
                    this.idJuntaEditando
                );

                // Llegó una respuesta que ya no es la última, o el usuario
                // cambió la fecha mientras corría: se descarta.
                if (token !== this.peticionHoras || this.nuevaJunta.fecha !== fecha) {
                    return;
                }

                this.horasDisponibles = horas;

                if (horas.length === 0) {
                    this.errorNuevaJunta =
                        'No hay horarios disponibles para esa fecha.';
                }

                // Reaprovecha la seleccion si sigue siendo valida.
                this.depurarHoras(
                    this.nuevaJunta,
                    this.horasDisponibles,
                    this.calcularTerminosPosibles(
                        this.nuevaJunta.hora_inicio,
                        this.horasDisponibles
                    )
                );

                this.actualizarHorasTermino();

            } catch (error) {

                if (token !== this.peticionHoras) {
                    return;
                }

                console.error(
                    'Error al cargar horarios:',
                    error.response?.data
                    ?? error
                );

                this.horasDisponibles = [];
                this.horasTermino = [];

                this.errorNuevaJunta =
                    'No fue posible consultar los horarios disponibles.';

            } finally {

                if (token === this.peticionHoras) {
                    this.cargandoHoras = false;
                }

            }

        },


        /*
        |--------------------------------------------------------------------------
        | CALCULAR HORAS DE TÉRMINO
        |--------------------------------------------------------------------------
        */

        actualizarHorasTermino() {

            const inicio =
                this.nuevaJunta
                    .hora_inicio;

            // Se recuerda la actual: al editar hay que conservarla si sigue libre.
            const actual =
                this.nuevaJunta
                    .hora_termino;

            /*
             * Si el inicio ya no esta entre las horas libres del dia (por
             * ejemplo porque seCreo otra junta mientras se editaba), se
             * descarta para no poder confirmar un horario ocupado.
             */
            if (inicio && !this.horasDisponibles.includes(inicio)) {

                this.horasDisponibles = [];

                this.horasTermino = [];

                this.nuevaJunta.hora_inicio = '';
                this.nuevaJunta.hora_termino = '';

                this.errorNuevaJunta =
                    'El horario seleccionado ya no esta disponible. Elige otro.';

                return;

            }

            this.horasTermino =
                this.calcularTerminosPosibles(
                    inicio,
                    this.horasDisponibles
                );

            /*
             * Al editar se conserva la hora de termino ya registrada si
             * sigue entre las posibles; si el usuario cambio la hora de
             * inicio, se limpia porque ya no corresponde.
             */
            if (actual) {

                this.nuevaJunta.hora_termino =
                    this.horasTermino.includes(actual)
                        ? actual
                        : '';

            }

        },


        /*
        |--------------------------------------------------------------------------
        | FORMATO DE HORA (12 horas con AM / PM)
        |--------------------------------------------------------------------------
        */

        /**
         * Convierte "14:30", "14:30:00" o "2:30 pm" a "02:30 PM".
         *
         * Se usa SOLO para mostrar. Los valores que se guardan y se envian
         * al servidor siguen en formato de 24 horas ("HH:MM"), que es lo que
         * entiende el backend.
         */
        formatearHora12(hora) {

            if (!hora) {
                return '';
            }

            const m = String(hora).match(/^(\d{1,2}):(\d{2})/);

            if (!m) {
                return String(hora);
            }

            let h = parseInt(m[1], 10);

            const sufijo = h >= 12 ? 'PM' : 'AM';

            h = h % 12;

            if (h === 0) {
                h = 12;
            }

            return String(h).padStart(2, '0') + ':' + m[2] + ' ' + sufijo;

        },


        /*
        |--------------------------------------------------------------------------
        | SUMAR MINUTOS
        |--------------------------------------------------------------------------
        */

        horaMasMinutos(
            hora,
            minutos
        ) {

            if (!hora) {
                return '';
            }


            const [
                horas,
                mins
            ] = hora
            .split(':')
            .map(Number);


            const fecha =
            new Date(
                2000,
                0,
                1,
                horas,
                mins
            );


            fecha.setMinutes(
                fecha.getMinutes()
                + minutos
            );

            /*
             * La ventana de la sala es 08:00 a 23:00: mas alla de esa
             * hora no existe un horario valido, asi que se devuelve vacio
             * para que el select no ofrezca un final fuera de rango.
             */
            if (
                fecha.getHours() > 23
                || (
                    fecha.getHours() === 23
                    && fecha.getMinutes() > 0
                )
            ) {
                return '';
            }


            return (
                String(
                    fecha.getHours()
                ).padStart(
                    2,
                    '0'
                )
                +
                ':'
                +
                String(
                    fecha.getMinutes()
                ).padStart(
                    2,
                    '0'
                )
            );

        },



        /*
        |--------------------------------------------------------------------------
        | RESET FORMULARIO
        |--------------------------------------------------------------------------
        */

          resetNuevaJunta() {

              // Limpiar las marcas de error de los campos obligatorios.
              Object.keys(this.erroresJunta)
                  .forEach((k) => this.erroresJunta[k] = false);

              this.nuevaJunta = {

                descripcion: '',

                idUsuario: '',

                fecha: '',

                hora_inicio: '',

                hora_termino: '',

                departamentos: [],

                personal: []

            };


            this.horasDisponibles = [];

            this.horasTermino = [];

            this.errorPersonal = false;

            this.errorNuevaJunta = '';

        },


        /*
        |--------------------------------------------------------------------------
        | FECHA LOCAL YYYY-MM-DD
        |--------------------------------------------------------------------------
        */

        fechaLocal() {

            const fecha =
                new Date();


            const year =
                fecha.getFullYear();


            const month =
                String(
                    fecha.getMonth() + 1
                ).padStart(
                    2,
                    '0'
                );


            const day =
                String(
                    fecha.getDate()
                ).padStart(
                    2,
                    '0'
                );


            return `${year}-${month}-${day}`;

        },


        /*
        |--------------------------------------------------------------------------
        | CAMBIAR TAMAÑO DÍAS
        |--------------------------------------------------------------------------
        */

        cambiarTamanoDias() {

            this.$nextTick(() => {

                document
                    .querySelectorAll(
                        '.fc-daygrid-day-number'
                    )
                    .forEach(item => {

                        item.style.fontSize =
                            '1rem';

                        item.style.fontWeight =
                            '700';

                    });

            });

        },


        /*
        |--------------------------------------------------------------------------
        | RECARGAR CALENDARIO
        |--------------------------------------------------------------------------
        */

        recargar() {

            if (
                this.calendar
            ) {

                this.calendar
                    .refetchEvents();

            }

        },


        /*
        |--------------------------------------------------------------------------
        | GUARDAR ESTADO CALENDARIO
        |--------------------------------------------------------------------------
        */

        guardarEstadoCalendario() {

            if (
                !this.calendar
            ) {

                return;

            }


            const fecha =
                this.calendar
                    .getDate();


            const estado = {

                view:
                    this.calendar
                        .view
                        .type,

                date:
                    this.formatearFechaLocal(
                        fecha
                    )

            };


            localStorage.setItem(
                'calendario_estado',
                JSON.stringify(
                    estado
                )
            );

        },


        /*
        |--------------------------------------------------------------------------
        | RECUPERAR ESTADO
        |--------------------------------------------------------------------------
        */

        obtenerEstadoCalendario() {

            const estado =
                localStorage.getItem(
                    'calendario_estado'
                );


            if (!estado) {

                return null;

            }


            try {

                return JSON.parse(
                    estado
                );

            } catch (error) {

                localStorage.removeItem(
                    'calendario_estado'
                );

                return null;

            }

        },


        /*
        |--------------------------------------------------------------------------
        | FORMATEAR DATE LOCAL
        |--------------------------------------------------------------------------
        */

        formatearFechaLocal(
            fecha
        ) {

            const year =
                fecha.getFullYear();


            const month =
                String(
                    fecha.getMonth()
                    + 1
                ).padStart(
                    2,
                    '0'
                );


            const day =
                String(
                    fecha.getDate()
                ).padStart(
                    2,
                    '0'
                );


            return `${year}-${month}-${day}`;

        },


        /*
        |--------------------------------------------------------------------------
        | UTILIDADES
        |--------------------------------------------------------------------------
        */

        leerJson(valor, porDefecto) {

            try {

                const v = JSON.parse(
                    valor || 'null'
                );

                return v === null
                    ? porDefecto
                    : v;

            } catch (e) {

                return porDefecto;

            }

        },

        /**
         * Fecha minima seleccionable en el formulario.
         *
         * ES UN METODO Y NO UN GETTER A PROPOSITO: la vista usa
         * x-data="{ ...calendario() }". El operador de dispersion copia el
         * VALOR de los getters, no la funcion, asi que un getter quedaria
         * congelado con el valor del arranque.
         */
        fechaMinima() {

            return this.fechaLocal();

        },

        /**
         * Descarga el manual de Sala de Juntas usando el mecanismo global
         * de Portal3 (/download), no una ruta del proyecto anterior.
         */
        descargarManual() {

            this.download(
                'sala-juntas',
                'manual-sala-juntas-V1.pdf'
            );

        },


        /*
        |--------------------------------------------------------------------------
        | EDITAR
        |--------------------------------------------------------------------------
        */

        /**
         * Carga el formulario de edicion con los datos de la junta.
         */
        async abrirModalEditar(id) {

            if (!this.permisos.editar) {

                this.notify(
                    'error',
                    'No tienes permiso para editar juntas.'
                );

                return;

            }


            this.errorNuevaJunta   = '';
            this.idJuntaEditando   = id;
            this.puestosDisponibles  = [];
            this.personalDisponible   = [];
            this.horasDisponibles     = [];


            try {

                const response =
                    await axios.get(
                        '/sala-juntas/calendario/editable/' + id
                    );


                if (
                    response.data.success
                    === false
                ) {

                    this.notify(
                        'error',
                        response.data.message
                        || 'No fue posible cargar la junta.'
                    );

                    return;

                }


                const datos   = response.data.datos;
                const catalogo = response.data.catalogo;

                this.nuevaJunta = {
                    descripcion: datos.descripcion || '',
                    idUsuario: datos.idUsuario || '',
                    id_puesto: Number(catalogo.id_puesto) || 0,
                    fecha: datos.fecha || '',
                    hora_inicio: datos.hora_inicio || '',
                    hora_termino: datos.hora_termino || '',
                    departamentos: datos.departamentos || [],
                    personal: datos.personal || []
                };

                this.idPuestoUsuario =
                    Number(catalogo.id_puesto) || 0;

                this.puestosDisponibles =
                    catalogo.puestos || [];

                this.personalDisponible =
                    catalogo.personal || [];

                /*
                 * El backend ya devuelve las horas libres de esa fecha
                 * excluida la propia junta (por eso aparece su horario).
                 */
                this.horasDisponibles =
                    response.data.horas || [];

                // Poblar las opciones de hora de término a partir del inicio.
                this.actualizarHorasTermino();

                // Select2 con los valores precargados de la junta.
                setTimeout(() => this.refrescarSelectsJunta(), 150);

                // Si veniamos del detalle, se cierra antes de abrir el formulario.
                this.abrirModalEncima(this.modalNuevaJunta);



            } catch (error) {

                console.error(
                    'Error al preparar edicion:',
                    error.response?.data ?? error
                );

                this.notify(
                    'error',
                    'No fue posible cargar la informacion de la reunion.'
                );

            }

        },


        /**
         * Valida el formulario de juntas y marca los campos obligatorios
         * vacios con is-invalid. Mismo patron que los demas modulos.
         */
        validarJunta() {

            Object.keys(this.erroresJunta)
                .forEach((k) => this.erroresJunta[k] = false);

            if (!this.nuevaJunta.descripcion.trim()) {
                this.erroresJunta.descripcion = true;
            }

            if (!this.nuevaJunta.fecha) {
                this.erroresJunta.fecha = true;
            }

            if (this.nuevaJunta.fecha
                && this.nuevaJunta.fecha < this.fechaMinima()) {
                this.erroresJunta.fecha = true;
            }

            if (!this.nuevaJunta.hora_inicio) {
                this.erroresJunta.hora_inicio = true;
            }

            if (!this.nuevaJunta.hora_termino
                || (this.nuevaJunta.hora_inicio
                    && this.nuevaJunta.hora_termino <= this.nuevaJunta.hora_inicio)) {
                this.erroresJunta.hora_termino = true;
            }

            if (this.nuevaJunta.departamentos.length === 0
                && this.nuevaJunta.personal.length === 0) {
                this.erroresJunta.destinatarios = true;
                this.errorPersonal = true;
            } else {
                this.errorPersonal = false;
            }

            return !Object.values(this.erroresJunta).some(Boolean);

        },

        /** Mensaje del toast segun que campo quedo pendiente. */
        mensajeValidacionJunta() {

            if (this.erroresJunta.descripcion) {
                return 'Captura el tema de la reunión.';
            }

            if (this.erroresJunta.fecha) {
                return this.nuevaJunta.fecha
                    ? 'La fecha no puede ser anterior a hoy.'
                    : 'Selecciona la fecha programada.';
            }

            if (this.erroresJunta.hora_inicio) {
                return 'Selecciona la hora de inicio.';
            }

            if (this.erroresJunta.hora_termino) {
                return 'Selecciona una hora de término posterior a la de inicio.';
            }

            return 'Selecciona al menos un departamento o una persona.';

        },

        /** Quita la marca de error en cuanto el campo ya tiene contenido. */
        limpiarErrorJunta(campo) {

            this.erroresJunta[campo] = false;

            if (campo === 'descripcion' && this.nuevaJunta.descripcion.trim()) {
                this.erroresJunta.descripcion = false;
            }

            if (campo === 'fecha' && this.nuevaJunta.fecha) {
                this.erroresJunta.fecha = false;
            }

        },

        /**
         * Guarda la junta: alta o edicion segun idJuntaEditando.
         */
        async guardarJunta() {

            const esEdicion = this.idJuntaEditando > 0;

            if (!esEdicion && !this.permisos.crear) {
                this.notify('error', 'No tienes permiso para agregar juntas.');
                return;
            }

            if (esEdicion && !this.permisos.editar) {
                this.notify('error', 'No tienes permiso para editar juntas.');
                return;
            }

            if (this.guardandoJunta) return;

            // Validaciones de negocio (las mismas que el backend).
            this.errorNuevaJunta = '';

            if (!this.validarJunta()) {
                this.notify('error', this.mensajeValidacionJunta());
                return;
            }



            this.guardandoJunta = true;

            try {

                const url = esEdicion
                    ? '/sala-juntas/calendario/update/' + this.idJuntaEditando
                    : '/sala-juntas/calendario/create';

                const res = await this.createAction({
                    url: url,
                    data: this.nuevaJunta
                });

                /*
                 * El backend responde con 'success' => true/false, asi que
                 * basta con revisar el fallo para no confusing un exito.
                 */
                if (res.success === false) {

                    this.errorNuevaJunta =
                        res.message
                        || 'No fue posible guardar la junta.';

                    return;

                }

                // createAction() ya notifica el exito o el error.
                this.juntaSeleccionada = null;

                if (this.modalNuevaJunta) {
                    this.modalNuevaJunta.hide();
                }

                this.resetNuevaJunta();
                this.recargar();

            } catch (error) {

                console.error(
                    'Error al guardar junta:',
                    error.response?.data ?? error
                );

                this.errorNuevaJunta =
                    (error.response
                        && error.response.data
                        && error.response.data.message)
                    || 'Ocurrio un error al guardar la junta.';

            } finally {

                this.guardandoJunta = false;

            }

        },


        /*
        |--------------------------------------------------------------------------
        | ELIMINAR
        |--------------------------------------------------------------------------
        */

        /**
         * Elimina (cancela) una junta usando el mecanismo global de
         * eliminacion de Portal3: confirmacion con SweetAlert2 y POST.
         */
        async eliminarJunta(id, tema) {

            if (!this.permisos.eliminar) {

                this.notify(
                    'error',
                    'No tienes permiso para eliminar juntas.'
                );

                return;

            }

            const res = await this.deleteAction({
                url: '/sala-juntas/calendario/delete/' + id,
                id: id,
                name: tema || 'esta junta',
                table: null
            });

            if (res && res.success !== false) {

                /*
                 * deleteAction() ya notifica la respuesta del backend,
                 * asi que aqui solo se refresca la vista.
                 */
                this.cerrarModalesJunta();

                this.juntaSeleccionada = null;

                this.recargar();

            }

        },


        /*
        |--------------------------------------------------------------------------
        | SOLICITUD DE USO DE LA SALA DE JUNTAS
        |--------------------------------------------------------------------------
        */

        _fillSolicitudDepartamentos() {

            const el = document.getElementById('solicitudDirigidoa');

            if (!el) return;

            el.innerHTML = '';

            for (const puesto of (this.puestosSolicitud || [])) {
                const option = document.createElement('option');
                option.value = puesto.id;
                option.text = puesto.nombre;
                el.appendChild(option);
            }

        },

        _fillSolicitudPersonal() {

            const el = document.getElementById('solicitudPersonal');

            if (!el) return;

            el.innerHTML = '';

            for (const grupo of (this.personalSolicitud || [])) {

                const optgroup = document.createElement('optgroup');
                optgroup.label = grupo.label;

                for (const usuario of (grupo.usuarios || [])) {
                    const option = document.createElement('option');
                    option.value = usuario.id;
                    option.text = usuario.nombre;
                    optgroup.appendChild(option);
                }

                el.appendChild(optgroup);

            }

        },

        _syncSelectSolicitud(el, valores) {

            if (!el) return;

            const esDept = el.id === 'solicitudDirigidoa';

            this._fillSolicitudDepartamentos();
            this._fillSolicitudPersonal();

            const aplicar = () => {
                const ids = Array.from(el.selectedOptions).map((o) => o.value);
                if (esDept) {
                    this.solicitud.departamentos = ids;
                } else {
                    this.solicitud.personal = ids;
                }
            };

            if (typeof $ !== 'undefined' && typeof $.fn.select2 === 'function') {

                const jq = $(el);

                if (jq.data('select2')) {
                    jq.select2('destroy');
                }

                  jq.select2({
                      width: '100%',
                      placeholder: 'Seleccione...',
                      allowClear: true,
                      closeOnSelect: false,
                      dropdownParent: this._select2Parent(el)
                  });

                jq.val([]).trigger('change.select2');

                jq.off('change.sjSolicitud').on('change.sjSolicitud', aplicar);

            } else {

                el.onchange = aplicar;
                el.value = '';

            }

        },

        initSelectsSolicitud() {

            this._syncSelectSolicitud(document.getElementById('solicitudDirigidoa'), []);
            this._syncSelectSolicitud(document.getElementById('solicitudPersonal'), []);

            if (typeof $ !== 'undefined' && typeof $.fn.select2 === 'function') {
                setTimeout(() => {
                    for (const id of ['solicitudDirigidoa', 'solicitudPersonal']) {
                        const el = document.getElementById(id);
                        const inst = el ? $(el).data('select2') : null;
                        if (inst && inst.$container) {
                            inst.$container.css('width', '100%');
                        }
                    }
                }, 120);
            }

        },

        /**
         * Rellena los controles deshabilitados con los datos de la solicitud.
         *
         * Se hace por DOM y no con bindings porque los <select disabled> del
         * legacy se renderizaban en PHP; aqui se rellenan al vuelo.
         */
        _pintarSolicitudFija(data) {

            const junction = data?.junta || {};
            const cat = data?.catalogo || {};

            const opcion = (select, valor, texto) => {

                if (!select) return;

                select.innerHTML = '';

                const opt = document.createElement('option');
                opt.value = valor ?? '';
                opt.text = texto ?? '';
                opt.selected = true;

                select.appendChild(opt);

            };

            // Quien convoca: es el solicitante, no el creador de la junta.
            opcion(
                document.getElementById('solicitudConvoca'),
                cat.convoca?.id ?? '',
                cat.convoca?.nombre ?? ''
            );

              // Hora inicio y hora termino de la junta (formato de 12 horas).
              opcion(
                  document.getElementById('solicitudHoraInicio'),
                  junction.hora_inicio ?? '',
                  this.formatearHora12(junction.hora_inicio)
              );

              opcion(
                  document.getElementById('solicitudHoraTermino'),
                  junction.hora_termino ?? '',
                  this.formatearHora12(junction.hora_termino)
              );

            // Fecha programada: input type=date requiere formato Y-m-d.
            const fecha = document.getElementById('solicitudFecha');

            if (fecha) {
                fecha.value = junction.fecha || '';
            }

            // Titulo descriptivo con la fecha formateada.
            const etiquetaFecha = document.getElementById('solicitudFechaLabel');

            if (etiquetaFecha) {
                etiquetaFecha.textContent =
                    junction.fecha_label || junction.fecha || '';
            }

        },

        /** Abre el modal de solicitud para una junta que no es del usuario. */
          async abrirSolicitudHorario(id) {

              if (!id) return;


              // Limpiar las marcas de error de los campos obligatorios.
              Object.keys(this.erroresSolicitud)
                  .forEach((k) => this.erroresSolicitud[k] = false);

              this.solicitud = {
                  id: id,
                  motivo: '',
                  descripcion: '',
                  departamentos: [],
                  personal: []
              };


            this.puestosSolicitud = [];
            this.personalSolicitud = [];

            try {

                const resp = await axios.get(
                    '/sala-juntas/calendario/solicitud/' + id
                );

                if (resp.data && resp.data.success === false) {
                    this.notify('error', resp.data.message || 'No fue posible abrir la solicitud.');
                    return;
                }

                    this.solicitud.junta = resp.data.junta;
                    this.solicitud.catalogo = resp.data.catalogo;

                    /*
                     * El tema NO se precarga: quien solicita solo quiere el
                     * horario, el nombre de la reunion lo escribe el para su
                     * nueva junta. Copiar el de la junta original hacia que
                     * ambas reuniones terminaran con el mismo nombre.
                     */
                    this.solicitud.descripcion = '';

                    this.puestosSolicitud = resp.data.catalogo?.puestos || [];
                    this.personalSolicitud = resp.data.catalogo?.personal || [];

                  /*
                   * Los datos fijos se escriben directamente en el DOM.
                   * El legacy los renderiza en PHP dentro de controles
                   * deshabilitados; aqui se rellenan por DOM para no
                   * depender de bindings de Alpine sobre atributos.
                   */
                  this._pintarSolicitudFija(resp.data);

                  this.abrirModalEncima(this.modalSolicitud);

                  setTimeout(() => this.initSelectsSolicitud(), 150);


            } catch (error) {

                console.error('Error al abrir la solicitud:', error.response?.data ?? error);

                this.notify(
                    'error',
                    (error.response?.data?.message) || 'No fue posible abrir la solicitud.'
                );

            }

        },

        /**
         * Valida la solicitud y marca los campos obligatorios vacios.
         * Mismo patron que los demas modulos (errors + is-invalid).
         */
        validarSolicitud() {

            Object.keys(this.erroresSolicitud)
                .forEach((k) => this.erroresSolicitud[k] = false);

            const motivo = (this.solicitud.motivo || '').trim();
            const tema = (this.solicitud.descripcion || '').trim();
            const tieneDestinatarios =
                this.solicitud.departamentos.length > 0
                || this.solicitud.personal.length > 0;

            if (!motivo) {
                this.erroresSolicitud.motivo = true;
            }

            if (!tema) {
                this.erroresSolicitud.descripcion = true;
            }

            if (!tieneDestinatarios) {
                this.erroresSolicitud.destinatarios = true;
            }

            return !this.erroresSolicitud.motivo
                && !this.erroresSolicitud.descripcion
                && !this.erroresSolicitud.destinatarios;

        },

        /** Quita la marca de error en cuanto el campo ya tiene contenido. */
        limpiarErrorSolicitud(campo) {

            this.erroresSolicitud[campo] = false;

            if (campo === 'motivo' && (this.solicitud.motivo || '').trim()) {
                this.erroresSolicitud.motivo = false;
            }

            if (campo === 'descripcion' && (this.solicitud.descripcion || '').trim()) {
                this.erroresSolicitud.descripcion = false;
            }

        },

        async guardarSolicitudHorario() {

            if (this.guardandoSolicitud) return;

            if (!this.validarSolicitud()) {
                this.notify('error', 'Completa los campos obligatorios.');
                return;
            }

            this.guardandoSolicitud = true;

            try {

                const resp = await axios.post(
                    '/sala-juntas/calendario/solicitud/' + this.solicitud.id,
                    {
                        motivo: (this.solicitud.motivo || '').trim(),
                        descripcion: (this.solicitud.descripcion || '').trim(),
                        departamentos: this.solicitud.departamentos,
                        personal: this.solicitud.personal
                    }
                );

                if (resp.data && resp.data.success === false) {

                    this.notify(
                        'error',
                        resp.data.message || 'No fue posible enviar la solicitud.'
                    );

                    return;

                }

                if (this.modalSolicitud) {
                    this.modalSolicitud.hide();
                }

                this.notify(
                    'success',
                    resp.data.message || 'La solicitud se envió correctamente.'
                );

                this.solicitud = {
                    id: 0, motivo: '', descripcion: '', departamentos: [], personal: []
                };

                this.recargar();

            } catch (error) {

                console.error('Error al enviar la solicitud:', error.response?.data ?? error);

                this.notify(
                    'error',
                    (error.response?.data?.message)
                    || 'No fue posible enviar la solicitud.'
                );

            } finally {

                this.guardandoSolicitud = false;

            }

        },

        /*
        |--------------------------------------------------------------------------
        | COMENTARIOS
        |--------------------------------------------------------------------------
        */

        /**
         * Abre el panel de comentarios (offcanvas) con el mismo diseno de
         * /departamento-operativo/solicitud-cheque.
         *
         * El offcanvas no necesita el dance de modales de Bootstrap, asi que
         * puede convivir con el modal de detalle abierto.
         */
        abrirComentarios(junta) {

            if (!junta || !junta.id) return;

            /*
             * rememberedDetalle permite que "Regresar al detalle" vuelva a
             * abrir la junta aunque el usuario entre directo desde el
             * calendario (sin haber pasado por el modal de detalle).
             */
            this.rememberedDetalle = this.juntaSeleccionada?.id === junta.id
                ? this.juntaSeleccionada
                : { id: junta.id, descripcion: junta.descripcion || '' };

            this.juntaComentarios = {
                id: junta.id,
                tema: junta.descripcion || '',
                puedeComentar: junta.puedeComentar !== false
            };

            this.volverAlDetalle = true;

            this.nuevoComentario = '';
            this.comentarios = [];
            this.comentariosCargados = false;

            const mostrarPanel = () => {
                if (this.modalComentarios) {
                    this.modalComentarios.show();
                }
                this.cargarComentarios();
            };

            /*
             * Bootstrap no tolera un modal y un offcanvas visibles a la vez:
             * el backdrop del modal tapa el panel. Se cierra el detalle y
             * solo despues se abre el panel de comentarios.
             */
            if (this.modalJunta) {

                const detalle = document.getElementById('modalJunta');

                if (detalle && detalle.classList.contains('show')) {

                    detalle.addEventListener('hidden.bs.modal', mostrarPanel, { once: true });
                    this.modalJunta.hide();
                    return;

                }

            }

            mostrarPanel();

        },

        /** Cierra el panel y vuelve al modal de detalle de la junta. */
        async regresarAlDetalle() {

            const junta = this.rememberedDetalle;

            if (this.modalComentarios) {
                this.modalComentarios.hide();
            }

            if (!junta || !junta.id) return;

            // Se espera a que el offcanvas termine de cerrar.
            await new Promise((r) => setTimeout(r, 180));

            this.abrirJunta(junta.id);

        },

        scrollChatToBottom() {

            this.$nextTick(() => {
                const el = this.$refs.chatContainer;
                if (el) {
                    el.scrollTop = el.scrollHeight;
                }
            });

        },

        /** Formatea la fecha del comentario como en solicitud-cheque. */
        formatearFechaComentario(fecha) {

            if (!fecha) return '';

            const d = fecha instanceof Date ? fecha : new Date(fecha);

            if (isNaN(d.getTime())) return '';

            return d.toLocaleString('es-MX', {
                day: '2-digit',
                month: 'short',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });

        },

        async cargarComentarios() {

            if (!this.juntaComentarios) return;

            this.comentariosCargados = false;

            try {

                const resp = await axios.get(
                    '/sala-juntas/calendario/'
                    + this.juntaComentarios.id
                    + '/comentarios'
                );

                if (resp.data && resp.data.success !== false) {

                    this.comentarios = (resp.data.data || []).map((c) => ({
                        ...c,
                        esMio: Number(c.id_usuario) === Number(this.idUsuarioActual),
                        usuario_nombre: c.autor || 'Usuario',
                        fecha_formateada: this.formatearFechaComentario(c.fecha_hora)
                    }));

                    this.scrollChatToBottom();

                } else {

                    this.comentarios = [];

                }

            } catch (error) {

                console.error(
                    'Error al cargar comentarios:',
                    error.response?.data ?? error
                );

                this.comentarios = [];

            } finally {

                /*
                 * El flag se limpia siempre en finally: si la peticion se
                 * queda colgada, el panel no muestra un spinner eterno.
                 */
                this.comentariosCargados = true;

            }

        },

        async guardarComentario() {

            const texto = this.nuevoComentario.trim();

            if (!texto || this.guardandoComentario) return;

            if (!this.juntaComentarios || !this.juntaComentarios.id) return;

            this.guardandoComentario = true;

            try {

                const resp = await axios.post(
                    '/sala-juntas/calendario/'
                    + this.juntaComentarios.id
                    + '/comentarios/create',
                    { comentario: texto }
                );

                if (resp.data && resp.data.success === false) {

                    this.notify(
                        'error',
                        resp.data.message || 'No fue posible enviar el comentario.'
                    );

                    return;

                }

                this.nuevoComentario = '';
                this.notify('success', resp.data.message || 'Comentario agregado.');

                await this.cargarComentarios();

            } catch (error) {

                console.error(
                    'Error al enviar comentario:',
                    error.response?.data ?? error
                );

                this.notify(
                    'error',
                    (error.response
                        && error.response.data
                        && error.response.data.message)
                    || 'No fue posible enviar el comentario.'
                );

            } finally {

                this.guardandoComentario = false;

            }

        },

        /*
        |--------------------------------------------------------------------------
        | RESPUESTA A LA SOLICITUD DE USO (creador)
        |--------------------------------------------------------------------------
        */

        /** Carga la solicitud pendiente y abre el modal de respuesta. */
        async abrirRespuestaSolicitud(id) {

            if (!id) return;

            this.respondiendoSolicitud = false;

            try {

                const resp = await axios.get(
                    '/sala-juntas/calendario/solicitud-pendiente/' + id
                );

                if (resp.data && resp.data.success === false) {
                    this.notify('error', resp.data.message || 'No hay solicitud pendiente.');
                    return;
                }

                this.respuestaSolicitud = resp.data.solicitud;
                this.idJuntaSolicitud = id;

                this.abrirModalEncima(this.modalRespuestaSolicitud);

            } catch (error) {

                console.error('Error al cargar la solicitud:', error.response?.data ?? error);

                this.notify(
                    'error',
                    (error.response?.data?.message) || 'No fue posible cargar la solicitud.'
                );

            }

        },

        /** El creador acepta o deniega la solicitud pendiente. */
        async responderSolicitud(aceptar) {

            if (this.respondiendoSolicitud) return;

            const id = this.idJuntaSolicitud || this.juntaSeleccionada?.id;

            if (!id) return;

            this.respondiendoSolicitud = true;

            try {

                const resp = await axios.post(
                    '/sala-juntas/calendario/responder-solicitud/' + id,
                    { aceptar: !!aceptar }
                );

                if (resp.data && resp.data.success === false) {

                    this.notify(
                        'error',
                        resp.data.message || 'No fue posible resolver la solicitud.'
                    );

                    return;

                }

                this.cerrarModalesSolicitud();

                this.notify(
                    'success',
                    resp.data.message || (aceptar ? 'Solicitud autorizada.' : 'Solicitud denegada.')
                );

                this.recargar();

            } catch (error) {

                console.error('Error al responder la solicitud:', error.response?.data ?? error);

                this.notify(
                    'error',
                    (error.response?.data?.message) || 'No fue posible resolver la solicitud.'
                );

            } finally {

                this.respondiendoSolicitud = false;

            }

        },


        /*
        |--------------------------------------------------------------------------
        | AUTORIZACIÓN DE USO (creador elige fecha y horario)
        |--------------------------------------------------------------------------
        */

        /** Abre el modal de autorización con la fecha y el horario actuales. */
        abrirAutorizacion() {

            if (!this.respuestaSolicitud) return;

            const s = this.respuestaSolicitud;

            Object.keys(this.erroresAutorizacion)
                .forEach((k) => this.erroresAutorizacion[k] = false);

            this.autorizacion = {
                fecha: s.fecha || '',
                hora_inicio: s.hora_inicio || '',
                hora_termino: s.hora_termino || ''
            };

            this.horasAutorizacion = [];
            this.horasTerminoAutorizacion = [];

            if (this.modalRespuestaSolicitud) {
                this.modalRespuestaSolicitud.hide();
            }

            // Abrir el modal de autorizacion despues de cerrar el anterior.
            setTimeout(() => {

                if (this.modalAutorizacion) {
                    this.modalAutorizacion.show();
                }

                this.cargarHorasAutorizacion();

            }, 180);

        },

        /** Horarios libres de la fecha elegida para la reunion del solicitante. */
        async cargarHorasAutorizacion() {

            const fecha = this.autorizacion.fecha;
            const token = ++this.peticionHoras;

            if (!fecha) {

                this.horasAutorizacion = [];
                this.horasTerminoAutorizacion = [];
                this.autorizacion.hora_inicio = '';
                this.autorizacion.hora_termino = '';

                return;

            }

            this.cargandoHorasAutorizacion = true;

            // Se limpian de inmediato para no dejar horas de otro dia.
            this.horasAutorizacion = [];
            this.horasTerminoAutorizacion = [];
            this.autorizacion.hora_inicio = '';
            this.autorizacion.hora_termino = '';

            try {

                /*
                 * No se excluye la junta original: la reunion del solicitante
                 * es aparte, asi que ese horario sigue estando ocupado.
                 */
                const { horas } = await this.consultarHorasLibres(fecha);

                if (token !== this.peticionHoras
                    || this.autorizacion.fecha !== fecha) {
                    return;
                }

                this.horasAutorizacion = horas;

                this.calcularHorasTerminoAutorizacion();

            } catch (error) {

                if (token !== this.peticionHoras) {
                    return;
                }

                console.error('Error al cargar horarios de autorizacion:', error.response?.data ?? error);

                this.horasAutorizacion = [];
                this.horasTerminoAutorizacion = [];

            } finally {

                if (token === this.peticionHoras) {
                    this.cargandoHorasAutorizacion = false;
                }

            }

        },

        /** Horas de termino consecutivas y libres, segun el inicio elegido. */
        calcularHorasTerminoAutorizacion() {

            const inicio = this.autorizacion.hora_inicio;

            if (inicio && !this.horasAutorizacion.includes(inicio)) {

                this.autorizacion.hora_inicio = '';
                this.autorizacion.hora_termino = '';
                this.horasTerminoAutorizacion = [];

                return;

            }

            this.horasTerminoAutorizacion =
                this.calcularTerminosPosibles(
                    inicio,
                    this.horasAutorizacion
                );

            this.depurarHoras(
                this.autorizacion,
                this.horasAutorizacion,
                this.horasTerminoAutorizacion
            );

        },

        limpiarErrorAutorizacion(campo) {

            this.erroresAutorizacion[campo] = false;

        },

        /** Confirma la autorizacion con la fecha y horario elegidos. */
        async autorizarSolicitud() {

            if (this.respondiendoSolicitud) return;

            Object.keys(this.erroresAutorizacion)
                .forEach((k) => this.erroresAutorizacion[k] = false);

            if (!this.autorizacion.fecha) {
                this.erroresAutorizacion.fecha = true;
            }

            if (!this.autorizacion.hora_inicio) {
                this.erroresAutorizacion.hora_inicio = true;
            }

            if (!this.autorizacion.hora_termino
                || (this.autorizacion.hora_inicio
                    && this.autorizacion.hora_termino <= this.autorizacion.hora_inicio)) {
                this.erroresAutorizacion.hora_termino = true;
            }

            if (Object.values(this.erroresAutorizacion).some(Boolean)) {
                this.notify('error', 'Elige la fecha, la hora de inicio y la de término.');
                return;
            }

            this.respondiendoSolicitud = true;

            try {

                const resp = await axios.post(
                    '/sala-juntas/calendario/responder-solicitud/'
                        + this.idJuntaSolicitud,
                    {
                        aceptar: true,
                        fecha: this.autorizacion.fecha,
                        hora_inicio: this.autorizacion.hora_inicio,
                        hora_termino: this.autorizacion.hora_termino
                    }
                );

                if (resp.data && resp.data.success === false) {

                    this.notify(
                        'error',
                        resp.data.message || 'No fue posible autorizar.'
                    );

                    return;

                }

                this.cerrarModalesSolicitud();

                this.notify(
                    'success',
                    resp.data.message || 'La solicitud fue autorizada.'
                );

                this.recargar();

            } catch (error) {

                console.error('Error al autorizar:', error.response?.data ?? error);

                this.notify(
                    'error',
                    (error.response?.data?.message) || 'No fue posible autorizar.'
                );

            } finally {

                this.respondiendoSolicitud = false;

            }

        },

        cerrarModalesSolicitud() {

            for (const modal of [
                this.modalAutorizacion,
                this.modalRespuestaSolicitud
            ]) {
                if (modal) {
                    modal.hide();
                }
            }

            this.respuestaSolicitud = null;
            this.idJuntaSolicitud = 0;
            this.juntaSeleccionada = null;
            this.autorizacion = {
                fecha: '', hora_inicio: '', hora_termino: ''
            };

        },

    }));
});
