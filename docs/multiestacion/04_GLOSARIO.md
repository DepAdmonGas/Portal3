# GLOSARIO DE ELEMENTOS

> El diccionario de la multiestación. Cada elemento trae: **qué es**, **para qué sirve** y
> **dónde se usa**. Consultalo cuando un término no te quede claro al leer los otros documentos.

---

## Elementos de configuración

| Elemento | Qué es | Para qué sirve | ¿Dónde? |
|---|---|---|---|
| **moduleKey** | El nombre único del módulo (`'sasisopa'`, `'sgm'`) | Ser el identificador **común** entre BD, Controller, Vista y JS | Tabla `tb_modulos_config.modulo_key`, `$data['moduleStationKey']`, `data-module-station-key` |
| **tipo** (`stations_only` / `stations_and_departments`) | Enum que define el modelo del menú | Elegir si el menú es **solo de estaciones** o **estaciones + departamentos** | Columna `tb_modulos_config.tipo` |
| **estaciones_soportadas** | Lista JSON de IDs de estaciones | Decir **qué estaciones puede mostrar el módulo** | Columna `tb_modulos_config.estaciones_soportadas` |
| **allow_all** | Bandería `1`/`0` | Activar la opción **"Todas las estaciones"** en el menú | Columna `tb_modulos_config.allow_all` |
| **placeholder** | Texto del menú por defecto | Guiar con **"Selecciona una estación..."** | Columna `tb_modulos_config.placeholder` |
| **activo** | Bandería `1`/`0` | Habilitar/deshabilitar una configuración | Columnas `activo` de las 4 tablas |
| **id_gas** | Estación por defecto del usuario | Ser el **plan B** cuando no hay selección | Tabla de usuarios (usuario `id_gas`) |

---

## El contexto (el corazón del sistema)

| Elemento | Qué es | Para qué sirve | ¿Dónde? |
|---|---|---|---|
| **Contexto** | La estación elegida en este momento para un módulo, guardada en la sesión (`$_SESSION['module_context'][$key]`) | Que **todas las pantallas del módulo** compartan la misma estación | Sesión del usuario |
| **`getContext($key)`** | Método que **lee** el contexto de un módulo | Obtener `id_estacion` para filtrar datos y renderizar | `ModuleStationService::getContext('sasisopa')['id_estacion']` |
| **`setContext($key, $idEstacion, $idDepto)`** | Método que **guarda** el contexto | Guardar la estación cuando el usuario cambie de estación | **2 vías**: desde **JS** (endpoint `POST /api/module-context/set`) o desde el **Controller** (llamada directa, ej: `ComparativoXmlController.php:380`, `SolicitudChequeController.php:105`) |
| **`isBlocked`** | Bandería interna del servicio | Saber si el módulo está **no disponible** para el usuario (intersección vacía) | `ModuleStationService::$isBlocked` |
| **Endpoint `POST /api/module-context/set`** | Ruta ya creada que recibe `{module_key, id_estacion, id_depto}` | Guardar la estación via **AJAX** (desde el selector o desde un botón) | `routes/web.php:24` + `ModuleContextController` |

---

## La interfaz (lo que ve el usuario)

| Elemento | Qué es | Para qué sirve | ¿Dónde? |
|---|---|---|---|
| **Selector** | El `<select>` generado por el sistema para cambiar de estación | Que el **usuario elija** la estación | Layout (lo imprime `$moduleStationSelector`) |
| **Badge** | Chip de color con la estación actual | Mostrar **dónde está parado** el usuario | Junto al selector, en el layout |
| **`$moduleStationSelector`** | Variable donde `View::render()` deja el HTML del badge + select | Imprimir el selector en el layout | `View.php` → `layouts/sasisopa.php:316`, `layouts/sgm.php:287` |
| **`moduleStationSelector` (layout)** | La única línea que escribe el layout | Hacer visible el selector (si falta → NO aparece) | `<?= $moduleStationSelector ?? '' ?>` |

---

## El Controller (lo que se pasa por `$data`)

| Elemento | Qué es | Para qué sirve | ¿Dónde? |
|---|---|---|---|
| **`moduleStationKey`** | La llave en `$data` que activa el selector | **Encender** la generación del `<select>` | `$data['moduleStationKey'] => 'mi-modulo'` |
| **`estacionId`** | La estación actual expuesta a la vista | Que la vista sepa qué estación mostrar | `$data['estacionId'] => $idEstacion` |
| **`ocultarSelectorEstacion`** | Bandería para ocultar el selector | Mostrar **solo badge** en páginas de detalle | `$data['ocultarSelectorEstacion'] => true` |
| **`guardModuleAccess($key, $title, $layout)`** | Método del `BaseController` | **Bloquear la pantalla** si el módulo no aplica al usuario | Dentro de métodos del Controller |

---

## La vista y el JavaScript

| Elemento | Qué es | Para qué sirve | ¿Dónde? |
|---|---|---|---|
| **`data-module-station-key`** | Atributo del contenedor con el moduleKey | Dejar constancia del módulo y guiar al JS | `<div id="container" data-module-station-key="...">` |
| **`data-estacion-id`** | Atributo con la estación actual | Exponer la estación a la lógica de la página | Mismo contenedor |
| **`module-station-selector.js`** | Script reutilizable y auto-inicializado | Manejar el cambio de estación (badge → POST → recarga) | `public/assets/js/core/module-station-selector.js` |
| **`dataTableMap`** | Mapa módulo → tabla (estático) | **Recargar solo la tabla** con AJAX al cambiar de estación | Dentro del script del selector |
| **`customReload`** | Función propia que se ejecuta al cambiar | **Controlar manualmente** el refresco (mensajes, recargas) | `ModuleStationSelector.init(key, { customReload: fn })` |
| **`window.location.reload()`** | Recarga completa de la página | Refrescar todo cuando no hay mapa ni función (caso SASISOPA/SGM) | El script lo decide solo |

---

## El patrón del "flujo" completo

```
1. El usuario elige estación (selector ○ botón)
2. El sistema guarda el contexto → POST /api/module-context/set
3. La sesión queda: $_SESSION['module_context']['mi-modulo'] = {id_estacion: X}
4. La pantalla se recarga (o la tabla) y el Controller lee getContext()
5. Toda consulta de datos filtra por esa estación
```

> **Variante B:** el paso 2 también puede ser **sin AJAX**, llamando `ModuleStationService::setContext()`
> directo desde el Controller (cuando la estación la decida la lógica: URL o rol). Ver `02` → 5.3.

---

## Para no confundirse nunca

| No confundir A con B | A es... | B es... |
|---|---|---|
| `getContext` vs `setContext` | **Leer** la estación | **Guardar** la estación |
| `moduleStationKey` vs `moduleKey` | La llave **del Controller** que activa el selector | El **nombre del módulo** en sí |
| `moduleStationKey` vs `$moduleStationSelector` | Lo que pasa el **Controller** | La variable que **imprime el layout** |
| Selector vs Badge | El menú para **cambiar** | El chip que **muestra la actual** |
| `guardModuleAccess` vs `ocultarSelectorEstacion` | Bloquear **toda la pantalla** | Ocultar **solo el menú** |

---

## Siguiente paso

Con el glosario dominado, mirá los **ejemplos reales** en `05_EJEMPLOS_SASISOPA_SGM_GESTORIA.md`.