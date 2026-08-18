# Auditoría de Seguridad - Framework Portal3

## Resumen Ejecutivo
Se ha realizado una auditoría estática de seguridad sobre el framework y la aplicación Portal3, identificando fallas estructurales en el manejo de peticiones, autenticación y gestión de archivos. Se identificaron **3 vulnerabilidades CRÍTICAS** y **3 vulnerabilidades ALTAS** que requieren atención inmediata, ya que permiten ejecución remota de código (RCE), ataques de fuerza bruta y manipulación de peticiones (CSRF).

---

## Hallazgos

### 1. Bypass Absoluto de CSRF
- **[SEVERIDAD]**: CRITICAL
- **[ESTADO]**: CONFIRMED (PENDING MITIGATION)
- **[UBICACIÓN]**: `app/Middleware/CsrfMiddleware.php` - Método `handle()`
- **[PROBLEMA]**: La lógica del middleware de CSRF permite la ejecución del request si el token `_csrf_token` viene completamente vacío. Genera un nuevo token para el futuro pero retorna `true` para la petición actual.
- **[IMPACTO]**: Un atacante puede realizar peticiones POST/PUT/DELETE arbitrarias en nombre del usuario (CSRF) simplemente omitiendo enviar el campo o header del token. Esto derrota completamente la protección.
- **[EVIDENCIA]**: 
  ```php
  if (empty($token)) {
      CsrfToken::token();
      return true; // Vulnerabilidad crítica
  }
  ```
- **[RECOMENDACIÓN]**: Eliminar la condición que retorna `true` cuando `$token` es `empty()`. Si no hay token en una ruta protegida, la petición debe ser rechazada obligatoriamente con HTTP 419 o 403.
- **[RIESGO DE REGRESIÓN]**: MEDIUM (Front-ends mal configurados que dependían de este "primer pase gratis" fallarán).
- **[DEPENDENCIAS]**: Formularios y scripts Axios/Fetch.

### 2. Ejecución Remota de Código (RCE) por Subida de Archivos
- **[SEVERIDAD]**: CRITICAL
- **[ESTADO]**: CONFIRMED (PENDING MITIGATION)
- **[UBICACIÓN]**: Múltiples controladores (Ej. `ControlDocumentosPersonalController.php`, `ReporteDiarioController.php`).
- **[PROBLEMA]**: Se utiliza `move_uploaded_file()` extrayendo la extensión directamente del nombre original (`pathinfo`) sin aplicar un filtro de lista blanca (whitelist). Además, los archivos se guardan bajo `public/uploads/` (directorio accesible desde la web) sin un archivo `.htaccess` que impida la ejecución del motor PHP.
- **[IMPACTO]**: Un usuario autenticado (o inautenticado en endpoints públicos) puede subir un archivo llamado `shell.php`. Al acceder a `/uploads/archivos/documentos-personal/incidencias/shell.php`, obtendrá control total (RCE) sobre el servidor.
- **[EVIDENCIA]**:
  ```php
  $ext = strtolower(pathinfo($_FILES['documento']['name'], PATHINFO_EXTENSION));
  $nombre = 'incidencia_' . $idAsistencia . '_' . time() . '.' . $ext;
  move_uploaded_file($_FILES['documento']['tmp_name'], $carpeta . $nombre);
  ```
- **[RECOMENDACIÓN]**: 
  1. Validar explícitamente `$ext` contra una whitelist (`['pdf', 'jpg', 'png']`).
  2. Impedir la subida de extensiones peligrosas.
  3. Crear un archivo `public/uploads/.htaccess` con `php_flag engine off` y `RemoveHandler .php`.
- **[RIESGO DE REGRESIÓN]**: LOW (Si se implementa bien la whitelist, la app seguirá funcionando normal).
- **[DEPENDENCIAS]**: Todos los endpoints de carga de archivos (Gafetes, Auditorias, Personal).

### 3. Bypass Total del Rate Limiting (Fuerza Bruta)
- **[SEVERIDAD]**: CRITICAL
- **[ESTADO]**: CONFIRMED (PENDING MITIGATION)
- **[UBICACIÓN]**: `app/Core/RateLimiter.php`
- **[PROBLEMA]**: El contador de rate limit utiliza variables de sesión `Session::get($key)` apoyadas indirectamente en la sesión nativa de PHP.
- **[IMPACTO]**: Un atacante (por ejemplo, atacando `/login`) puede ignorar el envío de la cookie `PHPSESSID`. Al hacerlo, PHP crea una nueva sesión vacía en cada petición, y el contador de límite siempre será `0`. Esto permite fuerza bruta ilimitada, ignorando por completo la restricción de 5 intentos. Además, la IP extraída de `X-Forwarded-For` no está saneada, permitiendo IP spoofing.
- **[EVIDENCIA]**:
  ```php
  $attempts = Session::get($key, 0); // Atado a la cookie del usuario, no al servidor
  ```
- **[RECOMENDACIÓN]**: Mover el almacenamiento del Rate Limiter a una capa persistente a nivel de servidor (Redis, Memcached, SQLite, o base de datos) utilizando directamente la IP o un hash único del cliente.
- **[RIESGO DE REGRESIÓN]**: LOW.
- **[DEPENDENCIAS]**: `LoginController` y cualquier API con limitación.

