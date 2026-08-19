# 📋 Backlog de Remediación de Seguridad - Portal3 (Cambios Atómicos)

## Reglas de Trabajo
1. Un commit por micro-tarea.
2. Probar la verificación manual antes de marcar la tarea como completada.
3. No tocar lógica de negocio no relacionada con la tarea activa.

---

### [SEC-RCE] Ejecución Remota de Código (RCE) vía Uploads

#### 🔹 Micro-Tarea [RCE-1.1]: Desactivar ejecución PHP vía servidor (.htaccess)
- **Componente / Archivo(s) Afectado(s):** `public/uploads/.htaccess` (Archivo nuevo)
- **Objetivo del Cambio:** Impedir que el motor de PHP ejecute cualquier archivo depositado en el directorio de subidas.
- **Límites de Seguridad (Qué NO tocar):** No modificar configuraciones del servidor central de Apache ni alterar el enrutamiento principal de `public/.htaccess`.
- **Instrucciones Técnicas del Cambio:**
  1. Crear el archivo `public/uploads/.htaccess`.
  2. Agregar las directivas `php_flag engine off` y `RemoveHandler .php .phtml .php3`.
  3. Denegar el acceso a archivos de extensión ejecutable: `<FilesMatch "\.(php|phtml|php3)$"> Require all denied </FilesMatch>`.
- **Criterio de Aceptación / Prueba de Verificación:** Subir manualmente un archivo `test.php` a la carpeta `public/uploads/` y acceder a él mediante el navegador (ej. `/uploads/test.php`). Debe retornar un error 403 o servir el archivo como texto plano, sin ejecutar el código.
- **Sugerencia de Commit Git:** `fix(security): disable php execution in uploads directory via htaccess`

#### 🔹 Micro-Tarea [RCE-1.2]: Crear helper/servicio de validación MIME
- **Componente / Archivo(s) Afectado(s):** `app/Services/FileValidatorService.php` (o similar, archivo nuevo)
- **Objetivo del Cambio:** Proveer un mecanismo centralizado y seguro para validar el tipo MIME real de un archivo, independiente de su extensión.
- **Límites de Seguridad (Qué NO tocar):** No aplicar este servicio aún en ningún controlador, solo definir su lógica y estructura base.
- **Instrucciones Técnicas del Cambio:**
  1. Crear la clase `FileValidatorService`.
  2. Implementar un método `isValidMimeType($tmpName, array $allowedMimes = [])`.
  3. Dentro del método, usar `finfo_file()` o `mime_content_type()` sobre `$tmpName` para determinar el MIME real y validar contra `$allowedMimes`.
- **Criterio de Aceptación / Prueba de Verificación:** Escribir un pequeño script de prueba en un controlador o ruta temporal que pase un archivo de prueba al nuevo método y verificar que retorna `true` para archivos válidos y `false` para inválidos.
- **Sugerencia de Commit Git:** `feat(security): create FileValidatorService for robust MIME type checking`

#### 🔹 Micro-Tarea [RCE-1.3]: Integrar validación MIME en controladores de subida
- **Componente / Archivo(s) Afectado(s):** Controladores con subida de archivos (ej. `app/Controllers/ControlDocumentosPersonalController.php`, `app/Controllers/ReporteDiarioController.php`)
- **Objetivo del Cambio:** Reemplazar la validación basada en la extensión del nombre del archivo por una validación del tipo MIME real utilizando el nuevo helper.
- **Límites de Seguridad (Qué NO tocar):** Mantener la lógica de nomenclatura y el path de destino. Solo cambiar el filtro/bloqueo de subida.
- **Instrucciones Técnicas del Cambio:**
  1. Inyectar o instanciar `FileValidatorService` en el controlador.
  2. Antes de llamar a `move_uploaded_file()`, llamar a `isValidMimeType()`.
  3. Si la validación falla, retornar un error amigable o redireccionar de vuelta con un mensaje de validación.
