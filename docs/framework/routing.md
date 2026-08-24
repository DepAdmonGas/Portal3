# Routing

## Propósito

Documentar el sistema de routing de Portal3: cómo se definen rutas, cómo se despachan y qué middlewares se aplican.

## Alcance

`routes/web.php`, `app/Core/Router.php`, `app/Core/Route.php`, `app/Core/Kernel.php`.

---

## Sistema de routing

### **Confirmado**

Portal3 usa `nikic/fast-route` como base del router, envuelto en la clase propia `App\Core\Router`.

---

## Definición de rutas

### **Confirmado** — `routes/web.php`

Todas las rutas del sistema están definidas en un único archivo: `routes/web.php` (1506 líneas, ~160 KB).

El archivo retorna una función que recibe un `RouteCollector` de FastRoute:

```php
return function (RouteCollector $r) {
    // Ruta pública (solo guests)
    $r->addRoute('GET', '/login', Route::guest(['LoginController', 'index']));
    
    // Ruta autenticada
    $r->addRoute('GET', '/home', Route::auth(['HomeController', 'index']));
    
    // Grupo de rutas
    $r->addGroup('/personal', function (RouteCollector $r) {
        $r->addRoute('GET', '', Route::auth(['PersonalController', 'index']));
        $r->addRoute('POST', '/create', Route::auth(['PersonalController', 'createPersonal']));
    });
};
```

---

## Middlewares de ruta

### **Confirmado**

| Helper | Middlewares aplicados | Uso |
|---|---|---|
| `Route::auth($handler)` | `['auth', 'csrf']` | Rutas que requieren autenticación |
| `Route::guest($handler)` | `['guest']` | Rutas solo para usuarios no autenticados |
| `Route::middleware($middlewares, $handler)` | Personalizable | Uso genérico |

Los middlewares registrados en `Kernel`:
- `auth` → `AuthMiddleware::class`
- `guest` → `GuestMiddleware::class`
- `csrf` → `CsrfMiddleware::class`

---

## Métodos HTTP usados (confirmados en routes/web.php)

| Método | Uso |
|---|---|
| `GET` | Vistas HTML y datos para DataTables |
| `POST` | Crear, actualizar, eliminar, login, acciones |

- TODO: Verificar si se usan `PUT`, `PATCH`, `DELETE`

---

## Inconsistencia identificada

**Inferido (posible deuda técnica):**

El `Router::callController()` instancia controladores con `new $controllerClass()` sin DI. Solo las rutas definidas con `Route::auth()` o `Route::guest()` tienen acceso al Container. Los controladores simples (sin middleware) no tienen inyección de dependencias automática.

---

## Archivos relevantes

- `routes/web.php` — Todas las rutas
- `app/Core/Router.php` — Dispatcher
- `app/Core/Route.php` — Helper de rutas
- `app/Core/Kernel.php` — Registro de middlewares

---

## Preguntas pendientes

- TODO: ¿Cuántas rutas GET, POST existen en total en web.php?
- TODO: ¿Existen rutas con parámetros dinámicos `{id}`? ¿Cuántas?
- TODO: ¿Existe algún mecanismo de caché de rutas?
- TODO: ¿Se planea dividir `routes/web.php` en múltiples archivos?
