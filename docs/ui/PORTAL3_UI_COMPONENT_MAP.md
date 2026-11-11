# Portal3 — mapa verificado de componentes UI

**Revisión local:** 28 de octubre de 2026

**Alcance:** referencias concretas del checkout de Portal3; los patrones son específicos a cada componente y no deben generalizarse sin inspeccionar la vista objetivo.

## Fuentes e infraestructura comprobadas

| Elemento | Implementación local | Dependencias / alcance comprobado |
|---|---|---|
| Hoja base | `public/assets/css/styles.css` | CSS instalado de Modernize con Bootstrap: botones `btn`, `btn-sm`, `btn-outline-*`, tablas, modales, utilidades y tema. Es la primera referencia visual. |
| Layout de Departamento Operativo | `app/Views/layouts/departamento-operativo.php:367-368,384,400-402` | Emite el título dinámico en un `<h4>`, luego `Breadcrumb::render()`, después el contenido. Carga `bootstrap.bundle.min.js` y los scripts de cada vista. Carga además `table-actions.css` justo después de `styles.css` (`:13-14`). |
| Breadcrumb | `app/Core/Breadcrumb.php:5-40` | La vista/controlador agrega elementos y el layout los renderiza como `nav[aria-label="breadcrumb"] > ol.breadcrumb`. Es navegación; no sustituye el encabezado de página. |
| Selector/contexto de estación | `app/Views/layouts/departamento-operativo.php:356,358-365`; `app/Controllers/ChecadorController.php:1447-1457` | El layout inserta `$moduleStationSelector` antes del título. Para Asistencia, el contenido de `daily-attendance.php:174-190` también presenta estación y fecha laboral como contexto de la consulta. El controlador enlaza CSS Select2/DataTables y sus inicializadores para el espacio de trabajo. |
| Tabla de Asistencia | `app/Views/departamento-operativo/2-recursos-humanos/checador/daily-attendance.php:284-353` | PHP genera filas y detalle; `table-responsive` contiene la tabla. Los datos se alinean normalmente. La celda final (`:331-350`) centra el control. DataTables se carga por el layout con recursos enlazados en `ChecadorController.php:1447-1471`. |
| Botón directo de tabla | `app/Views/departamento-operativo/2-recursos-humanos/checador/workspace-adjustments.php` (botón `btn btn-sm btn-outline-primary`); CSS nativo en `public/assets/css/styles.css` | Aplicable cuando queda exactamente una acción permitida. Tabler se usa mediante clases `ti ti-*`. Esta es una referencia concreta del estilo del botón; revisar también el patrón de la vista que se modifica. |
| Dropdown de varias acciones | `public/assets/js/grupos/datatable.init.js`, `public/assets/js/gafetes/gafetes.detalle.datatable.init.js`, `public/assets/js/bitacora/reporte.datatable.init.js`; en PHP, `daily-attendance.php:334-350` | Patrón común verificado: celda centrada, `.dropdown.dropstart`, tres puntos Tabler `fs-6`, `.dropdown-menu` y `.dropdown-item`. Grupos aporta la alineación de icono/texto `d-flex align-items-center gap-3` con iconos `fs-4`. Asistencia conserva sus acciones, presenta un `<button>` plano con color muted para teclado/foco y usa los estilos de los elementos de Grupos. El Bootstrap bundle gestiona la apertura y estado ARIA. |
| Modal de detalle | Asistencia: `daily-attendance.php:355-480`; incidencias: `workspace-incidences.php:219-246` | `modal fade`, `modal-dialog`, `modal-content`, `modal-header`, `modal-body` y `modal-footer`; Bootstrap bundle del layout gestiona el comportamiento. El cuerpo informativo mantiene alineación normal. |
| Modal de formulario | Asistencia: `daily-attendance.php:216-281`; patrón comparable: `app/Views/aditivo/index.php` | Controles Bootstrap (`form-label`, `form-control`, `form-select`), etiquetas explícitas y botones del tema. Los formularios conservan CSRF donde corresponde. |
| Select2 en modal | `public/assets/css/select2-modal.css`; `public/assets/js/core/actions.alpine.js` (`bindModalSelect2`); `ChecadorController.php:1447-1470` | Select2 se carga sólo en los espacios de trabajo que lo requieren; usar el helper actual y `dropdownParent` del modal cuando corresponda. No agregar inicialización paralela. |
| Tabla responsive y acciones alineadas | `app/Views/tarjetas/index.php`; `public/assets/js/tarjetas/tarjetas.datatable.init.js`; `app/Views/aditivo/index.php` | Referencias concretas de `table-responsive`, `align-middle` y alineación de la columna de acciones. No implican centrar tablas o datos. |

## Regla de acciones verificada y exigida por el gate

- Cero acciones autorizadas: no renderizar control de acción.
- Una acción autorizada: un botón directo Modernize (`btn`, `btn-sm` y variante existente) con Tabler Icon.
- Dos o más acciones autorizadas: dropdown Bootstrap/Modernize con Tabler Icon de tres puntos y las opciones permitidas.
- No exponer acciones no permitidas como elementos deshabilitados. El control de disponibilidad se hace con las mismas condiciones autorizadas del componente.
- Centrar únicamente la celda Acciones y el control que contiene. Datos, tablas completas y cuerpos de modal conservan la alineación normal.

