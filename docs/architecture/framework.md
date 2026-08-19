# El Framework Propio de Portal3

## Propósito

Documentar el framework propio ubicado en `app/Core/`: sus componentes, responsabilidades y cómo interactúan.

## Alcance

Las 22 clases del namespace `App\Core`.

---

## Descripción general

### **Confirmado**

Portal3 no usa un framework PHP estándar (Laravel, Symfony, Slim, etc.). En su lugar, tiene un framework propio construido en `app/Core/` que orquesta paquetes Composer estándar. 

El framework provee:
- Bootstrap de aplicación
- Routing HTTP (vía FastRoute)
- Contenedor de inyección de dependencias
- Sistema de middlewares
- Gestión de sesiones
- Autenticación dual (JWT + Sesión)
- Protección CSRF
- Rate limiting
- Renderizado de vistas
- Respuestas JSON estandarizadas
- Logging

---

## Inventario de clases Core

| Clase | Archivo | Responsabilidad |
|---|---|---|
| `Bootstrap` | `Bootstrap.php` | Inicialización de zona horaria y Carbon |
| `App` | `App.php` | **Obsoleto/duplicado** — DI setup también en index.php |
| `Auth` | `Auth.php` | Helper estático de autenticación |
| `Breadcrumb` | `Breadcrumb.php` | TODO: analizar uso |
| `Container` | `Container.php` | DI container con autowiring por Reflection |
| `Cookie` | `Cookie.php` | Gestión de cookies |
| `CsrfToken` | `CsrfToken.php` | Generación y validación de tokens CSRF |
| `Database` | `Database.php` | Inicialización de Eloquent Capsule |
| `ErrorHandler` | `ErrorHandler.php` | Manejo global de errores (Whoops/Monolog) |
| `JWTService` | `JWTService.php` | Creación y validación de JWT |
| `JsonResponse` | `JsonResponse.php` | Respuestas JSON estandarizadas |
| `Kernel` | `Kernel.php` | Registro y ejecución de middlewares |
| `Logger` | `Logger.php` | Singleton wrapper de Monolog |
| `PasswordService` | `PasswordService.php` | Hashing y verificación de contraseñas |
| `PasswordValidator` | `PasswordValidator.php` | Reglas de complejidad de contraseñas |
| `RateLimiter` | `RateLimiter.php` | Rate limiting basado en sesión por IP |
| `Request` | `Request.php` | Abstracción de petición HTTP |
| `Route` | `Route.php` | Helper estático de rutas con middleware |
| `Router` | `Router.php` | Dispatcher FastRoute |
| `Session` | `Session.php` | Wrapper estático de sesión PHP |
| `TwoFactorService` | `TwoFactorService.php` | Autenticación de dos factores (TOTP) |
| `View` | `View.php` | Renderizador de vistas PHP con layouts |

---

## Detalles de clases clave

### Container

**Confirmado:** Contenedor DI propio con:
- `bind($abstract, $factory)` — binding simple
- `singleton($abstract, $factory)` — singleton que se cachea
- `get($abstract)` — resuelve con autowiring por Reflection
- `make($abstract)` — alias de `get()`
- `has($abstract)` — verifica si puede resolver
- `forget($abstract)` — elimina instancia cacheada

Implementa autowiring: si una clase no está registrada pero existe, intenta instanciarla resolviendo sus dependencias por tipo.

### Router

**Confirmado:** Wrapper sobre `nikic/fast-route`:
- Lee rutas de `routes/web.php`
- Despacha `GET`, `POST`, etc.
- Soporta handlers como `Closure` o `[ControllerClass, method]`
- Errores: 404 y 405 con vistas propias
- Construye controladores directamente con `new $controllerClass()` (sin DI, ver nota)

> **NOTA IMPORTANTE:** El `Router::callController()` instancia controladores con `new $controllerClass()` sin usar el Container, mientras que `Route::middleware()` sí usa el Container. Esto es una inconsistencia: las rutas sin middleware no tienen DI.

### Kernel

**Confirmado:** Registro de middlewares disponibles:
```php
'auth'  => AuthMiddleware::class
'guest' => GuestMiddleware::class
'csrf'  => CsrfMiddleware::class
```

### Session

**Confirmado:** Wrapper estático sobre `$_SESSION`:
- `init()` — inicia sesión con parámetros seguros (httponly, samesite Lax)
- `set/get/has/forget/pull/all/destroy/regenerate`
- `isLogged()` — verifica si hay clave `usuario` en sesión
- Lifetime: 90000 segundos (~25 horas)
- Verifica expiración en cada petición (`check()` → redirect /login)

### JWTService

**Confirmado:** Manejo de tokens JWT:
- Access token: 1 hora (HS256)
- Refresh token: 7 días (HS256)
- Claims: `iss`, `iat`, `sub`, `nombre`, `jti`, `exp`, `type`
- Guarda tokens en cookies HttpOnly
- `JWT_SECRET` desde `.env`

### Auth

**Confirmado:** Helper estático para acceder al usuario autenticado:
- `Auth::id()` — desde sesión
- `Auth::user()` — busca `Usuario::find($id)` (lazy load, cacheado en memoria)
- `Auth::check()` / `Auth::guest()`
- `Auth::session()` / `Auth::name()`

### View

**Confirmado:** Renderizador de vistas PHP:
- Variables globales inyectadas: `$user`, `$filtro_usuario`, `$estaciones`, `$pendientes`
- Layouts múltiples: `main`, `auth`, `configuracion`, `departamento-operativo`, `sasisopa`, `sgm`
- Sistema de módulo-estación: `ModuleStationService` para selector de estación
- Buffer de output para incluir vistas dentro de layouts

---

## Archivos relevantes

- `app/Core/` — Todas las clases del framework
- `public/index.php` — Bootstrap y configuración del DI container
- `routes/web.php` — Definición de rutas

---

## Preguntas pendientes

- TODO: ¿Qué hace exactamente `Breadcrumb.php`?
- TODO: ¿`App.php` está actualmente en uso o es código muerto?
- TODO: ¿`TwoFactorService.php` es un stub o tiene implementación completa?
- TODO: ¿Cómo se manejan las transacciones de base de datos?
- TODO: ¿El `php-di/php-di` registrado en composer.json se usa realmente o fue reemplazado por el Container propio?
