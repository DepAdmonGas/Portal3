# Auditoría técnica, arquitectónica y de seguridad — 2026-09-11

## 1. Executive Summary

**Estado general:** la aplicación es un framework PHP propio, grande y en refactorización, con mecanismos de autenticación, CSRF, JWT, CSP y permisos por módulo ya introducidos. Sin embargo, no existe un *enforcement* central y uniforme de autorización por estación/objeto. La revisión estática confirma un bypass de CSRF y un bypass de cambio de estación para usuarios multiestación; ambos afectan la integridad y el aislamiento de información operacional y de RR. HH.

Hallazgos nuevos de esta auditoría: **2 Critical, 4 High, 5 Medium, 2 Low y 4 Info**. No se ejecutaron pruebas, migraciones, comandos de aplicación ni auditorías de dependencias que pudieran alterar estado.

Fortalezas: todas salvo una de las 1,397 rutas registradas pasan por `Route::auth()` o `Route::guest()`; las rutas autenticadas incorporan `auth` y `csrf`; hay cookies HttpOnly/SameSite, JWT de corta duración, protección de traversal en `DownloadController`, cabeceras de seguridad, un validador MIME moderno y controles de estación/permisos en módulos refactorizados de RR. HH.

**Recomendación:** `REMEDIATE_CRITICAL_ISSUES_BEFORE_CONTINUING`. Corregir primero CSRF y cambio de estación, y diseñar un límite canónico de autorización/tenancy antes de extender el refactor a nuevos módulos. No se hizo ninguna remediación en esta fase.

## 2. Scope

Se inspeccionaron estructura, `composer.json`/lockfiles, routing, bootstrap, Core, middleware, modelos, controladores y servicios representativos, vistas/Alpine, rutas de archivos, integraciones Telegram, documentación y metadatos Git. Se usó análisis estático con `rg`, `find`, `sed` y Git de sólo lectura.

Fuera de alcance o no verificable: base de datos y esquema real (no hay migraciones versionadas), `.env` y sus secretos, servidor web, proxy/CDN, almacenamiento, cron/colas, logs desplegados, correo, Telegram real, configuración de PHP y producción. No se ejecutaron pruebas: no hay una suite de aplicación/versionada ni evidencia suficiente de una BD de pruebas aislada.

## 3. Methodology

Inventario primero; reconstrucción del flujo HTTP después; revisión estática dirigida de autenticación, autorización, multiestación, entradas, Eloquent/raw SQL, salidas HTML/Alpine, CSRF, cookies, archivos, secretos, webhook, dependencias y documentación. Los estados de evidencia se expresan explícitamente; una ausencia de verificación de producción no se presenta como vulnerabilidad confirmada.

## 4. Repository State

- Commit inicial: `c7529fc`; rama: `Silvino`, adelantada 5 commits sobre `origin/Silvino`.
- Worktree inicial: limpio según `git status --short --branch` (sin archivos modificados o no rastreados mostrados).
- Restricción: fase estrictamente read-only; el único cambio de esta auditoría es este reporte.

## 5. Actual Architecture

**Confirmado.** Portal3 no es Laravel completo. Es una aplicación PHP 8.2+ con Composer/PSR-4 y framework propio en `app/Core`, sobre FastRoute, Eloquent Capsule (`illuminate/database` 12.x), PHP-DI, Monolog, dotenv, firebase/php-jwt, Guzzle, Dompdf/FPDF y PhpSpreadsheet.

Flujo principal: `public/index.php` carga Composer/.env, cabeceras, sesión, bootstrap, handler de errores y DB; `Router` carga `routes/web.php`; `Route::auth()` ejecuta `AuthMiddleware` y `CsrfMiddleware`; el contenedor construye el controlador; controladores mezclan acciones, validación ad hoc, Eloquent, servicios, vistas PHP y JSON. La app contiene 160 controladores, 527 modelos y 261 vistas. Hay 1,397 rutas; 1,393 usan `Route::auth()`.

Patrones coexistentes: controladores legacy extensos con acceso directo a modelos/`$_FILES`/JSON, y servicios más recientes (notablemente RR. HH.) con objetos de permisos y contexto de estación. No hay policies/gates/form requests/global scopes de Laravel ni una capa genérica de tenancy.

