# Plan de remediación de seguridad — 2026-09-11

## Executive Summary

Este documento planifica, sin implementar, la remediación del informe canónico [`technical-security-audit-2026-09-11.md`](./technical-security-audit-2026-09-11.md). El orden protege primero las dos fronteras rotas: CSRF y cambio global de estación. A continuación establece una forma reutilizable de autorización por módulo, acción, estación y recurso para descargas y Telegram, sin una reescritura amplia de los módulos legacy.

La ejecución futura debe realizarse en slices pequeños, con pruebas de negación antes de activar cada control. No se requieren migraciones para P0. El plan no asume un superadministrador: el repositorio tiene puestos, permisos por módulo y una estación corporativa (`id_gas=8`), pero no una capacidad explícita y auditable de superadministrador; no debe usarse ninguno como bypass implícito.

## Source Audit

Fuente primaria: `docs/audits/technical-security-audit-2026-09-11.md`. Evidencia revalidada para P0/P1: `Route`, `CsrfMiddleware`, `SwitchEstacionController`, `MultiestacionService`, `ModuleStationService`, `DownloadController`, `TokenTelegramController`, `TokenTelegram`, `TelegramService`, `ModuloService`, `ModuloDptoOperativoService`, las rutas afectadas y la documentación multiestación.

## Remediation Principles

1. Fallar cerrado para acciones mutables, cambio de contexto y acceso a archivos.
2. Autenticación de ruta no autoriza un objeto: toda decisión depende de identidad autenticada, permiso de acción y alcance de estación/propiedad.
3. Reutilizar los mecanismos existentes: `ModuloService`/`ModuloDptoOperativoService` para permiso de módulo; `MultiestacionService` para estaciones permitidas; `ModuleStationService` para contexto por módulo. No introducir policies/gates Laravel paralelos.
4. Una excepción privilegiada sólo puede existir como capacidad explícita, persistida y auditada; no por ID mágico, UI ni parámetro del cliente.
5. Separar código, configuración e infraestructura; no declarar completada una defensa dependiente de producción hasta verificarla allí.
6. Security fix first: no convertir este trabajo en refactor global de controllers, vistas o Eloquent.

## Root Cause Analysis

| Root cause | Affected surfaces | Dependent findings | Follow-up |
| --- | --- | --- | --- |
| Middleware CSRF fail-open y exclusión por subcadena | 1,393 rutas `Route::auth()` mutables, incluida `/api/module-context/set` | SEC-CSRF-001 | Política exacta por tipo de ruta y pruebas AJAX |
| Contexto global de estación mutado desde ID del cliente sin validar allowlist | `/switch-estacion`; contexto downstream en sesión | AUTHZ-TENANT-001, AUTHZ-TENANT-007 | Helper canónico de alcance de estación y auditoría por módulo |
| Sólo autenticación transversal; autorización semántica distribuida | downloads, Telegram y muchos controladores legacy | AUTHZ-DL-002, AUTHZ-TOKEN-003, AUTHZ-TENANT-007 | Adaptadores por recurso antes de ampliar cobertura |
| Archivos tratados como pathname y no como recurso de dominio | 58 tipos de `/download`, PII/finanzas/documentos | AUTHZ-DL-002, SEC-UPLOAD-004 | Registro de resolutores con default-deny |
| Entradas/salidas no tienen contratos uniformes | uploads, JSON, vistas/Alpine | SEC-UPLOAD-004, SEC-XSS-005, DATA-VALID-011 | Inventario gradual por módulo |

## Finding Dependency Map

```text
P0 CSRF fail-closed ──► seguridad de todas las mutaciones web

P0 estación autorizada ──► helper de alcance de estación
                                 ├──► P1 descargas por recurso
                                 └──► P1 revisión gradual de BOLA legacy

Permisos de módulo existentes ──► P1 Telegram y descargas

Registro de archivos + alcance ──► P1 uploads privados/autorizados
```

