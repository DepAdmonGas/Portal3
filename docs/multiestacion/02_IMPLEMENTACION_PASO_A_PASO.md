# CÓMO SE IMPLEMENTA LA MULTIESTACIÓN (PASO A PASO)

> **La guía central.** Acá está TODO lo que se necesita para implementar multiestación en un
> módulo de Portal 3. Cada elemento trae su **propósito** (para qué sirve), explicado simple.
>
> Es un proceso de **7 pasos**: BD → Controller → Vista → Layout → JavaScript → Rutas → Datos.

---

## PASO 1 · BASE DE DATOS

### 1.1 · Registrar el módulo

Agregás **una fila** en la tabla `tb_modulos_config` que dice *"este módulo soporta estas
estaciones"*. (Schemas y detalles: `03_BASE_DE_DATOS_Y_TABLAS.md`.)

```sql
INSERT INTO tb_modulos_config
    (modulo_key, tipo, estaciones_soportadas, departamentos_soportados,
     tipo_departamento, allow_all, placeholder, activo)
VALUES
    ('mi-modulo', 'stations_only', '[1,2,3,5]', NULL,
     NULL, 0, 'Selecciona una estación...', 1)
ON DUPLICATE KEY UPDATE
    tipo                  = VALUES(tipo),
    estaciones_soportadas = VALUES(estaciones_soportadas),
    allow_all             = VALUES(allow_all),
    placeholder           = VALUES(placeholder),
    activo                = VALUES(activo);
```

### Elementos de este paso y su propósito

| Elemento | Qué es | Para qué sirve |
|---|---|---|
| `modulo_key` | Nombre único del módulo (`'sasisopa'`, `'sgm'`) | Ser el **identificador común** con el Controller y la vista |
| `tipo` | Solo estaciones (`stations_only`) o estaciones + departamentos (`stations_and_departments`) | Decidir **qué tipo de menú** muestra el sistema |
| `estaciones_soportadas` | Lista de IDs de estaciones que el módulo usa | Limitar **qué estaciones puede elegir** el usuario |
| `allow_all` | `1` o `0` | Activar (o no) la opción **"Todas las estaciones"** |
| `placeholder` | Texto del menú sin elegir | Guiar al usuario con **"Selecciona una estación..."** |
| `ON DUPLICATE KEY UPDATE` | Cláusula del INSERT | Hacer el script **re-ejecutable** (no falla si ya existe) |

> ⚠️ **TRAMPA:** `tipo` es un enum; **solo** acepta `'stations_only'` o `'stations_and_departments'`.
> Si escribís `'stations'` la columna lo rechaza y el selector **no aparece**.

### 1.2 · (Opcional) Limitar estaciones por usuario

| Tabla | Qué guarda | Propósito |
|---|---|---|
| `tb_multiestacion_puesto` | Estaciones permitidas por **puesto** | Que todo el puesto comparta las mismas estaciones |
| `tb_multiestacion_usuario` | Estaciones permitidas por **usuario** | Que un usuario puntual tenga estaciones distintas al puesto (tiene prioridad) |

El sistema calcula: **disponibles = estaciones del módulo ∩ estaciones permitidas del usuario**.
Si queda vacío → el módulo se bloquea para ese usuario.

---

## PASO 2 · CONTROLLER

### 2.1 · Los dos servicios que se usan

| Servicio | Para qué sirve |
|---|---|
| `ModuleStationService::getContext($key)` | **Leer** la estación actual del módulo desde la sesión |
| `ModuloService::permisosSesion($this->modulo)` | **Saber** qué permisos tiene el usuario sobre el módulo (para la interfaz) |

```php
use App\Services\ModuleStationService;

$moduleCtx  = ModuleStationService::getContext('mi-modulo');
$idEstacion = $moduleCtx['id_estacion'];   // int o null
$nombre     = $moduleCtx['nombre'];
```

> ⚠️ `$this->estacionId()` ya **no existe** (era el método del sistema viejo). Se reemplazó por
> `getContext()`. Tu Controller hereda de `BaseController`.

### 2.2 · La receta base (página normal)

```php
public function miPagina(){

    $title = 'MI PÁGINA';
    $permisos = ModuloService::permisosSesion($this->modulo);

    // 1. Leer la estación del módulo
    $moduleCtx = ModuleStationService::getContext('mi-modulo');
    $idEstacion = $moduleCtx['id_estacion'];

    // 2. (opcional) Bloquear si el usuario no puede usar el módulo
    // if (!$this->guardModuleAccess('mi-modulo', $title, 'mi-layout')) return;

    $data = [
        'title'            => $title,
        'permisos'         => $permisos,
        'estacionId'       => $idEstacion,       // → lo usa la vista
        'moduleStationKey' => 'mi-modulo',       // → ACTIVA el selector
        'scripts' => [
            '/js/core/module-station-selector.js?v=' . time(),
        ],
    ];

    View::render('mi-modulo/mi-pagina', $data, 'mi-layout');
}
```