Módulos: estaciones/empresa/usuarios, SASISOPA, SGM, operaciones corporativas y ventas, finanzas, personal/RR. HH., calibración/mantenimiento, expedientes/documentos/reportes e integración Telegram. Los activos incluyen PII laboral, documentos oficiales y laborales, firmas, biométricos, datos de estaciones, facturación, ventas, auditorías y credenciales de sesión.

## 6. Security Boundaries

Actores observados: visitante, usuario autenticado, usuario multiestación, usuarios con permisos por módulo/puesto y procesos/integración Telegram. La estación (`id_gas`/`id_estacion`) actúa como límite de datos, con contexto guardado en sesión y, en módulos nuevos, `ModuleStationService`. Browser→backend, usuario→estación, backend→BD/uploads y Telegram→webhook son límites de confianza. La pertenencia a estación y el permiso de módulo deben tratarse como controles de backend independientes de los botones/UI.

## 7. Documentation Audit

Mapa resumido:

| Documento/grupo | Propósito y vigencia | Autoridad frente a esta auditoría |
| --- | --- | --- |
| `docs/README.md` | Índice canónico propuesto; declara “esqueleto inicial” (2026-08-10) | Índice activo, parcialmente desactualizado |
| `docs/architecture/*`, `docs/framework/*` | Arquitectura y Core | Referencia activa; varias secciones aún contienen TODO/inferencias |
| `docs/security/security-audit.md` | Auditoría de seguridad previa | Histórico, parcialmente vigente |
| `docs/security/REMEDIATION_BACKLOG.md`, `security-roadmap.md`, `CHANGELOG_SECURITY.md` | Backlog y seguimiento | Activos; no sustituyen una auditoría integral |
| `docs/audits/*` | Auditorías previas; `architecture-audit.md` es plantilla TODO | Histórico/auxiliar; no canónico para seguridad actual |
| `docs/refactoring/*` | Roadmap/deuda/refactor | Activo, complementario |
| `docs/multiestacion/*` | Diseño y ejemplos multiestación | Activo, debe reconciliarse con controles efectivos |
| `docs/decisions/README.md` | ADRs | Sin ADRs concretos encontrados |

Se crea este informe complementario, no se modifica `security-audit.md`: el anterior ya documenta CSRF, rate limit, contraseña legacy, XSS, uploads y proxy, pero no cubre con precisión el estado de todos los módulos/refactor actual. Existe `DOCUMENTATION_DRIFT`: `docs/architecture/security.md` afirma que falta verificar qué rutas aplican CSRF; el código confirma que `Route::auth()` lo agrega a 1,393 rutas, pero también revela exclusiones inseguras dentro del middleware. El documento también declara 5 intentos de login, mientras `RateLimiter::$limits['login']` configura 10.

## 8. Previous Known Findings

Los siguientes no se contabilizan como descubrimientos iniciales: bypass CSRF, rate limiter por sesión, contraseñas legacy, XSS de salidas crudas, uploads y cookies tras proxy ya aparecen en `docs/security/security-audit.md` y/o backlog. Esta auditoría confirma que siguen vigentes o incompletos; los reclasifica con evidencia de código actual. `CHANGELOG_SECURITY.md` declara validador MIME creado y endurecimiento local de `.htaccess`, pero `public/uploads/.htaccess` no está versionado y sólo 2 de los 49 call sites de `move_uploaded_file()` referencian `FileValidatorService`.

## 9. Findings Summary

