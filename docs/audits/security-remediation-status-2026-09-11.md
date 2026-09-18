# Estado consolidado de remediación de seguridad — 2026-09-11

## 1. Executive Status

- **Fecha del snapshot:** 2026-09-11.
- **Auditoría fuente:** [technical-security-audit-2026-09-11.md](./technical-security-audit-2026-09-11.md). Conserva los hallazgos, severidades, evidencia y conclusiones originales.
- **Plan fuente:** [security-remediation-plan-2026-09-11.md](./security-remediation-plan-2026-09-11.md). Conserva prioridades, orden y supuestos iniciales.
- **Registro histórico:** [security-remediation-progress-2026-09-11.md](./security-remediation-progress-2026-09-11.md). Conserva slices, decisiones, bloqueos y regresiones.
- **Fase:** remediación local por slices; no equivale a autorización de despliegue.
- **Producción tocada:** no.
- **Despliegue autorizado:** no.
- **Trabajo concurrente activo:** sí; el módulo `departamento-operativo` sigue protegido.

Este documento es una fotografía del estado actual. No sustituye ni reescribe los tres documentos fuente.

## 2. Finding Status Matrix

| ID | Severidad original | Hallazgo original | Estado actual | Remediación local | Pendiente en producción | Bloqueo concurrente | Siguiente acción |
| --- | --- | --- | --- | --- | --- | --- | --- |
| SEC-CSRF-001 | Critical | CSRF aceptaba solicitudes mutables sin token. | CLOSED_LOCAL | Sí; validación fail-closed. | No específica. | No. | Mantener regresión CSRF. |
| AUTHZ-TENANT-001 | Critical | Cambio de estación aceptaba una estación existente sin alcance. | CLOSED_LOCAL | Sí; selección acotada a estaciones permitidas. | No específica. | No. | Mantener regresión de estación. |
| AUTHZ-DL-002 | High | Descargas sensibles se autorizaban sólo por sesión. | PARTIAL_DEFERRED | SGM, SASISOPA y Gestoría requisitos legales usan descargas canónicas; private-first y legacy fallback están verificados; tipos no resueltos continúan default-deny. | Superficies restantes de `departamento-operativo` siguen diferidas. | Sí. | Reabrir cuando el desarrollo concurrente sea liberado. |
| AUTHZ-TOKEN-003 | High | Operaciones Telegram confiaban en `id_usuario` del cliente. | CLOSED_LOCAL | Sí; identidad desde sesión autenticada. | No específica. | No. | Mantener regresión de identidad. |
| SEC-UPLOAD-004 | High | Uploads no tenían validación/storage uniforme. | PARTIAL_CONCURRENT_WORK_BLOCKED | Parcial; nuevos documentos de personal son privados. | Relocar y validar archivos históricos. | Sí. | Plan aprobado de inventario, backup, migración y rollback. |
| SEC-XSS-005 | High | Salidas HTML y `x-html` sin política uniforme. | CLOSED_LOCAL | Sí; frontera DOMPurify y regresión de sinks. | No específica. | No. | Mantener revisión de nuevos sinks. |
| SEC-RATE-006 | Medium | Límite de login evadible por sesión/IP no confiable. | REMEDIATED_REMOTE_VERIFIED_PRODUCTION_RUNTIME_PENDING | Código y regresión verificados remotamente en Security run `35377218278`; contador atómico por cuenta/IP, ventana fija de 300 s y límite de 10 intentos. | Validar almacenamiento, permisos, proxy, identidad de cliente y 429. | No. | Verificación controlada tras el primer deploy. |
| AUTHZ-TENANT-007 | Medium | Autorización objeto/estación heterogénea. | PARTIAL_CONCURRENT_WORK_BLOCKED | Parcial; superficies ControlDocumentosPersonal, `SolicitudCheque::getDetalle` y `getDocumentos`. | No específica. | Sí. | Continuar fronteras acotadas tras liberar concurrencia. |
| SEC-WEBHOOK-008 | Medium | Webhook Telegram sin autenticidad ni replay protection. | REMEDIATED_REMOTE_VERIFIED_PRODUCTION_RUNTIME_PENDING | Código y regresión verificados remotamente en Security run `35377218278`; secret header y deduplicación cubiertos. | Configurar secreto, forwarding y storage compartido/privado. | No. | Verificación controlada tras el primer deploy. |
| SEC-SESSION-009 | Medium | Seguridad de sesión/TLS dependía de entorno/proxy. | REMEDIATED_REMOTE_VERIFIED_PRODUCTION_RUNTIME_PENDING | Código y regresiones de sesión verificadas remotamente en Security run `35377218278`; endurecimiento de sesión, rotación post-login y logout. | Verificar HTTPS, cookies, proxy y PHP session storage. | No. | Checklist de sesión tras el primer deploy. |
| PRIV-LOG-010 | Medium | Logging de autenticación retenía PII. | REMEDIATED_REMOTE_VERIFIED_PRODUCTION_RUNTIME_PENDING | Código y regresión de privacidad verificados remotamente en Security run `35377218278`; redacción central y neutralización de saltos de línea. | Verificar ruta, ACL, rotación, retención y exposición web. | No. | Revisión operacional tras el primer deploy. |
| DATA-VALID-011 | Medium | Validación y mass assignment heterogéneos. | REMEDIATED_REMOTE_VERIFIED_WITH_DOCUMENTED_RESIDUAL_CONSTRAINTS | Uploads y contrato JSON endurecidos y verificados remotamente; Seguro permanece en almacenamiento público por requisito arquitectónico. | Verificar listado de directorio y ejecución de scripts en producción. | 3 superficies de `departamento-operativo` diferidas. | Reassessment de SEC-CSP-012. |
| SEC-CSP-012 | Low | CSP mantiene `unsafe-inline`/`unsafe-eval`. | PARTIAL_HARDENING_CHECKPOINT_REMOTE_VERIFIED | Report-Only, explicit script sources and externalized highlight initialization verified remotely in Security run `35377218278`; enforced policy remains unchanged. | Alpine/`unsafe-eval`, inline styles, remaining JavaScript URLs, CDN/runtime and enforcement tightening. | No. | Scoped CSP residual remediation and deployment verification. |
| DEP-TEST-013 | Low | Dependencias/controles sin verificación automatizada visible. | REMEDIATED_REMOTE_VERIFIED | Sí; CDN exact-pinned con SRI, lockfile versionado, CI y Composer audit verificados remotamente. | No específica. | No. | Mantener vigilancia de dependencias y renovar hashes según cambios autorizados. |
| ARCH-001 | Informational | DI no se aplica uniformemente. | NEEDS_REASSESSMENT | No; fuera de alcance. | No evaluada. | No. | Revisión arquitectónica acotada. |
| OPS-001 | Informational | Infraestructura, retención y auditoría no verificables desde repositorio. | PRODUCTION_ONLY_PENDING | No aplica al repositorio. | Runbook e inspección de producción. | No. | Preparar y ejecutar runbook aprobado. |
| DOC-001 | Informational | Documentación de seguridad contradictoria/desfasada. | CLOSED_WITH_EVIDENCE | Sí; el snapshot consolida estados y enlaza auditoría, plan e historial sin eliminar trazabilidad. Remediación documental presente remotamente desde `6448939`. | No específica. | No. | Reabrir sólo ante drift material o guía contradictoria. |
| SQL-001 | Informational | Raw SQL sin flujo explotable confirmado. | CLOSED_WITH_EVIDENCE | 4 consultas directas parametrizadas y 34 superficies de expresiones raw revisadas; sin interpolación insegura ni candidato de inyección confirmado. | No específica; repetir revisión si cambia SQL antes del primer deploy. | `departamento-operativo` SQL permanece diferido. | Reabrir sólo ante nuevas superficies SQL o cambios materiales. |