- **Criterio de Aceptación / Prueba de Verificación:** Intentar subir un archivo con extensión `.jpg` pero que contenga código PHP internamente (MIME type `text/x-php`). El controlador debe rechazar la subida basándose en la validación MIME.
- **Sugerencia de Commit Git:** `refactor(security): integrate MIME type validation in document uploads`

---

### [SEC-CSRF] Bypass Total de Middleware CSRF

#### 🔹 Micro-Tarea [CSRF-1.1]: Corregir condicional de token en CsrfMiddleware
- **Componente / Archivo(s) Afectado(s):** `app/Middleware/CsrfMiddleware.php`
- **Objetivo del Cambio:** Asegurar que si el token CSRF no está presente en el request, la petición sea rechazada y no aceptada por defecto.
- **Límites de Seguridad (Qué NO tocar):** No alterar la generación inicial de tokens para peticiones GET. Solo enfocarse en las peticiones que mutan estado (POST, PUT, DELETE).
- **Instrucciones Técnicas del Cambio:**
  1. Ubicar el condicional `if (empty($token))` dentro del método `handle()`.
  2. Modificar la lógica para que en lugar de retornar `true`, lance una excepción, redireccione, o retorne un error HTTP 419.
- **Criterio de Aceptación / Prueba de Verificación:** Enviar una petición POST vía cURL o Postman sin el header `X-CSRF-TOKEN` o campo de formulario `_csrf_token`. Debe ser denegada con el error configurado (ej. HTTP 419).
- **Sugerencia de Commit Git:** `fix(security): enforce CSRF token validation and remove empty token bypass`

#### 🔹 Micro-Tarea [CSRF-1.2]: Verificar handler de Axios en layout principal
- **Componente / Archivo(s) Afectado(s):** `app/Views/layouts/main.php` (u otro layout central)
- **Objetivo del Cambio:** Asegurar que las peticiones asíncronas con Axios capturen los errores de token expirado o ausente de forma fluida.
- **Límites de Seguridad (Qué NO tocar):** No reescribir toda la lógica de Axios o Alpine. Solo verificar y afinar el interceptor de errores.
- **Instrucciones Técnicas del Cambio:**
  1. Verificar o implementar un interceptor de Axios en el layout global: `axios.interceptors.response.use()`.
  2. En el callback de error, interceptar el código HTTP 419.
  3. Mostrar un aviso al usuario (ej. SweetAlert) indicando que su sesión expiró y recargar la página (`window.location.reload()`) para refrescar el token.
- **Criterio de Aceptación / Prueba de Verificación:** Tras implementar [CSRF-1.1], probar una acción AJAX normal desde la UI. Luego forzar un error 419 (ej. eliminando la cookie de sesión o esperando a que expire) e intentar otra acción AJAX; la interfaz debe manejar el error y recargar la página de forma limpia.
- **Sugerencia de Commit Git:** `feat(security): implement Axios interceptor for 419 CSRF errors`

---

### [SEC-RATE] Bypass de Rate Limiting (Fuerza Bruta)

#### 🔹 Micro-Tarea [RATE-1.1]: Crear mecanismo de almacenamiento por IP
- **Componente / Archivo(s) Afectado(s):** `app/Core/RateLimiter.php` (u otra clase encargada del Rate Limit)
- **Objetivo del Cambio:** Establecer un storage que rastree los intentos basándose en un dato inmutable (como la IP o el username) y no en la sesión volátil del usuario.
- **Límites de Seguridad (Qué NO tocar):** No tocar aún la lógica del `LoginController` ni alterar la sesión de PHP de otras partes de la app.
- **Instrucciones Técnicas del Cambio:**
  1. Configurar un directorio en `storage/cache/rate_limit/` o usar Redis/Base de Datos (lo que aplique mejor al framework).
  2. Implementar funciones simples de lectura/escritura basándose en el Client IP (obtenido de `Request::ip()`) concatenado con la ruta o el nombre de usuario (ej. `ip_192.168.0.1_login`).
