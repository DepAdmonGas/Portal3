# Portal3 — Contrato de interfaz y reutilización de componentes

**Estado:** instalado en el checkout local el 28 de octubre de 2026; referencias cotejadas contra el código actual.
**Ámbito:** Portal3 (PHP MVC), con especial atención a Checador V2.
**Prioridad:** reglas específicas aprobadas por el usuario > este contrato > convenciones del módulo, sin alterar seguridad o contratos congelados.
**Última preparación:** 28 de octubre de 2026.

> Este archivo evita que Codex improvise CSS, JS o composiciones de UI. **No sustituye la inspección del checkout actual**. La auditoría de instalación y los hallazgos concretos están en `docs/ui/PORTAL3_UI_AUDIT_2026-10-28.md`.

## A. Fuentes de verdad — inspeccionar antes de tocar vistas

| Propósito | Ruta en el proyecto | Regla |
|---|---|---|
| Instrucciones del agente | `AGENTS.md` | Leer íntegro y conservar instrucciones actuales. |
| Skill nativa | `.agents/skills/portal3-native-module/SKILL.md` | Usar para toda modificación MVC o UI. |
| Skill de Checador | `.agents/skills/checador-v2-functional-slice/SKILL.md` | Usar adicionalmente en Checador. |
| Base visual Modernize + Bootstrap + Tabler Icons | `public/assets/css/styles.css` | **Primera opción** para botones, tablas, modales, badges, tipografía, espaciados y utilidades. No editar el vendor/base. |
| CSS compartido de tablas | No existe `public/assets/css/portal-tables.css` en este checkout. | No referenciar ni asumir esta hoja. Inspeccionar `styles.css`, Bootstrap y la vista real antes de proponer estilos. |
| CSS de Select2 en modales | `public/assets/css/select2-modal.css` | Reusar; evitar dropdowns recortados. |
| Layout operativo | `app/Views/layouts/departamento-operativo.php` | Verificar estilos y orden de carga. No alterar layouts globales por un cambio local. |
| Otros layouts | `app/Views/layouts/{main,configuracion,sasisopa,sgm}.php` | Solo inspección salvo autorización explícita. |
| JS base de interacciones | `public/assets/js/core/actions.alpine.js` | Buscar y reutilizar `bindModalSelect2`, la apertura/cierre de modales y patrones existentes. |
| Vista de Asistencia | `app/Views/departamento-operativo/2-recursos-humanos/checador/daily-attendance.php` | Usar el patrón de acción definido en la sección B según las acciones disponibles; conservar permisos y rutas locales. |
| Vista de Incidencias | `app/Views/departamento-operativo/2-recursos-humanos/checador/workspace-incidences.php` | Conservar búsqueda, registro, detalle e historial. |
| JS de incidencias | `public/assets/js/departamento-operativo/2-recursos-humanos/checador.incidences.init.js` | Inspeccionar en el checkout local; puede no estar en la rama remota. |
| CSS agregado recientemente | `public/assets/css/table-actions.css` | **Auditar antes de usarlo**: no asumir que es necesario ni conservar reglas duplicadas con Modernize. |

**Verificado en este checkout:** `styles.css` es el CSS compartido Modernize/Bootstrap y contiene las clases Bootstrap citadas; los cinco layouts inspeccionados cargan `styles.css` y, actualmente, también `table-actions.css`; `select2-modal.css` y `actions.alpine.js` existen. `portal-tables.css` no existe. Las rutas de referencia funcional y visual concretas se documentan en el informe de auditoría.

## B. Reglas visuales aprobadas — no reinterpretar