| Step | Finding | Depends on | Blocks |
| --- | --- | --- | --- |
| 1 | SEC-CSRF-001 | Inventario de clientes mutables | Todos los cambios web seguros |
| 2 | AUTHZ-TENANT-001 | Semántica vigente de `getAllowedStations()` | Módulos que confían en estación de sesión |
| 3 | AUTHZ-TENANT-007 | Step 2 | AUTHZ-DL-002 y revisión BOLA por módulo |
| 4 | AUTHZ-DL-002 | Steps 2–3, inventario tipo→modelo | Exposición de PII |
| 5 | AUTHZ-TOKEN-003 | Identidad `Auth::id()` y permiso existente | Vinculación Telegram segura |
| 6 | SEC-UPLOAD-004 | Verificación storage/servidor | Despliegue seguro de uploads |

## Tabla Maestra

| Order | Finding | Severity | Priority | Root Cause | Proposed Fix | Tests | Risk | Blocks Refactor |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| 1 | SEC-CSRF-001 | Critical | P0 | Fail-open/exclusión substring | Fail closed; exclusiones explícitas | CSRF/integración | Medium | BLOCKS ALL REFACTOR |
| 2 | AUTHZ-TENANT-001 | Critical | P0 | ID estación no validado | Allowlist servidor antes de sesión | Tenancy/autorización | Low | BLOCKS ALL REFACTOR |
| 3 | AUTHZ-TENANT-007 | Medium | P1 | Sin guardia común objeto-estación | Helper/adaptadores por módulo | Tenancy/regresión | High | BLOCKS AFFECTED MODULE ONLY |
| 4 | AUTHZ-DL-002 | High | P1 | Pathname sin recurso autorizado | Registro tipo→resolver, default deny | Authorization/feature | High | BLOCKS AFFECTED MODULE ONLY |
| 5 | AUTHZ-TOKEN-003 | High | P1 | ID de cliente como identidad | Derivar usuario de sesión | Authorization/integration | Low | BLOCKS AFFECTED MODULE ONLY |
| 6 | SEC-UPLOAD-004 | High | P1 | Validación/storage no uniforme | Política central y storage no ejecutable | Feature/integration | High | BLOCKS AFFECTED MODULE ONLY |
| 7 | SEC-XSS-005 | High | P1 | Salida no escapada | Inventario fuente→sink, escape por módulo | Regression/feature | High | BLOCKS AFFECTED MODULE ONLY |
| 8 | SEC-RATE-006 | Medium | P1 | Contador de sesión/IP no confiable | Storage atómico, proxy confiable | Integration | Medium | DOES NOT BLOCK REFACTOR |
| 9 | SEC-WEBHOOK-008 | Medium | P2 | Webhook sin autenticidad | Secret Telegram + deduplicación | Integration | Medium | BLOCKS AFFECTED MODULE ONLY |
| 10 | SEC-SESSION-009 | Medium | P2 | TLS/proxy implícito | Trusted proxy/config verificable | Integration/production | Medium | PRODUCTION ONLY |
| 11 | PRIV-LOG-010 | Medium | P2 | PII operacional en logs | Redacción y retención | Unit/integration | Low | DOES NOT BLOCK REFACTOR |
| 12 | DATA-VALID-011 | Medium | P2 | JSON/mass assignment heterogéneo | Contratos por caso de uso | Feature | High | DOES NOT BLOCK REFACTOR |
| 13 | SEC-CSP-012 | Low | P3 | CSP compatible con inline/eval | Nonces/hashes tras inventario | Browser regression | High | DOES NOT BLOCK REFACTOR |
| 14 | DEP-TEST-013 | Low | P3 | Sin CI/security tests visible | Audit lockfiles y pruebas | CI | Medium | DOES NOT BLOCK REFACTOR |
| 15 | ARCH-001, DOC-001 | Info | P2 | Deuda/documentación drift | Documentar y planificar | N/A | Low | DOES NOT BLOCK REFACTOR |
| 16 | OPS-001, SQL-001 | Info | P3/P4 | Infra no versionada/raw SQL contextual | Verificar/monitorizar | Production/review | Low | PRODUCTION ONLY |

