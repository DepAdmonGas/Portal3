# Hotspots

## Hotspot 1: `routes/web.php`

### Ubicación
`routes/web.php`

### Motivo
Es un archivo monolítico de más de 1500 líneas que contiene la definición de absolutamente todas las rutas de la aplicación.

### Evidencia
Tamaño de ~160KB. Crece con cada nuevo módulo que se añade al sistema.

### Impacto potencial
Conflictos de control de versiones constantes en equipos grandes y mantenibilidad reducida.

### Dependencias
FastRoute. Todos los controladores dependen de estar registrados aquí.

### Riesgo de modificarlo
Alto, un error de sintaxis aquí rompe el enrutamiento de toda la aplicación.

### Prioridad de análisis
Alta.

---

## Hotspot 2: `app/Core/Router.php`

### Ubicación
`app/Core/Router.php`

### Motivo
Dispatcher in-house crítico que maneja la inicialización de controladores.

### Evidencia
Posee una lógica de fallback extraña donde si una ruta no usa el middleware `Route::auth()` o `Route::guest()`, crea el controlador usando `new $controllerClass()` saltándose el Contenedor de Inyección de Dependencias.

### Impacto potencial
Incoherencia arquitectónica que limita la refactorización futura orientada a interfaces o Unit Testing de Controladores.

### Dependencias
`FastRoute`, Controladores, `app/Core/Route.php`.

### Riesgo de modificarlo
Muy alto, es el corazón de la aplicación HTTP.

### Prioridad de análisis
Alta.

---

## Hotspot 3: `app/Views/layouts/main.php`

### Ubicación
`app/Views/layouts/main.php`

### Motivo
Contiene lógica de seguridad crítica y lógica de negocio mezclada con UI.

### Evidencia
Inyecta AlpineJS y Axios mediante CDN. Maneja globalmente mediante un `<script>` local la inyección del token CSRF para Axios y la intercepción del error `419` (Expiración CSRF).

### Impacto potencial
Si una vista utiliza otro layout que no sea `main.php` y realiza peticiones POST (AJAX), fallarán silenciosamente por no incluir este parche.

### Dependencias
Axios, CSRFToken de backend.

### Riesgo de modificarlo
Medio.

### Prioridad de análisis
Media.

---

## Hotspot 4: `app/Controllers/BaseController.php`

### Ubicación
`app/Controllers/BaseController.php`

### Motivo
Contiene lógica acoplada a variables de sesión específicas del sistema (estación, multiestación, etc).

### Evidencia
Llama estáticamente a `Session::get('usuario')` en el constructor, creando un fuerte acoplamiento a que la petición esté en una sesión activa. 

### Impacto potencial
Difícil de testear unitariamente. Cualquier controlador que lo extienda hereda esta dependencia rígida.

### Dependencias
`Session`, `ModuleStationService`.

### Riesgo de modificarlo
Alto, afecta a los 128 controladores que heredan de él.

### Prioridad de análisis
Alta.

---

## Hotspot 5: Consultas DB Crudas y Transacciones (`DB::`)

### Ubicación
`app/Services/AnalisisCompraService.php`, `app/Controllers/EstacionController.php`, etc.

### Motivo
Manejo inconsistente del acceso a datos.

### Evidencia
Se descubrió el uso de `DB::connection()->select("...")` y `DB::transaction()` saliéndose del uso habitual de Modelos Eloquent. Además, los repositorios apenas se usan (solo 2 existen).

### Impacto potencial
Falta de estandarización en las consultas a base de datos (raw SQL vs Eloquent vs Query Builder), propensión a errores de sintaxis o SQL injection si no se ligan bien los parámetros en raw queries.

### Dependencias
`Illuminate\Database\Capsule\Manager`

### Riesgo de modificarlo
Medio-Alto (según la query).

### Prioridad de análisis
Media.
