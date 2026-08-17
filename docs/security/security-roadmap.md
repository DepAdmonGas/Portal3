# Roadmap de Remediación de Seguridad

Este documento detalla la estrategia incremental para mitigar las vulnerabilidades identificadas sin generar conflictos con el desarrollador que se encuentra trabajando en la aplicación, garantizando estabilidad funcional (0 downtime).

---

## 🛑 P0 — Crítico (Explotación Inmediata Posible)
*Requieren ser arregladas inmediatamente. El atacante podría comprometer el servidor o tomar control de cuentas libremente.*

### Tarea P0.1: Mitigar RCE por Subida de Archivos
- **Objetivo:** Impedir la ejecución de archivos PHP en las carpetas de subida (`/public/uploads/`).
- **Archivos Afectados:**
  - `public/uploads/.htaccess` (Nuevo)
- **Estrategia Incremental:** 
  Como el sistema entero usa uploads en múltiples controladores sin validación nativa, refactorizar todos los controladores es invasivo (riesgo alto de merge conflicts). La solución más eficaz e indolora es **crear un archivo `.htaccess`** en la carpeta principal de subidas.
- **Riesgo:** BAJO (Afectará únicamente intentos de ejecutar archivos dentro del directorio estático).
- **Dependencias:** Ninguna (Se puede aplicar directamente en main/master).
- **Pruebas:** Intentar subir un script PHP simple y navegar a él (debería mostrar código como texto o dar un error del servidor en lugar de ejecutar).

### Tarea P0.2: Bloquear Bypass de Middleware CSRF
- **Objetivo:** Impedir que los requests que carecen de token salten la validación del middleware.
- **Archivos Afectados:** 
  - `app/Middleware/CsrfMiddleware.php`
- **Estrategia Incremental:** 
  Eliminar el bloque `if (empty($token))` que retorna `true`. En su lugar, redirigir al flujo de error de CSRF (HTTP 419).
- **Riesgo:** MEDIO (Podría quebrar requests desde AJAX/Axios que no envían cabeceras correctas. Asegurarse que el layout principal o `main.js` siempre inyecte el token).
- **Dependencias:** Verificación del script de frontend global para Axios/Fetch.
- **Pruebas:** Mandar POST a `/recursos-humanos/control-documentos-personal/add` desde cURL o Postman sin cabecera `X-CSRF-TOKEN` y sin `_csrf_token`. Debe retornar error.

### Tarea P0.3: Rate Limiting Persistente
- **Objetivo:** Hacer que la protección contra fuerza bruta ignore el ID de sesión enviado por el cliente.
- **Archivos Afectados:** 
  - `app/Core/RateLimiter.php`
  - Archivos de caché/base de datos (Implementar un cache driver o DB).
- **Estrategia Incremental:** 
  Modificar temporalmente `RateLimiter` para que en lugar de usar `Session::get()` use logs en disco en `/storage/cache/rate_limit` (ya que `mkdir_safe` está en helpers) o una tabla sencilla.
- **Riesgo:** MEDIO.
- **Dependencias:** Ninguna, centralizado en `RateLimiter.php`.
- **Pruebas:** Hacer 10 peticiones fallidas al login, borrando la cookie en cada intento. El sistema debe bloquear al intento 6.

---

## 🟠 P1 — Alto (Impacto severo pero explotación condicionada)

### Tarea P1.1: Opportunistic Password Rehashing
- **Objetivo:** Migrar todas las contraseñas en texto plano (`Legacy`) al estándar `bcrypt/Argon2` una vez que el usuario se loguea exitosamente.
- **Archivos Afectados:** 
  - `app/Services/AuthenticationService.php`
- **Estrategia Incremental:** 
  Al validar `PasswordService::isLegacy($user->password)`, actualizar silenciosamente la base de datos usando `PasswordService::hash($password)`.
- **Riesgo:** BAJO.
- **Dependencias:** Ninguna.
- **Pruebas:** Ingresar con usuario de password legado, revisar BD, debe haberse convertido a hash. Ingresar de nuevo y validar que funcione.

### Tarea P1.2: Vistas XSS-Safe (Defensa en Profundidad)
- **Objetivo:** Prevenir Stored/Reflected XSS mediante codificación HTML en salidas.
- **Archivos Afectados:** 
  - `app/Helpers/helpers.php` (para agregar método global `e()`)
  - `app/Views/**/*.php` (Decenas de archivos).
- **Estrategia Incremental:** 
  **Trabajo en rama separada `feat/security-xss`**. Añadir `function e($str) { return htmlspecialchars(...); }` a `helpers.php`. Ejecutar una refactorización con regex de todo `<?= $var ?>` a `<?= e($var) ?>`. 
- **Riesgo:** ALTO (Posibles conflictos (merge conflicts) masivos si otro Dev cambia vistas simultáneamente). **Recomendación:** Retrasar la refactorización masiva hasta sincronizarse con el otro desarrollador, pero crear la función `e()` de inmediato y requerirla para **nuevas vistas**.

### Tarea P1.3: Detección de Proxy Inverso Segura (Cookies Secure)
- **Objetivo:** Marcar JWT y Sesión como Secure cuando la app está bajo SSL final.
- **Archivos Afectados:** 
  - `app/Core/Request.php`
- **Estrategia Incremental:** 
  Añadir chequeo de `HTTP_X_FORWARDED_PROTO` == 'https' a `Request::isSecure()`, asegurándose de validar ips de confianza de ser posible.
- **Riesgo:** BAJO.

---

## 🟡 P2 — Medio (Mejoras estructurales)

### Tarea P2.1: Implementación de Whitelists para Archivos
- **Objetivo:** Validar MIME types y extensiones internamente, no depender solo del `.htaccess`.
- **Estrategia Incremental:** Crear una clase `FileValidator` y reemplazar progresivamente los `move_uploaded_file()` de los distintos controladores.
- **Riesgo:** MEDIO (Altos conflictos con el otro Dev).

### Tarea P2.2: Saneamiento Global de Inputs de Eloquent
- **Objetivo:** Remover uso de `selectRaw` inseguro y pasar todo a DB Bindings estrictos.

---

## 🟢 P3 — Mejora Preventiva
- **Mejora:** Mover los tokens de sesión de `Lax` a `Strict` si la arquitectura lo permite.
- **Mejora:** Eliminar librerías deprecadas del `composer.json` si las hubiera.