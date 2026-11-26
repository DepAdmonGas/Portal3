document.addEventListener('alpine:init', () => {
    Alpine.data('tutorialBienvenida', () => ({
        indice: 0,
        pasos: [],
        guardando: false,
        error: '',
        obligatorio: false,
        instanciaModal: null,
        capturas: [],
        capturasAnimadas: [],
        capturasConError: [],

        init() {
            const elemento = this.$el.closest('#portal3TutorialBienvenida');
            try {
                const capturas = JSON.parse(elemento?.dataset.capturas || '[]');
                this.capturas = Array.isArray(capturas) ? capturas : [];
            } catch (_) {
                this.capturas = [];
            }
            try {
                const animadas = JSON.parse(elemento?.dataset.capturasAnimadas || '[]');
                this.capturasAnimadas = Array.isArray(animadas) ? animadas : [];
            } catch (_) {
                this.capturasAnimadas = [];
            }
            this.pasos = this.crearPasos(
                elemento?.dataset.multiestacion === '1'
            );
            this.obligatorio = elemento?.dataset.obligatorio === '1';
            if (!this.pasos.length || !window.bootstrap?.Modal) return;

            this.instanciaModal = bootstrap.Modal.getOrCreateInstance(
                elemento,
                {
                    backdrop: true,
                    keyboard: true,
                    focus: true,
                }
            );
            elemento.addEventListener('hide.bs.modal', evento => {
                if (this.obligatorio) evento.preventDefault();
            });
            if (elemento?.dataset.autostart === '1') {
                this.$nextTick(() => this.instanciaModal.show());
            }
        },

        crearPasos(tieneMultiestacion) {
            const pasos = [
                {
                    id: 'bienvenida', seccion: 'Bienvenida', imagen: 'bienvenida', icono: 'ti-sparkles',
                    titulo: 'Bienvenido a Portal3', encabezado: 'Un recorrido breve para conocer tu portal',
                    descripcion: 'Portal3 concentra tus accesos y herramientas de trabajo en un mismo espacio. Este recorrido te mostrará cómo ubicarte y reconocer los controles más comunes.',
                    puntos: ['Avanza a tu ritmo; puedes cerrar esta ventana y retomarla al volver a Home.'], nota: ''
                },
                {
                    id: 'navegacion', seccion: 'Navegación', imagen: 'navegacion-sidebar', icono: 'ti-menu-2',
                    titulo: 'Navegación y sidebar', encabezado: 'Encuentra los módulos disponibles para tu usuario',
                    descripcion: 'La barra lateral agrupa las secciones del portal. El menú se construye con los accesos habilitados para tu cuenta y permite entrar a sus páginas y subsecciones.',
                    puntos: ['Selecciona Home para regresar al inicio.', 'Abre una categoría para consultar las opciones disponibles.'], nota: 'Los accesos visibles pueden variar de acuerdo con los permisos de tu usuario.'
                },
                {
                    id: 'cards', seccion: 'Interfaz', imagen: 'cards-componentes', icono: 'ti-layout-dashboard',
                    titulo: 'Cards y componentes', encabezado: 'La información importante se presenta por bloques',
                    descripcion: 'Las tarjetas agrupan datos relacionados y acciones del mismo contexto. En Home, cada tarjeta representa un módulo al que tu usuario tiene acceso.',
                    puntos: ['Revisa el nombre y el indicador antes de entrar.', 'Los componentes mantienen patrones visuales consistentes entre secciones.'], nota: ''
                },
                {
                    id: 'periodo', seccion: 'Orientación', imagen: 'breadcrumbs-periodo', icono: 'ti-calendar-month',
                    titulo: 'Breadcrumbs de año y mes', encabezado: 'Ubica el periodo y el lugar donde estás',
                    descripcion: 'En las pantallas que trabajan por ejercicio o periodo, el encabezado ayuda a reconocer el año y el mes seleccionados. Los breadcrumbs muestran la ruta desde Home hasta la sección actual.',
                    puntos: ['Confirma el periodo antes de consultar o capturar información.', 'Usa los breadcrumbs para volver a un nivel anterior.'], nota: ''
                },
                {
                    id: 'controles', seccion: 'Interfaz', imagen: 'dropdowns-botones-iconos', icono: 'ti-pointer',
                    titulo: 'Dropdowns, botones e iconografía', encabezado: 'Reconoce las acciones y opciones del contexto',
                    descripcion: 'Los botones ejecutan acciones visibles y los dropdowns reúnen opciones relacionadas. Los iconos acompañan estas acciones; cuando tengas duda, consulta su etiqueta o ayuda contextual.',
                    puntos: ['Antes de guardar o eliminar, revisa la acción y el registro seleccionado.', 'Los controles pueden aparecer sólo si cuentas con permiso para usarlos.'], nota: ''
                },
                {
                    id: 'alertas', seccion: 'Interfaz', imagen: 'alertas-notificaciones', icono: 'ti-bell-ringing',
                    titulo: 'Alertas y notificaciones', encabezado: 'Portal3 confirma el resultado de tus acciones',
                    descripcion: 'Los avisos muestran confirmaciones, advertencias o errores después de una operación. Lee el mensaje completo para saber si el cambio se guardó o requiere atención.',
                    puntos: ['Los mensajes de error indican que la operación no concluyó.', 'Las confirmaciones aparecen después de una respuesta exitosa.'], nota: ''
                },
                {
                    id: 'tablas', seccion: 'Consulta', imagen: 'datatables', icono: 'ti-table-options',
                    titulo: 'Tablas y DataTables', encabezado: 'Localiza registros sin recorrer toda la tabla',
                    descripcion: 'En las tablas habilitadas, puedes buscar texto, ordenar columnas y cambiar la página para revisar los registros. Algunos listados también ofrecen filtros específicos.',
                    puntos: ['Usa Buscar para reducir los resultados.', 'Ordena desde los encabezados disponibles.', 'La paginación y el selector de cantidad ayudan a recorrer listas largas.'], nota: 'Los filtros dependen de cada listado.'
                },
                {
                    id: 'estatus', seccion: 'Consulta', imagen: 'estatus-indicadores', icono: 'ti-traffic-lights',
                    titulo: 'Estatus e indicadores', encabezado: 'Identifica rápidamente la situación de un registro',
                    descripcion: 'Las etiquetas, colores e iconos de estatus resumen el estado que reporta cada proceso. Consulta el texto asociado al indicador para confirmar su significado antes de actuar.',
                    puntos: ['El indicador representa el estado del registro.', 'Las acciones disponibles dependen del estado y de tus permisos.'], nota: ''
                },
                {
                    id: 'oscuro', seccion: 'Preferencias', imagen: 'modo-oscuro', icono: 'ti-moon',
                    titulo: 'Modo oscuro', encabezado: 'Elige el tema de visualización del portal',
                    descripcion: 'El control de tema en la barra superior permite cambiar entre la apariencia clara y oscura. El modo oscuro también facilita consultar el portal en ambientes con poca luz.',
                    puntos: ['Cambia el tema desde el control de la parte superior.', 'Los colores de texto, fondos y componentes se adaptan al tema seleccionado.'], nota: ''
                },
            ];

            pasos.push({
                id: 'checador', seccion: 'Asistencia', imagen: 'checador', icono: 'ti-clock-check',
                titulo: 'Checador', encabezado: 'Consulta la asistencia y las marcaciones',
                descripcion: 'El checador concentra la asistencia del personal y herramientas para consultar sus horarios, jornadas y marcaciones por estación y periodo.',
                puntos: [
                    'Consulta la asistencia del día, las checadas y el estado del personal.',
                    'Revisa la programación semanal, los turnos, las jornadas y las incidencias.',
                    'Explora reportes de marcaciones, solicitudes de PIN temporal y comparaciones con nómina cuando estén disponibles.'
                ],
                nota: 'Las funciones y estaciones disponibles dependen de tus permisos.'
            });

            if (tieneMultiestacion) {
                pasos.push({
                    id: 'multiestacion', seccion: 'Contexto', imagen: 'multiestacion', icono: 'ti-switch-2',
                    titulo: 'Multiestación', encabezado: 'Consulta la información del contexto seleccionado',
                    descripcion: 'En los módulos que ofrecen selector, puedes cambiar entre las estaciones o departamentos autorizados para tu usuario. El selector define el contexto de consulta de ese módulo.',
                    puntos: ['Confirma la estación o departamento antes de revisar datos.', 'Sólo aparecen opciones habilitadas para tu cuenta y el módulo.'], nota: 'La disponibilidad depende de la configuración y los permisos de tu usuario.'
                });
                pasos.push({
                    id: 'multiestacion-todas', seccion: 'Contexto', imagen: 'multiestacion-todas', icono: 'ti-building-store',
                    titulo: 'Consulta de varias estaciones', encabezado: 'Identifica el origen de cada registro',
                    descripcion: 'En algunos módulos, al seleccionar todas las estaciones y departamentos, la tabla muestra una columna adicional que indica a qué estación o departamento pertenece cada registro.',
                    puntos: ['Consulta la columna Estación / Departamento para identificar el origen.', 'Selecciona una estación o departamento específico para consultar su información.'],
                    nota: 'La opción y la columna dependen del módulo y de los accesos de tu usuario.'
                });
            }

            pasos.push({
                id: 'perfil', seccion: 'Cuenta', imagen: 'perfil-foto-telegram', icono: 'ti-user-circle',
                titulo: 'Perfil, fotografía y Telegram', encabezado: 'Consulta tus datos y opciones de cuenta',
                descripcion: 'El menú de perfil reúne el acceso a tu configuración personal y la salida de sesión. Desde las opciones de cuenta puedes administrar la fotografía y revisar la vinculación de Telegram cuando esté habilitada.',
                puntos: ['Mantén actualizados tus datos personales.', 'Vincula Telegram sólo desde la opción oficial de tu perfil.'], nota: 'Las opciones disponibles pueden variar según la configuración de tu cuenta.'
            });

            pasos.push(
                {
                    id: 'perfil-datos', seccion: 'Cuenta', imagen: 'perfil-datos', icono: 'ti-camera',
                    titulo: 'Fotografía y datos', encabezado: 'Mantén actualizado tu perfil',
                    descripcion: 'En tu perfil puedes consultar tus datos personales y de contacto, actualizar la información disponible y cambiar tu fotografía.',
                    puntos: ['Usa el control de fotografía para actualizar tu imagen.', 'Selecciona Editar perfil o Actualizar para modificar los datos disponibles.'],
                    nota: 'Las opciones de edición dependen de tu cuenta.'
                },
                {
                    id: 'perfil-telegram', seccion: 'Cuenta', imagen: 'perfil-telegram', icono: 'ti-brand-telegram',
                    titulo: 'Notificaciones en Telegram', encabezado: 'Configura tus notificaciones internas',
                    descripcion: 'La sección Telegram reúne las instrucciones para vincular la recepción de notificaciones internas del portal.',
                    puntos: ['Consulta el manual y escanea el código QR para abrir el chat.', 'Genera el código de verificación siguiendo las indicaciones del portal.'],
                    nota: 'La disponibilidad depende de la configuración de tu cuenta.'
                },
                {
                    id: 'perfil-firma', seccion: 'Cuenta', imagen: 'perfil-firma', icono: 'ti-signature',
                    titulo: 'Firma de usuario', encabezado: 'Consulta y registra tu firma',
                    descripcion: 'La sección Firma muestra la firma asociada a tu cuenta o informa cuando aún no hay una registrada. Puedes dibujarla directamente o subir una imagen de tu firma.',
                    puntos: ['Selecciona Nueva firma y elige Dibujar firma o Subir imagen.', 'Guarda tu firma y consulta su estado; también puedes eliminarla cuando la opción esté disponible.'],
                    nota: 'Las acciones disponibles dependen de tu cuenta.'
                }
            );

            return pasos;
        },

        get pasoActual() {
            return this.pasos[this.indice] || this.pasos[0] || {
                titulo: 'Bienvenido a Portal3', encabezado: '', descripcion: '', puntos: [], nota: '', seccion: '', imagen: '', icono: ''
            };
        },

        get esUltimo() {
            return this.indice === this.pasos.length - 1;
        },

        get progreso() {
            if (this.pasos.length <= 1) return 100;
            return Math.round((this.indice / (this.pasos.length - 1)) * 100);
        },

        get etiquetaPaso() {
            return this.indice === 0 ? 'Introducción' : `Tema ${this.indice} de ${this.pasos.length - 1}`;
        },

        capturaDisponible(clave) {
            return this.capturas.includes(clave) && !this.capturasConError.includes(clave);
        },

        capturaRuta(clave) {
            return `/assets/images/tutorial-bienvenida/${clave}.webp`;
        },

        capturaAnimadaDisponible(clave) {
            return this.capturasAnimadas.includes(clave);
        },

        capturaAnimadaRuta(clave) {
            const version = clave === 'multiestacion' ? '?v=2' : '';
            return `/assets/images/tutorial-bienvenida/${clave}.gif${version}`;
        },

        marcarCapturaNoDisponible(clave) {
            if (!this.capturasConError.includes(clave)) this.capturasConError.push(clave);
        },

        siguiente() {
            if (!this.esUltimo) {
                this.indice += 1;
                this.error = '';
            }
        },

        anterior() {
            if (this.indice > 0) {
                this.indice -= 1;
                this.error = '';
            }
        },

        siguienteDesdeTeclado(evento) {
            if (evento.target.closest('input, textarea, select, [contenteditable="true"]')) return;
            evento.preventDefault();
            this.siguiente();
        },

        anteriorDesdeTeclado(evento) {
            if (evento.target.closest('input, textarea, select, [contenteditable="true"]')) return;
            evento.preventDefault();
            this.anterior();
        },

        async finalizar() {
            if (this.guardando) return;
            this.guardando = true;
            this.error = '';

            try {
                const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
                const respuesta = await fetch(this.$el.closest('#portal3TutorialBienvenida').dataset.completeUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                    },
                    body: '{}',
                });
                const resultado = await respuesta.json();
                if (!respuesta.ok || !resultado.success || !resultado.completed) {
                    throw new Error(resultado.message || 'No se pudo guardar tu avance. Inténtalo de nuevo.');
                }

                this.obligatorio = false;
                const botonReplay = document.querySelector('#portal3TutorialReplay');
                if (botonReplay) {
                    botonReplay.hidden = false;
                    botonReplay.classList.remove('d-none');
                }
                this.instanciaModal?.hide();
            } catch (error) {
                this.error = error.message || 'No se pudo guardar tu avance. Inténtalo de nuevo.';
            } finally {
                this.guardando = false;
            }
        },
    }));
});