| ID | Finding | Category | Severity | Priority | Confidence | Status |
| -- | -- | -- | -- | -- | -- | -- |
| SEC-CSRF-001 | Peticiones mutables sin token se aceptan | SECURITY | Critical | P0 | High | Confirmed |
| AUTHZ-TENANT-001 | Cambio multiestación acepta cualquier estación existente | AUTHORIZATION | Critical | P0 | High | Confirmed |
| AUTHZ-DL-002 | Descargas sensibles autorizadas sólo por sesión | PRIVACY | High | P1 | High | Confirmed |
| AUTHZ-TOKEN-003 | Endpoints Telegram operan sobre `id_usuario` aportado por cliente | AUTHORIZATION | High | P1 | High | Confirmed |
| SEC-UPLOAD-004 | Validación de archivo no centralizada en 47 cargas públicas | SECURITY | High | P1 | High | Configuration dependent |
| SEC-XSS-005 | Salidas PHP y `x-html` sin política uniforme de sanitización | SECURITY | High | P1 | Medium | Probable |
| SEC-RATE-006 | Limitación de login evadible por sesión/IP no confiable | SECURITY | Medium | P1 | High | Confirmed |
| AUTHZ-TENANT-007 | No hay garantía transversal de permiso/estación por objeto | AUTHORIZATION | Medium | P1 | High | Confirmed |
| SEC-WEBHOOK-008 | Webhook Telegram no verifica autenticidad ni replay | SECURITY | Medium | P2 | High | Confirmed |
| SEC-SESSION-009 | Detección TLS/proxy y revocación de sesión dependen de entorno | SECURITY | Medium | P2 | High | Production verification required |
| PRIV-LOG-010 | Logging de login incluye identificador de usuario e IP | PRIVACY | Medium | P2 | High | Confirmed |
| DATA-VALID-011 | Validación y mass assignment son heterogéneos | DATA INTEGRITY | Medium | P2 | Medium | Confirmed |
| SEC-CSP-012 | CSP reduce mitigación ante XSS por unsafe-inline/eval | SECURITY | Low | P3 | High | Confirmed |
| DEP-TEST-013 | Dependencias y controles críticos sin verificación automatizada visible | TEST COVERAGE | Low | P3 | Medium | Confirmed |
| ARCH-001 | DI no se usa uniformemente por Router/controladores | ARCHITECTURE | Info | P2 | High | Confirmed |
| OPS-001 | No hay infraestructura/retención/auditoría verificable | OPERATIONS | Info | P3 | High | Not verifiable from repository |
| DOC-001 | Auditorías y documentos de seguridad contienen estado contradictorio | DOCUMENTATION | Info | P2 | High | Confirmed |
| SQL-001 | Raw SQL identificado sin flujo explotable confirmado | SECURITY | Info | P4 | Medium | False positive potential |

## 10. Critical Findings

### SEC-CSRF-001 — Middleware CSRF acepta la ausencia de token

- **Categoría / severidad / prioridad:** SECURITY / Critical / P0; confianza High; **CONFIRMADO**.
- **Ubicación:** `app/Middleware/CsrfMiddleware.php:36-43`; todas las rutas `Route::auth()` pasan por `Route::auth()` → `Kernel::handle(['auth','csrf'])` en `app/Core/Route.php`.
- **Evidencia:** para POST/PUT/PATCH/DELETE no excluido, un token vacío genera uno nuevo y retorna `true`. Además, exclusiones se hacen por subcadena (`/api/`, `/logout`, `/refresh-token`), no por ruta/guard explícito.
- **Riesgo e impacto:** un navegador que envíe cookies de sesión/JWT puede provocar cambios de estado autenticados sin prueba CSRF. Afecta las operaciones mutables del portal.
- **Recomendación conceptual:** fallar cerrado ante token ausente/inválido; sustituir exclusiones por una política de rutas autenticadas con mecanismo explícito y pruebas de regresión.
- **Verificación futura:** prueba controlada de todas las rutas mutables con y sin token, incluidos JSON/Axios y refresh/logout.

### AUTHZ-TENANT-001 — Cambio de estación no valida pertenencia/autorización

- **Categoría / severidad / prioridad:** AUTHORIZATION / Critical / P0; confianza High; **CONFIRMADO**.
- **Ubicación:** `routes/web.php:23`; `app/Controllers/SwitchEstacionController.php:31-57`.
- **Evidencia:** para un usuario con `MultiestacionService::isEnabled($user)`, se ejecuta `Estacion::find($idEstacion)` y se coloca el resultado en sesión. No existe verificación de que la estación solicitada sea elegible para ese usuario, su empresa o su conjunto permitido.
- **Riesgo e impacto:** un usuario multiestación puede seleccionar un ID de estación existente y obtener contexto de esa estación, habilitando acceso posterior de módulos que confían en `Session['usuario']['id_estacion']`.
- **Recomendación conceptual:** resolver estación únicamente desde la relación/lista permitida del usuario y centralizar la comprobación antes de mutar sesión.
- **Verificación futura:** pruebas con usuario multiestación limitado, estación propia permitida, ajena y administrativa.

## 11. High Findings

### AUTHZ-DL-002 — Descarga de documentos sensibles sin autorización por recurso

