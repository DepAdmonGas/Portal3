# Flujo de una Petición HTTP

## Propósito

Documentar el ciclo completo que recorre una petición HTTP desde que entra al servidor hasta que se genera la respuesta.

## Alcance

Cubre el flujo de una petición estándar autenticada (GET de vista) y una petición AJAX (POST de datos).

---

## Flujo confirmado (petición autenticada GET)

```
1. Petición HTTP llega al servidor web
   └── public/.htaccess redirige todo a public/index.php

2. public/index.php
   ├── require vendor/autoload.php         (Composer PSR-4)
   ├── require app/Helpers/helpers.php     (funciones globales)
   ├── Session::init()                     (inicia sesión PHP con parámetros seguros)
   ├── Dotenv::createImmutable()->safeLoad() (carga .env)
   ├── Bootstrap::init()                   (zona horaria + Carbon)
   ├── mb_internal_encoding('UTF-8')
   ├── ErrorHandler::register()            (Whoops dev / Monolog prod)
   ├── Database::initialize()              (Eloquent Capsule + setAsGlobal)
   ├── new Container()                     (DI container propio)
   ├── [registro de bindings/singletons]   (UsuarioRepository, EstacionRepository,
   │                                        TokenService, AuthenticationService,
   │                                        LoginController)
   ├── Route::setContainer($container)
   ├── Kernel::setContainer($container)
   └── new Router($container)->dispatch()

3. Router::dispatch()
   ├── Lee $_SERVER['REQUEST_METHOD'] y $_SERVER['REQUEST_URI']
   ├── Llama a simpleDispatcher(routes/web.php)
   └── Hace dispatch del método+URI

4. routes/web.php
   └── Retorna función con RouteCollector
       └── Rutas definidas como:
           $r->addRoute('GET', '/ruta', Route::auth(['Controller', 'method']))
           $r->addRoute('GET', '/ruta', Route::guest(['Controller', 'method']))

5. Route::auth() / Route::guest()
   └── Retorna Closure que:
       a. Llama Kernel::handle(['auth', 'csrf']) / Kernel::handle(['guest'])
       b. Resuelve controlador desde $container->get($controllerClass)
       c. Llama $instance->method(...$params)

6. Kernel::handle($middlewares)
   └── Para cada middleware en la lista:
       a. Busca la clase en self::$routeMiddleware[]
       b. Resuelve instancia via $container->get($class)
       c. Llama $instance->handle()

7. AuthMiddleware::handle()
   ├── Valida access token JWT (cookie 'token')
   ├── Si expirado: intenta refresh desde cookie 'refresh_token'
   ├── Si no hay sesión válida: redirect a /login (GET) o 401 JSON (POST)
   └── Actualiza LAST_ACTIVITY en sesión

8. CsrfMiddleware::handle()
   └── TODO: confirmar comportamiento exacto

9. Controlador::method()
   ├── Extiende BaseController (que lee Session::get('usuario'))
   ├── Llama a Service(s) para lógica de negocio
   └── Llama View::render('vista', $data) o JsonResponse::success(...)

10. View::render($view, $data, $layout)
    ├── Llama self::globals() → obtiene user, estaciones, pendientes
    ├── Hace array_merge($globals, $data) y extract()
    ├── ob_start()
    ├── Si $moduleKey: llama ModuleStationService::render()
    └── require layout (main.php, sasisopa.php, sgm.php, etc.)
        ├── El layout incluye el $content (output bufferado de la vista)
        └── Genera HTML final

11. Respuesta HTTP enviada al cliente
```

---

## Flujo confirmado (petición AJAX/JSON POST)

```
1–6. Igual que arriba (misma entrada y routing)

7. AuthMiddleware::handle()
    └── Si token inválido: responde 401 JSON (no redirect)

8. Controlador::method()
    ├── Lee datos de $_POST o php://input (via Request)
    ├── Valida datos
    ├── Llama Service
    └── Llama JsonResponse::success() / JsonResponse::error()

9. JsonResponse::send()
    ├── http_response_code($status)
    ├── header('Content-Type: application/json; charset=UTF-8')
    └── echo json_encode($data, ...)
        exit;
```

---

## Headers de seguridad

**Confirmado:** Se establecen en `public/index.php` antes de cualquier procesamiento:

- `X-Frame-Options: DENY`
- `X-Content-Type-Options: nosniff`
- `X-XSS-Protection: 1; mode=block`
- `Content-Security-Policy: ...`
- `Strict-Transport-Security` (solo si HTTPS activo)
- `Referrer-Policy: strict-origin-when-cross-origin`
- `Permissions-Policy: geolocation=(), microphone=(), camera=()`

---

## Archivos involucrados

- `public/index.php` — Bootstrap y dispatch
- `public/.htaccess` — Rewrite rules
- `app/Core/Router.php` — FastRoute dispatcher
- `app/Core/Route.php` — Helper de rutas con middleware
- `app/Core/Kernel.php` — Ejecutor de middlewares
- `app/Core/Container.php` — DI container
- `app/Core/Session.php` — Gestión de sesión
- `app/Core/Auth.php` — Helper de autenticación
- `app/Core/View.php` — Renderizador de vistas
- `app/Core/JsonResponse.php` — Respuestas JSON estandarizadas
- `routes/web.php` — Todas las rutas (1506 líneas)
- `app/Middleware/AuthMiddleware.php` — Validación de tokens
- `app/Middleware/CsrfMiddleware.php` — Validación CSRF
- `app/Controllers/BaseController.php` — Controlador base

---

## Preguntas pendientes

- TODO: Confirmar el comportamiento exacto de `CsrfMiddleware` (¿cuándo aplica, qué valida exactamente?).
- TODO: ¿Existe algún mecanismo de caché de vistas?
- TODO: ¿El `.htaccess` de `public/` tiene reglas adicionales relevantes?
- TODO: ¿Cómo maneja el sistema las peticiones a archivos estáticos (assets)?
