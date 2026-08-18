# Inyección de Dependencias

## Propósito

Documentar el sistema de inyección de dependencias (DI) de Portal3.

## Alcance

`app/Core/Container.php`, configuración en `public/index.php`.

---

## Container propio

### **Confirmado**

Portal3 implementa su propio DI Container en `app/Core/Container.php`, con soporte para:

- `bind($abstract, $factory)` — binding simple (nueva instancia cada vez)
- `singleton($abstract, $factory)` — instancia única cacheada
- `get($abstract)` — resolución con autowiring por Reflection
- `make($abstract)` — alias de `get()`
- `has($abstract)` — verifica si puede resolver la clase
- `forget($abstract)` — elimina instancia del cache

### Autowiring (confirmado)

Si una clase no está registrada pero existe, el Container intenta instanciarla automáticamente resolviendo sus dependencias por tipo usando `ReflectionClass`.

---

## Bindings registrados (confirmado en `public/index.php`)

| Clase | Tipo | Dependencias |
|---|---|---|
| `UsuarioRepository` | Singleton | Ninguna |
| `EstacionRepository` | Singleton | Ninguna |
| `TokenService` | Singleton | `UsuarioRepository` |
| `AuthenticationService` | Singleton | `UsuarioRepository`, `EstacionRepository`, `TokenService` |
| `LoginController` | Singleton | `AuthenticationService`, `TokenService` |

---

## Observaciones

### **Inferido (posible deuda técnica)**

- Solo una pequeña parte de la aplicación usa el Container DI (5 bindings)
- La mayoría de los 128 controladores son instanciados con `new $controllerClass()` directamente en `Router::callController()`
- La mayoría de los 55 servicios son instanciados directamente en los controladores

### **Observación sobre php-di/php-di**

`php-di/php-di` está declarado en `composer.json` pero el sistema usa el Container propio (`App\Core\Container`). Se desconoce si `php-di` se usa en alguna parte del código.

---

## Archivos relevantes

- `app/Core/Container.php`
- `public/index.php` (bindings)
- `app/Core/App.php` (posiblemente obsoleto — mismo contenido que index.php)

---

## Preguntas pendientes

- TODO: ¿Se usa `php-di/php-di` en algún lugar del código?
- TODO: ¿Por qué existe `app/Core/App.php` con el mismo contenido que el bootstrap de index.php?
- TODO: ¿Está planificado expandir el uso del Container a más controladores/servicios?
