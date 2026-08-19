---
# 🛡️ Historial de Remediación de Seguridad y Cambios Auditados (CHANGELOG)

**Proyecto:** Portal3  
**Estrategia:** Refactorización Atómica (Zero Downtime)  
**Regla de Registro:** Solo se agregan tareas probadas físicamente en local y commiteadas en Git.

---

## 📊 Resumen de Progreso
- **P0 (Críticas):** 2 / 3 Completadas
- **P1 (Altas):** 0 / 3 Completadas
- **Total Micro-tareas:** 2 / 12 Finalizadas

---

## 📝 Registro de Micro-Tareas Finalizadas

### [SEC-RCE] Ejecución Remota de Código (RCE) vía Uploads

#### [x] 🔹 Micro-Tarea [RCE-1.1]: Desactivar ejecución PHP vía servidor (.htaccess)
- **Fecha de Finalización:** 2026-08-17
- **Archivos Modificados/Creados:** `public/uploads/.htaccess`
- **Estado Git:** Configuración local de servidor (no se sube a Git)
- **Resultado de Prueba Física:** Exitoso. La consulta a /uploads/test.php devolvió un HTTP 403 Forbidden limpio tras ajustar reglas universales de Apache.
- **Auditado por:** silvinol

#### [x] 🔹 Micro-Tarea [RCE-1.2]: Crear helper/servicio de validación MIME
- **Fecha de Finalización:** 2026-08-17
- **Archivos Modificados/Creados:** `app/Services/FileValidatorService.php`
- **Estado Git:** Listo para commit
- **Resultado de Prueba Física:** Exitoso. Se instanció la clase y se probaron archivos disfrazados (PHP en .jpg y TXT en lista de imágenes), confirmando el bloqueo de tipos no permitidos mediante inspección de Magic Bytes con finfo.
- **Auditado por:** silvinol

#### [ ] 🔹 Micro-Tarea [RCE-1.3]: Integrar validación MIME en controladores de subida
- **Fecha de Finalización:** YYYY-MM-DD
- **Archivos Modificados/Creados:** `[Controladores Afectados]`
- **Hash de Commit Git:** `[pegar hash aquí]`
- **Resultado de Prueba Física:** `[Describir brevemente la prueba manual realizada y el resultado]`
- **Auditado por:** `[Tu Nombre / DevSecOps]`

---

### [SEC-CSRF] Bypass Total de Middleware CSRF

#### [ ] 🔹 Micro-Tarea [CSRF-1.1]: Corregir condicional de token en CsrfMiddleware
- **Fecha de Finalización:** YYYY-MM-DD
- **Archivos Modificados/Creados:** `app/Middleware/CsrfMiddleware.php`
- **Hash de Commit Git:** `[pegar hash aquí]`
- **Resultado de Prueba Física:** `[Describir brevemente la prueba manual realizada y el resultado]`
- **Auditado por:** `[Tu Nombre / DevSecOps]`

#### [ ] 🔹 Micro-Tarea [CSRF-1.2]: Verificar handler de Axios en layout principal
- **Fecha de Finalización:** YYYY-MM-DD
- **Archivos Modificados/Creados:** `app/Views/layouts/main.php`
- **Hash de Commit Git:** `[pegar hash aquí]`
- **Resultado de Prueba Física:** `[Describir brevemente la prueba manual realizada y el resultado]`
- **Auditado por:** `[Tu Nombre / DevSecOps]`

---

### [SEC-RATE] Bypass de Rate Limiting (Fuerza Bruta)

#### [ ] 🔹 Micro-Tarea [RATE-1.1]: Crear mecanismo de almacenamiento por IP
- **Fecha de Finalización:** YYYY-MM-DD
- **Archivos Modificados/Creados:** `app/Core/RateLimiter.php`
- **Hash de Commit Git:** `[pegar hash aquí]`
- **Resultado de Prueba Física:** `[Describir brevemente la prueba manual realizada y el resultado]`
- **Auditado por:** `[Tu Nombre / DevSecOps]`