- **Categoría / severidad / prioridad:** PRIVACY / High / P1; confianza High; **CONFIRMADO**.
- **Ubicación:** `routes/web.php:17`; `app/Controllers/DownloadController.php`.
- **Evidencia:** la ruta requiere sesión, pero `tipo` elige una carpeta y `file` (tras `basename`) un archivo. El controlador protege traversal correctamente, pero no verifica módulo, dueño, estación, usuario o relación del archivo. El mapa incluye CURP, INE, NSS, actas, contratos, documentos laborales, facturas y archivos operacionales.
- **Riesgo e impacto:** cualquier usuario autenticado que conozca/adivine un nombre válido puede descargar PII/documentos de otra estación. Es BOLA/IDOR de archivo.
- **Recomendación conceptual:** autorizar cada descarga contra metadatos de BD/estación/módulo antes de servirla y no basarse sólo en pathname.

### AUTHZ-TOKEN-003 — Operaciones Telegram sobre usuario arbitrario

- **Categoría / severidad / prioridad:** AUTHORIZATION / High / P1; confianza High; **CONFIRMADO**.
- **Ubicación:** `routes/web.php:1858-1861`; `app/Controllers/TokenTelegramController.php`.
- **Evidencia:** `status`, `generate`, `revoke` y `testNotification` aceptan `id_usuario` desde JSON y lo usan directamente; no comparan con `Auth::id()` ni consultan privilegio administrativo/relación.
- **Riesgo e impacto:** usuario autenticado puede consultar/exponer token temporal, revocar vínculo o disparar notificación de otro usuario mediante ID controlado.
- **Recomendación conceptual:** derivar identidad de sesión para operaciones personales; para administración, exigir permiso explícito y alcance de estación.

### SEC-UPLOAD-004 — Mitigación de uploads incompleta y dependiente del servidor

- **Categoría / severidad / prioridad:** SECURITY / High / P1; confianza High; **DEPENDIENTE DE CONFIGURACIÓN**.
- **Ubicación:** 49 usos en controladores de `move_uploaded_file()`; sólo `GestoriaEntregasController` y `SgmInventarioEquipoController` importan `FileValidatorService`. Destinos frecuentes: `public/uploads/archivos/*`.
- **Evidencia:** `app/Services/FileValidatorService.php` valida MIME/contenido de forma adecuada, pero la mayoría de cargas no lo usa. `.gitignore` excluye `public/uploads`; no existe un `public/uploads/.htaccess` versionado que pruebe bloqueo de ejecución.
- **Riesgo e impacto:** si el servidor ejecuta PHP/u otros contenidos activos desde uploads, una carga no validada puede conducir a ejecución o XSS; también hay riesgo de documentos maliciosos, sobreescritura y contenido no permitido.
- **Recomendación conceptual:** política única de tipo/tamaño/contenido/nombre, storage no ejecutable privado y entrega autorizada; validar configuración efectiva de Apache/Nginx/CDN.

### SEC-XSS-005 — Salida HTML no escapada de forma sistemática

- **Categoría / severidad / prioridad:** SECURITY / High / P1; confianza Medium; **PROBABLE**.
- **Ubicación:** 228 vistas contienen `<?=`; `app/Views/*`; 19 usos de `x-html` (por ejemplo `app/Views/sgm/responsabilidad-direccion/index.php:113`, `app/Views/controlactividadproceso/bitacora-calibracion-equipos.php:243`).
- **Evidencia:** PHP nativo no autoescapa `<?=`; varios `x-html` reciben contenido de modelos/JSON. Hay una excepción positiva con `DOMPurify.sanitize` en `capacitacioninterna/index.php`, pero no política equivalente transversal ni trazabilidad suficiente de todas las fuentes.
- **Riesgo e impacto:** contenido persistido o reflejado puede ejecutar JS en usuarios privilegiados si alcanza uno de esos sinks.
- **Recomendación conceptual:** escape por defecto, lista corta de HTML permitido sanitizada en backend/cliente y revisión fuente→sink de campos ricos.

## 12. Medium Findings

### SEC-RATE-006 — Rate limiting de login no es resistente a evasión

**SECURITY / Medium / P1 / High / CONFIRMADO.** `app/Core/RateLimiter.php` almacena contadores en sesión; un atacante puede iniciar sesiones nuevas. También prioriza cabeceras de IP sin lista de proxies confiables. `LoginController::login()` sí lo invoca. Migrar a almacenamiento atómico compartido y una política de proxy confiable; añadir límites por cuenta y alertas.