## 3. Closed Locally

- **SEC-CSRF-001:** las mutaciones autenticadas requieren token válido; token ausente o inválido recibe 419.
- **AUTHZ-TENANT-001:** el cambio de estación se autoriza contra el alcance permitido antes de mutar la sesión.
- **AUTHZ-TOKEN-003:** las operaciones personales de Telegram derivan identidad de la sesión, no del cliente.
- **SEC-XSS-005:** los sinks HTML inventariados permanecen detrás de la frontera de sanitización acordada.
- **DATA-VALID-011:** las superficies revisadas fuera de `departamento-operativo` tienen validación estricta de uploads y contrato JSON. Seguro permanece bajo `public/uploads/archivos/poliza-seguro/` por requisito arquitectónico; el acceso estático público es residual y el listado/ejecución de scripts requiere verificación de producción.
- **DOC-001:** `CLOSED_WITH_EVIDENCE`. El snapshot centraliza el estado y remite a la auditoría, el plan y el historial sin reemplazarlos; la remediación documental del commit `6448939` está contenida en el branch remoto actual.

## 4. Remediated Locally / Production Pending

### SEC-WEBHOOK-008

La implementación y su regresión están verificadas remotamente en el commit `2e3bad2c89b353ebef9d8df0e15f7927b6ae54da`, Security workflow run `35377218278`; el log remoto incluye `telegram_webhook_security_regression.php` y `161 PASS / 0 FAIL / 0 SKIPPED`. La verificación runtime queda pendiente del primer deploy: secreto real, forwarding del header, registro del webhook y almacenamiento de replay privado, escribible y compartido cuando corresponda.