## P0 Remediation Plan

### SEC-CSRF-001

**Current failure.** `CsrfMiddleware::handle()` acepta token vacío y excluye cualquier URI que contenga `/login`, `/api/`, `/refresh-token` o `/logout`. `Route::auth()` combina sesión y cookies JWT; una exclusión por nombre no convierte una ruta en API bearer sin cookies.

**Security invariant.** Toda mutación autenticada basada en cookies/sesión debe requerir token CSRF válido. Sólo login invitado y webhooks/servicio-a-servicio con autenticación propia pueden quedar fuera, mediante una decisión exacta y revisable.

**Minimum safe change.** Mantener `Route::auth()` para rutas de navegador; en `CsrfMiddleware` rechazar token ausente o inválido con 419. Reemplazar coincidencia por subcadena con exclusiones exactas sólo para rutas que no dependen de cookies: `POST /login/acceso` ya es `guest` y no pasa por el middleware; `/telegram/webhook` no pasa por `Route::auth()` y debe seguir fuera de CSRF, con su propia autenticidad en SEC-WEBHOOK-008. No excluir `/logout`, `/refresh-token` ni `/api/module-context/set` mientras usen cookies/sesión.

**Canonical long-term design.** Añadir primitivas de ruta explícitas (por ejemplo, web con CSRF, guest y webhook autenticado) al framework propio; no inferirlas desde URI. Sólo adoptar una ruta API sin CSRF si primero usa credenciales no enviadas automáticamente por el navegador y se documenta su guard.

**Tests required.**

| Type | GIVEN | WHEN | THEN |
| --- | --- | --- | --- |
| CSRF regression | Usuario autenticado con sesión/JWT | POST/PUT/PATCH/DELETE sin token | 419; controlador no se ejecuta |
| CSRF happy path | Mismo usuario y token de sesión | Misma mutación con header o campo válido | Se conserva comportamiento exitoso |
| CSRF denial | Token expirado/ajeno | Mutación | 419; no hay cambio |
| Integration | Login guest | `POST /login/acceso` | Opera sin requerir token, sujeto a rate limit |
| Integration | Webhook Telegram | POST legítimo tras SEC-WEBHOOK-008 | No requiere CSRF; exige autenticación de webhook |
| Regression | Usuario autenticado | `/logout`, `/refresh-token`, `/api/module-context/set` sin token | Rechazo, salvo que se rediseñe formalmente su auth |

**Changes and rollback.** CODE CHANGE: middleware y, si hace falta, cliente Axios/layout para enviar el token ya generado por `CsrfToken`. NO DB CHANGE. Riesgo Medium por numerosas mutaciones AJAX. Rollback: revert atómico del middleware/cliente; no hay rollback de datos. No activar una exclusión temporal amplia como workaround.

**Definition of done.** Todas las mutaciones web críticas pasan las rutas feliz/denegada; no quedan exclusiones por substring; webhook y login siguen con mecanismos apropiados; pruebas ejecutadas en entorno aislado.

### AUTHZ-TENANT-001

**Current failure.** `/switch-estacion` recibe `id_estacion`, confirma sólo que el usuario tenga multiestación y que `Estacion::find()` exista, después persiste estación, nombre y razón social en sesión y limpia contextos de módulos.

**Security invariant.** Una estación global sólo puede pasar a sesión si pertenece al conjunto permitido del usuario. La UI es auxiliar, nunca fuente de autorización.

**Minimum safe change.** Crear dentro de `MultiestacionService` una operación de consulta de sólo dominio, reutilizable por controlador, que determine si el ID está permitido para ese usuario. Semántica a preservar desde documentación/código: una lista explícita restringe; `*`, `[]` o `null` resuelto como “sin restricción” permite el catálogo global sólo cuando existe una configuración multiestación activa. Validar además estación activa si ésa es la semántica vigente del catálogo (confirmar antes de activar). El controlador sólo cambia sesión después de resultado permitido; en denegación no modifica contexto ni lo resetea.