### AUTHZ-TENANT-007 — Autorización/tenancy heterogénea

**AUTHORIZATION / Medium / P1 / High / CONFIRMADO.** No hay middleware/policy/global scope que imponga estación y permiso de objeto. Hay controles buenos en `PermisosRrhhService`, `BitacoraRrhhService` y `FormatoDescargaMermaService`, pero numerosos servicios/controladores hacen `Model::find($id)` sin una comprobación común; ejemplos: `ControlDocumentosPersonalService.php`, `ResumenMonederoService.php`, `SolicitudChequeService.php`, `VentasService.php`. Esto confirma una causa raíz arquitectónica y riesgo de BOLA a investigar por módulo, sin afirmar que cada llamada sea explotable.

### SEC-WEBHOOK-008 — Webhook Telegram sin verificación de origen

**SECURITY / Medium / P2 / High / CONFIRMADO.** `routes/web.php:1856` registra `/telegram/webhook` sin `Route::auth()`; `TelegramWebhookController::handle()` deserializa el cuerpo y llama `TelegramService::processUpdate()` sin secret header, firma, timestamp, replay/idempotencia o límite. Establecer autenticidad a nivel de Telegram/proxy y deduplicación antes de procesar.

### SEC-SESSION-009 — Protección de cookie/TLS depende de proxy y producción

**SECURITY / Medium / P2 / High / REQUIERE VERIFICACIÓN EN PRODUCCIÓN.** `Request::isSecure()` sólo examina `$_SERVER['HTTPS']`; `Session::init()` usa ese valor y `Cookie::isSecure()` requiere además `APP_ENV=prod`. Detrás de terminación TLS puede faltar `Secure`; aceptar cabeceras reenviadas sin lista de proxy confiable tampoco sería seguro. Sesión dura 90,000 s (~25 h); verificar HTTPS, proxy, rotación en login, expiración y revocación real.

### PRIV-LOG-010 — PII en logs de autenticación

**PRIVACY / Medium / P2 / High / CONFIRMADO.** `AuthenticationService::login()` registra `usuario`, `user_id` e IP en éxito/fallo/2FA; `Logger`/retención/ACL no son verificables. Minimizar identificadores, redactar y definir retención/acceso. No se afirma exposición pública de logs.

### DATA-VALID-011 — Validación y asignación masiva no son uniformes

**DATA INTEGRITY / Medium / P2 / Medium / CONFIRMADO.** Existen helpers y `Usuario::$fillable/$guarded`, pero controladores consumen `json_decode(file_get_contents('php://input'), true)` y `$_FILES` de forma repetida sin Form Requests/DTO central. El modelo `Usuario` contradice su propia intención: `id_gas` y `estatus` aparecen en `$fillable` y `$guarded`; Eloquent normalmente prioriza guardado, pero es una señal de ambigüedad. Inventariar primero payloads alcanzables y crear validación por caso de uso.

## 13. Low Findings

### SEC-CSP-012 — CSP permisiva

**SECURITY / Low / P3 / High / CONFIRMADO.** `public/index.php` define CSP, frame-ancestors, nosniff, XFO, referrer y permissions policy, pero permite `unsafe-inline` y `unsafe-eval`; HSTS sólo se emite al observar HTTPS local. Es hardening, no sustituto de escape. Planificar nonces/hashes y retirar eval tras inventario.

### DEP-TEST-013 — Dependencias y regresión de seguridad

**TEST COVERAGE / Low / P3 / Medium / CONFIRMADO.** `composer.json` fija rangos compatibles y hay lockfiles, pero no se ejecutó `composer audit` por no realizar red/instalación y no se encontró suite de pruebas de aplicación o `phpunit.xml`. No se identificó advisory concreto estáticamente. Ejecutar auditoría de lockfiles en CI y crear pruebas de authz/CSRF/tenancy/uploads.

## 14. Informational / Technical Debt

