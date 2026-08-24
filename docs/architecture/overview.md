# Visión General de la Arquitectura

## Propósito

Describir la arquitectura de alto nivel de Portal3: capas, patrones y componentes principales.

## Alcance

Este documento cubre la estructura organizativa del código, los patrones de diseño utilizados y las decisiones arquitectónicas visibles en el código fuente.

---

## Arquitectura identificada

### **Confirmado**

Portal3 implementa una arquitectura en capas con los siguientes niveles:

```
public/index.php          ← Punto de entrada único
    ↓
app/Core/                 ← Framework propio (22 clases)
    ├── Bootstrap         ← Inicialización de zona horaria y Carbon
    ├── Session           ← Gestión de sesión PHP
    ├── Database          ← Inicialización de Eloquent Capsule
    ├── ErrorHandler      ← Manejo global de errores
    ├── Container         ← Inyección de dependencias (DI)
    ├── Router            ← Dispatcher de rutas (FastRoute)
    ├── Kernel            ← Registro y ejecución de middlewares
    └── Route             ← Helper estático de rutas con middleware
    ↓
routes/web.php            ← Definición de todas las rutas
    ↓
app/Middleware/           ← Auth, Guest, CSRF
    ↓
app/Controllers/          ← 128 controladores (lógica de entrada)
    ↓
app/Services/             ← 55 servicios (lógica de negocio)
    ↓
app/Models/               ← Eloquent Models (~200+ modelos)
    ↓
app/Views/                ← Plantillas PHP (44 directorios, 208 archivos)
```

### Capas identificadas

| Capa | Directorio | Responsabilidad |
|---|---|---|
| Entrada | `public/index.php` | Bootstrap, DI, dispatch |
| Framework Core | `app/Core/` | Infraestructura transversal |
| Rutas | `routes/web.php` | Definición de URLs → controladores |
| Middlewares | `app/Middleware/` | Auth, sesión, CSRF |
| Controladores | `app/Controllers/` | Entrada HTTP, delegación |
| Servicios | `app/Services/` | Lógica de negocio |
| Repositorios | `app/Repositories/` | Acceso a datos (uso mínimo) |
| Modelos | `app/Models/` | Eloquent ORM |
| Vistas | `app/Views/` | Templates PHP |
| DTOs | `app/DTO/` | Objetos de transferencia de datos |
| Helpers | `app/Helpers/` | Funciones globales |
| Renderers | `app/Renderers/` | Generación de PDF |

---

## Stack tecnológico confirmado

| Tecnología | Uso | Fuente |
|---|---|---|
| PHP 8.2+ | Backend principal | `composer.json` |
| MySQL | Base de datos | `.env` (variables DB_*) |
| Composer / PSR-4 | Autoloading | `composer.json` |
| FastRoute 1.3 | Router HTTP | `app/Core/Router.php` |
| Eloquent (illuminate/database 12) | ORM | `app/Core/Database.php` |
| firebase/php-jwt 7 | JWT tokens | `app/Core/JWTService.php` |
| vlucas/phpdotenv 5 | Variables de entorno | `public/index.php` |
| Monolog 3 | Logging | `app/Core/Logger.php` |
| filp/whoops 2 | Errores en desarrollo | `app/Core/ErrorHandler.php` |
| respect/validation 2 | Validación | `composer.json` |
| guzzlehttp/guzzle 7 | HTTP cliente | `composer.json` |
| nesbot/carbon 3 | Manejo de fechas | `app/Core/Bootstrap.php` |
| ramsey/uuid 4 | UUIDs | `composer.json` |
| dompdf/dompdf 3 | Generación de PDF | `composer.json` |
| setasign/fpdf 1.8 | Generación de PDF (legacy) | `composer.json` |
| bacon/bacon-qr-code 3 | Códigos QR (2FA) | `composer.json` |
| phpmailer/phpmailer 7 | Envío de emails | `composer.json` |
| phpoffice/phpspreadsheet 5 | Generación de Excel | `composer.json` |
| Bootstrap 5 | CSS UI framework | `public/assets/libs/bootstrap/` |
| Alpine.js | JS reactivo declarativo | TODO: confirmar versión |
| Axios | HTTP cliente JS | `public/assets/js/vendor.min.js` |
| DataTables | Tablas paginadas | `public/assets/libs/datatables.net/` |
| ApexCharts | Gráficas | `public/assets/libs/apexcharts/` |
| FullCalendar | Calendario | `public/assets/libs/fullcalendar/` |
| Quill | Editor de texto rico | `public/assets/libs/quill/` |
| Select2 | Dropdowns avanzados | `public/assets/libs/select2/` |
| SweetAlert2 | Alertas modales JS | `public/assets/libs/sweetalert2/` |
| SignaturePad | Captura de firmas | `public/assets/libs/signature_pad/` |
| SimpleBar | Scrollbar personalizado | `public/assets/libs/simplebar/` |

---

## Patrones de diseño confirmados

| Patrón | Evidencia |
|---|---|
| MVC | `app/Controllers/`, `app/Models/`, `app/Views/` |
| Service Layer | `app/Services/` con 55 servicios |
| Repository Pattern | `app/Repositories/` (solo 2: Usuario, Estacion) |
| Dependency Injection | `app/Core/Container.php` con autowiring por reflection |
| Singleton | `app/Core/Logger.php`, `app/Core/Container.php::singleton()` |
| DTO | `app/DTO/LoginResult.php` |
| Template Method | `app/Views/layouts/` con múltiples layouts |
| Middleware Pipeline | `app/Core/Kernel.php` + `app/Middleware/` |

---

## Notas sobre el Repository Pattern

**Inferido:** El uso del Repository Pattern es mínimo (solo 2 repositorios). La mayoría de los controladores y servicios acceden directamente a los modelos Eloquent, lo cual es un patrón mixto.

---

## Preguntas pendientes

- TODO: ¿Cuál es exactamente la versión de Alpine.js utilizada?
- TODO: ¿Existe algún mecanismo de caché de consultas?
- TODO: ¿Existe configuración de múltiples entornos (dev/staging/prod)?
- TODO: ¿Cuál es la estrategia de deploy actual?
- TODO: ¿Existe algún proceso de build para assets JS/CSS?