**Canonical long-term design.** `MultiestacionService` debe ser la única fuente de “estaciones permitidas”; `ModuleStationService::setContext()` continúa como control por módulo y debe delegar a las mismas reglas, incluyendo las conversiones `tb_estaciones`↔`op_rh_localidades`.

**Tenant isolation test matrix.**

| Actor/context | Station A | Station B | Expected |
| --- | --- | --- | --- |
| Usuario A, allowlist `[A]` | cambiar a A | cambiar a B | 200 y sesión A / 403 sin mutar sesión |
| Usuario B, allowlist `[B]` | cambiar a A | cambiar a B | 403 sin mutar sesión / 200 y sesión B |
| Usuario con `*` documentado | A | B activa | Permitido sólo tras confirmar intención administrativa |
| Usuario sin multiestación | estación propia/ajena | — | Rechazo; conservar sesión |
| Contexto de módulo | A permitida | B no permitida | `setContext` conserva/rechaza según misma allowlist |

**Changes and rollback.** CODE CHANGE: servicio y controlador; posiblemente respuesta HTTP de denegación. NO DB CHANGE. Riesgo Low/Medium por la semántica existente de null/`*`. Rollback: revert atómico; no hay datos persistentes ni migración. Registrar en audit log el cambio de estación cuando exista infraestructura para ello, pero no bloquear P0 por ese trabajo.

**Definition of done.** El ID arbitrario nunca se persiste; la estación permitida conserva flujos; la denegada no altera sesión/contextos; la matriz A/B pasa.

## P1 Remediation Plan

### AUTHZ-TENANT-007 — Fundación canónica de autorización por objeto

**Minimum safe change.** No crear una policy Laravel. Definir un helper de servicio pequeño que reciba identidad autenticada, módulo/acción y estación del recurso ya resuelto; compone `ModuloService` o `ModuloDptoOperativoService` y `MultiestacionService`. Aplicarlo primero a rutas PII/financieras y a los módulos que ya usan `ModuleStationService`; inventariar los `find($id)` restantes por riesgo antes de tocarlos.

**Long-term design.** Adaptadores por agregado (solicitud, documento, personal, venta), no un global scope Eloquent ciego: hay módulos corporativos/departamentos y dos espacios de ID. Cada adaptador expresa cómo obtiene estación/owner y permite “todas” sólo con permiso explícito.

**Tests.** Para cada adaptador: GIVEN recurso A/B y usuario A/B; WHEN read/update/delete; THEN A accede A y recibe 403/404 no revelador para B. Añadir pruebas de permiso `leer/crear/editar/eliminar/descargar` donde el módulo ya los define.

**Risk/rollback/DoD.** HIGH IMPLEMENTATION RISK; bloquear sólo módulos convertidos. Rollback por slice y sin DB change. Está listo cuando el helper se usa en los tres primeros recursos de alto riesgo con pruebas A/B; no declarar cobertura de los 527 modelos.

### AUTHZ-DL-002 — Descarga autorizada por recurso

**Current failure.** `DownloadController` hace validación de path efectiva, pero el mapa `tipo`→directorio (incluye INE, CURP, NSS, contratos, firmas y facturas) no resuelve un modelo ni autoriza estación/acción.

**Minimum safe change.** Mantener el controlador/ruta y sustituir el mapa plano por un registro interno `tipo`→resolver de recurso. Cada resolución devuelve metadatos: path canónico, módulo, acción `descargar`, estación, propietario opcional y clasificación sensible. Autorizar antes de `readfile`. Tipos sin resolutor pasan a default-deny (403/404) hasta incorporar su adaptador. Priorizar las 15 clases `docs-personal-*`, `dia-doble-firma`, `permisos-firma`, `bitacora-rrhh`, contratos, pólizas y documentos financieros; validar si cada filename se vincula en BD antes de activar.

**Long-term design.** Guardar archivos privados con identificador de documento y descargar sólo por endpoint basado en recurso, no por filename. Esto depende de diseño/storage y no es requisito para el primer cierre.