### 4. Stored XSS y Reflected XSS en Vistas (Generalizado)
- **[SEVERIDAD]**: HIGH
- **[ESTADO]**: CONFIRMED (PENDING MITIGATION)
- **[UBICACIÓN]**: Motor de Vistas (`app/Core/View.php`) y todo `app/Views/`
- **[PROBLEMA]**: El motor `View::render` usa `extract()` y expone las variables. En las vistas se imprime directamente usando tags cortos `<?= $variable ?>` sin utilizar `htmlspecialchars()` o algún helper como `e()`.
- **[IMPACTO]**: Si los datos inyectados a la base de datos (por ejemplo, nombres de incidentes, títulos de capacitaciones) contienen etiquetas HTML/JS maliciosas (`<script>`), estas se ejecutarán en el navegador de los administradores que vean la información, llevando a robo de sesión o CSRF forzado.
- **[EVIDENCIA]**: `app/Views/asistencia/asistencia.php:6: lugar='<?= htmlspecialchars($asistencia->lugar) ?>';` pero en muchísimas otras partes falta: `<?= $asistencia->num_tema ?> - <?= $asistencia->titulo ?>`
- **[RECOMENDACIÓN]**: Usar sistemáticamente `htmlspecialchars($var, ENT_QUOTES, 'UTF-8')`. Crear una función `e($var)` en `helpers.php` y hacer refactor progresivo en las vistas.
- **[RIESGO DE REGRESIÓN]**: MEDIUM (Si alguna vista dependía de renderizar HTML crudo intencionadamente, requeriría excepciones controladas).
- **[DEPENDENCIAS]**: Todas las vistas de la aplicación.

### 5. Retención Indefinida de Contraseñas en Texto Plano
- **[SEVERIDAD]**: HIGH
- **[ESTADO]**: CONFIRMED (PENDING MITIGATION)
- **[UBICACIÓN]**: `app/Core/PasswordService.php` y `app/Services/AuthenticationService.php`
- **[PROBLEMA]**: El sistema detecta contraseñas en texto plano y usa `hash_equals` para validarlas (Legacy). Muestra un warning en logs (`Logger::warning`), pero **nunca** obliga al re-hash de la contraseña para actualizarla en la base de datos a `bcrypt/argon2`.
- **[IMPACTO]**: Las contraseñas en texto plano se mantienen indefinidamente en la base de datos, exponiendo la seguridad en caso de volcado de la BD.
- **[EVIDENCIA]**: 
  ```php
  if (PasswordService::isLegacy($user->password)) {
      Logger::warning('Usuario con contraseña no hasheada detectado'); 
      // Falta: rehashing o actualización en BD.
  }
  ```
- **[RECOMENDACIÓN]**: Si el login Legacy es exitoso, aprovechar para re-hashear la contraseña con `PasswordService::hash()` y actualizar el campo en la tabla de usuarios de forma transparente al usuario.
- **[RIESGO DE REGRESIÓN]**: LOW.
- **[DEPENDENCIAS]**: Módulo de Autenticación.

### 6. Omisión de la Bandera Secure en Cookies detrás de Proxies
- **[SEVERIDAD]**: HIGH
- **[ESTADO]**: CONFIRMED (PENDING MITIGATION)
- **[UBICACIÓN]**: `app/Core/Cookie.php` y `app/Core/Request.php`
- **[PROBLEMA]**: `Cookie::isSecure()` usa `Request::isSecure()`. Este último solo verifica `$_SERVER['HTTPS']`. Si la aplicación se despliega detrás de un Load Balancer (Ej: AWS ALB, Cloudflare) que termina el SSL, `HTTPS` estará "off", y el JWT / Session ID viajará sin la bandera `Secure`, siendo susceptible a intercepción en redes locales o proxies intermedios si el tráfico fluye en HTTP.
- **[IMPACTO]**: Robo de cookies de autenticación JWT y Session.
- **[EVIDENCIA]**:
  ```php
  public static function isSecure(): bool {
      return !empty(self::server()['HTTPS']) && self::server()['HTTPS'] !== 'off';
  }
  ```
- **[RECOMENDACIÓN]**: Configurar el framework para confiar en cabeceras de proxy (`HTTP_X_FORWARDED_PROTO`) bajo ciertas validaciones, para detectar SSL de proxy inverso correctamente.
- **[RIESGO DE REGRESIÓN]**: LOW.
- **[DEPENDENCIAS]**: Generación de Sesiones y JWT.

---

### Notas Adicionales / Menciones (INFO / LOW)
- **Inyección SQL**: El uso extensivo de `selectRaw` en servicios (ej: `KpiAceitesService`) depende de concatenación de variables en algunos casos. Aunque la revisión preliminar muestra que las variables vienen de capas controladas, representa una deuda técnica y riesgo potencial de *SQLi de Segundo Orden*.
- **Autorización (IDOR)**: Las rutas con endpoints de modificación de IDs de módulos u objetos a menudo carecen de verificación de propiedad/estación a nivel de modelo de base de datos.
- **Fallas en Manejo de Errores**: `ErrorHandler` se encuentra configurado correctamente utilizando `APP_ENV = prod` como predeterminado (Safe by default).