1. **Modernize primero.** Usar clases nativas Bootstrap/Modernize (`btn`, `btn-sm`, `btn-outline-primary`, `btn-outline-secondary`, `text-start`, `text-center`, `align-middle`, `d-inline-flex`, `align-items-center`, `justify-content-center`, `gap-*`, etc.) y los iconos Tabler ya instalados. No introducir nuevas librerías ni CSS paralelo sin demostrar una carencia concreta y obtener aprobación.
2. **La orden «centrar botones de tabla» solo afecta la celda de acciones y sus botones.** Nunca significa centrar filas, celdas de datos, nombres, tarjetas, etiquetas, títulos, descripciones, estado ni contenidos de modales.
3. **Las tablas muestran datos alineados según su significado.** Texto, nombres, categorías y descripciones: izquierda por defecto. Números y fechas: conservar convención del módulo. Columna de acción: contenido centrado horizontal y verticalmente. No aplicar `text-center` al `table`, `tr`, `tbody`, `.modal-body`, `.card-body` o contenedores compartidos.
4. **Acciones de tabla según disponibilidad.** Con una acción disponible, mostrar un botón directo Modernize. Con dos o más acciones disponibles, agruparlas en un dropdown nativo Modernize/Bootstrap con icono Tabler de tres puntos y una opción por acción. Si no hay acciones disponibles, no mostrar botón ni disparador. Omitir por completo las opciones no permitidas; no presentarlas como deshabilitadas. Mantener los dropdowns existentes donde ya sean el patrón, y no añadir CSS paralelo.
5. **Modales: cabecera institucional aprobada; información del cuerpo alineada a la izquierda.** No centrar cards, etiquetas o valores. No repetir el nombre del colaborador como subtítulo debajo del título si ya aparece en la tarjeta «Colaborador». Conservar datos y orden; cambios tipográficos mínimos.
6. **Incidencias: no recuperar el recuadro superior ni el botón «Volver a asistencia diaria».** Mantener la estación en la cabecera y el contexto de fecha/colaborador de forma compacta; acciones principales en cabecera de la tarjeta según ACL: Encargado `Buscar` + `Nueva incidencia`, Departamento Operativo solo `Buscar`.
7. **No cambiar estructura ni tipografía del portal sin aprobación visual.** Respetar color, contraste, bordes y tamaños institucionales existentes. No reinterpretar los ejemplos como permiso de rediseñar.
8. **No mostrar etiquetas de QA, simulación o depuración en la UI operativa.** La trazabilidad técnica vive en logs/documentación, no en las tarjetas visibles.

## C. Patrones de código preferidos (ilustrativos; cotejar con implementación actual)

### Acciones dentro de una tabla

Aplicar la regla de disponibilidad de B.4: una acción usa un botón directo; dos o más usan dropdown; ninguna acción disponible significa que no se muestra un botón.

#### Una acción disponible

```html
<td class="text-center align-middle">
    <button type="button" class="btn btn-sm btn-outline-primary"
            data-bs-toggle="modal" data-bs-target="#detalle-id">
        <i class="ti ti-eye me-1" aria-hidden="true"></i>
        Ver detalle
    </button>
</td>
```

#### Dos o más acciones disponibles

```html
<td class="text-center align-middle">
    <div class="d-flex justify-content-center">
        <div class="dropdown dropstart">
            <button type="button" class="btn btn-sm btn-outline-secondary"
                    data-bs-toggle="dropdown" aria-expanded="false" aria-label="Acciones">
                <i class="ti ti-dots-vertical" aria-hidden="true"></i>
            </button>
            <ul class="dropdown-menu">
                <li><a class="dropdown-item d-flex align-items-center gap-2" href="/ruta-autorizada">
                    <i class="ti ti-eye" aria-hidden="true"></i>Ver detalle
                </a></li>
            </ul>
        </div>
    </div>
</td>
```

El ejemplo muestra la estructura Bootstrap observada en `public/assets/js/grupos/datatable.init.js`, `public/assets/js/gafetes/gafetes.datatable.init.js` y `public/assets/js/bitacora/aditivo.datatable.init.js`; conservar las acciones, iconos, destinos y clases de acceso reales del módulo. No renderizar opciones sin permiso como elementos `disabled`. La alineación se limita a la celda Acciones y su control; no centrar datos ni contenido de modales. No crear CSS paralelo.

### Contenido de detalle de una incidencia

```html
<div class="modal-header bg-primary">
    <h2 class="modal-title h5 text-white mb-0">Detalle de incidencia · Justificación</h2>
    <!-- Botón de cierre según patrón existente -->
</div>
<div class="modal-body text-start">
    <!-- Campos: etiquetas y valores alineados a la izquierda; sin duplicar nombre en cabecera -->
</div>
```

El ejemplo indica **alcance**, no ordena copiar el HTML ni modificar todas las cabeceras. Revisar primero la vista real, las clases existentes y el tema oscuro. Evitar forzar texto blanco si el componente no lo requiere.

### Select2 dentro de modal

- Reutilizar el helper `bindModalSelect2` de `actions.alpine.js` y `select2-modal.css` cuando sea el patrón actual.
- `dropdownParent` debe apuntar al modal correspondiente, sin crear listeners duplicados.
- Verificar selección, búsqueda, cierre y reapertura en navegador real.