**Tests.** Anonymous recibe 401/redirección; usuario autorizado descarga recurso A; usuario B/otra estación no recibe bytes ni encabezados de archivo A; filename traversal continúa bloqueado; tipo no registrado se deniega; procesos internos legítimos usan la misma autorización.

**Changes and rollback.** CODE CHANGE: controlador + resolutores por tipo/modelo; posible frontend al migrar a IDs. NO DB CHANGE para la primera fase, pero algunos tipos podrían requerir backfill y entonces se posponen, no se adivinan. HIGH IMPLEMENTATION RISK. Rollback por tipo/slice; feature flag de allowlist sólo si ya existe mecanismo seguro. DoD: todo tipo PII crítico tiene resolver y pruebas A/B; no hay fallback pathname-only.

### AUTHZ-TOKEN-003 — Identidad Telegram desde contexto autenticado

**Minimum safe change.** Para `status`, `generate`, `revoke` y `testNotification`, ignorar/eliminar `id_usuario` del payload y derivar ID exclusivamente de `Auth::id()`/sesión validada. No existe en código un flujo administrativo legítimo identificado que necesite operar la cuenta de otra persona; si aparece, crear un endpoint separado que requiera permiso de módulo explícito, target dentro de alcance de estación y audit trail.

**Integration considerations.** El webhook vincula chat ID con un token de dos minutos. `TokenTelegram::generateToken()` borra registros previos del usuario; tests deben preservar la expectativa de renovación/revocación. No mostrar el token temporal a nadie salvo al titular autenticado.

**Tests.** Usuario A genera/consulta/revoca su vínculo; payload con `id_usuario=B` no cambia ni revela B; usuario B no puede disparar `testNotification` a A; vínculo Telegram válido sigue asociando token de A a chat de A. NO DB CHANGE; LOW IMPLEMENTATION RISK; revert de controlador solamente. DoD: no queda identidad efectiva tomada de cuerpo de cliente.

### SEC-UPLOAD-004 — Uploads

**Minimum safe change.** Antes de refactor storage, verificar producción: handler de archivos activos, acceso directo y permisos. A continuación catalogar los 49 call sites por tipo de negocio y aplicar el existente `FileValidatorService` de manera incremental con allowlist MIME/tamaño/nombre generado en servidor. Priorizar PII, PDF/documentos y imágenes públicas. No aceptar MIME sólo por extensión.

**Long-term design.** Storage privado no ejecutable, metadatos de documento, antivirus/proceso asíncrono si la operación lo justifica y entrega mediante AUTHZ-DL-002. No migrar archivos existentes sin plan de backfill/rollback.

**Tests.** Happy path de cada tipo permitido; PHP disfrazado, extensión no permitida, MIME incorrecto, tamaño excesivo y SVG hostil se rechazan; archivo válido se asocia al registro autorizado; upload de otra estación se deniega donde aplique. HIGH RISK; rollback por controlador; producción bloquea deployment, no la preparación local.

### SEC-XSS-005 — Salida y HTML rico

**Minimum safe change.** No reemplazar 228 vistas en masa. Inventariar primero los 19 `x-html` y determinar fuente/campo. Escapar salidas de texto en los módulos PII/administrativos que se modifiquen; preservar HTML intencional sólo con sanitización allowlist explícita. `DOMPurify` visible en una vista no es política suficiente para todas.

**Tests.** Texto con caracteres HTML se muestra literal; HTML permitido se conserva sólo donde haya sanitizador; contenido peligroso no crea nodos/script; layout/Tablas/URLs legítimas no se rompen. HIGH RISK; bloquear sólo módulos intervenidos; rollback por vista/sanitizador. DB change: NO.

### SEC-RATE-006 — Limitación de login

Planificar almacenamiento compartido/atómico y límites por cuenta + red, con una lista de proxies confiables configurada fuera del código. No confiar en `X-Forwarded-For` si la petición no procede de proxy autorizado. Tests: superar límite desde sesiones nuevas bloquea; login correcto/ventana expirada restablecen según política; IP spoofed no cambia clave; instancias concurrentes comparten contador. MEDIUM RISK; INFRASTRUCTURE CHANGE probable; no bloquea refactor, pero debe preceder exposición pública amplia.