## CSS adicional: `table-actions.css`

El layout operativo lo carga desde `app/Views/layouts/departamento-operativo.php:14`. La hoja actual tiene selectores `.table-actions-cell`, `.table-actions-group` y `.btn-table-action*`.

- `.btn-table-action*` vuelve a implementar presentación que ya ofrecen `btn`, `btn-sm` y variantes `btn-outline-*` de Modernize/Bootstrap. Luego de la remediación ya no se usa en `workspace-incidences.php`; sigue teniendo consumidores en `workspace-journeys.php:63-64`, `workspace-temporary-access.php:46-66`, `schedule-week.php:91` y `checador.datatable.init.js:213-216`.
- `.table-actions-group` duplica utilidades flex de Bootstrap para alineación, distribución y separación.
- `.table-actions-cell` aplica centro y padding a **todo el contenido de la celda opt-in**, no sólo a botones. No se encontró un selector de centrado global para todas las tablas o modales; el riesgo depende de dónde se aplique esta clase.
- Las reglas de `.btn-table-action`, `.table-actions-group` y `.table-actions-cell` se conservan porque los consumidores anteriores siguen presentes. Se retiraron únicamente las ramas Font Awesome `.fa/.fas/.far/.fab` del selector de icono: no se encontraron consumidores en los botones compartidos; se mantiene la rama Tabler `.ti`.
- El gate escanea selectores globales obvios, comprueba que la acción de Incidencias no use el estilo paralelo y verifica que sus reglas restantes sigan teniendo consumidores. También reporta el alcance de `.table-actions-cell`.

## Validación automatizada y límites

Comando focalizado: `php tests/portal3_ui_quality_gate.php`.

La prueba compone la salida de los archivos PHP reales `daily-attendance.php`, `workspace-incidences.php` y `layouts/departamento-operativo.php` con datos sintéticos locales y examina el HTML generado con `DOMDocument`/`DOMXPath`. Comprueba títulos visuales únicos, breadcrumb, contexto de estación/fecha, carga del Bootstrap bundle, acciones por permiso/estado, destino del modal, centramiento aislado, alineación de datos/modales y consumo CSS específico de Incidencias. **El resultado se etiqueta `FIXTURE_RENDERED_DOM`; no es una solicitud HTTP autenticada, ejecución de JavaScript, inspección visual ni captura de navegador.**

La inspección local no encontró configuración/paquetes de Playwright, Puppeteer, PHPUnit ni un runner de navegador del proyecto; tampoco una sesión autenticada autorizada en el navegador disponible. No se encontraron capturas de referencia aprobadas. Por ello la verificación visual real, la comparación antes/después y las capturas en 375, 768, 1024 y 1440 px quedan `BLOCKED`/`NOT_VERIFIED`. No se aprueba visualmente un cambio con este bloqueo.

### Resultado de referencia del checkout actual

Ejecución local de `php tests/portal3_ui_quality_gate.php`: **30 PASS, 0 FAIL, 1 BLOCKED**. Comprueba el DOM de Asistencia e Incidencias con fixtures, incluida la detección de duplicados semánticos y no semánticos y el patrón estructural del dropdown. El duplicado visual inyectado en el fixture se detecta; breadcrumb, menú, control oculto y opción de selector no cuentan. El render actual tiene un único encabezado visual de página procedente de `departamento-operativo.php:367`. No constituye validación de píxeles ni de JavaScript en navegador.

La acción única de Incidencias usa ahora clases nativas en sus ramas QA y normal. Las reglas de `table-actions.css` que todavía consumen otras vistas permanecen; retirar el archivo o las reglas base dañaría esos componentes. Se removieron sólo los selectores de iconos Font Awesome sin consumidores. El riesgo de `.table-actions-cell` se reporta por separado: centra todo el contenido de la celda donde se aplica.

El wrapper responsive de Asistencia usa `overflow-y-hidden`; el inicializador existente de su DataTable instala dropdowns Bootstrap con Popper `strategy: fixed` en `draw` y dispone instancias anteriores en `preDraw`. Es el mecanismo de clipping ya usado por la tabla Gafetes en `public/assets/js/gafetes/gafetes.datatable.init.js`; no se añade una librería ni un inicializador paralelo.

Una comparación de estilo calculado en Chrome headless con `public/assets/css/styles.css` local confirmó que la referencia gris y el activador plano de Asistencia comparten color muted, fondo transparente, borde 0, padding 0, familia/tamaño de fuente y altura de línea. Ambos menús usan la misma sombra Modernize y padding Bootstrap de 10px 16px. La prueba usó fragmentos HTML de componente y **no** representa una visita autenticada ni valida apertura, posicionamiento real o respuesta responsive de la página.

`tests/checador_m03_incidence_registration_regression.php`: 26 PASS, 0 FAIL; verifica el detalle nativo y conserva las comprobaciones de registro/readback existentes. Google Chrome 154 y Firefox 156 están instalados localmente, pero el navegador integrado no tiene pestañas ni sesión autenticada, y el checkout/global npm no tiene Playwright/Puppeteer. La verificación de navegador y la aceptación visual permanecen `VISUAL_BLOCKED`.