### SEC-RATE-006

La implementación y su regresión están verificadas remotamente en el commit `2e3bad2c89b353ebef9d8df0e15f7927b6ae54da`, Security workflow run `35377218278`; el log incluye `rate_limiter_security_regression.php` y `161 PASS / 0 FAIL / 0 SKIPPED`. El límite usa almacenamiento privado atómico, clave `login|cuenta normalizada|REMOTE_ADDR`, ventana de 300 segundos y máximo de 10 intentos. La verificación runtime queda pendiente del primer deploy: almacenamiento, proxy, topología compartida, identidad de cliente y comportamiento HTTP 429/`Retry-After`.

### SEC-CSP-012

La policy Report-Only y la externalización de highlight están verificadas remotamente en el commit `2e3bad2c89b353ebef9d8df0e15f7927b6ae54da`, Security workflow run `35377218278`; `csp_report_only_regression.php` y la suite `161 PASS / 0 FAIL / 0 SKIPPED` pasaron. La policy enforced conserva `'unsafe-inline'`/`'unsafe-eval'`; permanecen pendientes Alpine/`unsafe-eval`, estilos inline, URLs JavaScript restantes, CDN/runtime y tightening de enforcement.

### PRIV-LOG-010

La implementación y su regresión están verificadas remotamente en el commit `2e3bad2c89b353ebef9d8df0e15f7927b6ae54da`, Security workflow run `35377218278`; el log incluye `privacy_logging_security_regression.php` y `161 PASS / 0 FAIL / 0 SKIPPED`. La aplicación redacta secretos, credenciales, payloads, headers y PII, y neutraliza saltos de línea. La verificación runtime queda pendiente del primer deploy: ruta, permisos, inaccesibilidad web, rotación, retención, backups y shipping.

### SEC-SESSION-009

La implementación y las regresiones `session_post_login_regression.php` y `session_security_regression.php` están verificadas remotamente en el commit `2e3bad2c89b353ebef9d8df0e15f7927b6ae54da`, Security workflow run `35377218278`; el log incluye ambos tests y `161 PASS / 0 FAIL / 0 SKIPPED`. El runtime queda pendiente del primer deploy: HTTPS/TLS, proxy, cookies efectivas, `session.save_path`, permisos, modo estricto y configuración PHP efectiva.

#### Regresión post-login resuelta

Durante la remediación, el login local por HTTP llegó a redirigir a `/login` después de credenciales válidas. La causa fue cargar `.env` después de `Session::init()`: el fallback productivo emitía una cookie `Secure` que el navegador HTTP local no devolvía. Se corrigió el orden de bootstrap en `public/index.php`. El estado es **RESOLVED**; no se redujo el endurecimiento de sesión.

## 5. Concurrent Work Blocked

- **AUTHZ-TENANT-007:** están cerradas las superficies acotadas de `ControlDocumentosPersonal`, `SolicitudCheque::getDetalle` y `SolicitudCheque::getDocumentos`. Permanecen pendientes búsquedas/mutaciones de ambos módulos y candidatos a revisar, entre ellos `ResumenMonederoService` y `VentasService`.
- **AUTHZ-DL-002:** la autorización por recurso cubre sólo el grupo `docs-personal-*`; otros tipos de descarga requieren inventario de dominio y resolutores.
- **SEC-UPLOAD-004:** nuevos uploads de personal quedan en storage privado, pero los archivos históricos y las superficies más amplias están diferidos.
- **Integración CSRF de SolicitudCheque:** `POST /departamento-operativo/solicitud-cheque/store` falla manualmente porque el `fetch` nativo con `FormData` no manda el token CSRF actual. El servidor está correctamente fail-closed. El archivo protegido es `public/assets/js/departamento-operativo/1-corporativo/solicitud-cheque.crear.js`.

La futura corrección de SolicitudCheque debe reutilizar el token actual del meta tag de la página autenticada y enviar sólo el header CSRF adicional:

```js
const currentCsrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

fetch(url, {
  method: 'POST',
  headers: { 'X-CSRF-TOKEN': currentCsrfToken },
  body: fd
});
```

No se debe fijar manualmente `Content-Type` con `FormData`, reutilizar un token pre-login, excluir esta ruta de CSRF ni permitir tokens ausentes.

## 6. Production Security Checklist