## P2 Remediation Plan

- **SEC-WEBHOOK-008 — FIX AFTER P0/P1.** Configurar y verificar header secret de Telegram, compararlo timing-safe, validar forma de update, registrar `update_id` para deduplicación e imponer tamaño/límite. La deduplicación puede requerir índice/tabla: primero inspeccionar esquema. Bloquea deployment de webhook endurecido, no la implementación local.
- **SEC-SESSION-009 — PRODUCTION VERIFICATION.** Configurar proxy confiable y HTTPS para que `Request::isSecure()` no acepte headers de cualquier cliente. Confirmar `Secure`, HSTS, rotación y revocación. CODE + CONFIG + PRODUCTION; no cambiar lógica sólo por hipótesis.
- **PRIV-LOG-010 — FIX AFTER P0/P1.** Redactar usuario/IP según necesidad operacional, limitar niveles y definir retención/ACL. CODE + OPERATIONS; no borrar logs históricos sin política aprobada.
- **DATA-VALID-011 — FIX AFTER P0/P1.** Crear contratos de entrada por caso de uso nuevo/remediado; resolver conflicto `Usuario::$fillable/$guarded` con prueba específica. No refactor global.
- **ARCH-001 / DOC-001 — DOCUMENTATION ONLY.** Tras los fixes, documentar la ruta DI canónica y actualizar cifras/estado de seguridad sin reescribir historial.

## P3 / Hardening

- **SEC-CSP-012 — HARDENING:** inventariar scripts/event handlers, introducir nonces/hashes por layout y retirar `unsafe-eval`/`unsafe-inline` por slices. No prometer una CSP estricta mientras la UI depende de inline.
- **DEP-TEST-013 — HARDENING:** añadir auditoría de lockfiles a CI y una suite aislada; no actualizar dependencias por el mero audit.
- **OPS-001 — PRODUCTION VERIFICATION:** runbook de backups, retención, storage, cache, CORS, cron, workers y auditoría.
- **SQL-001 — ACCEPT / MONITOR:** mantener bindings/allowlists para identificadores; revisar cada raw dinámico cuando se cambie el módulo. No hay SQLi confirmada que justifique cambio urgente.

## Production Verification Items

| Item | Blocks implementation | Blocks deployment | Decision |
| --- | --- | --- | --- |
| TLS/proxy/cookies/HSTS | No | Sí para SEC-SESSION-009 | Verificar antes de deploy |
| Upload handler/no execution/direct access | No | Sí para SEC-UPLOAD-004 | Verificar antes de deploy |
| `.env`, DB, logs, backups ACL | No | Sí para datos sensibles | Operación aprobada |
| APP_DEBUG/CORS/trusted proxy | No | Sí | Verificar en release checklist |
| JWT refresh/revocation/session rotation | No | Sí para auth changes | Prueba en staging/producción controlada |
| Schema/FK/index/audit trail | No | No, salvo migración futura | Descubrimiento separado |
| Telegram secret/replay | No | Sí para webhook | Configurar antes de exponer |

## Test Strategy

Crear primero una base de pruebas aislada, sin ejecutar contra BD compartida. Si no puede existir, usar pruebas de componente puras para Core y un entorno staging efímero para integración. Cada slice incluye una prueba de regresión que reproduce el fallo antes del cambio, happy path y denial path. Las pruebas de autorización nunca aceptan únicamente un 200: confirman que no hubo mutación/lectura/bytes para el actor denegado.

Prioridad de suites: 1) CSRF middleware/route integration; 2) sesión y multiestación A/B; 3) autorización de archivo por tipo; 4) Telegram local con cliente HTTP falso; 5) uploads con fixtures inertes; 6) rate limit con clock/storage falso; 7) XSS de render/browser.

## Authorization Matrix

