# Documentación: Clase `App\Core\Request`

La clase `App\Core\Request` proporciona un punto centralizado y estático para acceder a toda la información de la petición HTTP entrante: datos enviados por el cliente (`GET`, `POST`, `JSON`, `COOKIES`), encabezados (*headers*), métodos HTTP e información del servidor/cliente.

---

## 🚀 Uso Rápido: Lectura de Datos de Entrada

### 1. Peticiones JSON (APIs / Axios / Fetch)
Si estás enviando un payload JSON desde el cliente (ej. Axios, Alpine.js, React), el método ideal es `jsonInput()`:

```php
use App\Core\Request;

// Obtener un campo específico del cuerpo JSON
$nombre = Request::jsonInput('nombre');

// Con valor por defecto si la clave no existe
$rol = Request::jsonInput('rol', 'usuario');

// Obtener todo el payload JSON como arreglo
$dataJson = Request::json();
```
---

### 2. Entradas Unificadas (input, all, only, except)
Los métodos generales unifican automáticamente $_GET, $_POST y el cuerpo JSON en una sola fuente de datos (priorizando JSON sobre POST y GET).

```php
// Obtener cualquier parámetro sin importar si vino por GET, POST o JSON
$nombre = Request::input('nombre', 'invitado');

// Comprobar si existe la clave en la petición
if (Request::has('email')) { ... }

// Comprobar si existe Y no está vacío (después de trim)
if (Request::filled('comentario')) { ... }

// Obtener TODOS los datos unificados
$todo = Request::all();

// Obtener solo ciertos campos
$credenciales = Request::only(['usuario', 'password']);

// Obtener todos los campos EXCEPTO algunos
$datosGuardar = Request::except(['_token', 'confirm_password']);

```
---

### 3. Peticiones URL Query (GET) o Formularios Clásicos (POST)

```php
// Leer datos enviados exclusivamente por la URL (?pagina=2&busqueda=php)
$pagina = Request::get('pagina', 1);$todosLosGets = Request::query();

// Leer datos enviados exclusivamente por formulario HTML POST
$todosLosPosts = Request::post();

// Leer cookies de la petición
$sessionCookie = Request::cookie('PHPSESSID');$todasLasCookies = Request::cookies();
```
---


## 🌐 Métodos e Información de la Petició

### 1. Identificación del Método HTTP
```php
$metodo = Request::method(); // Devuelve 'GET', 'POST', 'PUT', etc.

if (Request::isPost()) { ... }
if (Request::isGet()) { ... }
if (Request::isPut()) { ... }
if (Request::isPatch()) { ... }
if (Request::isDelete()) { ... }
```
---

### 2. Encabezados (Headers)

```php
// Obtener un encabezado específico (case-insensitive para el parámetro)
$token = Request::header('Authorization');$contentType = Request::header('Content-Type');

// Obtener todos los encabezados
$headers = Request::headers();
```
---

### 3. Información del Servidor y Cliente

```php
$uri = Request::uri();           // Ej: '/login/acceso' (sin query params)$host = Request::host();         // Ej: 'portal3.test'
$ip = Request::ip();             // IP del cliente$userAgent = Request::userAgent(); // Navegador/Cliente del usuario
$referer = Request::referer();   // URL previa de procedencia$protocolo = Request::protocol(); // Ej: 'HTTP/1.1'

// Verificaciones booleanas
$esHttps = Request::isSecure();    // true si usa SSL/HTTPS
$esAjax = Request::isAjax();        // true si viene de XMLHttpRequest$quiereJson = Request::expectsJson(); // true si el header Accept contiene 'application/json'
```