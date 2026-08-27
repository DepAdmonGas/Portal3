# LOS EJEMPLOS REALES: CÓMO SE USA CADA UNO

> Casi al final del paquete a propósito: primero aprendiste a implementar (`02_IMPLEMENTACION_...
> `), conocés las tablas (`03_...`) y el glosario (`04_...`). Ahora mirá **cómo los 5 casos reales
> del proyecto** usan la multiestación, con el código de verdad y el flujo paso a paso.

---

## Antes de empezar: recordá los dos "cerebros" del sistema

| Método | Qué hace | Quién lo llama |
|---|---|---|
| `getContext('mi-modulo')` | **Leer** qué estación está seleccionada en este momento | Siempre el **Controller**, para filtrar datos |
| `setContext('mi-modulo', $estacion, $depto)` | **Guardar** qué estación elegir de ahora en adelante | El **JavaScript** (AJAX) **o** el **Controller** (PHP directo) |

> Los 5 ejemplos son **formas distintas de llamar a `setContext`** (guardar) y después **todas** usan
> `getContext` igual (leer). Eso es el "hilo común".

---

## Cómo elegir tu ejemplo (guía rápida)

| Lo que tu módulo necesita | Ejemplo que te sirve |
|---|---|
| Una página normal donde el usuario cambie estación con un **menú** | **1 · SASISOPA** |
| Un módulo con menú + **bloqueo de pantalla** (y varias tablas) | **2 · SGM** |
| Que el **botón/acción** del usuario lleve la estación a otro módulo | **3 · Gestoría** |
| La estación llega por **URL** (link directo) y hay que recordarla | **4 · Comparativo XML** |
| Una **regla de negocio** fija la estación (p. ej. por rol) | **5 · Solicitud de cheques** |

---

## EJEMPLO 1 · SASISOPA — la página clásica con selector (usálo en el 90% de los casos)

### En una frase
Una página normal del módulo muestra arriba el **badge + selector**; el usuario elige la estación,
la página se recarga y **todas** las consultas de esa página (incluidas las del DataTable) filtran
por la estación elegida.

### Qué ve el usuario
Entra a **Política** (`/sasisopa/politica`). Arriba hay un chip celeste con la estación actual y un
menú desplegable con las estaciones que le tocan. Cambia de estación → la página se recarga y ahora
muestra la política, misión, visión, las listas de comprobación y hasta el **botón "descargar PDF"**
de esa estación.

### Flujo por dentro
```
1. GET /sasisopa/politica
2. PoliticaController::politica()  →  ModuleStationService::getContext('sasisopa')
                                      → $idEstacion = la que esté en la sesión
3. ¿Qué hay en la sesión?
   - Hay elección guardada  → se muestra ESA estación
   - No hay                 → plan B: la estación del usuario (id_gas)
4. View::render() genera el badge + selector y lo deja en $moduleStationSelector
5. El layout imprime $moduleStationSelector → el usuario ve el menú
6. El usuario cambia de estación:
   module-station-selector.js → POST /api/module-context/set  → setContext()
   → window.location.reload() → vuelve al paso 1 con la nueva estación
```

### Archivos involucrados

| Archivo | Por qué entra |
|---|---|
| `app/Controllers/PoliticaController.php` | Lee el contexto y filtra los datos |
| `app/Views/sasisopa/politica.php` | La vista con los datos de la estación |
| `app/Views/layouts/sasisopa.php:316` | Imprime el selector (`<?= $moduleStationSelector ?? '' ?>`) |
| `public/assets/js/core/module-station-selector.js` | Controla el cambio de estación (es automático) |

### El código (real)

**Controller — armar la página:**

```php
public function politica(){

    // 1. LEER la estación del módulo (siempre igual)
    $moduleCtx  = ModuleStationService::getContext('sasisopa');
    $idEstacion = $moduleCtx['id_estacion'];

    // 2. Modelo de esa estación (para mostrar política/misión/visión)
    $estacion = $idEstacion ? Estacion::find($idEstacion) : null;

    $data = [
        'estacionId'       => $idEstacion,          // la vista sabe qué estación es
        'estacion'         => $estacion,            // los datos que se muestran
        'moduleStationKey' => 'sasisopa',           // ← enciende la generación del selector
        'scripts' => [
            '/js/core/module-station-selector.js?v=' . time(),  // ← el JS del selector
            // ... los scripts de los DataTables de la página
        ],
    ];

    View::render('sasisopa/politica', $data, 'sasisopa');   // layout sasisopa.php
}
```