### Elementos de este paso y su propósito

| Elemento | Para qué sirve |
|---|---|
| `getContext($key)['id_estacion']` | Obtener **la estación elegida** para filtrar los datos |
| `'moduleStationKey' => $key` | **Encender** el selector: sin esto NO aparece |
| `'estacionId' => $idEstacion` | Pasar la estación a la vista (se usa en `data-estacion-id`) |
| `guardModuleAccess()` | **Proteger** la página: si el módulo no aplica al usuario → pantalla bloqueada |
| `'ocultarSelectorEstacion' => true` | **Ocultar el selector** en páginas de detalle (solo badge) |
| `module-station-selector.js` en `scripts` | Cargar el **JavaScript** que maneja el menú |

### 2.3 · Variantes

**Detalle (ocultar selector):** página hija que no debe cambiar de estación → agregar
`'ocultarSelectorEstacion' => true` al `$data`.

**Endpoint compartido por 2 módulos** (ej: lista de asistencia sirve a SASISOPA y SGM): derivar
la clave según el registro:

```php
private static function moduleKeyPorPunto(int $punto): string
{
    return $punto >= 100 ? 'sgm' : 'sasisopa';
}
```

---

## PASO 3 · VISTA PHP

El contenedor principal expone 2 atributos. Ejemplo real (`app/Views/sasisopa/politica.php:1-3`):

```php
<div id="container" data-elemento="1" data-herramienta="1"
    data-module-station-key="<?= htmlspecialchars($moduleStationKey ?? '', ENT_QUOTES, 'UTF-8') ?>"
    data-estacion-id="<?= (int) ($estacionId ?? 0) ?>">

    <!-- contenido normal -->
</div>
```

| Elemento | Para qué sirve |
|---|---|
| `data-module-station-key` | Dejar constancia del módulo en la página (lo lee el JS) |
| `data-estacion-id` | Exponer la estación actual a la lógica de la página |

### (Opcional) Mensaje cuando no hay estación

Si el módulo aplica pero el usuario aún no eligió estación, se muestra un aviso y se oculta el
contenido (`app/Views/sasisopa/index.php:7-14`):

```php
<?php if (empty($estacionId)): ?>
    <div id="sasisopa-empty-message"
         class="alert alert-secondary border-0 text-center text-muted py-4 mt-4">
        Debes de seleccionar una estación del menú superior para poder visualizar los elementos de SASISOPA.
    </div>
<?php else: ?>
    <div id="sasisopa-content">
        <!-- TODO el contenido va aquí -->
    </div>
<?php endif; ?>
```

---

## PASO 4 · LAYOUT

