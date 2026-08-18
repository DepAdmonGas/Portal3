# Autenticación

## Propósito

Documentar el sistema de autenticación de Portal3: mecanismos, flujo y componentes.

## Alcance

Login, logout, sesiones, JWT, refresh tokens, 2FA.

---

## Mecanismo de autenticación

### **Confirmado**

Portal3 usa un sistema **dual** de autenticación:

1. **Sesión PHP** (`$_SESSION['usuario']`) — datos del usuario para la aplicación
2. **JWT en cookies HttpOnly** — access token + refresh token para validación por petición

Ambos mecanismos deben estar válidos para que una petición sea autenticada.

---

## Flujo de login (confirmado)

```
POST /login
  → LoginController::login()
  → AuthenticationService::login($usuario, $password, $twoFactorCode)
      → UsuarioRepository::findActiveByUsername($usuario)
      → PasswordService::verify($password, $user->password)
      → TwoFactorService::validate($user, $twoFactorCode)  [si 2FA activo]
      → SessionService::start([datos del usuario])
      → ModuloService::guardarEnSesion($user->id)
      → ModuloDptoOperativoService::guardarEnSesion($user->id)
      → TokenService::issue($user)  → emite JWT + cookies
  → Retorna LoginResult (DTO)
```

---

## Flujo de validación por petición (confirmado)

```
AuthMiddleware::handle()
  → TokenService::validateAccessToken()   (lee cookie 'token')
      → Si válido: continúa
      → Si inválido/expirado:
          → TokenService::refresh()       (lee cookie 'refresh_token')
          → Si refresh válido: emite nuevo access token
          → Si ambos inválidos: redirectLogin()
  → Session::has('usuario')              (verifica sesión activa)
  → Actualiza LAST_ACTIVITY en sesión
  → Si access token expira en <10 min: renueva automáticamente
```

---

## JWT

### **Confirmado** — `app/Core/JWTService.php`

| Parámetro | Valor |
|---|---|
| Algoritmo | HS256 |
| Access token TTL | 3600 segundos (1 hora) |
| Refresh token TTL | 604800 segundos (7 días) |
| Claim `type` | `'access'` o `'refresh'` |
| Claim `jti` | UUID aleatorio (16 bytes hex) para identificación única |
| Secreto | `JWT_SECRET` (variable de entorno) |

---

## Autenticación de Dos Factores (2FA)

### **Confirmado** — `app/Models/Usuario.php`, `app/Core/TwoFactorService.php`

- Basada en TOTP (Time-based One-Time Password)
- Secreto almacenado en `two_factor_secret` del usuario
- Códigos de respaldo en `two_factor_backup_codes` (array JSON)
- QR code generado via `bacon/bacon-qr-code`
- Estados: `REQUIRED`, `VALID`, `INVALID`

---

## Datos de sesión del usuario (confirmados)

```php
$_SESSION['usuario'] = [
    'id'              => $user->id,
    'usuario'         => $user->usuario,
    'nombre'          => $user->nombre,
    'id_estacion'     => $user->id_gas,
    'nombre_estacion' => $estacion?->nombre,
    'multiestacion'   => bool,
]
```

---

## Flujo de logout (confirmado)

```
POST /logout
  → AuthController::logout()
  → AuthenticationService::logout()
      → TokenService::forgetCookies()   (elimina cookies JWT)
      → SessionService::logout()         (destruye sesión)
```

---

## Archivos relevantes

- `app/Controllers/LoginController.php`
- `app/Controllers/AuthController.php`
- `app/Services/AuthenticationService.php`
- `app/Services/TokenService.php`
- `app/Services/SessionService.php`
- `app/Core/JWTService.php`
- `app/Core/Auth.php`
- `app/Core/Session.php`
- `app/Core/PasswordService.php`
- `app/Core/TwoFactorService.php`
- `app/Middleware/AuthMiddleware.php`
- `app/DTO/LoginResult.php`
- `app/Repositories/UsuarioRepository.php`

---

## Preguntas pendientes

- TODO: ¿Existe blacklist de JWT revocados?
- TODO: ¿Cómo se manejan múltiples sesiones simultáneas del mismo usuario?
- TODO: ¿Qué hace exactamente `ModuloDptoOperativoService::guardarEnSesion()`?
- TODO: ¿Qué datos almacena `ModuloService::guardarEnSesion()` en sesión?
- TODO: ¿El 2FA es obligatorio o opcional por usuario?