#### [ ] 🔹 Micro-Tarea [RATE-1.2]: Reemplazar Session::get por el nuevo storage en RateLimiter
- **Fecha de Finalización:** YYYY-MM-DD
- **Archivos Modificados/Creados:** `app/Core/RateLimiter.php`
- **Hash de Commit Git:** `[pegar hash aquí]`
- **Resultado de Prueba Física:** `[Describir brevemente la prueba manual realizada y el resultado]`
- **Auditado por:** `[Tu Nombre / DevSecOps]`

---

### [SEC-HASH] Retención de Contraseñas en Texto Plano

#### [ ] 🔹 Micro-Tarea [HASH-1.1]: Crear método rehashIfRequired
- **Fecha de Finalización:** YYYY-MM-DD
- **Archivos Modificados/Creados:** `app/Services/AuthenticationService.php`
- **Hash de Commit Git:** `[pegar hash aquí]`
- **Resultado de Prueba Física:** `[Describir brevemente la prueba manual realizada y el resultado]`
- **Auditado por:** `[Tu Nombre / DevSecOps]`

#### [ ] 🔹 Micro-Tarea [HASH-1.2]: Invocar rehash tras autenticación exitosa
- **Fecha de Finalización:** YYYY-MM-DD
- **Archivos Modificados/Creados:** `app/Services/AuthenticationService.php` o `LoginController.php`
- **Hash de Commit Git:** `[pegar hash aquí]`
- **Resultado de Prueba Física:** `[Describir brevemente la prueba manual realizada y el resultado]`
- **Auditado por:** `[Tu Nombre / DevSecOps]`

---

### [SEC-XSS] Stored y Reflected XSS en Vistas

#### [ ] 🔹 Micro-Tarea [XSS-1.1]: Crear helper global de sanitización e()
- **Fecha de Finalización:** YYYY-MM-DD
- **Archivos Modificados/Creados:** `app/Helpers/helpers.php`
- **Hash de Commit Git:** `[pegar hash aquí]`
- **Resultado de Prueba Física:** `[Describir brevemente la prueba manual realizada y el resultado]`
- **Auditado por:** `[Tu Nombre / DevSecOps]`

#### [ ] 🔹 Micro-Tarea [XSS-1.2]: Migrar salidas crudas en vistas críticas
- **Fecha de Finalización:** YYYY-MM-DD
- **Archivos Modificados/Creados:** `app/Views/` (Archivos específicos modificados)
- **Hash de Commit Git:** `[pegar hash aquí]`
- **Resultado de Prueba Física:** `[Describir brevemente la prueba manual realizada y el resultado]`
- **Auditado por:** `[Tu Nombre / DevSecOps]`

---

### [SEC-PROXY] Cookies Inseguras detrás de Proxies

#### [ ] 🔹 Micro-Tarea [PROXY-1.1]: Mejorar detección de proxy HTTPS en Request
- **Fecha de Finalización:** YYYY-MM-DD
- **Archivos Modificados/Creados:** `app/Core/Request.php`
- **Hash de Commit Git:** `[pegar hash aquí]`
- **Resultado de Prueba Física:** `[Describir brevemente la prueba manual realizada y el resultado]`
- **Auditado por:** `[Tu Nombre / DevSecOps]`

---

## 🔍 Protocolo para Registrar un Cambio Finalizado (Paso a Paso)

Para mantener la integridad y trazabilidad de la remediación, sigue estos 4 pasos una vez completada la tarea en código:

1. **Prueba Física Exitosa:** Asegúrate de ejecutar la prueba manual descrita en el Backlog y confirmar que resuelve la vulnerabilidad sin causar regresiones.
2. **Commit Atómico:** Realiza un commit en Git conteniendo únicamente los cambios de esta micro-tarea (usando el formato de Convensional Commits sugerido, ej. `fix(security): ...`). Extrae el hash resultante del commit (ej. `a1b2c3d4`).
3. **Actualización del Registro:** 
   - Cambia la marca de la casilla de la micro-tarea de `[ ]` a `[x]`.
   - Completa los campos de Fecha, Archivos, Hash, Resultado y el autor.
4. **Actualización del Resumen:** Incrementa el contador en la sección **📊 Resumen de Progreso** para mantener la visibilidad del avance global.
