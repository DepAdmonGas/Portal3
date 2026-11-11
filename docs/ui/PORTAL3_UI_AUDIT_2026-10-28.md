# Instalación del contrato visual — verificación local

**Fecha:** 28 de octubre de 2026
**Fase:** 1, documentación y auditoría de fuentes; no se aplicó corrección visual.

## Referencias verificadas en este checkout

- `public/assets/css/styles.css`: CSS compartido de Modernize/Bootstrap. Contiene `.btn-outline-primary`, `.btn-sm` y utilidades Bootstrap; son opciones disponibles antes de inventar botones CSS propios.
- `app/Views/tarjetas/index.php` (tabla responsive) y `public/assets/js/tarjetas/tarjetas.datatable.init.js` (columna de acciones `text-center align-middle`, disparador Tabler y menú Bootstrap) son una referencia local de tabla/acciones existente.
- `app/Views/aditivo/index.php` (estructura de modal, cabecera `.modal-header.modal-colored-header.bg-primary.text-white`, controles y footer) y `public/assets/js/bitacora/aditivo.datatable.init.js` (acciones DataTables en dropdown Bootstrap) son referencias locales de modal y acciones existentes. Se reutiliza sólo su estructura visual; su lógica específica no se traslada.
- `app/Views/layouts/{main,configuracion,departamento-operativo,sasisopa,sgm}.php` cargan `styles.css` y, en el estado local actual, `table-actions.css`. En el layout operativo `table-actions.css` aparece inmediatamente después de `styles.css`.
- `.agents/skills/portal3-native-module/SKILL.md`, `.agents/skills/checador-v2-functional-slice/SKILL.md`, `app/Views/departamento-operativo/2-recursos-humanos/checador/daily-attendance.php`, `workspace-incidences.php` y `public/assets/js/departamento-operativo/2-recursos-humanos/checador.datatable.init.js` existen en el checkout.
- **No existe** `public/assets/css/portal-tables.css`. Se retiró esa ruta del contrato como referencia utilizable; no se presupone su existencia ni su carga.
- `public/assets/css/select2-modal.css` y `public/assets/js/core/actions.alpine.js` existen. El helper `bindModalSelect2` está definido en `actions.alpine.js`; la hoja contiene reglas específicas para Select2 en modales.

## Auditoría de `table-actions.css`

Clasificación por regla solicitada. “Redundante” significa que Modernize/Bootstrap ya ofrece estructura equivalente; no implica que el CSS se haya eliminado.

| Regla | Clasificación | Evidencia y alcance |
|---|---|---|
| `.table-actions-cell` (`padding`, `text-align:center`, `vertical-align:middle`) | `RIESGO_DE_ALCANCE` | Es una regla sobre todo el `<td>`, no sólo los botones. `text-align:center` puede centrar cualquier texto/dato o contenido ajeno si la clase se pone en otra celda. El padding tiene `!important`. En los usos localizados está en celdas de acciones. |
| `.table-actions-group` (flex, wrap, center, gap) | `REDUNDANTE_CON_MODERNIZE` | Equivalentes disponibles como `d-flex flex-wrap align-items-center justify-content-center gap-2`. En los usos localizados sólo envuelve botones de acción. |
| `.btn-table-action` (display, alineación, gap, padding, borde, radio, tipografía, transición) | `REDUNDANTE_CON_MODERNIZE` | Reimplementa el aspecto de botón compacto. `btn btn-sm btn-outline-primary` y sus variantes ya existen en `styles.css`/Bootstrap. Altura mínima, radio y métricas tipográficas propias introducen diferencias visuales respecto al botón institucional; se debe justificar cualquier excepción por componente antes de conservarla. |
| Selectores de icono `.btn-table-action > .ti/.fa/...` | `REDUNDANTE_CON_MODERNIZE` | Ajustan el icono dentro del botón; utilidades flex existentes permiten la composición sin una regla adicional. Tabler es el patrón local (`ti ti-*`). |
| `:hover` | `REDUNDANTE_CON_MODERNIZE` | Duplica estados de botón Bootstrap. Los colores de hover se vuelven una implementación paralela. |
| `:focus-visible` | `REDUNDANTE_CON_MODERNIZE` | Bootstrap ya define foco de botones; hay dos `outline` sucesivos, por lo que el segundo reemplaza al primero cuando `color-mix()` es compatible. No se comprobó visualmente la compatibilidad en navegadores. |
| `:disabled`, `.disabled`, `[aria-disabled]` | `REDUNDANTE_CON_MODERNIZE` | Bootstrap contempla el estado deshabilitado. `pointer-events:none` y la opacidad duplican/personalizan el estado nativo. |
| Variantes `--primary/secondary/warning/danger` | `REDUNDANTE_CON_MODERNIZE` | Replican variantes outline de Bootstrap. La variante warning impone además borde amarillo propio. |
| `prefers-reduced-motion` | `SEGURA_Y_NECESARIA` | Limita la transición de los botones personalizados para usuarios que reducen movimiento; si se sustituye por clases Bootstrap, usar el soporte de movimiento reducido del tema. |

### ¿Está afectando información fuera de botones?

No encontré una regla CSS sin prefijo de componente que afecte todas las tablas o todos los modales. Las reglas están acotadas a clases opt-in. Sin embargo, `.table-actions-cell` sí afecta **todo el contenido de la celda marcada**, y el centrado no está limitado a un botón. Los usos hallados de la clase son celdas de acciones en `workspace-incidences.php`, `workspace-journeys.php`, `workspace-temporary-access.php`, `schedule-week.php` y el inicializador de DataTables de Asistencia. No se localizó el uso de esa clase en celdas de datos. La carga desde cinco layouts amplía disponibilidad global de esas clases, no su aplicación automática.

