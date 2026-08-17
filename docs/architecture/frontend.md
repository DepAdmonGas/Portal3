# Arquitectura Frontend

## Propósito

Documentar la arquitectura frontend de Portal3: sistema de vistas, librerías, JavaScript y patrones de interacción.

## Alcance

Templates PHP, Alpine.js, Axios, librerías de UI, assets y convenciones.

---

## Sistema de vistas

### **Confirmado**

Portal3 usa **PHP nativo como motor de templates**. No hay motor de plantillas como Blade, Twig o Smarty.

```
app/Views/
├── layouts/          6 layouts PHP
│   ├── main.php          Layout principal (aplicación)
│   ├── auth.php          Layout de autenticación (login)
│   ├── configuracion.php Layout de configuración
│   ├── departamento-operativo.php
│   ├── sasisopa.php      Layout del módulo SASISOPA
│   └── sgm.php           Layout del módulo SGM
├── partials/         Componentes parciales reutilizables
├── errors/           Páginas de error (404, 405)
├── login/            Vistas de login
├── home/             Vista de inicio
└── [44 directorios]  Un directorio por módulo funcional
```

### Sistema de renderizado (confirmado)

`View::render($view, $data, $layout)` en `app/Core/View.php`:

1. Mezcla variables globales (`user`, `estaciones`, `pendientes`) con `$data`
2. Hace `extract()` de variables en el scope de la vista
3. Si la vista usa `moduleKey`: genera selector de estación/departamento
4. Buffer de output con `ob_start()` / `ob_get_clean()`
5. Incluye el layout correspondiente con `$content` disponible

### Variables globales inyectadas en todas las vistas (confirmado)

| Variable | Valor |
|---|---|
| `$title` | `'Portal3'` (default) |
| `$user` | Objeto `Usuario` autenticado (Eloquent) |
| `$filtro_usuario` | Array de sesión del usuario |
| `$estaciones` | Lista de estaciones permitidas para el usuario |
| `$pendientes` | Pendientes del calendario vía `CalendarioService` |

---

## Librerías de UI confirmadas

| Librería | Versión | Ubicación | Uso |
|---|---|---|---|
| Bootstrap | 5.x | `public/assets/libs/bootstrap/` | Layout, componentes CSS |
| Alpine.js | TODO: verificar | `public/assets/js/vendor.min.js` | Reactividad declarativa |
| Axios | TODO: verificar | `public/assets/js/vendor.min.js` | Peticiones HTTP |
| ApexCharts | - | `public/assets/libs/apexcharts/` | Gráficas y KPIs |
| DataTables | - | `public/assets/libs/datatables.net/` | Tablas paginadas |
| DataTables BS5 | - | `public/assets/libs/datatables.net-bs5/` | Integración Bootstrap 5 |
| FullCalendar | - | `public/assets/libs/fullcalendar/` | Calendarios |
| Quill | - | `public/assets/libs/quill/` | Editor de texto rico |
| Select2 | - | `public/assets/libs/select2/` | Dropdowns avanzados |
| SweetAlert2 | - | `public/assets/libs/sweetalert2/` | Alertas y modales |
| SignaturePad | - | `public/assets/libs/signature_pad/` | Captura de firmas digitales |
| SimpleBar | - | `public/assets/libs/simplebar/` | Scrollbar personalizado |

---

## JavaScript

### **Confirmado**

Archivos JS identificados en `public/assets/js/`:

- `vendor.min.js` (87 KB) — Bundle de librerías (Alpine.js, Axios, etc.)
- `web.php` (113 KB) — TODO: verificar propósito (nombre inusual para un .js)
- `loader.min.js` (115 B) — Loader mínimo
- `switch.estacion.min.js` (412 B) — JS para switch de estación
- Subdirectorios por módulo (43 directorios con JS específico por módulo)

### Estructura JS por módulo (confirmado)

Cada módulo tiene su propio directorio en `public/assets/js/[modulo]/` que contiene el JavaScript específico de ese módulo. Esto espeja la estructura de `app/Views/`.

---

## Alpine.js

### **Parcialmente identificado**

Alpine.js está incluido en `vendor.min.js`. Se usa en las vistas para reactividad declarativa.

- TODO: Identificar directivas usadas: `x-data`, `x-init`, `x-show`, `x-if`, `x-for`, `x-model`
- TODO: Identificar stores globales (`Alpine.store()`)
- TODO: Documentar componentes Alpine complejos por módulo
- TODO: Verificar si se usa Alpine con `@alpine:initialized` o patterns específicos

---

## Axios

### **Parcialmente identificado**

Axios está incluido en `vendor.min.js`. Se usa para peticiones AJAX al backend.

- TODO: Identificar si existe un wrapper/helper centralizado de Axios
- TODO: Identificar si hay interceptores configurados (manejo global de 401, etc.)
- TODO: Documentar endpoints más frecuentemente llamados desde el frontend
- TODO: Verificar el manejo de errores en respuestas Axios

---

## CSS

### **Confirmado (parcialmente)**

- Bootstrap 5 como base
- CSS propio en `public/assets/css/`
- Fuentes en `public/assets/fonts/`
- Imágenes en `public/assets/images/` e `img/`

- TODO: Listar archivos CSS custom
- TODO: Verificar si existe preprocesador (SASS/LESS)

---

## Archivos relevantes

- `app/Core/View.php` — Motor de renderizado
- `app/Views/layouts/main.php` — Layout principal
- `public/assets/js/vendor.min.js` — Bundle de librerías
- `public/assets/libs/` — Librerías de terceros

---

## Preguntas pendientes

- TODO: ¿Cuál es la versión exacta de Alpine.js?
- TODO: ¿Qué contiene `public/assets/js/web.php`? (nombre inusual)
- TODO: ¿Existe algún proceso de build/minificación de assets?
- TODO: ¿Se usan componentes Alpine reutilizables entre módulos?
- TODO: ¿Cómo se gestiona el token CSRF en peticiones Axios?
- TODO: ¿Existe manejo global de errores 401 para redirección al login?