### CSS excepcional

Si Modernize no resuelve una necesidad comprobada, un ajuste debe:

- estar **encapsulado** por una clase única de esa vista/componente;
- afectar exclusivamente el elemento pedido;
- documentar qué clase nativa fue insuficiente;
- comprobar que no modifica modales, datos, otras tablas ni los cinco layouts;
- evitar selectores globales como `table td`, `.modal-body *`, `.card *` o `body .btn` para una excepción local.

## D. Inventario mínimo de JS, tablas y fuentes de datos

Antes de editar una tabla o modal, Codex debe mapear **la ruta y la cadena real de ejecución**, sin inferir arquitectura a partir de ejemplos:

1. Ruta HTTP GET/POST y parámetros; FastRoute y prefijos de grupo.
2. Controlador y variables definidas antes de usar (`$date`, `$requestedWorker`, etc.).
3. ACL, estación/localidad y validación del servidor.
4. Fuente de datos y formato: PHP server-rendered o DataTables/AJAX. No convertir una en otra sin autorización.
5. Vista PHP, layout, CSS efectivamente cargado, JavaScript con sus eventos, Bootstrap modal y Select2.
6. Prueba previa de apertura, filtrado, submit, redirect, readback y recarga cuando corresponda.

**Checador:** no asumir que Asistencia e Incidencias comparten transporte ni contrato de parámetros. Verificar la ruta y el controlador vigentes, la vista local y el inicializador JS; el checkout puede contener cambios no reflejados en descripciones previas.

## E. Alcance estricto — antes y después del cambio

Para cada solicitud, redactar este minicontrato antes de editar:

```text
CAMBIO_PEDIDO: ...
ELEMENTOS_PROTEGIDOS: ...
PATRÓN_EXISTENTE_A_REUTILIZAR: ...
CÓMO_SE_VALIDARÁ: ...
```

**Regla de bloqueo:** si la petición dice «centrar botones de tabla», cualquier diff que introduzca centrado en datos o modales es una regresión y debe quitarse antes de presentar el resultado. Si una tarea visual intenta modificar permisos, persistencia, autenticación o router, detener esa ampliación y explicar la causa.

**Revisión de diff obligatoria:** inspeccionar todos los archivos modificados y clases CSS afectadas, no solo los archivos planeados. Cambios a los layouts globales requieren justificación específica. Proteger worktree existente; no `git clean`, `git reset`, staging, commit ni push sin autorización.

## F. Calidad y aceptación — nunca confundirlas

**Código:** PHP lint, JS parse, pruebas focalizadas, `git diff --check` y CSS válido cuando la herramienta esté disponible. Son necesarias, no bastan para aceptar un diseño.

**Runtime:** petición autenticada y flujo real GET/POST cuando corresponda, validación/CSRF/ACL, datos, estados y persistencia/readback.

**Visual:** capturas del mismo estado antes y después a 375, 768, 1024 y 1440 px; comprobar tipografía, alineación, espacio, scroll, modal y botones. Incluir al menos una captura con modal abierto y otra con tabla visible. Si navegador del agente está bloqueado, informar `VISUAL_NOT_VERIFIED` y solicitar revisión del usuario; **nunca declarar aprobación visual por test HTML o PHP**.

**No aplicar automáticamente un nuevo diseño a todo Portal3**: primero una pantalla piloto con aprobación explícita. Reutilización posterior solo en áreas autorizadas.

## G. Contrato de reporte para Codex

```text
LOCAL_REFERENCES_INSPECTED:
MODERNIZE_CLASSES_REUSED:
CUSTOM_CSS_ADDED_AND_WHY:
CHANGED_FILES:
UNAUTHORIZED_VISUAL_ELEMENTS_CHANGED: NONE / LIST
DATA_ALIGNMENT_PRESERVED:
TABLE_ACTION_ALIGNMENT_ONLY:
DROPDOWNS_UNCHANGED:
FUNCTIONAL_REGRESSION:
REAL_HTTP_VERIFIED:
SCREENSHOTS_375_768_1024_1440:
USER_VISUAL_APPROVAL: PENDING / APPROVED
FINAL_RESULT: TECHNICAL_PASS_VISUAL_PENDING / PASS / FAIL
```

**Sin aprobación visual, el estado no puede ser `PASS` completo.**