- **ARCH-001:** `public/index.php` registra servicios en DI, pero `Router::callController()` todavía instancia controladores directamente para handlers no envueltos. Usar una sola ruta de construcción tras revisar compatibilidad.
- **OPS-001:** no hay configuración versionada de workers, cron, proxy, CORS, backups, storage privado, rotación/retención de logs o despliegue; requiere revisión de producción.
- **DOC-001:** documentación de seguridad mezcla resultados previos, TODOs y cifras desfasadas; consolidar después de la remediación sin borrar el histórico.
- **SQL-001:** hay `selectRaw/whereRaw/orderByRaw`, incluido `KpiAceitesService` con interpolación de un campo. La evidencia revisada muestra agregados/valores internos o bindings en casos visibles; no se confirmó una entrada de usuario alcanzando SQL. Mantener allowlists para identificadores dinámicos.

## 15. Authentication Review

Autenticación dual confirmada: sesión PHP + JWT access/refresh HttpOnly; `AuthMiddleware` exige token válido y sesión. Contraseñas modernas usan `password_verify`, pero `PasswordService` admite texto plano legacy y `AuthenticationService` sólo lo registra, no lo rehashéa. 2FA TOTP está presente; su configuración/recuperación necesita revisión dinámica. Login usa mensaje genérico, lo que reduce enumeración. No hay evidencia de reset/verificación email/remember-me o bloqueo de cuenta. Logout está excluido por CSRF mediante coincidencia de URI y debe revisarse junto con SEC-CSRF-001.

## 16. Authorization Review

La autenticación de ruta no equivale a autorización. Permisos por módulo existen y algunos módulos nuevos los aplican en backend; no hay una API común de policy/gate ni control global por recurso. El frontend no puede ser considerado control: menús, botones, Alpine y datos de permisos son modificables por el navegador. Los hallazgos AUTHZ-TENANT-001, AUTHZ-DL-002 y AUTHZ-TOKEN-003 son pruebas concretas de esta brecha.

## 17. Tenant Isolation Review

**Aplicable.** La estación es el tenant operativo. Su contexto nace de `Usuario::id_gas`, sesión y `ModuleStationService`; algunos servicios restringen `id_estacion`, otros resuelven objetos por ID. No hay global scope ni binding scoped. La selección de estación es vulnerable (Critical) y el acceso por objeto debe ser normalizado. Caches, jobs y comandos no son verificables/no se identificó infraestructura asíncrona versionada.

## 18. Input Validation Review

Hay `Request`, helpers y validaciones locales, pero no contrato de entrada uniforme. Los parámetros de rutas suelen ser `\d+`, lo que ayuda a tipado, no a ownership. JSON y archivos necesitan esquemas/tamaños/listas permitidas por endpoint. No se confirmó SQL injection.

## 19. ORM / Database Review

Eloquent 12.x es la capa predominante. No hay migraciones/constraints/versionado de esquema para auditar FK, índices, unicidad, cascadas, transacciones o locking. Los raw expressions requieren revisión de datos dinámicos; no se halló flujo confirmado de SQLi. Operaciones compuestas y estados financieros/RR. HH. deben recibir pruebas de concurrencia e idempotencia en una fase posterior.

## 20. XSS / Frontend Review

Vistas PHP, Alpine y JS vanilla predominan; no hay escaping automático. `x-html` es un sink de alto riesgo y sólo un caso visible usa DOMPurify. CSP proporciona defensa parcial pero no bloquea inline/eval. No se ejecutaron payloads ni pruebas de navegador.

## 21. CSRF Review

Todas las rutas `Route::auth()` incorporan CSRF, una fortaleza estructural, pero el middleware falla abierto ante token ausente y excluye URIs con coincidencias parciales. **Resultado: no efectivo para operaciones mutables; Critical.**

## 22. Session / Cookie Review

HttpOnly y SameSite=Lax están configurados; el `Secure` efectivo y HSTS requieren confirmar TLS/proxy. `Session::regenerate()` existe, pero el flujo de login debe verificarse dinámicamente para confirmar que se llama. Refresh JWT, invalidez y revocación persistente requieren producción/BD.

## 23. Filesystem Review

`DownloadController` bloquea traversal mediante `basename`, mapa permitido y `realpath`, pero carece de autorización semántica. Las cargas son muy numerosas, suelen estar bajo `public/uploads`, y la validación MIME no es transversal. No se afirmaron vulnerabilidades de symlink ni lectura arbitraria fuera de los hallazgos documentados.

## 24. Secrets Review

`.env` está ignorado por Git y no fue leído ni expuesto. No se halló un secreto confirmado en archivos versionados durante el escaneo prudente; `JWT_SECRET` y Telegram dependen de entorno. Revisar repositorio histórico, CI/CD, backups y permisos de `.env` en una revisión con autorización separada.