- **Criterio de Aceptación / Prueba de Verificación:** Comprobar que tras un intento fallido (simulado), se crea un registro persistente con la IP que no se borra al limpiar cookies del navegador.
- **Sugerencia de Commit Git:** `feat(security): create persistent IP-based storage for rate limiting`

#### 🔹 Micro-Tarea [RATE-1.2]: Reemplazar Session::get por el nuevo storage en RateLimiter
- **Componente / Archivo(s) Afectado(s):** `app/Core/RateLimiter.php`
- **Objetivo del Cambio:** Desacoplar el límite de intentos de la variable de sesión para prevenir el reseteo del contador borrando cookies.
- **Límites de Seguridad (Qué NO tocar):** Evitar cambiar los límites ya configurados (ej. límite de 5 intentos en 60 segundos). Solo cambiar el "dónde" se almacena la cuenta.
- **Instrucciones Técnicas del Cambio:**
  1. Eliminar el uso de `Session::get($key)` y `Session::put($key)` dentro de los métodos que incrementan o leen intentos.
  2. Reemplazarlos con las llamadas al nuevo almacenamiento por IP implementado en [RATE-1.1].
- **Criterio de Aceptación / Prueba de Verificación:** En el navegador, intentar 5 inicios de sesión fallidos, borrando la cookie `PHPSESSID` tras cada intento. El sistema debe bloquear el acceso en el intento n° 6.
- **Sugerencia de Commit Git:** `refactor(security): migrate rate limiter to use IP-based storage instead of session`

---

### [SEC-HASH] Retención de Contraseñas en Texto Plano

#### 🔹 Micro-Tarea [HASH-1.1]: Crear método rehashIfRequired
- **Componente / Archivo(s) Afectado(s):** `app/Services/AuthenticationService.php` (o donde se valide el password)
- **Objetivo del Cambio:** Proveer una función que actualice la contraseña en la base de datos a un formato seguro tras un login exitoso de legacy.
- **Límites de Seguridad (Qué NO tocar):** No alterar el flujo principal de validación de contraseñas ya hasheadas, ni forzar el re-hash en cada inicio de sesión, solo en los de legacy.
- **Instrucciones Técnicas del Cambio:**
  1. Crear un método `rehashIfRequired(User $user, string $plainPassword)`.
  2. Dentro del método, verificar si `$user->password` está en formato legacy (`PasswordService::isLegacy()`).
  3. Si es legacy, hashear `$plainPassword` usando el algoritmo estándar y actualizar el campo en la base de datos (con Eloquent: `$user->update(['password' => Hash::make($plainPassword)])`).
- **Criterio de Aceptación / Prueba de Verificación:** Testear unitariamente (si es posible) o aislar el método pasándole un usuario con clave vieja y comprobando que en la BD se guarda la versión encriptada.
- **Sugerencia de Commit Git:** `feat(security): implement rehashIfRequired method for legacy passwords`

#### 🔹 Micro-Tarea [HASH-1.2]: Invocar rehash tras autenticación exitosa
- **Componente / Archivo(s) Afectado(s):** `app/Services/AuthenticationService.php` (o `LoginController.php`)
- **Objetivo del Cambio:** Automatizar la migración de claves al momento en que un usuario entra al sistema exitosamente.
- **Límites de Seguridad (Qué NO tocar):** No inyectar lógica de actualización de contraseña si la validación del login ha fallado.
- **Instrucciones Técnicas del Cambio:**
  1. Ubicar la línea de código donde la autenticación devuelve "true" para la contraseña provista (justo después del `PasswordService::isLegacy()` check).
  2. Insertar la llamada a `rehashIfRequired($user, $request->password)`.
- **Criterio de Aceptación / Prueba de Verificación:** Identificar un usuario de prueba en BD que tenga una clave legacy en texto plano. Iniciar sesión en el portal y luego revisar la BD. El campo de contraseña debió actualizarse a su versión encriptada (Bcrypt/Argon2).
- **Sugerencia de Commit Git:** `refactor(security): opportunistically upgrade legacy passwords on successful login`