**Controller — endpoint AJAX de la tabla (usa el MISMO contexto):**

```php
public function datatableListaComprobacion(){
    $idEstacion = ModuleStationService::getContext('sasisopa')['id_estacion'];

    $data = PoliticaListaComprobacion::where('id_estacion', $idEstacion)
        ->orderBy('fecha', 'desc')
        ->get();

    echo json_encode(["data" => $data, "permisos" => [...]]);
    exit;
}
```

> Ojo a esto: **el DataTable no recibe la estación por POST ni por GET**. Se la pide al contexto
> por su cuenta. Por eso, cuando cambias de estación y se recarga, la tabla "sabe" sola qué estación
> mostrar. Lo mismo hacen `updatePolitica()` y `descargarPolitica()`.

**Vista y layout (lo único visual):**

```php
<!-- vista: contenedor marcado con el módulo -->
<div id="container" data-module-station-key="sasisopa" data-estacion-id="...">
    <!-- cards de $estacion -->
</div>

<!-- layout: esta línea es la que hace VISIBLE el selector -->
<?= $moduleStationSelector ?? '' ?>
```

### La clave para entenderlo
- El Controller **no guarda nada**: solo **lee**. Quien guarda es el JS cuando el usuario cambia.
- La estación viaja en la **sesión**, nunca en la URL ni en cada consulta.
- Si te olvidás del `moduleStationKey` o de la línea del layout → no sale NADA (error #1).

### Cuándo usar este patrón y cómo probarlo
- **Usalo en:** toda página de un módulo multiestación con menú.
- **Probá:**
  1. Entrá a la página → debe verse el badge y el selector con las estaciones que tocan al usuario.
  2. Elegí otra estación → la página se recarga y los textos + tabla cambian.
  3. Refrescá con F5 → sigue la última estación (está en la sesión).

---

## EJEMPLO 2 · SGM — con bloqueo de pantalla y 2 tablas a la vez

### En una frase
Igual que SASISOPA, **pero con una protección extra**: si el módulo no le corresponde al usuario,
la pantalla se bloquea ("estación no disponible") ANTES de renderizar nada. Además, alimenta **dos
DataTables** (asistencia y revisiones) que filtran por la misma estación.

### Qué ve el usuario
Entra a **Estructura del sistema de medición** (`/sgm/estructura-sistema-medicion`). Ve el selector
igual que SASISOPA. Debajo, dos tablas: **Lista de asistencia** y **Revisiones SGM**. Si su usuario
no está configurado para el módulo SGM, en lugar de todo eso ve un mensaje de bloqueo y nada más.

### Flujo por dentro
```
1. GET /sgm/estructura-sistema-medicion
2. SgmEstructuraController::index():
   a. ModuleStationService::getContext('sgm')      → leer estación
   b. $this->guardModuleAccess('sgm', ...)          → ¿el usuario puede ver el módulo?
        NO  → se renderiza el bloqueo y se corta  (return)
        SÍ  → seguir
3. Se renderiza la página con selector + las 2 tablas
4. Cada tabla, cuando el JS la pide (AJAX), lee getContext('sgm') y filtra por la estación
5. Al cambiar de estación: como 'sgm' NO está en dataTableMap,
   el script hace window.location.reload() → la página entera vuelve a cargar
```

### Archivos involucrados

| Archivo | Por qué entra |
|---|---|
| `app/Controllers/SgmEstructuraController.php` | Lee contexto + bloquea si no aplica |
| `app/Views/sgm/estructura/index.php` | La página con las 2 tablas |
| `app/Views/layouts/sgm.php:287` | Imprime el selector |
| `public/assets/js/asistencia/listaasistencia.datatable.init.js` | Tabla 1 (lee el contexto) |
| `public/assets/js/sgm/revision/index.datatable.init.js` | Tabla 2 (lee el contexto) |

### El código (real)

```php
public function index(){

    // 1. LEER la estación (se hace ANTES del bloqueo)
    $moduleCtx  = ModuleStationService::getContext('sgm');
    $idEstacion = $moduleCtx['id_estacion'];

    // 2. BLOQUEO: si SGM no le toca al usuario, ¡ni se dibuja la página!
    if (!$this->guardModuleAccess('sgm', $title, 'sgm')) {
        return;   // lanza vista 403/bloqueo internamente
    }

    $data = [
        'estacionId'       => $idEstacion,
        'moduleStationKey' => 'sgm',                // enciende el selector
        'scripts' => [
            '/js/core/module-station-selector.js?v=' . time(),
            '/js/asistencia/listaasistencia.datatable.init.js?v=' . time(),   // tabla 1
            '/js/sgm/revision/index.datatable.init.js?v=' . time(),           // tabla 2
        ],
    ];

    View::render('sgm/estructura/index', $data, 'sgm');   // layout sgm.php
}
```

### La clave para entenderlo
- `guardModuleAccess('sgm', ...)` es **una línea que protege todo el módulo**: sin ella, cualquiera
  entraría aunque su intersección de estaciones quedara vacía.
- El selector y **varias tablas conviven** sin conflicto: cada tabla pide la estación al contexto.
- Nada de `dataTableMap` para SGM → el refresco es por recarga de página (simple y eficaz).

### Cuándo usar este patrón y cómo probarlo
- **Usalo en:** módulos donde el acceso depende del puesto/estaciones del usuario y hay más de una
  tabla por página.
- **Probá:**
  1. Entrá con un usuario que SÍ tiene SGM → se ve selector + 2 tablas.
  2. Cambia de estación → recarga y las 2 tablas se actualizan.
  3. Entrá con un usuario sin SGM → se ve el bloqueo (nada más).

---

## EJEMPLO 3 · GESTORÍA — la estación desde un botón (sin selector)

### En una frase
Acá **no hay selector**: la estación se elige desde un **botón** (menú lateral). Se guarda con un
POST AJAX y se navega al módulo destino, que ya muestra esa estación.

### Qué ve el usuario
En la pantalla de **Gestoría** cada estación tiene un botón (⋮). Al tocarlo se abre un panel lateral
(offcanvas) y aparece una acción **"SASISOPA"**. Al tocarla, el usuario viaja a SASISOPA **con la
estación que eligió en Gestoría ya seleccionada**.

### Flujo por dentro
```
1. El usuario toca un botón ⋮ de una estación
2. JS (Alpine): handleClick() → guarda this.estacionId = el `data-estacion-id` del botón
                                   y muestra el panel lateral
3. El usuario toca "SASISOPA" → irSasisopa()
4. JS:  axios.post('/api/module-context/set', { module_key: 'sasisopa',
                                                  id_estacion: this.estacionId, id_depto: null })
5. En .then() → window.location.href = '/sasisopa'
6. SASISOPA (Ejemplo 1) lee getContext('sasisopa') → muestra la estación de Gestoría
```

### Archivos involucrados

| Archivo | Por qué entra |
|---|---|
| `app/Views/gestoria/index.php:44-48` | El botón "SASISOPA" del panel lateral |
| `public/assets/js/gestoria/index.actions.init.js` | La lógica `irSasisopa()` |
| `routes/web.php:24` + `ModuleContextController` | El endpoint que guarda (ya existe) |

### El código (real)

**Vista — el botón del panel lateral:**

```php
<a class="list-group-item list-group-item-action border-0 fs-4 p-3" href="#"
    @click.prevent="irSasisopa()">              <!-- ← tocar → irSasisopa -->
    <i class="ti ti-currency-solana fs-4 me-2"></i>
    SASISOPA
</a>
```

**JS — de dónde sale la estación y cómo se guarda:**

```javascript
// 1. Al tocar el botón ⋮ de una estación:
handleClick(event) {
    const button = event.target.closest('.btn-menu-estacion');
    if (!button) return;

    this.estacionId    = button.dataset.estacionId;     // ← la estación elegida
    this.estacionNombre = button.dataset.estacionNombre;
    this.offcanvas.show();
}

// 2. Al tocar "SASISOPA":
irSasisopa() {
    if (!this.estacionId) {                  // sin estación → va directo
        window.location.href = '/sasisopa';
        return;
    }
    axios.post('/api/module-context/set', {  // GUARDA la estación (AJAX)
        module_key: 'sasisopa',
        id_estacion: this.estacionId,
        id_depto: null
    }).then(() => {
        window.location.href = '/sasisopa';  // y navega al destino
    }).catch(() => { /* Swal de error */ });
}
```

### El patrón de "cualquier botón" (en 4 pasos) — memoralo
```
1. Tener el id_estacion (data-estacion-id del botón, una variable, etc.)
2. axios.post('/api/module-context/set', { module_key, id_estacion, id_depto })
3. En .then() → navegar (window.location.href)
4. El Controller del destino lee getContext() → filtra y renderiza
```

### La clave para entenderlo
- Guardar una estación **no requiere** el selector: cualquier código puede llamar al endpoint.
- `setContext()` valida en el servidor: si la estación no le corresponde al usuario, **la ignora**
  y el destino usa el `id_gas` (plan B). No podés "colarte" a otra estación.

### Cuándo usar este patrón y cómo probarlo
- **Usalo en:** puntos de entrada que "empujan" al usuario hacia otro módulo (perfil de estación,
  resúmenes, menús), o cuando querés un selector no estándar.
- **Probá:** tocá una estación, abrí el panel, tocá "SASISOPA" → SASISOPA debe abrir con esa
  estación. Si no hay estación elegida → va directo.

---

## EJEMPLO 4 · COMPARATIVO XML — la estación viene por URL (guardada desde el Controller)

### En una frase
La estación llega como parte de la **URL** (`/seguimiento/2025/12`). El **Controller la lee y la
guarda en el contexto** con PHP directo (sin AJAX). Así el vínculo "memoriza" la estación y las
demás pantallas del módulo la heredan.

### Qué ve el usuario
En **Dirección de Operaciones → Comparativo XML** hay un botón "Seguimiento" por estación. Al
tocarlo, la URL queda `/seguimiento/{año}/{estacion}` y la página abre con el **badge de esa
estación** (sin menu desplegable: la estación ya viene decidida en la URL).

### Flujo por dentro
```
1. El usuario toca "Seguimiento" de la estación 12 → navega a /seguimiento/2025/12
2. ComparativoXmlController::seguimiento(2025, 12)
3. ¿La URL trae estación? (12 > 0)
   → SÍ: ModuleStationService::setContext('comparativo-xml', 12)   // se guarda sola
   → NO (idEstacion = 0): se lee del contexto lo que ya había
4. Si aún no hay estación ni año → vista de error 403
5. Se renderiza la página con el badge de la estación 12 (select oculto)
```

### Archivos involucrados

| Archivo | Por qué entra |
|---|---|
| `app/Controllers/ComparativoXmlController.php:374-381` | Lee/guarda el contexto según la URL |
| `app/Views/departamento-operativo/1-corporativo/comparativo-xml/seguimiento` | La vista |
| Los vínculos del Dashboard / lista de estaciones | El origen del `idEstacion` que viaja en la URL |

### El código (real)

```php
public function seguimiento(int $idYear, int $idEstacion = 0)
{
    // La URL decide: si trae estación → la GUARDO; si no → leo la que haya.
    if ($idEstacion <= 0) {
        // caso B: no trae estación → usar la del contexto
        $moduleCtx = ModuleStationService::getContext('comparativo-xml');
        $idEstacion = (int)($moduleCtx['id_estacion'] ?? 0);
    } else {
        // caso A: la URL trae estación → GUARDARLA (directo, sin AJAX)
        ModuleStationService::setContext('comparativo-xml', $idEstacion);
    }

    // Sin estación ni año → error
    if (!$idEstacion || !$idYear) {
        View::render('errors/403', [], 'departamento-operativo');
        return;
    }

    $estacion = Estacion::find($idEstacion);
    $nombreES = $estacion ? $estacion->nombre : "ES{$idEstacion}";

    View::render('departamento-operativo/1-corporativo/comparativo-xml/seguimiento', [
        'idEstacion'             => $idEstacion,
        'nombreES'               => $nombreES,
        'moduleStationKey'       => 'comparativo-xml',
        'ocultarSelectorEstacion'=> true,     // ← badge SÍ, menú desplegable NO
        // ... scripts y links
    ], 'departamento-operativo');
}
```

### La clave para entenderlo
- **El `setContext()` puede llamarse directo en el Controller**, sin ningún HTTP/AJAX: es solo un
  método del servicio.
- Usa **`ocultarSelectorEstacion`** para mostrar **solo el badge** (la estación ya la decidió la
  URL, no hay por qué volver a preguntarle al usuario).
- Donde sea que el usuario entre al módulo **sin** estación en la URL → se usa la que quedó guardada.

### Cuándo usar este patrón y cómo probarlo
- **Usalo en:** páginas a las que se llega desde un **link con parámetro** (reportes, seguimientos,
  drill-down desde tablas).
- **Probá:** entrá a `/seguimiento/2025/12` → abrí el módulo y el badge debe decir **Acueducto de
  Guadalupe** (estación 12). Luego entrá por el menú a otra vista del módulo → `getContext()` sigue
  devolviendo la 12 (se heredó).

---

## EJEMPLO 5 · SOLICITUD DE CHEQUES — estación forzada por regla (desde el Controller)

### En una frase
Una **regla de negocio** dice: "todo usuario de Gestoría solo ve la estación 8". El Controller
**fuerza** el contexto con `setContext('solicitud-cheques', 8, 5)`. El usuario no puede cambiarlo.

### Qué ve el usuario
Un miembro de Gestoría entra a **Solicitud de cheques** y directamente ya está parado en la
estación 8 (con el filtro "Gestoría"). No tiene menú desplegable: la regla decidió por él.

### Flujo por dentro
```
1. GET /departamento-operativo/solicitud-cheque/{año}/{mes}
2. SolicitudChequeController → $permisos = SolicitudChequeService::getPermisos()
3. ¿permisos['es_gestoria'] ?
   → SÍ: setContext('solicitud-cheques', 8, 5)   // forzado, guardado en sesión
         $idEstacion = 8, $idDepto = 5
         y se filtran las listas para dejar SOLO las de la estación 8 / depto Gestoría
   → NO: flujo normal (multiestación o estación del puesto)
4. Todas las consultas de la página usan $idEstacion / $idDepto
```

### Archivos involucrados

| Archivo | Por qué entra |
|---|---|
| `app/Controllers/SolicitudChequeController.php:104-114` | La regla de Gestoría |
| `app/Services/SolicitudChequeService.php` | Permisos y listas auxiliares |
| La vista + sus DataTables | Filtran con la estación forzada |

### El código (real)

```php
if ($permisos['es_gestoria']) {

    // REGLA: Gestoría SOLO ve la estación 8 y el departamento 5
    ModuleStationService::setContext('solicitud-cheques', 8, 5);

    // Fijar las variables locales para forzar el filtrado
    $idEstacion  = 8;
    $idDepto     = 5;
    $nombreFiltro = 'Gestoría';
    $esMultiestacion = false;

    // Dejar SOLO lo de esa estación/departamento en las listas de pendientes
    $estacionesFiltradas    = array_filter($estacionesFiltradas, fn($s) => $s['id'] == 8);
    $departamentosFiltrados = array_filter($departamentosFiltrados, fn($d) => $d['id_puesto'] == 5);
}
```

### La clave para entenderlo
- El `setContext()` de 3 argumentos: `setContext($moduleKey, $idEstacion, $idDepto)`.
- Se usa el contexto como **garantía**: aunque otra parte del sistema (otra página, otro módulo)
  pregunte por `getContext('solicitud-cheques')`, va a recibir 8/5.
- Es una **regla del lado del servidor**: el usuario (ni el JS) puede desobedecerla.

### Cuándo usar este patrón y cómo probarlo
- **Usalo en:** reglas de negocio rígidas (rol X solo estación Y), o para dejar "preparado" el
  contexto antes de que la vista consulte.
- **Probá (con un usuario de Gestoría):** entrá a Solicitud de cheques → filtro "Gestoría", sin
  menú de estaciones. Cambiá de `{mes}`/`{año}` en los vínculos → sigue en la estación 8.

---

## Comparación final de los 5

| | SASISOPA | SGM | Gestoría | Comparativo XML | Solicitud cheques |
|---|---|---|---|---|---|
| Quién guarda la estación | JS | JS | JS (botón) | **Controller** | **Controller** |
| Cómo guarda | `POST /api/module-context/set` | ídem | ídem | `setContext()` directo | `setContext()` directo |
| Selector visible para el usuario | Sí | Sí | No (botón) | No (solo badge) | No |
| Protección extra | — | `guardModuleAccess()` | — | `ocultarSelectorEstacion` + 403 | Regla de rol |
| Cuántas tablas/páginas usa el contexto | Varias (tabla, PDF, guardados) | 2 tablas | El destino (SASISOPA) | Vistas del módulo | Las listas de cheques |

**El hilo común de los cinco:** *guardar la estación en la sesión (`setContext`) → las pantallas del
módulo filtran por ella (`getContext`).* Lo único que cambia es **quién ejecuta el guardado**: el
**JavaScript** (interacción del usuario: selector o botón) o el **Controller** (lógica del servidor:
URL o rol).

---

## Fin del paquete V3

Ya tenés: el objetivo (`01`), cómo se implementa (`02`), las tablas (`03`), el glosario (`04`) y
los ejemplos (`05`).