## 25. API Review

No es una API REST separada: son endpoints HTML/JSON en `routes/web.php`. La ruta `/api/module-context/set` sigue usando `Route::auth()` y queda accidentalmente incluida en la exclusión substring `/api/` del CSRF. Autenticación es sesión/JWT; autorización por recurso es heterogénea. CORS no es verificable en código versionado.

## 26. Dependency Review

`composer.json` exige PHP `^8.2` y dependencias actuales por rangos; se detectaron `composer.lock` y `package-lock.json`. No se instalaron ni actualizaron paquetes, ni se ejecutó audit de red. No hay vulnerabilidad de dependencia confirmada; ejecutar `composer audit --locked` y auditoría npm desde CI en la próxima fase read-only controlada.

## 27. Privacy / Sensitive Data Review

El repositorio maneja documentos laborales e identificadores oficiales (INE, CURP, NSS, RFC, domicilio), firmas, biométricos, historial laboral, finanzas/ventas y expedientes operativos. AUTHZ-DL-002 y AUTHZ-TENANT-001 elevan el riesgo de exposición. No es una certificación legal; retención, base legal, cifrado en reposo, backups y contratos con proveedores requieren revisión de compliance/producción.

## 28. Logging / Audit Trail Review

Monolog/`Logger` existen. Hay logs de login y errores; no se comprobó un audit trail inmutable que cubra cambios sensibles, actor, estación y antes/después. La retención/ACL y exposición de logs no se pueden verificar. Evitar payloads y secretos completos.

## 29. Production Configuration Review

**Confirmado en repositorio:** headers en `public/index.php`, cookies HttpOnly/Lax, middleware de autenticación/CSRF, dotenv `safeLoad`, `.env` ignorado.

**Dependiente de `.env`/infraestructura:** `APP_ENV`, `APP_DEBUG`, JWT/Telegram/DB/mail secrets, HTTPS, proxy confiable, HSTS, sesión, logs, storage, permisos de directorios, PHP handler, CORS y backups.

**Requiere producción:** TLS end-to-end, cabeceras efectivas, despliegue del bloqueo de ejecución de uploads, acceso público a `uploads`, reglas de proxy, workers/cron, logging/auditoría, rotación y retención.

## 30. Test Coverage and Security Regression Risk

No se encontró una suite de aplicación aislada ni configuración PHPUnit. Riesgo alto de regresión por el tamaño (1,397 rutas) y controles distribuidos. Priorizar pruebas de token CSRF ausente/inválido, cambio de estación, descarga ajena, Telegram de otro usuario, upload MIME/extensión, rutas mutables y controles por estación.

## 31. Documentation Drift

- `docs/architecture/security.md`: TODO para rutas CSRF, pero ya están definidas; no identifica bypass actual.
- `docs/architecture/security.md`: 5 intentos de login; código: 10.
- `docs/security/security-audit.md`: describe RCE como crítica; el riesgo presente depende de servidor y de integración incompleta de la validación, por lo que se reclasifica High condicionado.
- `CHANGELOG_SECURITY.md`: afirma endurecimiento local de `.htaccess`, pero no hay archivo versionado que permita verificarlo.

## 32. Production Verification Required

1. Confirmar servidor/CDN/proxy confiable, TLS, cookies Secure/HSTS y cabeceras efectivas.
2. Confirmar que `public/uploads` no ejecuta contenido activo y no se expone por listado/direct path.
3. Revisar permisos y backups de `.env`, BD, logs y archivos; no copiar secretos al reporte.
4. Confirmar APP_DEBUG, handler de errores, CORS y trusted hosts/proxies.
5. Verificar revocación/rotación JWT, regeneración de sesión en login y expiración de refresh.
6. Inventariar cron, workers, colas, cache y claves con station/user; evaluar jobs reintentados.
7. Revisar tabla/esquema, FK, índices, unicidad, transacciones y auditoría de cambios.
8. Probar Telegram con secret token, origen, replay e idempotencia.

## 33. Remediation Backlog