| Capability real en código | Source of truth | Server check planned | No asumir |
| --- | --- | --- | --- |
| Leer/crear/editar/eliminar/descargar módulo portal | `ModuloService` y sesión | `ModuloService::can(clave, acción)` en adaptador | Que menú visible autorice |
| Permisos departamento operativo | `ModuloDptoOperativoService` | `validaPermiso`/can del módulo correspondiente | Que `id_gas=8` sea admin |
| Selección multiestación | `MultiestacionService` | Lista explícita/semántica documentada | Que selector HTML sea allowlist |
| Contexto por módulo | `ModuleStationService` | Misma allowlist + módulo soportado | Que contexto de sesión autorice objeto |
| Superadministrador | No identificado | Ningún bypass hasta modelarlo | Puesto numérico o estación corporativa |

## Tenant Isolation Test Matrix

| GIVEN | WHEN | THEN |
| --- | --- | --- |
| Tenant A, tenant B; usuario A con permiso/allowlist A; recurso A/B | usuario A lee/descarga/muta A | Permitido sólo si tiene acción requerida |
| Mismos datos | usuario A lee/descarga/muta B | 403/404 sin bytes ni mutación |
| Usuario B con allowlist B | opera B y luego A | B permitido; A denegado |
| Usuario con acceso explícito `*` | selecciona/consulta A y B | Permitido sólo según configuración documentada y acción de módulo |
| Usuario sin multiestación | envía estación A/B | No modifica contexto global |

## Database Changes

| Finding | Decision |
| --- | --- |
| SEC-CSRF-001, AUTHZ-TENANT-001, AUTHZ-TOKEN-003 | NO DB CHANGE |
| AUTHZ-DL-002 | NO DB CHANGE para resolutores existentes; posible backfill/migration sólo si tipos no pueden asociarse a modelo/estación |
| AUTHZ-TENANT-007 | NO DB CHANGE inicial; evaluar constraints cuando se conozca esquema |
| SEC-UPLOAD-004 | NO DB CHANGE inicial; metadata/storage privado es diseño posterior |
| SEC-WEBHOOK-008 | posible tabla/índice de `update_id`; decidir tras inspección de esquema |
| SEC-RATE-006 | posible cache/tabla; decidir con infraestructura |

## Configuration Changes

- **Code:** CSRF, selector, adaptadores authz, Telegram, validación, logs.
- **Configuration:** proxy confiable, JWT/session, CSRF cliente si no se envía el header, Telegram secret, rate-limit storage.
- **Production-only:** servidor web no ejecutable para uploads, HTTPS/HSTS, acceso storage/logs/backups, CORS, workers/cron.
- **Infrastructure:** cache atómico para rate limit; storage privado y, si procede, tabla de replay Telegram.

## Integration Risks

CSRF puede afectar Axios/fetch, refresh/logout y formularios; validar token desde layouts y no exceptuar por URI. La semántica `null`/`*` de multiestación puede representar acceso amplio legítimo; conservarla sólo con confirmación del dueño funcional. Descargas pueden estar referenciadas por reportes/enlaces existentes; migrar tipos por fases. Telegram puede perder la capacidad de administración cruzada si existía fuera de código; exigir caso de uso y permiso explícito. Uploads y CSP tienen alto riesgo de compatibilidad y requieren rollout por tipo/vista.

## Deployment Risks

Liberar P0 juntos sólo después de pruebas AJAX y matriz A/B. Para P1, desplegar adaptadores de archivos por allowlist y observar denegaciones antes de ampliar. No desplegar cambios de uploads, proxy o webhook sin checklist de producción correspondiente. Crear métricas/redacted logs de 419/403/denegación de estación para detectar regresiones sin registrar secretos o documentos.

## Rollback Strategy

Cada slice debe ser un cambio reversible sin migración. P0: revert del middleware/controlador/cliente como unidad; no restaurar datos. Descargas/uploads/XSS: revert por tipo o módulo, manteniendo default-deny para PII si el rollback funcional no es seguro. Rate limit/webhook/session: revert de código y restaurar configuración previa documentada; no borrar estado de rate/replay. Toda migración futura debe tener rollback probado y plan de preservación de datos antes de ejecutarse.