## Estilos existentes reutilizables

- Botón compacto con icono: `app/Views/departamento-operativo/2-recursos-humanos/checador/workspace-adjustments.php` usa `btn btn-sm btn-outline-primary`; `public/assets/js/tarjetas/tarjetas.datatable.init.js` documenta un dropdown Bootstrap existente con iconografía Tabler. Son patrones distintos: reutilizar según acción existente, sin convertir un botón normal en menú.
- Alineación de tabla: `app/Views/tarjetas/index.php` usa `table-responsive` y `align-middle`; su inicializador asigna `text-center align-middle` por columna sólo donde lo declara. `app/Views/aditivo/index.php` conserva `text-center` en el encabezado de la columna de acciones. Esto confirma el patrón de limitar alineación a la columna necesaria.
- Modal institucional: `app/Views/aditivo/index.php` líneas de cabecera/cuerpo/footer muestran el patrón Bootstrap con encabezado azul, campos `.form-label`/`.form-control` y botones institucionales. No hace falta CSS nuevo para esa composición.
- Select2 en modal: `public/assets/css/select2-modal.css` y `public/assets/js/core/actions.alpine.js` proveen reglas y helper compartidos; verificar inicialización y `dropdownParent` en el flujo real.

## Cambios visuales recientes que requieren restauración/revisión en fase 2

Basado en comparación del diff local con el estado versionado; no representa inspección en navegador:

1. **Asistencia perdió su jerarquía de página.** En `daily-attendance.php`, el `<h1 class="h3">Asistencia del día</h1>` fue eliminado y el contexto de estación/fecha pasó a texto pequeño como único encabezado de la vista. Restaurar el título institucional y mantener el contexto como información secundaria, salvo aprobación explícita de ese cambio.
2. **Acciones de fila de Asistencia se ocultaron en un dropdown nuevo.** La versión local reemplazó botones directos “Ver detalle”, “Jornada”, “Incidencias”/“Registrar incidencia” por un disparador de puntos verticales con “Ver detalle” dentro del menú. La columna también pasó de rotularse “Acciones” a mostrar sólo un icono. Esto contradice el contrato aprobado para botones normales de tabla: acción visible con icono y texto, y no introducir dropdown nuevo. Restaurar acciones directas y etiqueta legible en fase 2; conservar cualquier dropdown que ya existía en otros módulos.
3. **El menú limita la visibilidad de acciones disponibles.** En la vista actual sólo aparece “Ver detalle” en el menú generado; las acciones “Jornada” e “Incidencias” del estado anterior ya no están en esa celda. Confirmar el alcance funcional/ACL por separado antes de corregir; esta fase no cambia comportamiento.

No se incluye como error comprobado el color/cabecera Bootstrap de los nuevos modales: existe patrón local equivalente en Aditivo. La presentación de incidencias y asistencia sí requiere revisión de navegador antes de concluir cualquier otro defecto de espaciado o responsive.

## Cambios no realizados y validación pendiente

- No se modificaron controladores, vistas, CSS funcional de la aplicación, JavaScript funcional, datos, ACL, base de datos, API ni Lector. No hubo commit ni push.
- No se abrió navegador ni se tomaron capturas: `VISUAL_NOT_VERIFIED`. Por tanto, los puntos de restauración anteriores son regresiones verificadas en el diff/contrato, no una evaluación de píxeles renderizados. Fase 2 debe revisar consola y tamaños 375, 768, 1024 y 1440 px.

## Actualización: remediación del Quality Gate — 28 de octubre de 2026

- `workspace-incidences.php:217,247` ahora representa Ver detalle en ambas ramas (QA y normal) con `btn btn-sm btn-outline-primary` y Tabler `ti ti-eye`; conserva `data-bs-toggle="modal"`, el destino, el modal y la celda centrada. Buscar/Nueva incidencia y sus condiciones de permiso no cambiaron.
- La inspección completa encontró consumidores activos de `.table-actions-cell`, `.table-actions-group` y `.btn-table-action*` en `workspace-journeys.php`, `workspace-temporary-access.php`, `schedule-week.php` y `checador.datatable.init.js`. Por ello se conservaron las reglas base y las cinco referencias de layout. Sólo se retiraron los selectores de hijos Font Awesome `.fa/.fas/.far/.fab`, sin consumidores; se mantiene la regla Tabler `.ti`.
- El DOM de Asistencia renderizado por `daily-attendance.php` dentro del layout contiene un único encabezado visual `Asistencia del día`, emitido por `app/Views/layouts/departamento-operativo.php:367`. El test ahora detecta encabezados con etiqueta semántica o clases visuales, excluye breadcrumbs, controles ocultos, dropdowns y modales, y prueba que detecta un duplicado no semántico inyectado en su fixture. No se encontró ni se corrigió un segundo título en la vista actual.
- `php tests/portal3_ui_quality_gate.php`: 25 PASS, 0 FAIL, 1 BLOCKED. `php tests/checador_m03_incidence_registration_regression.php`: 26 PASS, 0 FAIL; valida la acción, el modal y la referencia de Alicia con el botón nativo. La prueba HTML usa fixtures y no acredita persistencia productiva.
- Chrome 154 y Firefox 156 están instalados; Playwright/Puppeteer no están instalados ni configurados y el navegador integrado no tiene pestañas/sesión autenticada. La prueba visual continúa `VISUAL_BLOCKED`; no se generaron capturas ni se declaró aceptación visual.