| Priority | Finding ID | Recommended action | Dependency | Complexity |
| --- | --- | --- | --- | --- |
| P0 | SEC-CSRF-001 | Fail closed y política explícita de exclusiones | Inventario de clientes AJAX | M |
| P0 | AUTHZ-TENANT-001 | Validar estación contra conjunto permitido antes de sesión | Modelo/reglas multiestación | S |
| P1 | AUTHZ-TENANT-007 | Capa canónica de contexto + autorización por recurso | Mapa módulos legacy | XL |
| P1 | AUTHZ-DL-002 | Autorizar descarga por metadato y estación | Modelo de documentos | L |
| P1 | AUTHZ-TOKEN-003 | Identidad de sesión/permisos para Telegram | UX admin | S |
| P1 | SEC-UPLOAD-004 | Centralizar validación y storage no ejecutable | Infraestructura storage | XL |
| P1 | SEC-XSS-005 | Escape por defecto y HTML permitido sanitizado | Inventario rich text | XL |
| P1 | SEC-RATE-006 | Rate limiter persistente/atómico con proxy confiable | Cache/infra | M |
| P2 | SEC-WEBHOOK-008 | Validación de webhook y deduplicación | Configuración Telegram | M |
| P2 | SEC-SESSION-009 | Política TLS/proxy/sesión | Infraestructura | M |
| P2 | PRIV-LOG-010 | Minimización y retención de logs | Operación/compliance | M |
| P2 | DATA-VALID-011 | Contratos de entrada y allowlists | Refactor modular | L |
| P3 | SEC-CSP-012 | CSP por nonce/hash | Inventario JS | L |
| P3 | DEP-TEST-013 | Audit CI y pruebas de regresión | CI/entorno test | L |

## 34. Suggested Remediation Order

1. SEC-CSRF-001 y AUTHZ-TENANT-001, con pruebas de no regresión.
2. Definir contrato de station/context y autorización de objeto (AUTHZ-TENANT-007).
3. Cerrar descargas, Telegram y uploads; revisar PII expuesta.
4. Rate limiting, webhook, sesión/proxy y logging.
5. Validación, XSS/CSP, tests, dependencia/operación y deuda de DI/documentación.

## 35. What NOT to Change Yet

No realizar reemplazos masivos de `<?=`/`x-html`, migración global de Eloquent, cambios de `$fillable`, rutas, nombres de upload ni restricciones de estación sin inventario de flujos y pruebas. Los módulos legacy y los módulos RR. HH. refactorizados coexisten; intervenciones transversales apresuradas pueden romper reportes, rich text, permisos de corporativo o selector multiestación. Primero fijar invariantes y pruebas de los P0/P1.

## 36. Audit Coverage Matrix

| Área | Revisada | Resultado | Confianza |
| --- | --- | --- | --- |
| Authentication | Sí | Sesión/JWT/2FA; legacy password pendiente | High |
| Authorization | Sí | Controles heterogéneos; BOLA confirmada | High |
| Tenancy | Sí | Estación como tenant; bypass confirmado | High |
| Input validation | Sí | Heterogénea | Medium |
| SQL/ORM | Sí | Raw SQL sin SQLi confirmada; esquema no verificable | Medium |
| XSS | Sí | Sinks sin política transversal | Medium |
| CSRF | Sí | Bypass confirmado | High |
| Files | Sí | Downloads BOLA; uploads incompletos | High |
| Sessions | Sí | Hardening parcial, proxy pendiente | High |
| Secrets | Sí | No secreto confirmado; entorno no inspeccionado | Medium |
| APIs | Sí | Endpoints mixtos, no REST separada | High |
| Dependencies | Sí | Sin audit dinámico/advisory confirmado | Medium |
| Privacy | Sí | PII alta; riesgos de acceso/log | High |
| Production config | Parcial | Requiere verificación | High |
| Documentation | Sí | Drift y auditorías históricas | High |
| Tests | Sí | No suite de aplicación visible | Medium |
| Queues/scheduler | Sí | NOT VERIFIED / no infraestructura versionada | Low |
| CORS | Sí | NOT VERIFIED | Low |

## 37. Final Assessment

`REMEDIATE_CRITICAL_ISSUES_BEFORE_CONTINUING`

La aplicación tiene una base de seguridad útil y avances documentados, pero CSRF fail-open y el selector de estación sin comprobación de pertenencia son controles de frontera críticos fallidos. La falta de enforcement común amplifica el coste/riesgo del refactor. La siguiente fase debe ser **REMEDIATION_PLANNING**, no implementación directa.