---

### [SEC-XSS] Stored y Reflected XSS en Vistas

#### 🔹 Micro-Tarea [XSS-1.1]: Crear helper global de sanitización `e()`
- **Componente / Archivo(s) Afectado(s):** `app/Helpers/helpers.php` (o equivalente de utilidades globales)
- **Objetivo del Cambio:** Estandarizar la sanitización de salidas HTML para prevenir inyecciones maliciosas.
- **Límites de Seguridad (Qué NO tocar):** No alterar otros helpers o funciones existentes.
- **Instrucciones Técnicas del Cambio:**
  1. Definir la función `e($str)`:
     ```php
     if (!function_exists('e')) {
         function e($str) { return htmlspecialchars((string) $str, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
     }
     ```
- **Criterio de Aceptación / Prueba de Verificación:** Llamar a `e('<script>alert("test")</script>')` en cualquier parte y verificar que devuelve el string codificado correctamente con entidades HTML (`&lt;script&gt;...`).
- **Sugerencia de Commit Git:** `feat(security): add global e() helper for HTML sanitization`

#### 🔹 Micro-Tarea [XSS-1.2]: Migrar salidas crudas en vistas críticas (Paso a paso)
- **Componente / Archivo(s) Afectado(s):** Vistas PHP afectadas (ej. `app/Views/asistencia/asistencia.php`, `app/Views/dashboard/index.php`)
- **Objetivo del Cambio:** Implementar el uso del helper `e()` en las vistas en lugar de imprimir variables directamente (XSS).
- **Límites de Seguridad (Qué NO tocar):** Precaución con variables que intencionalmente deben renderizar HTML (ej. contenido generado por un WYSIWYG editor como Quill). No reemplazar ciegamente en esos casos.
- **Instrucciones Técnicas del Cambio:**
  1. Identificar módulos u outputs que provengan directamente de inputs del usuario o base de datos.
  2. Modificar etiquetas de la forma `<?= $variable ?>` a `<?= e($variable) ?>`.
  3. (Opcional) Hacer esto de forma gradual, módulo por módulo para evitar bloqueos.
- **Criterio de Aceptación / Prueba de Verificación:** Almacenar en la BD un registro con el nombre `<b>Bold</b>`, abrir la vista que lo muestra y verificar que se muestre literalmente como la etiqueta "<b>Bold</b>" (escapado) y no como texto en negrita.
- **Sugerencia de Commit Git:** `refactor(security): sanitize outputs in views to prevent XSS`

---

### [SEC-PROXY] Cookies Inseguras detrás de Proxies

#### 🔹 Micro-Tarea [PROXY-1.1]: Mejorar detección de proxy HTTPS en Request
- **Componente / Archivo(s) Afectado(s):** `app/Core/Request.php` (o `Cookie.php`)
- **Objetivo del Cambio:** Asegurar que si la app está bajo un balanceador/proxy con SSL, la bandera `Secure` sea enviada con JWTs y Sesiones.
- **Límites de Seguridad (Qué NO tocar):** No confiar ciegamente en headers reenviados sin considerar configuraciones de IP del proxy de confianza (si es que aplican en la arquitectura actual).
- **Instrucciones Técnicas del Cambio:**
  1. En la función `isSecure()`, agregar la comprobación del header `X-Forwarded-Proto`:
     ```php
     return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
            (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
     ```
- **Criterio de Aceptación / Prueba de Verificación:** Analizar las cabeceras de respuesta al establecer una sesión o JWT (vía developer tools). El flag `Secure` debe estar activo si el sistema se consulta a través de HTTPS, incluso si el servidor PHP final procesa tráfico HTTP desde el proxy local.
- **Sugerencia de Commit Git:** `fix(security): support X-Forwarded-Proto for correct secure cookie flag detection`
