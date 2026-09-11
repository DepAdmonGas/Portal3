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
| AUTHZ-DL-002 | High | Descargas sensibles se autorizaban sólo por sesión. | PARTIAL_CONCURRENT_WORK_BLOCKED | Parcial; resolutor `docs-personal-*` y default deny para tipos no resueltos. | Inventario/relocación de archivos históricos. | Sí. | Inventariar los demás tipos y sus resolutores al liberar módulos. |
| AUTHZ-TOKEN-003 | High | Operaciones Telegram confiaban en `id_usuario` del cliente. | CLOSED_LOCAL | Sí; identidad desde sesión autenticada. | No específica. | No. | Mantener regresión de identidad. |
| SEC-UPLOAD-004 | High | Uploads no tenían validación/storage uniforme. | PARTIAL_CONCURRENT_WORK_BLOCKED | Parcial; nuevos documentos de personal son privados. | Relocar y validar archivos históricos. | Sí. | Plan aprobado de inventario, backup, migración y rollback. |
| SEC-XSS-005 | High | Salidas HTML y `x-html` sin política uniforme. | CLOSED_LOCAL | Sí; frontera DOMPurify y regresión de sinks. | No específica. | No. | Mantener revisión de nuevos sinks. |
| SEC-RATE-006 | Medium | Límite de login evadible por sesión/IP no confiable. | REMEDIATED_LOCAL_PRODUCTION_VERIFICATION_REQUIRED | Sí; contador atómico por cuenta/IP. | Validar almacenamiento, permisos, proxy y 429. | No. | Verificación controlada de producción. |
| AUTHZ-TENANT-007 | Medium | Autorización objeto/estación heterogénea. | PARTIAL_CONCURRENT_WORK_BLOCKED | Parcial; superficies ControlDocumentosPersonal, `SolicitudCheque::getDetalle` y `getDocumentos`. | No específica. | Sí. | Continuar fronteras acotadas tras liberar concurrencia. |
| SEC-WEBHOOK-008 | Medium | Webhook Telegram sin autenticidad ni replay protection. | REMEDIATED_LOCAL_PRODUCTION_VERIFICATION_REQUIRED | Sí; secret header y deduplicación. | Configurar secreto y storage compartido/privado. | No. | Registro y entrega controlada del webhook. |
| SEC-SESSION-009 | Medium | Seguridad de sesión/TLS dependía de entorno/proxy. | REMEDIATED_LOCAL_PRODUCTION_VERIFICATION_REQUIRED | Sí; endurecimiento de sesión y rotación CSRF post-login. | Verificar HTTPS, cookies y session storage. | No. | Checklist de sesión en producción. |
| PRIV-LOG-010 | Medium | Logging de autenticación retenía PII. | REMEDIATED_LOCAL_PRODUCTION_VERIFICATION_REQUIRED | Sí; minimización y redacción central. | Verificar ruta, ACL, rotación y retención. | No. | Revisión operacional de logs. |
| DATA-VALID-011 | Medium | Validación y mass assignment heterogéneos. | NEEDS_REASSESSMENT | No; fuera de alcance. | No evaluada. | No. | Inventario acotado de payloads alcanzables. |
| SEC-CSP-012 | Low | CSP mantiene `unsafe-inline`/`unsafe-eval`. | NEEDS_REASSESSMENT | No; fuera de alcance. | Requiere estrategia de nonces/hashes. | No. | Inventario de scripts antes de endurecer CSP. |
| DEP-TEST-013 | Low | Dependencias/controles sin verificación automatizada visible. | NEEDS_REASSESSMENT | No; fuera de alcance. | CI y auditoría de lockfiles pendientes. | No. | Diseñar slice de CI/dependencias. |
| ARCH-001 | Informational | DI no se aplica uniformemente. | NEEDS_REASSESSMENT | No; fuera de alcance. | No evaluada. | No. | Revisión arquitectónica acotada. |
| OPS-001 | Informational | Infraestructura, retención y auditoría no verificables desde repositorio. | PRODUCTION_ONLY_PENDING | No aplica al repositorio. | Runbook e inspección de producción. | No. | Preparar y ejecutar runbook aprobado. |
| DOC-001 | Informational | Documentación de seguridad contradictoria/desfasada. | CLOSED_LOCAL | Sí; este snapshot enlaza el historial sin eliminarlo. | No específica. | No. | Revisar el snapshot al cerrar un slice. |
| SQL-001 | Informational | Raw SQL sin flujo explotable confirmado. | INFORMATIONAL_NO_REMEDIATION | No aplica; sin vulnerabilidad confirmada. | No. | No. | Revisar contexto al modificar módulos afectados. |

## 3. Closed Locally

- **SEC-CSRF-001:** las mutaciones autenticadas requieren token válido; token ausente o inválido recibe 419.
- **AUTHZ-TENANT-001:** el cambio de estación se autoriza contra el alcance permitido antes de mutar la sesión.
- **AUTHZ-TOKEN-003:** las operaciones personales de Telegram derivan identidad de la sesión, no del cliente.
- **SEC-XSS-005:** los sinks HTML inventariados permanecen detrás de la frontera de sanitización acordada.
- **DOC-001:** este documento centraliza el estado y remite a la auditoría, el plan y el historial sin reemplazarlos.

## 4. Remediated Locally / Production Pending

### SEC-WEBHOOK-008

La aplicación valida el encabezado secreto de Telegram y protege contra replay antes de procesar. Producción debe definir `TELEGRAM_WEBHOOK_SECRET`, registrar el webhook con ese secreto, comprobar el encabezado recibido y asegurar que el almacenamiento de replay sea privado, escribible y compartido si existen varias instancias.

### SEC-RATE-006

El límite de login usa almacenamiento privado atómico. Producción debe comprobar `storage/private/rate-limits`, permisos, la topología de almacenamiento compartido, que `REMOTE_ADDR` represente una dirección confiable detrás del proxy y el comportamiento HTTP 429/`Retry-After`.

### PRIV-LOG-010

La aplicación minimiza eventos de autenticación y redacta contexto sensible. Producción debe verificar la ruta efectiva de logs, permisos, ACL de lectores, inaccesibilidad desde web, rotación y retención.

### SEC-SESSION-009

Las sesiones usan cookies, modo estricto, `HttpOnly`, `SameSite=Lax` y cookies `Secure` en producción; el login rota sesión y CSRF. Producción debe verificar detección HTTPS efectiva, atributos de cookie, `session.save_path` fuera del webroot, permisos, modo estricto y cookie-only mode.

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
- No iniciar DATA-VALID-011, SEC-CSP-012, DEP-TEST-013 ni ARCH-001 sin un nuevo slice de evaluación.
- OPS-001 permanece **PRODUCTION_ONLY_PENDING** con runbook pendiente.
- SQL-001 permanece **INFORMATIONAL_NO_REMEDIATION** y se reevalúa contextualmente al cambiar módulos.