El `<select>` **no se escribe a mano**: `View::render()` lo genera y lo guarda en la variable
`$moduleStationSelector`. El layout **solo la imprime** (y si no la imprime → el selector no
aparece, es el error #1).

**Dónde va** (`app/Views/layouts/sasisopa.php:316` y `app/Views/layouts/sgm.php:287`):

```php
<?php include __DIR__ . '/../partials/_global-badge.php'; ?>

<?= $moduleStationSelector ?? '' ?>   <!-- ← aquí -->

<h4 class="fw-semibold mt-3"><?= $title; ?></h4>
<?php \App\Core\Breadcrumb::render(); ?>
<?= $content ?>
```

> ⚠️ `departamento-operativo.php` tiene su propia lógica de badge/selector (líneas 333-370)
> independiente. SASISOPA y SGM usan layout propio, no ese.

---

## PASO 5 · JAVASCRIPT

### 5.1 · Cargar el script (automático)

```php
'scripts' => [
    '/js/core/module-station-selector.js?v=' . time(),
],
```

El script se **auto-inicializa**: busca los `<select>` del layout y los activa solos. No escribís
código por página.

> `asset()` ya agrega `assets/` → usá `/js/...`, nunca `/assets/js/...` (quedaría duplicado y no
> cargaría).

### 5.2 · Qué pasa al cambiar de estación

```
1. El usuario elige otra estación
2. El badge se actualiza
3. Se guarda el contexto: POST /api/module-context/set {module_key, id_estacion, id_depto}
4. Se recargan los datos (tabla o página completa)
```

| Elemento | Para qué sirve |
|---|---|
| `POST /api/module-context/set` | **Guardar** la estación en la sesión (endpoint que YA existe) |
| `dataTableMap` | Mapear módulo → tabla para **recargar solo la tabla** (AJAX) |
| `customReload` | Tu función propia para **controlar** el refresco a mano |
| `window.location.reload()` | **Recargar la página** (lo que hacen SASISOPA y SGM) |

### 5.3 · ⚠️ LAS DOS VÍAS PARA GUARDAR LA ESTACIÓN (JavaScript o Controller)

La estación se puede guardar **desde el JavaScript** (lo más común) o **directamente desde el
Controller en PHP**. Las dos terminan llamando al **mismo método**:
`ModuleStationService::setContext()`.

**VÍa 1 · Desde el JavaScript (AJAX) — el selector y los botones**

Cuando el usuario cambia en el `<select>` (lo hace `module-station-selector.js`) o toca un botón
(el caso Gestoría), se hace:

```javascript
axios.post('/api/module-context/set', {
    module_key: 'mi-modulo',
    id_estacion: 5,
    id_depto: null
});
```

Ese endpoint (`ModuleContextController`) llama a `ModuleStationService::setContext()` y guarda la
estación en la sesión.

**Usalo cuando:** el usuario elige la estación de forma interactiva (selector, botón, menú).

**VÍA 2 · Desde el Controller (PHP directo) — lógica del servidor**

En el Controller podés llamar al método directamente para **decidir o forzar** la estación:

```php
ModuleStationService::setContext('mi-modulo', 5);           // solo estación
ModuleStationService::setContext('mi-modulo', 8, 5);        // estación + departamento
```

**Usalo cuando:** la estación la decide la **lógica** y no el usuario: viene por URL, la fija el
rol del usuario, o hay que prepararla antes de renderizar.

**Ejemplos reales en el código del proyecto:**

| Archivo real (línea) | Qué hace |
|---|---|
| `ComparativoXmlController.php:380` | La estación viene por URL en `/seguimiento/{año}/{idEstacion}` → se guarda al contexto: `ModuleStationService::setContext('comparativo-xml', $idEstacion)` |
| `SolicitudChequeController.php:105` | Si el usuario es Gestoría, **fuerza** estación 8 y departamento 5: `ModuleStationService::setContext('solicitud-cheques', 8, 5)` |

> En las dos vías el resto del sistema funciona idéntico: **la lectura SIEMPRE es con
> `getContext()`** y todas las consultas filtran por la estación del contexto.

---

## PASO 6 · RUTAS

Solo se registran las rutas del módulo. El endpoint del contexto **ya existe**:
`POST /api/module-context/set` (`routes/web.php:24`). **Nunca** crear otro.

```php
$r->addGroup('/mi-modulo', function (RouteCollector $r) {
    $r->addRoute('GET', '', Route::auth(['MiModuloController', 'index']));
    $r->addRoute('GET', '/mi-pagina', Route::auth(['MiModuloController', 'miPagina']));
});
```

---

## PASO 7 · FILTRAR LOS DATOS (lo más importante)

**Toda consulta** que devuelva o cree datos del módulo DEBE filtrar por la estación del contexto:

```php
// Al LEER (DataTable, listados, PDFs):
$datos = MiTabla::where('id_estacion', ModuleStationService::getContext('mi-modulo')['id_estacion'])->get();

// Al CREAR un registro:
$registro = MiTabla::create([
    'id_estacion' => ModuleStationService::getContext('mi-modulo')['id_estacion'],
    // ...
]);
```

**Reglas de seguridad:**

| Caso | Regla |
|---|---|
| Leer un registro específico | Filtrar también por `id_estacion` (si no coincide → 404) |
| PDFs / descargas | Usar la estación del contexto para el encabezado y la validación |
| Endpoint compartido | Derivar la clave con `moduleKeyPorPunto()` **antes** de leer el contexto |
| Contexto nulo (sin estación) | Devolver datos vacíos (`['data' => []]`) o redirigir a `/404` |

> ⚠️ Si no filtrás, verás (o crearás) datos de **TODAS** las estaciones. Es un bug grave.

---

## Resumen: las piezas que se tocan por capa

```
BASE DE DATOS   → tb_modulos_config (¿qué estaciones soporta?)
CONTROLLER      → getContext() + moduleStationKey (+ guard, ocultarSelector)
VISTA           → data-module-station-key + data-estacion-id (+ mensaje vacío)
LAYOUT          → <?= $moduleStationSelector ?? '' ?>
JAVASCRIPT      → module-station-selector.js (auto-init + recarga)
RUTAS           → las del módulo (el endpoint de contexto YA existe)
CONSULTAS       → SIEMPRE filtrar por getContext($key)['id_estacion']
```

---

## Siguiente paso

Vas a necesitar entender las tablas. Seguí con **`03_BASE_DE_DATOS_Y_TABLAS.md`**.