- [ ] Definir `TELEGRAM_WEBHOOK_SECRET` sin documentar su valor.
- [ ] Registrar el webhook con el secreto y verificar la recepción del encabezado secreto.
- [ ] Comprobar privacidad, permisos, atomicidad y, si corresponde, almacenamiento compartido del registro de replay.
- [ ] Verificar `storage/private/rate-limits`, permisos, topología compartida, `REMOTE_ADDR` y respuestas 429.
- [ ] Revisar `LOG_PATH` efectivo, permisos, ACL, inaccesibilidad web, rotación y retención.
- [ ] Validar HTTPS efectivo, `Secure`, `HttpOnly`, `SameSite`, `session.save_path`, modo estricto y cookie-only mode.
- [ ] Inventariar, respaldar y relocalizar archivos históricos privados; validar referencias de base de datos, negar URL estáticas y conservar rollback.
- [ ] Preparar el runbook de **OPS-001** antes de inspeccionar infraestructura o desplegar.

## 7. Regression Test Status

El harness compartido de seguridad está verde a nivel de infraestructura. Esto no significa que todos los flujos manuales de aplicación estén cerrados: la integración frontend protegida de SolicitudCheque sigue diferida.

| Test | Cobertura principal |
| --- | --- |
| `tests/p0_security_regression.php` + `tests/http_router.php` | SEC-CSRF-001, AUTHZ-TENANT-001, AUTHZ-DL-002, SEC-UPLOAD-004, AUTHZ-TOKEN-003, SEC-XSS-005 y fronteras cerradas de AUTHZ-TENANT-007. |
| `tests/telegram_webhook_security_regression.php` | SEC-WEBHOOK-008: secret, JSON inválido, replay e idempotencia. |
| `tests/rate_limiter_security_regression.php` | SEC-RATE-006: límite, aislamiento, ventana y limpieza post-éxito. |
| `tests/privacy_logging_security_regression.php` | PRIV-LOG-010: redacción, contexto útil e inyección de líneas. |
| `tests/session_security_regression.php` + `tests/session_security_router.php` | SEC-SESSION-009: atributos, fijación, CSRF post-login y logout. |
| `tests/session_post_login_regression.php` + `tests/session_post_login_router.php` | Regresión post-login por orden de carga del entorno. |
| `tests/csrf_post_login_regression.php` + `tests/csrf_post_login_router.php` | Contrato CSRF post-login: token actual válido; ausente, inválido y pre-login rechazados; múltiples mutaciones válidas. |

## 8. Known Functional Regressions / Deferred Integration

**SolicitudCheque CSRF — DEFERRED_CONCURRENT_WORK.** El flujo de creación sigue devolviendo 419 hasta que el módulo protegido envíe el header con el token actual. La cobertura compartida CSRF permanece verde porque valida la infraestructura de sesión/token, no esa llamada `fetch` protegida. No debilitar el middleware es una condición de la integración futura.

## 9. Safe Resume Points

1. Integrar el header CSRF en `solicitud-cheque.crear.js` cuando el trabajo concurrente termine.
2. Reprobar manualmente la creación de registro después del login.
3. Ejecutar el suite completo de regresión de seguridad.
4. Continuar las fronteras restantes de AUTHZ-TENANT-007.
5. Retomar inventario y resolutores de AUTHZ-DL-002 / SEC-UPLOAD-004.

## 10. Explicit Non-Authorizations

- No hay autorización para producción, despliegue, secretos, infraestructura, proxy, base de datos ni archivos históricos.
- No hay autorización para modificar el módulo concurrente `departamento-operativo` hasta liberación explícita.
- DATA-VALID-011 queda `REMEDIATED_REMOTE_VERIFIED` con restricciones residuales documentadas: storage público de Seguro por requisito arquitectónico, verificaciones web pendientes y tres superficies diferidas de `departamento-operativo`. SEC-CSP-012 está en `PARTIAL_HARDENING_CHECKPOINT`: la CSP enforced permanece sin cambios, no quedan scripts ejecutables inline ni handlers inline fuera de `departamento-operativo`, y permanecen pendientes Alpine/unsafe-eval, `javascript:void(0)`, estilos inline, el módulo diferido y el eventual tightening de enforcement. DEP-TEST-013 queda `REMEDIATED_REMOTE_VERIFIED`; ARCH-001 sigue pendiente de reevaluación.
- OPS-001 permanece **PRODUCTION_ONLY_PENDING** con runbook pendiente.
- SQL-001 queda **CLOSED_WITH_EVIDENCE** al commit `2e3bad2c89b353ebef9d8df0e15f7927b6ae54da`: 4 consultas directas parametrizadas y 34 superficies raw revisadas, con 0 candidatos reales de inyección. Las superficies SQL de `departamento-operativo` siguen diferidas y requieren revisión antes del primer deploy si cambian materialmente.