## Implementation Slices

1. **Base de pruebas aislada:** reproducir CSRF vacío y selector A/B; no tocar producción.
2. **P0-CSRF:** token fail-closed, exclusiones exactas, cliente mínimo y pruebas de mutación/login/webhook.
3. **P0-estación:** helper de permiso de estación + controlador; pruebas de sesión/contexto A/B.
4. **Fundación P1:** adaptador de alcance recurso con permisos de módulo; aplicar a un recurso PII piloto y documentar patrón.
5. **P1-descargas:** resolveres de tipos PII críticos, default-deny y pruebas de bytes/tenant; ampliar por grupos.
6. **P1-Telegram:** identidad de sesión, pruebas A/B y luego secret/replay webhook.
7. **P1 archivos/XSS/rate:** cada uno por módulo/tipo, con verificación de infraestructura previa para uploads/rate.
8. **P2/P3:** sesiones/proxy, logs, validación, CSP, CI, documentación y operación.

## Refactor Blocking Matrix

| Finding | Classification | Reason |
| --- | --- | --- |
| SEC-CSRF-001 | BLOCKS ALL REFACTOR | Toda mutación nueva heredaría el bypass |
| AUTHZ-TENANT-001 | BLOCKS ALL REFACTOR | El contexto de estación es una frontera transversal |
| AUTHZ-TENANT-007 | BLOCKS AFFECTED MODULE ONLY | No ampliar módulos con objetos de estación sin adaptador |
| AUTHZ-DL-002 | BLOCKS AFFECTED MODULE ONLY | No agregar documentos/PII al download actual |
| AUTHZ-TOKEN-003 | BLOCKS AFFECTED MODULE ONLY | No extender Telegram hasta corregir identidad |
| SEC-UPLOAD-004 / SEC-XSS-005 | BLOCKS AFFECTED MODULE ONLY | No añadir uploads/HTML rico sin patrón seguro |
| SEC-RATE-006 / logs / validation | DOES NOT BLOCK REFACTOR | Remediar en paralelo con prioridad P1/P2 |
| Proxy, storage, CORS, backups | PRODUCTION ONLY | Bloquean release seguro, no diseño local |

## Definition of Done

- **SEC-CSRF-001:** mutaciones cookie-auth sin token son rechazadas; rutas legítimas prueban token; no hay exclusión substring.
- **AUTHZ-TENANT-001:** estación ajena no cambia sesión ni contextos; matriz A/B pasa.
- **AUTHZ-TENANT-007:** cada módulo convertido usa la misma decisión de permiso/estación; denegación no filtra recurso.
- **AUTHZ-DL-002:** usuario autorizado descarga; otro usuario/tenant y anónimo no reciben archivo; tipos sin resolver se deniegan.
- **AUTHZ-TOKEN-003:** payload no determina identidad; sólo titular realiza acciones propias; flujo de vínculo continúa.
- **SEC-UPLOAD-004:** cada controlador migrado valida MIME/tamaño/contenido y servidor no ejecuta uploads; pruebas negativas pasan.
- **SEC-XSS-005:** sinks intervenidos tienen escape/sanitización según tipo de contenido; pruebas de render pasan.
- **SEC-RATE-006:** contadores no se reinician al cambiar sesión y proxies no confiables no determinan IP.
- **P2/P3:** criterios específicos pasan y producción queda verificada/documentada antes del release correspondiente.

## Final Planning Decision

La primera implementación debe ser el **slice 1: base de pruebas aislada que reproduce token CSRF ausente y cambio de estación Tenant A/B**, seguido inmediatamente por los dos cambios P0 mínimos. La verificación de producción no bloquea preparar ni probar dichos cambios localmente, pero **sí bloquea desplegar** cambios de uploads, proxy/TLS, rate limiting compartido o webhook endurecido sin sus controles de infraestructura.
