# Arquitectura de Seguridad

## Propósito

Documentar los mecanismos de seguridad implementados en Portal3.

## Alcance

Autenticación, autorización, CSRF, headers HTTP, rate limiting, validación y sanitización.

---

## Autenticación

### **Confirmado**

Portal3 usa un sistema **dual** de autenticación:

1. **Sesión PHP** — almacena datos del usuario en `$_SESSION['usuario']`
2. **JWT en cookies HttpOnly** — access token (1h) + refresh token (7d)

El `AuthMiddleware` valida el access token JWT en cada petición protegida. Si el access token expiró, intenta renovarlo usando el refresh token. Si ambos fallan, destruye la sesión y redirige a `/login`.

### Flujo de login (confirmado)

```
LoginController → AuthenticationService::login()
  → UsuarioRepository::findActiveByUsername()
  → PasswordService::verify()
  → TwoFactorService::validate()     (si 2FA habilitado)
  → SessionService::start()          (crea sesión con datos del usuario)
  → ModuloService::guardarEnSesion() (permisos del usuario)
  → TokenService::issue()            (emite JWT + cookies)
```

### JWT (confirmado)

| Parámetro | Valor |
|---|---|
| Algoritmo | HS256 |
| Access token TTL | 3600 s (1 hora) |
| Refresh token TTL | 604800 s (7 días) |
| Claims | `iss`, `iat`, `sub`, `nombre`, `jti`, `exp`, `type` |
| Almacenamiento | Cookies HttpOnly |
| Secreto | `JWT_SECRET` en `.env` |

---

## Autenticación de Dos Factores (2FA)

### **Confirmado**

El modelo `Usuario` tiene soporte para 2FA TOTP:
- Campo `two_factor_enabled` (boolean)
- Campo `two_factor_secret` (secreto TOTP, oculto en serialización)
- Campo `two_factor_backup_codes` (array JSON)
- Clase `TwoFactorService` en `app/Core/`
- Generación de QR codes para configuración (via `bacon/bacon-qr-code`)

---

## Protección CSRF

### **Confirmado**

- Clase `app/Core/CsrfToken.php`
- Token almacenado en sesión (`_csrf_token`)
- Validez: 1 hora (`_csrf_token_time`)
- Comparación timing-safe (`hash_equals`)
- Middleware `CsrfMiddleware` registrado en Kernel

- TODO: Verificar en qué rutas se aplica el middleware CSRF exactamente
- TODO: Verificar cómo se envía el token desde el frontend (header, campo oculto, etc.)

---

## Headers HTTP de seguridad

### **Confirmado** — Establecidos en `public/index.php`

| Header | Valor |
|---|---|
| `X-Frame-Options` | `DENY` |
| `X-Content-Type-Options` | `nosniff` |
| `X-XSS-Protection` | `1; mode=block` |
| `Content-Security-Policy` | Permitivo (unsafe-inline, unsafe-eval) |
| `Strict-Transport-Security` | Solo si HTTPS activo |
| `Referrer-Policy` | `strict-origin-when-cross-origin` |
| `Permissions-Policy` | `geolocation=(), microphone=(), camera=()` |

> **Nota:** El CSP está configurado con `unsafe-inline` y `unsafe-eval`, lo que reduce su efectividad contra XSS.

---

## Rate Limiting

### **Confirmado** — `app/Core/RateLimiter.php`

| Tipo | Límite | Ventana |
|---|---|---|
| `login` | 5 intentos | 5 minutos |
| `api` | 60 requests | 1 minuto |
| `default` | 100 requests | 1 minuto |

**Inferido:** El rate limiting está basado en sesión PHP (no en caché distribuida), lo que puede tener limitaciones en entornos con múltiples servidores o balanceo de carga.

---

## Protección de Mass Assignment

### **Confirmado** — `app/Models/Usuario.php`

El modelo `Usuario` tiene protección explícita:
- `$fillable` — campos permitidos para asignación masiva
- `$guarded` — campos protegidos (`id`, `id_gas`, `estatus`, `two_factor_enabled`, etc.)
- `$hidden` — campos excluidos de serialización JSON (`password`, `two_factor_secret`, etc.)
- Boot method con validación adicional al crear/actualizar

---

## Sesiones

### **Confirmado**

| Parámetro | Valor |
|---|---|
| Lifetime | 90000 s (~25 horas) |
| `httponly` | `true` |
| `samesite` | `Lax` |
| `secure` | Solo si HTTPS (via `Request::isSecure()`) |

---

## Sanitización y validación de entrada

### **Confirmado** — `app/Helpers/helpers.php`

Funciones globales disponibles:
- `sanitize_input($value, $type)` — tipos: `string`, `int`, `email`, `url`, `alphanumeric`, `uuid`
- `validate_input($data, $rules)` — reglas básicas: `required`, `email`, `numeric`, `min`, `max`

**Inferido:** El uso de estas funciones puede no ser consistente en todos los controladores. Requiere revisión.

---

## Contraseñas

### **Confirmado** (parcialmente)

- `app/Core/PasswordService.php` — hashing y verificación
- `app/Core/PasswordValidator.php` — reglas de complejidad (7093 bytes, archivo considerable)
- El sistema detecta contraseñas "legacy" (sin hash) y las registra como warning

---

## Archivos relevantes

- `public/index.php` — Headers HTTP de seguridad
- `app/Core/Auth.php` — Helper de autenticación
- `app/Core/JWTService.php` — JWT
- `app/Core/CsrfToken.php` — CSRF
- `app/Core/RateLimiter.php` — Rate limiting
- `app/Core/Session.php` — Sesiones
- `app/Core/PasswordService.php` — Contraseñas
- `app/Middleware/AuthMiddleware.php` — Middleware de autenticación
- `app/Models/Usuario.php` — Protección de mass assignment

---

## Preguntas pendientes

- TODO: ¿En qué rutas específicas se aplica el middleware CSRF?
- TODO: ¿Cómo se gestiona la revocación de JWT (blacklist)?
- TODO: ¿Se valida el input en todos los controladores de forma consistente?
- TODO: ¿Existe protección contra SQL injection más allá de Eloquent?
- TODO: ¿Cómo se manejan los uploads de archivos (validación, tipo, tamaño)?
- TODO: ¿Existe logging de intentos de acceso no autorizados?
