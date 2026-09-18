# Security remediation progress — 2026-09-11

## SEC-CSRF-001

- Initial status: confirmed critical, fail-open CSRF validation.
- Root cause: absent tokens and substring route exclusions were accepted.
- Changed: `app/Middleware/CsrfMiddleware.php`; `tests/http_router.php`; `tests/p0_security_regression.php`.
- Result: missing and invalid tokens are rejected before the handler; valid tokens pass.
- Status: remediated locally.

## AUTHZ-TENANT-001

- Initial status: confirmed critical, global station selection trusted the client ID.
- Root cause: no allowlist comparison before session mutation.
- Changed: `app/Services/MultiestacionService.php`; `app/Controllers/SwitchEstacionController.php`; `tests/p0_security_regression.php`.
- Result: an allowed station succeeds; cross-tenant and nonexistent selections leave the session unchanged.
- Status: remediated locally.

## AUTHZ-DL-002 — first slice

- Affected surfaces: 58 `DownloadController` types.
- Changed: introduced `SensitiveDownloadAuthorizationService` for the critical `docs-personal-*` resource group; unknown types fail closed at `/download`.
- Result: authorized, cross-tenant, anonymous, and missing-resource tests pass.
- Remaining risk at that point: sensitive bytes were still stored under `public/uploads` and could bypass the controller through a static URL.
- Status before SEC-UPLOAD-004: partial.

## SEC-UPLOAD-004 — direct storage bypass

- Date: 2026-09-11.
- Root cause: `ControlDocumentosPersonalService` wrote PII into `public/uploads/archivos/documentos-personal`, inside the apparent webroot.
- Changed: new documents in that sensitive group are written to `storage/private/documentos-personal`; the authorized resolver reads the private location.
- Tests: static former path denial, private destination check, authorized download, unauthorized download, and P0 regression coverage.
- Existing files: production files under the former public location require a controlled filesystem relocation before this private resolver can serve them. No public fallback was added.
- Production verification: required; repository inspection cannot prove deployed web-server behavior.
- Next recommended slice: AUTHZ-TOKEN-003.

### Required production relocation sequence

1. Back up and inventory `public/uploads/archivos/documentos-personal`.
2. Deploy the compatible private-storage code while preserving the database filenames.
3. Relocate the inventoried tree to `storage/private/documentos-personal` with least-privilege ownership.
4. Verify an authorized application download and that the former static URL does not deliver bytes; only then remove any residual sensitive public files.

## AUTHZ-TOKEN-003

- Date: 2026-09-11.
- Affected browser-authenticated surfaces: `status`, `generate`, `revoke`, and `testNotification` in `TokenTelegramController`.
- Root cause: each endpoint treated JSON `id_usuario` as the effective identity.
- Changed: the controller now derives the effective user exclusively from `Auth::id()`; client-provided `id_usuario` is ignored for compatibility. No administrative cross-user endpoint was identified.
- Tests: generation works without a client user ID; malicious User B IDs do not revoke or disclose B's record; anonymous generation is rejected. The complete security harness passes 16 tests.
- Webhook note: `/telegram/webhook` has no browser session and uses token-to-chat association; webhook authenticity and replay protection remain SEC-WEBHOOK-008 and were not changed.
- Status: remediated locally; production verification of the existing Telegram integration remains required.

## SEC-XSS-005

- Date: 2026-09-11.
- Type: stored/DOM XSS at Alpine `x-html` sinks.
- Source: persisted policy content and detail/template values supplied through application JSON.
- Sinks remediated: `politica.contenido`, `otrosDetalle`, and `tablaDetalle`.
- Changed: each rich-text sink now passes content through the existing DOMPurify dependency immediately before Alpine renders it. Legitimate markup remains supported while scripts, event attributes, and dangerous URLs are removed by the sanitizer.
- Tests: regression assertions verify the sanitizer boundary and the trusted dependency in the SGM layout; complete security harness passes.
- Remaining review: other `x-html` sinks are separate module-specific contexts and remain outside this narrowly evidenced slice.
- SEC-WEBHOOK-008 remains pending.

### Closure review

- Inventory: 20 `x-html` sinks across 14 views; one textual table header was converted to `x-text`.
- Classification: 19 rich-text/generated-HTML sinks require sanitization; 1 text-only sink requires escaped text. No remaining raw `x-html` expression was found.
- Resolution: all 19 remaining HTML sinks use the existing DOMPurify boundary. This preserves legitimate markup and removes executable tags, event handlers, and dangerous URL schemes before Alpine uses `innerHTML`.
- Regression: a repository scan test fails if a view adds an `x-html` expression that does not invoke DOMPurify. Full harness remains green.
- Final status: remediated locally.

## AUTHZ-TENANT-007

- Initial implementation attempt: blocked; repository evidence showed several global ID lookups without a bounded shared surface. No code changes; 19 tests passed.
- Boundary 1: `ControlDocumentosPersonalService` personnel reads. A personnel record belongs to `id_estacion`; callers may resolve it only from the module's authorized stations.
- Changed: introduced `findAuthorizedPersonal()` and applied it to personnel detail and attendance read paths.
- Tests: Tenant A can resolve personnel A; cannot resolve personnel B or an unknown ID. No mutation occurs on denial.
- Boundary 1 status: remediated.
- Boundary 2A / `SolicitudChequeService::getDetalle`: remediated through the station-bounded `findAuthorizedSolicitudCheque()` resolver; Tenant A may resolve its request, while Tenant B and missing IDs are denied.
- Boundary 2B / `SolicitudChequeService::getDocumentos`: remediated; the same resolver is applied before document metadata is returned, so a cross-station request yields an empty result.
- Concurrent-work protection: all remaining `departamento-operativo` surfaces are deferred while parallel work is active. This execution must not modify that module or its direct dependencies.
- Status: partial because the remaining department-operativo surfaces are deferred concurrent work; other services remain unreviewed.

## SEC-WEBHOOK-008

- Date: 2026-09-11.
- Root cause: `POST /telegram/webhook` accepted arbitrary JSON and called `TelegramService::processUpdate()` without request authenticity or idempotency. Telegram provides a protocol `update_id`, but the application previously ignored it.
- Authenticity before: none. The webhook registration helper did not send Telegram's `secret_token`, and the controller accepted no header.
- Replay before: none. A repeated valid payload could repeat the token-linking/message side effect.
- Changed: the controller now fails closed unless `X-Telegram-Bot-Api-Secret-Token` matches `TELEGRAM_WEBHOOK_SECRET` using `hash_equals()`. Invalid JSON, incomplete updates, and missing/invalid `update_id` are rejected. `TelegramWebhookReplayGuard` atomically claims each `update_id` with exclusive creation in private storage before processing; a duplicate receives an idempotent success without calling the service. Failed processing releases the claim so Telegram may retry.
- Webhook registration: `TelegramService::setWebhook()` now includes Telegram's native `secret_token` field whenever `TELEGRAM_WEBHOOK_SECRET` is configured. No webhook was registered or changed by this remediation.
- Identity: the incoming payload only supplies Telegram message/chat data; it does not supply an internal user ID. The service continues to resolve the pending server-side `TokenTelegram` record by token before linking a chat.
- Configuration required: `TELEGRAM_WEBHOOK_SECRET` must be set in the appropriate environment and supplied when the real webhook is registered. Production deployments with more than one application node must use shared private storage for `storage/private/telegram-webhook-replay` (or replace the guard with an atomic shared cache/database implementation) so the atomic claim spans nodes.
- Tests: dedicated `tests/telegram_webhook_security_regression.php` covers missing/invalid secret rejection, authenticated delivery, replay idempotency, a subsequent new event, invalid JSON, and an incomplete update. The 26-test existing security harness also remains green.
- Remote code/test verification: commit `2e3bad2c89b353ebef9d8df0e15f7927b6ae54da`, Security workflow run `35377218278`; `php-syntax`, `security-tests`, and `dependency-audit` passed. The remote security log includes `telegram_webhook_security_regression.php` and `161 PASS / 0 FAIL / 0 SKIPPED`.
- Production runtime verification: pending first production deploy. The actual secret configuration, proxy/header forwarding, durable/shared replay storage, deployment topology, and Telegram webhook registration remain unverified. No production service, secret, webhook, database, or deployment was touched.
- Status: `REMEDIATED_REMOTE_VERIFIED`; production verification remains `PENDING_FIRST_DEPLOY`.

## SEC-WEBHOOK-008-REMOTE-VERIFICATION-RECONCILIATION

- Authentication and replay-protection code are remotely verified by the Security workflow. The regression covers missing/invalid secret rejection, authenticated delivery, `update_id` replay idempotency, invalid JSON, and incomplete updates.
- Production checklist for later: configure the secret through deployment secret management, verify header delivery to PHP, reject invalid/missing secrets, accept a legitimate webhook, reject duplicate `update_id`, verify replay-state durability across the deployment topology, and ensure logs do not expose secrets or unnecessary payload data.
- Reopen if the webhook route/controller, authentication, replay guard, proxy forwarding, or replay persistence changes or fails deployment verification.

## SEC-RATE-006

- Date: 2026-09-11.
- Confirmed affected surface: `POST /login/acceso` (credential stuffing/brute force). The repository has no password-reset route; authenticated Telegram token generation, authenticated polling, and the already-authenticated/replay-protected Telegram webhook were inventoried but are not part of this login-specific finding.
- Root cause: the former limiter stored counters in the PHP session, so a new session reset the limit. It also preferred client-supplied forwarding headers, allowing trivial key spoofing.
- Changed: `RateLimiter` now uses a fixed 300-second window with a maximum of 10 attempts. Its key is `login + normalized account identifier + REMOTE_ADDR`, hashed before it becomes a private-storage filename. Counter updates use an exclusive file lock in `storage/private/rate-limits`, so concurrent requests sharing the storage cannot both consume the same remaining attempt.
- Behavior: requests over the limit receive the existing generic failure shape with HTTP 429 and `Retry-After`; the login response remains independent of whether an account exists. A fully successful login clears its own account/IP counter. Failed credentials and incomplete authentication continue to consume attempts.
- Storage failure policy: fail open, to avoid a private-storage outage becoming a global login outage. The failure is logged without account credentials; production monitoring and writable private storage are required.
- IP/proxy assumption: only `REMOTE_ADDR` is used. It is not client-header spoofable, but a reverse proxy may cause several clients to share its address unless production preserves the client address through a trusted proxy configuration.
- Tests: dedicated `tests/rate_limiter_security_regression.php` covers requests under the limit, limit exceeded, account/IP isolation, window reset without sleeping, and post-success counter clearing. Webhook regression (7) and the existing security harness (26) remain green.
- Remote code/test verification: commit `2e3bad2c89b353ebef9d8df0e15f7927b6ae54da`, Security workflow run `35377218278`; `rate_limiter_security_regression.php` passed and the remote suite reported `161 PASS / 0 FAIL / 0 SKIPPED`.
- Production runtime verification: pending first production deploy. The limiter key is `login|normalized account|REMOTE_ADDR`, the fixed window is 300 seconds, the threshold is 10 attempts, and over-limit requests receive HTTP 429 with `Retry-After`. Reverse-proxy identity, shared storage/topology, real traffic thresholds, and operational alerting remain unverified.
- Status: `REMEDIATED_REMOTE_VERIFIED`; production verification remains `PENDING_FIRST_DEPLOY`.

## SEC-RATE-006-REMOTE-VERIFICATION-RECONCILIATION

- The finding scope remains login abuse/authentication brute force. `LoginController::login` invokes the fixed-window `RateLimiter` before authentication, and successful login clears the account/IP counter.
- The limiter uses private file storage with exclusive locking. The key is not client-header controlled; it uses `REMOTE_ADDR` and a normalized account identifier. Storage failure is fail-open by design and logs only the storage error.
- The regression covers the 10-attempt threshold, rejection of attempt 11, account/IP key isolation, window reset, and successful-operation counter clearing.
- Later post-deploy checks: preserve client identity through the trusted proxy, prevent forwarding-header bypass, verify shared persistence across instances, confirm abusive requests are rejected without pathological blocking, and confirm diagnostic logs/metrics do not expose secrets.
- Reopen if the limiter, protected routes, key source, deployment topology, or production verification reveals ineffective throttling or bypass.

## PRIV-LOG-010

- Date: 2026-09-11.
- Root cause: authentication events recorded the account identifier and IP address directly; the custom Monolog facade did not centrally redact sensitive context. The production error handler also bypassed the facade through a nonexistent `Logger::getLogger()` call.
- Affected logging surfaces: authentication login/logout events, token refresh events, the shared `Logger` facade, and the production error/exception handler. The baseline evidence confirmed PII (username and IP); no baseline direct logging of raw passwords, JWT values, cookies, or Telegram secrets was found in these paths.
- Changed: `AuthenticationService` retains only event outcome and internal `user_id` where available. `Logger` now centrally redacts secrets, authentication/session values, request/response bodies, headers, document paths, SQL/query content, and common PII keys recursively. It also neutralizes line breaks in messages/context values. The error handler now uses the facade and records bounded exception metadata (class, source file, line) rather than the exception message or full trace.
- Retained operational/audit context: event name, outcome, internal user ID where known, error code, exception class, source file, and line. No raw password, auth token, cookie/session value, Telegram secret, account identifier, or IP is retained by these logger calls.
- Tests: dedicated `tests/privacy_logging_security_regression.php` verifies redaction of dummy password/token/cookie/Telegram-secret/username/IP values and confirms useful context remains while newline log injection is neutralized. Rate limiting (5), webhook (7), and existing security regression (26) suites remain green.
- Remote code/test verification: commit `2e3bad2c89b353ebef9d8df0e15f7927b6ae54da`, Security workflow run `35377218278`; `privacy_logging_security_regression.php` passed and the remote suite reported `161 PASS / 0 FAIL / 0 SKIPPED`.
- Status: `REMEDIATED_REMOTE_VERIFIED`; production log runtime verification remains `PENDING_FIRST_DEPLOY`. Verify log path permissions, web inaccessibility, exception verbosity, rotation/retention, backup/shipping ACLs, and authorized readers after deployment.

## PRIV-LOG-010-REMOTE-VERIFICATION-RECONCILIATION

- `Logger` sanitizes messages against line injection and recursively redacts secret, credential, request-body, header, path, SQL, and PII context keys. Exception logging records bounded class/file/line metadata rather than exception messages or payloads.
- Reopen if logger sanitization, exception handling, log destinations, or production verification reveals sensitive data exposure.
- Operations: log retention, access control, and any `LOG_PATH` override are deployment concerns not controlled by this code. The default `storage/logs` path is outside the apparent webroot, but production must verify the effective path, permissions, rotation, retention, and log-reader ACLs. No existing log was deleted.
- Status: remediated locally.

## SEC-SESSION-009

- Date: 2026-09-11.
- Architecture: native PHP filesystem sessions initialized by `Session::init()`, with a session cookie plus separate HttpOnly JWT access/refresh cookies. `SessionService::start()` establishes authenticated state; logout destroys the native session and removes JWT cookies. No remember-me flow was found.
- Root cause: `Secure` depended on PHP's local HTTPS indicator and cookie/session hardening flags were not explicitly centralized. That can yield insecure cookies when production TLS terminates before PHP. The authenticated-login path already regenerated the session ID, but its CSRF token remained from the anonymous session.
- Changed: session initialization now enforces cookie-only IDs, strict mode, and disabled URL session IDs before `session_start()`. Production cookies are always marked `Secure`; development keeps secure cookies when the direct request is HTTPS. Session and JWT cookies remain HttpOnly, `SameSite=Lax`, and path `/`. Login regeneration now refreshes the CSRF token. Session-cookie deletion preserves its security attributes.
- Fixation/logout: `SessionService::start()` regenerates the ID with deletion of the old server-side session. `Session::destroy()` clears in-memory state, expires the cookie, and destroys the server-side session. No password, Telegram secret, or JWT value is stored in the native session; it contains identity/station/permission context and CSRF state.
- Tests: dedicated `tests/session_security_regression.php` validates Secure/HttpOnly/SameSite attributes, post-login session-ID rotation, old-session rejection, valid and invalid post-login CSRF behavior, and logout invalidation. The legacy P0 fixture keeps its pre-existing test-only session behavior under `P0_TEST_DB`; application environments use strict mode. Privacy (2), rate limiting (5), webhook (7), and existing security regression (26) suites remain green.
- Production verification: required. Confirm the effective PHP `session.save_path` is outside the webroot with appropriate permissions, that production HTTPS is end-to-end, that `APP_ENV=prod` is set, and that proxy/CDN configuration does not replace the remote connection security assumptions. Session retention/garbage collection and deployment-specific storage sharing remain infrastructure concerns. No server, `php.ini`, proxy, cookie, or active production session was touched.
- Status: remediated locally.

## SECURITY_REMEDIATION_BACKLOG_REASSESSMENT_2026-09-11

- Scope: read-only reassessment; no implementation, test, configuration, production, or protected `departamento-operativo` file was changed.
- Audit total: 18 findings (2 Critical, 4 High, 6 Medium, 2 Low, 4 Informational).
- Consolidated status: 4 closed locally with no specific production action (`SEC-CSRF-001`, `AUTHZ-TENANT-001`, `AUTHZ-TOKEN-003`, `SEC-XSS-005`); 4 remediated locally pending production verification (`SEC-WEBHOOK-008`, `SEC-RATE-006`, `PRIV-LOG-010`, `SEC-SESSION-009`); 3 deferred by concurrent work (`AUTHZ-TENANT-007`, `AUTHZ-DL-002`, `SEC-UPLOAD-004`); 1 production-only (`OPS-001`); 1 ready documentation-only item (`DOC-001`); 1 informational monitor item (`SQL-001`); and 4 requiring scoped reassessment before implementation (`DATA-VALID-011`, `SEC-CSP-012`, `DEP-TEST-013`, `ARCH-001`).
- `AUTHZ-TENANT-007`: Boundary 1 personnel reads and Boundaries 2A/2B in `SolicitudChequeService` are remediated. Confirmed remaining protected surfaces include unreviewed object lookups/mutations in `ControlDocumentosPersonalService` and `SolicitudChequeService`; suspected non-protected candidates requiring their own evidence-first slice include `ResumenMonederoService` and `VentasService` global-ID document/report reads.
- `AUTHZ-DL-002`: authorization is complete only for the `docs-personal-*` resolver group; unknown download types fail closed. The controller still maps many other download types whose domain ownership/tenant resolvers require an inventory, and several are in or depend on protected operational/RH surfaces.
- `SEC-UPLOAD-004`: new personnel-document uploads are private locally; historical production files require an approved inventory, backup, relocation to private storage, authorization verification, and rollback plan. The broader 49-call-site upload inventory remains protected/architectural work.
- Next safe local work: `DOC-001` can consolidate documentation after the concurrent module is released; no further security implementation slice is recommended without a new bounded evidence inventory. Production verification preparation is the next phase.

## SEC-SESSION-009-POST-LOGIN-REGRESSION

- Symptom: valid credentials produced the successful-login event, but a browser running the local HTTP environment reached `/home` without the PHP authenticated session and was redirected back to `/login`.
- Root cause: `public/index.php` called `Session::init()` before loading `.env`. `Session` therefore used its fail-closed production fallback and emitted the PHP session cookie with `Secure` before `APP_ENV=dev` became available. The browser correctly did not return that cookie over HTTP. JWT cookies were created later, after environment loading, leaving the authenticated middleware with a JWT but no `Session['usuario']` state.
- Fix: environment loading now occurs before session initialization. Local HTTP with `APP_ENV=dev` receives non-Secure development cookies; production and direct HTTPS retain `Secure` cookies. No session hardening control was removed.
- Tests: `tests/session_post_login_regression.php` uses a browser-like cookie jar to prove the corrected local HTTP flow and the production HTTPS flow, and reproduces the old bootstrap order as a `302 /login` post-login failure. Existing session, privacy, rate-limit, webhook, and P0 suites remain green.
- Final status: application-level hardening is `REMEDIATED_REMOTE_VERIFIED` by Security workflow run `35377218278` for commit `2e3bad2c89b353ebef9d8df0e15f7927b6ae54da`; production runtime verification remains `PENDING_FIRST_DEPLOY`.

## SEC-SESSION-009-REMOTE-VERIFICATION-RECONCILIATION

- Scope remains session fixation, post-login session persistence, logout invalidation, CSRF continuity after rotation, and hardened cookie attributes.
- `Session::init()` enables cookie-only sessions, strict mode outside the P0 fixture, disables transparent SID transport, sets the 90,000-second application lifetime, and configures `HttpOnly`, `SameSite=Lax`, and environment/HTTPS-dependent `Secure` cookies.
- Successful login rotates the session ID with old-session deletion; logout clears authentication state, destroys the session, and expires the session cookie.
- `session_security_regression.php` covers cookie attributes, session rotation, stale-session rejection, CSRF after rotation, and logout invalidation. `session_post_login_regression.php` covers authenticated state across local HTTP/production HTTPS and the historical bootstrap-order regression.
- Remote evidence: both session regression scripts passed in Security run `35377218278`; the remote suite reported `161 PASS / 0 FAIL / 0 SKIPPED`.
- Runtime checks remain pending first deploy: HTTPS/TLS termination, proxy-aware secure-cookie behavior, effective PHP session settings/storage permissions, session lifetime, and cookie forwarding.

## SEC-SESSION-009-CSRF-POST-LOGIN-REGRESSION

- Date: 2026-09-11.
- Reproduction: after login, `public/assets/js/departamento-operativo/1-corporativo/solicitud-cheque.crear.js` constructs `POST /departamento-operativo/solicitud-cheque/store` with native `fetch` and `FormData`, but supplies neither `X-CSRF-TOKEN` nor `_csrf_token`. The CSRF middleware correctly returns 419 before `SolicitudChequeController::store` runs.
- Root cause: this is a protected concurrent front-end transport omission, not a session-ID or CSRF-token desynchronization. `SessionService::start()` correctly rotates the anonymous token, and an authenticated GET form exposes the resulting current token. Native `fetch` is not covered by the Axios interceptor.
- Scope decision: no protected `departamento-operativo` file or its direct dependency was modified. The required local correction is to include the current token in that protected fetch request (or a protected-module scoped fetch wrapper) once concurrent work is released; CSRF validation must remain fail-closed.
- Tests: `tests/csrf_post_login_regression.php` independently proves the shared contract: anonymous and authenticated tokens differ, the authenticated token authorizes one and multiple mutations, and missing, invalid, and pre-login tokens receive 419. This confirms that the shared CSRF/session remediation remains sound.
- Status: blocked only for the protected module correction; `SEC-CSRF-001` and `SEC-SESSION-009` remain remediated locally.

## DOC-001 — documentation consolidation

- Snapshot: [security-remediation-status-2026-09-11.md](./security-remediation-status-2026-09-11.md) consolidates the current local, production-pending and concurrent-work status without replacing this progress log, the original audit or the remediation plan.

## SOLICITUD-CHEQUE-CSRF-INTEGRATION

- Status: remediated locally.
- Root cause: native `fetch` with `FormData` omitted the CSRF token for `POST /departamento-operativo/solicitud-cheque/store`.
- Fix: `solicitudChequeCrearComponent.guardar()` reads the current authenticated-page `meta[name="csrf-token"]`, fails safely through the existing notifier if absent, and sends it as `X-CSRF-TOKEN`. The browser retains ownership of the multipart `Content-Type` boundary.
- Server CSRF security: unchanged and fail-closed; missing or invalid tokens still receive 419.
- Follow-up: manual authenticated record creation must be retested before commit/deployment.

## VIEW-LAYOUT-CSRF-LOGOUT

- Status: remediated locally.
- Affected: `app/Views/layouts/sgm.php` and `app/Views/layouts/sasisopa.php`.
- Root cause: both layouts used the shared Axios `performLogout()` flow for `POST /logout` but omitted the current CSRF meta tag and Axios header configuration.
- Fix: both layouts now render the current session CSRF token and use the canonical Axios default/request-interceptor bootstrap. Server validation, the POST-only logout route, and fail-closed behavior are unchanged.
- Follow-up: manually retest logout from `/sgm` and `/sasisopa`. `configuracion.php` remains outside this slice.

## CONFIGURACION-LOGOUT-CSRF-CONSISTENCY

- Status: remediated locally.
- Root cause: `configuracion.php` combined the shared POST logout action with a legacy `GET /logout` anchor and omitted the current CSRF meta/Axios header configuration.
- Fix: the layout now uses the canonical current-token Axios bootstrap and both visible logout controls invoke `performLogout()`; the legacy GET client flow was removed.
- Backend: unchanged. The POST logout route and fail-closed CSRF middleware remain required.
- Follow-up: manually retest logout from Configuración before commit/deployment.

## SECURITY-PAUSE-CONCURRENT-WORK

- Pause point: the local security regression suite is 62 passed, 0 failed, 0 skipped. Production remains untouched and deployment is not authorized.
- `VIEW-LAYOUT-CSRF-LOGOUT`: remediated locally. Seven active layouts were reviewed; five provide logout and all five use POST with the current CSRF token. No legacy GET logout or mutable logout without CSRF remains.
- `SOLICITUD-CHEQUE-CSRF-INTEGRATION`: the client hotfix is present, but `MANUAL_CREATE_RETEST` remains **PENDING** because no manual confirmation has been provided.
- Concurrent-work block remains active for `AUTHZ-TENANT-007`, `AUTHZ-DL-002`, and `SEC-UPLOAD-004` in or dependent on `departamento-operativo`.
- Resume only after concurrent work is complete and versioned, SolicitudCheque creation is manually retested, and the security suite remains green. Resume order: finalize SolicitudCheque CSRF verification, run the full suite, then continue AUTHZ-TENANT-007, AUTHZ-DL-002, SEC-UPLOAD-004, and production-verification preparation.
- Do not initiate DATA-VALID-011, SEC-CSP-012, DEP-TEST-013, ARCH-001, or OPS-001 without new prioritization.

## DATA-VALID-011 — input validation reassessment outside departamento-operativo

- Scope: read-only reassessment outside `departamento-operativo`; its views, JavaScript, and the protected `SolicitudChequeService`, `ResumenMonederoService`, and `VentasService` were not reviewed in depth. The operational/RH upload and JSON flows encountered through `ControlDocumentosPersonalController`, `ControlVolumetricoController`, `FormatoDescargaMermaController`, and the protected route group remain deferred.
- Reviewed request-to-use surfaces: public login and Telegram webhook; authenticated station and module-context selection; authorized download query parameters; Seguro uploads; Solicitud de Gafetes uploads; and Solicitud de Tarjetas create/update JSON and multipart forms. Login, webhook, station selection, module context, downloads, and Gafetes have a bounded contract or an existing security control and are not new DATA-VALID findings. No client request array is passed directly to an Eloquent mass-assignment method in the reviewed non-operational flows.
- Confirmed DATA-VALID-011-A: `POST /solicitud-tarjetas/create-reporte` accepts an optional `archivo` and persists it under `public/uploads/archivos/solicitud-tarjetas/` after deriving only its client-supplied extension. It imposes no size limit, real-MIME verification, extension/MIME correspondence, or allowlist. The same controller's `POST /solicitud-tarjetas/update-reporte-formulario` accepts decoded JSON and checks only truthiness, so IDs and mutable strings have no strict type, maximum-length, or schema validation. Recommended follow-up: a bounded non-operational upload/JSON contract slice that validates the multipart file before storage and applies explicit typed DTO-style validation to the update payload.
- Confirmed DATA-VALID-011-B: `POST /seguro/create-poliza-seguro` and `POST /seguro/create-cobertura-poliza-seguro` accept `poliza`/`cobertura` uploads, retain only the client filename extension, and move them to `public/uploads/archivos/poliza-seguro/` with no maximum size, real-MIME check, or extension allowlist. Recommended follow-up: a bounded Seguro upload-validation slice with server-side MIME/size/extension validation and a review of whether public storage is appropriate.
- Classification: two confirmed findings (Medium, high confidence); no mass assignment observed in the reviewed flows; no new enum-validation gap was confirmed. `UNVALIDATED_JSON_FOUND` is yes because the Tarjetas update payload lacks a structural contract. Broader raw JSON and file-upload call sites in the excluded operational module remain deferred, not counted as confirmed here.
- Tests: existing security regression baseline passed: 62 passed, 0 failed, 0 skipped. No tests or implementation were changed. Production was not touched.
- Next recommended slice: `DATA-VALID-011-TARJETAS-SEGURO-UPLOAD-JSON-CONTRACT`.

## DATA-VALID-011-TARJETAS-SEGURO-UPLOAD-JSON-CONTRACT

- Scope: only `TarjetasController` and `SeguroController`; `departamento-operativo`, its views/assets, routes, middleware, and deferred services were untouched.
- `DATA-VALID-011-A`: upload validation is remediated locally. Tarjetas accepts one optional JPG/JPEG/PNG/PDF upload up to 5,242,880 bytes, requires a server-detected MIME/extension pair, rejects upload errors and spoofed extensions, and stores a server-generated filename. Existing authenticated download behavior remains public-storage based, so historical files and a future private-storage decision remain separate work.
- Tarjetas JSON contract is remediated locally for `POST /solicitud-tarjetas/update-reporte-formulario`: valid JSON is required; only the documented keys are accepted; `id` must be a positive integer; business strings are trimmed, non-empty, and bounded to their database-aligned limits; `tipo_tarjeta` uses the existing UI allowlist. Malformed JSON, wrong scalar types, overlong values, and unexpected keys return validation errors.
- `DATA-VALID-011-B`: upload validation is remediated locally. Seguro accepts one required JPG/JPEG/PNG/PDF upload up to 10,485,760 bytes, requires server-detected MIME/extension correspondence, rejects upload errors and spoofing, and stores a server-generated filename. Insurance policies and coverage may contain contract/customer information; public storage is not considered justified. New private storage would require extending the existing authorized download resolver without breaking current downloads.
- Historical Seguro files remain in the existing public location and require a separately approved inventory, migration, authorization, and rollback plan. Therefore `DATA-VALID-011-B` remains partial pending that storage slice.
- Regression coverage: `tests/data_valid_011_regression.php` covers valid files, invalid extensions, MIME spoofing, oversized files, partial uploads, and the strict JSON contract. The new suite passes 10 tests. Existing security baseline has one unrelated pre-existing failure in the XSS sink closure scan for `app/Views/revisionresultados/index.php`; no file in that view was changed here.

## SEC-XSS-005-REGRESSION-REASSESSMENT

- Regression detected: yes. `app/Views/revisionresultados/index.php` had no uncommitted changes, but commit `2882c40` changed all three closure-protected sinks from `DOMPurify.sanitize(...)` back to raw `x-html`: `implementacion.resultado`, `incidentes.semestre1`, and `incidentes.semestre2`.
- Root cause: a later merge/checkpoint reintroduced the three sinks; this was a real regression, not a test false positive or a newly discovered sink.
- Fix: restored the minimum three-line DOMPurify hunk. The values are persisted/generated report HTML and require HTML rendering; `x-text` would change behavior.
- Verification: SEC-XSS-005 closure now passes. Full security regression coverage is green: 72 passed, 0 failed, 0 skipped, including the 10 DATA-VALID-011 regression tests.
- Status: `SEC-XSS-005` remediated locally. No production files, deployment, or commit were created.

## DATA-VALID-011-B — public storage hardening closure

- Architecture decision: Seguro files must remain under `public/uploads/archivos/`; no private-storage migration or historical relocation is planned for this finding.
- `POLIZA_STORAGE_PATH` and `COBERTURA_STORAGE_PATH`: `public/uploads/archivos/poliza-seguro/`. Both endpoints retain the strict 10,485,760-byte limit, real MIME validation, extension/MIME correspondence, upload-error rejection, and server-generated random filenames.
- The original filename is used only to derive the validated extension; it is never used as a physical path. PHP, PHTML, double-extension, traversal-like, and other executable/scriptable extensions are rejected or neutralized by the server-generated name.
- Downloads use the authenticated `/download?tipo=poliza-seguro` controller flow, but direct static access remains possible because the required storage root is public. Directory listing and script execution behavior are deployment/server settings and remain `UNKNOWN` pending production verification.
- `PUBLIC_STORAGE_REQUIRED_BY_ARCHITECTURE: YES`; `PUBLIC_EXPOSURE: RESIDUAL_ARCHITECTURAL_CONSTRAINT`. This is not private storage and does not provide filesystem-level private-file guarantees.
- `DATA-VALID-011-B`: `UPLOAD_VALIDATION: REMEDIATED_LOCAL`; final status `REMEDIATED_LOCAL_WITH_RESIDUAL_PUBLIC_STORAGE_CONSTRAINT`.

## DATA-VALID-011-CLOSURE

- Final status: `REMEDIATED_LOCAL_WITH_DOCUMENTED_RESIDUAL_CONSTRAINTS` for all reviewed surfaces outside `departamento-operativo`.
- `DATA-VALID-011-A`: `REMEDIATED_LOCAL`. Tarjetas upload and JSON validation remain strict: real MIME, extension allowlist, size limit, server-generated filename, typed JSON, bounds, and unexpected-key rejection.
- `DATA-VALID-011-B`: `REMEDIATED_LOCAL_WITH_RESIDUAL_PUBLIC_STORAGE_CONSTRAINT`. Seguro remains at `public/uploads/archivos/poliza-seguro/` because public storage is required by architecture. Static access is possible; filenames are not predictable. Directory listing and script execution remain `PRODUCTION_VERIFICATION_PENDING` and are not new findings.

## DATA-VALID-011-REMOTE-VERIFICATION-RECONCILIATION

- The remediated Tarjetas and Seguro upload boundaries and typed JSON contract are `REMEDIATED_REMOTE_VERIFIED` at commit `2e3bad2c89b353ebef9d8df0e15f7927b6ae54da`; Security run `35377218278` passed `data_valid_011_regression.php` and reported `161 PASS / 0 FAIL / 0 SKIPPED`.
- Covered boundaries include server-detected MIME/extension allowlists, upload-size and upload-error checks, server-generated filenames, strict JSON parsing, typed/bounded fields, and unexpected-key rejection. Invalid inputs fail closed.
- Residual constraints are non-blocking and separately tracked: Seguro remains in architecturally required public storage (expected domain behavior); directory listing and script execution remain production verification; three `departamento-operativo` surfaces are deferred concurrency; authorization is tracked separately under `AUTHZ-DL-002`/tenant findings.
- No true unresolved validation vulnerability remains within the reviewed scope. This is not a blanket validation audit of future or deferred modules.
- Three `departamento-operativo` surfaces identified during reassessment remain deferred and excluded from this closure.
- Final regression baseline: 76 passed, 0 failed, 0 skipped. No functional code changed in this closure; production and historical files were untouched.

## SEC-CSP-012-REASSESSMENT

- Scope: read-only inventory outside `departamento-operativo`; six active layouts were reviewed (`auth`, `blank`, `configuracion`, `main`, `sasisopa`, `sgm`). No CSP implementation or layout change was made.
- Current state: `public/index.php` sends an enforced CSP, but it explicitly includes `'unsafe-inline'`, `'unsafe-eval'`, CDN script/style exceptions, `data:`, `blob:`, and `https:` image sources. No Report-Only header or CSP report endpoint was found.
- Inventory: 91 external script-source occurrences and 16 inline script blocks outside the excluded module; 17 legacy inline event-handler attributes; approximately 258 inline style attributes and 4 inline style blocks; Alpine expressions are widespread (`174` `x-data`, `16` `x-init`, and numerous `@click`/`@change` bindings). `eval()`/`new Function()` were not found in application code outside vendor bundles, but the current Alpine CDN build requires evaluation of expressions and therefore cannot be treated as strict-CSP compatible without a CSP-specific build/refactor.
- External domains: `cdn.jsdelivr.net` (DOMPurify, Axios, Iconify and other CDN assets), `unpkg.com` (Alpine CDN), `cdn.ckeditor.com` (CKEditor loader), and `www.admongas.com.mx` for a browser-side external API call. Server-side Telegram/Pwned Password calls are not browser CSP sources.
- Other directives: same-origin forms are the observed pattern, so `form-action 'self'` is feasible. No `<base>` was found, so `base-uri 'self'`/`none` is feasible. `frame-ancestors 'self'` is currently used; no third-party embedding requirement was found. A same-origin PDF `<embed>` exists, so `object-src 'none'` is not immediately compatible without changing that feature. No worker or WebSocket requirement was found.
- Nonce architecture: central per-request nonce generation is feasible in bootstrap/render context, and propagation to the six layouts is feasible. Existing dynamic inline blocks and event-handler attributes mean a nonce migration requires a deliberate refactor; static hash coverage is limited. Report-Only should precede enforcement, with an approved report collector/analysis process (none currently exists).
- Proposed conceptual Report-Only policy: `default-src 'self'; script-src 'self' https://cdn.jsdelivr.net https://unpkg.com 'unsafe-inline' 'unsafe-eval'; style-src 'self' https://cdn.jsdelivr.net 'unsafe-inline'; img-src 'self' data: blob: https:; font-src 'self' data: https://cdn.jsdelivr.net; connect-src 'self' https://www.admongas.com.mx; object-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'self'`. This is an inventory baseline, not an enforcement recommendation; `'unsafe-inline'` and `'unsafe-eval'` are temporary compatibility exceptions requiring removal work.
- Classification: `CSP_DEPLOYMENT_RISK: HIGH`; confirmed blockers for strict enforcement are inline scripts/handlers/styles, current Alpine evaluation behavior, external CDN dependencies, and the same-origin PDF embed. `SEC-CSP-012` is ready for a separately authorized Report-Only implementation slice, not closed.

## SEC-CSP-012-REPORT-ONLY-IMPLEMENTATION

- Status: `REPORT_ONLY_STAGE_IMPLEMENTED`; final finding status remains `PARTIAL_REPORT_ONLY_STAGE`.
- `public/index.php` now emits `Content-Security-Policy-Report-Only` at the same central bootstrap point as the existing enforced header. The enforced policy value is preserved byte-for-byte; no enforcement directive was changed or weakened.
- Candidate policy uses explicit script origins (`self`, jsDelivr, unpkg, and CKEditor), omits `script-src 'unsafe-inline'`, and retains `'unsafe-eval'` only as the documented temporary Alpine compatibility exception. `style-src 'unsafe-inline'` remains temporary for the observed inline style attributes. `connect-src` is limited to `self` and `https://www.admongas.com.mx`; `object-src 'self'`, `base-uri 'self'`, `form-action 'self'`, and `frame-ancestors 'none'` are present.
- No nonce architecture, inline-script migration, Alpine change, layout change, report endpoint, or `departamento-operativo` change was made. Violation collection remains browser-console-only; no manual browser observation was performed in this slice.
- Regression coverage: `tests/csp_report_only_regression.php` passes 8 tests, including exact enforcement preservation, Report-Only directives, no script inline exception, and no wildcard sources. Combined local security tests pass 84, fail 0, skipped 0.
- Production was not touched and no commit was created. Next authorized slice: `SEC-CSP-012-INLINE-SCRIPT-INVENTORY-REMEDIATION`.

## SEC-CSP-012-INLINE-SCRIPT-INVENTORY-REMEDIATION

- Status: `PARTIAL_INLINE_SCRIPT_REMEDIATION`; scope excludes `departamento-operativo` and its assets.
- Baseline inventory: 16 inline script blocks and 17 inline event-handler attributes. Current inventory remains 14 inline script blocks and 17 handlers. The two-block reduction is the static Highlight.js initialization extracted from `sgm.php` and `sasisopa.php` into `public/assets/js/core/highlight-init.js`.
- The extracted code has no PHP/request-specific data and preserves existing load order by remaining at the same layout footer position. No new bundler, dependency, or global refactor was introduced.
- Remaining inline scripts are classified as: dynamic JSON/configuration blocks (`window.temas`, `window.estacionesSgm`, `window.__PUESTOS__`, and `calibracion-data`) requiring a DOM/JSON or nonce design; shared Axios/CSRF and scroll bootstrap blocks requiring coordinated layout migration; and Alpine-dependent expressions, which remain deferred. No inline styles were touched.
- The 17 remaining inline event handlers are not changed in this slice; `javascript:` URLs remain pending for the same reason. `departamento-operativo` occurrences remain `DEFERRED_DEPARTAMENTO_OPERATIVO`.
- CSP enforcement remains unchanged. Report-Only remains enabled with `script-src` free of `'unsafe-inline'` and temporary `'unsafe-eval'` preserved. Regression test now passes 10 CSP checks; combined local security tests pass 82, fail 0, skipped 0.
- Production was not touched and no commit was created. Next slice: `SEC-CSP-012-NONCE-AND-ALPINE-PLAN`.

## SEC-CSP-012-NONCE-AND-ALPINE-PLAN

- This is a read-only design checkpoint. No production code, CSP header, Alpine asset, handler, or style was changed.
- CSP source is `public/index.php`, directly in the HTTP bootstrap `header()` calls; there is no separate CSP header service or middleware. The earliest central nonce source should therefore be a request-scoped security-header/bootstrap context created before the headers are emitted, with the same value passed into the renderer/layout data. It must be generated with `base64_encode(random_bytes(16))`, never from session/timestamp/client input, and never persisted or cookie-delivered. One nonce must serve the response header and all authorized inline scripts.
- Layout propagation should use the existing layout variables/context (`$scripts`, `$links`, and controller-provided view data), adding one `$cspNonce` value at the common render boundary rather than generating it independently in `main.php`, `sgm.php`, `sasisopa.php`, or `configuracion.php`. Active layouts outside `departamento-operativo` requiring access: 6 (`auth`, `blank`, `main`, `sgm`, `sasisopa`, `configuracion`); only layouts containing future nonce-authorized scripts need the attribute.
- The 12 dynamic candidates are: `public`/layout CSRF-Axios bootstrap blocks (main has two historical copies; sgm, sasisopa, configuracion have one each), `app/Views/gestoria/sgm.php` (`window.estacionesSgm`), `app/Views/cursos/modulo.php` (`window.temas`), `app/Views/personal/index.php` (`window.__PUESTOS__`), and the four `controlactividadproceso/*` `calibracion-data` JSON blocks. The PHP values are request data and must use JSON serialization/DOM transport, never string concatenation.
- Classification: `NONCE_REQUIRED=0`, `BETTER_EXTERNALIZE=4` (the four JSON/config bootstraps can move to `data-*` or inert JSON nodes), `JSON_DATA_BOOTSTRAP=8`, `REMOVE_AS_DEAD_CODE=0`, `NEEDS_MORE_REVIEW=0`. The remaining layout CSRF/scroll bootstrap code is static and should be externalized or consolidated before nonce adoption; it is not a reason to introduce a nonce by itself.
- No response-cache configuration was found in the repository; HTML caching is `UNKNOWN` and nonce cache risk is therefore `MEDIUM` until deployment/proxy behavior is verified. Nonce-bearing HTML must not be shared across requests with a mismatched CSP header.
- Alpine is loaded as the standard CDN build `https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js` in active layouts; a local standard build also exists at `public/assets/libs/alpinejs/dist/alpinejs.min.js`. Exact CDN version is unresolved (`3.x.x`), and the build is `STANDARD`, not CSP. The local bundle contains dynamic-function evaluation, so removing `'unsafe-eval'` requires an Alpine CSP build or equivalent refactor; changing only the URL is insufficient.
- Outside `departamento-operativo`, the inventory remains approximately `174 x-data`, `16 x-init`, `266 x-if`, `293 x-show`, and `658 x-model` attributes, plus extensive `@`/`:` bindings. Complexity is predominantly `INLINE_EXPRESSION`/`COMPLEX_EXPRESSION`; reusable component methods already exist in external `Alpine.data` files. Migration risk is `HIGH` because markup expressions, lifecycle timing, dynamic components, and representative authenticated modules must remain behaviorally compatible.
- The 17 handler inventory includes 12 in-scope legacy HTML handlers (logout and history actions) and 5 deferred `departamento-operativo` handlers. Nonce does not authorize any of them. In-scope handlers are mostly `EASY_EVENT_LISTENER` candidates (8 logout controls plus 4 history controls); deferred handlers remain `DEFERRED_DEPARTAMENTO_OPERATIVO`.
- Conditions to remove Report-Only `'unsafe-eval'`: activate and pin a verified Alpine CSP-compatible build; migrate unsupported expressions to `Alpine.data`/external listeners; scan application dependencies for `eval`/`new Function`; manually verify navigation, dropdowns, modals, forms, reactive fields, logout, SGM, SASISOPA, and Configuración; then observe a clean browser-console Report-Only run outside the excluded module.
- Recommended order: (1) nonce infrastructure and cache policy, (2) externalize/DOM-transport the 12 dynamic blocks, (3) migrate the 12 in-scope legacy handlers, (4) plan and pilot Alpine CSP migration, (5) remove Report-Only `'unsafe-eval'`, (6) browser validation, (7) only then consider enforcement tightening. `style-src 'unsafe-inline'` remains outside scope.
- Baseline security suite remains green at 82 passed, 0 failed, 0 skipped. `SEC-CSP-012` status: `PARTIAL_NONCE_ALPINE_PLAN_READY`.

## SEC-CSP-012-DYNAMIC-INLINE-ELIMINATION

- Status: `PARTIAL_DYNAMIC_INLINE_ELIMINATION`; `departamento-operativo` remains excluded.
- Converted the three executable PHP data bootstraps (`cursos/modulo.php`, `gestoria/sgm.php`, and `personal/index.php`) into non-executable `script type="application/json"` DOM payloads with `JSON_HEX_*` flags. Their external Alpine modules now parse the scoped DOM payloads and preserve the existing defaults/timing; no `window.*` data globals remain for these modules.
- The four calibration payloads were already `application/json` and remain unchanged. No nonce was introduced, no handlers were migrated, and Alpine/CSP were not changed.
- Dynamic executable inline inventory reduced from 12 to 9 (the remaining cases are shared layout CSRF/scroll bootstrap blocks requiring a coordinated extraction). Executable inline blocks now count 7; application/json blocks count 7.
- Enforced CSP remains unchanged; Report-Only remains present with no `script-src 'unsafe-inline'` and temporary `'unsafe-eval'`. PHP syntax checks and the existing security suite remain green at 82 passed, 0 failed, 0 skipped.
- Production was not touched and no commit was created. Next slice: `SEC-CSP-012-INLINE-EVENT-HANDLER-REMEDIATION` only after the remaining shared bootstrap blocks are separately resolved.

## SEC-CSP-012-INLINE-EVENT-HANDLER-REMEDIATION

- Inventory reconciliation: `EXECUTABLE_INLINE_SCRIPT_BLOCKS_BASELINE=7`, `DYNAMIC_EXECUTABLE_INLINE_BASELINE=9` was corrected to `7` (the prior 9 included non-executable JSON data), `STATIC_EXECUTABLE_INLINE_BASELINE=0`, `APPLICATION_JSON_DATA_BLOCKS_BASELINE=7`, and `INLINE_EVENT_HANDLERS_BASELINE=17` globally. Five handlers belong to excluded `departamento-operativo`; 12 were in scope.
- Migrated the 12 in-scope easy handlers: eight logout controls and four history-back controls. They now use `data-action` and the external delegated listener `public/assets/js/core/inline-handler-remediation.js`; no Alpine conversion or inline JavaScript replacement was used.
- `INLINE_EVENT_HANDLERS_AFTER=5`, all deferred to `departamento-operativo`. `JAVASCRIPT_URLS_AFTER=142` in the broader view inventory; remaining occurrences are outside the migrated handler set and require separate review.
- Executable inline script count remains 7 and application/json count remains 7; handler migration did not add inline scripts. Alpine, styles, enforced CSP, and Report-Only policy were unchanged.
- Updated the logout regression assertion to cover the new `data-action` contract. Full local security suite remains green: 82 passed, 0 failed, 0 skipped. `SEC-CSP-012` status: `PARTIAL_EVENT_HANDLER_REMEDIATION`.
- Production was not touched and no commit was created. Next slice: `SEC-CSP-012-ALPINE-CSP-MIGRATION-REASSESSMENT`.

## SEC-CSP-012-JAVASCRIPT-URL-REMEDIATION

- Reconciled the reported 142 matches outside `departamento-operativo`: all 142 are `javascript:void(0)` in first-party view templates. No executable `javascript:<function(...)>` URL was found in this inventory.
- Classification: `ACTIVE_FIRST_PARTY=142`; `THIRD_PARTY_VENDOR=0`; `DEAD_OR_UNUSED=0`; `COMMENT_OR_DOCUMENTATION=0`; `TEST_FIXTURE=0`; `DEFERRED_DEPARTAMENTO_OPERATIVO=0`; `FALSE_POSITIVE=0`. The matches are inert placeholder hrefs used by Bootstrap/Alpine controls, but remain active markup requiring semantic/button or listener migration rather than mechanical replacement.
- No vendor files, generated module HTML, Alpine expressions, or styles were changed. No safe single-hunk migration was identified that would preserve all dropdown/modal behavior across the affected templates without a broader component review.
- `ACTIVE_FIRST_PARTY_REMEDIATED=0`; active first-party URLs after remain 142 and raw matches remain 142. Plugin-generated JavaScript URLs: none identified in this view-template inventory.
- Executable inline scripts remain 7; application/json blocks remain 7; in-scope inline handlers remain 0. Alpine, styles, enforced CSP, and Report-Only policy are unchanged.
- Security suite remains green at 82 passed, 0 failed, 0 skipped. Status: `PARTIAL_JAVASCRIPT_URL_REMEDIATION`; next slice is `SEC-CSP-012-REMAINING-INLINE-SCRIPTS` for a scoped semantic migration plan.

## SEC-CSP-012-REMAINING-INLINE-SCRIPTS

- Exact executable inventory outside `departamento-operativo`: 6 blocks. The reported seventh block is the excluded `departamento-operativo` layout bootstrap.
- All six in-scope blocks are shared layout bootstrap logic: CSRF/Axios initialization in `main`, `sgm`, `sasisopa`, and `configuracion`; a second main response-retry bootstrap; and SGM scroll-state persistence.
- Classification: `EXTERNALIZE=0`, `JSON_DATA_BOOTSTRAP=0`, `REMOVE_DEAD_CODE=0`, `TEMPORARILY_BLOCKED=6`. Their duplicated interceptors and layout-specific retry/scroll timing require a coordinated shared-bootstrap design; removing one inline copy without that design could change behavior.
- No code was changed. Previously migrated JSON bootstraps remain untouched. Executable inline count remains 7 globally (6 in scope, 1 deferred), application/json remains 7, and no nonce was introduced.
- Alpine, styles, enforced CSP, and Report-Only remain unchanged. Security suite remains green: 82 passed, 0 failed, 0 skipped. Status: `PARTIAL_REMAINING_INLINE_SCRIPT_REMEDIATION`.

## SEC-CSP-012-JAVASCRIPT-VOID-PATTERN-REASSESSMENT

- Read-only pattern mapping of the 142 in-scope `javascript:void(0)` occurrences. No replacements were made.
- Four reusable functional patterns account for the inventory: `DROPDOWN_TRIGGER=24`, `COLLAPSE_TRIGGER=5`, `ALPINE_COUPLED_ACTION_TRIGGER=44`, and `PLACEHOLDER_LINK=69`; total 142. No modal or tab-specific pattern was found in the current view-template inventory.
- The dropdown/collapse cases are predominantly repeated Modernize/Bootstrap theme markup. Shared layout/menu sources exist for the repeated header/sidebar triggers; other occurrences are direct view or generated-table templates. Alpine-coupled triggers must not be converted to native buttons in this slice because their `@click`/component context is a separate migration concern.
- Pattern strategy: dropdown/collapse should retain Bootstrap `data-bs-toggle`/`data-bs-target` and use semantic buttons where the shared theme allows it; Alpine-coupled actions require an Alpine-compatible plan; placeholder links should become buttons or real anchors only after selector/plugin review. A global `href="#"` replacement is unsafe.
- Classification by implementation priority: `P1_LOW_RISK_SHARED_FIX=0` pending confirmation of all shared selectors; `P2_LOW_RISK_LOCAL_FIX=0`; `P3_MEDIUM_RISK=2` (dropdown/collapse); `DEFERRED_COMPLEX=2` (Alpine-coupled and placeholder links). Theme-pattern matches: 29; shared-component candidates: 24; direct-view/generated matches: 89.
- Bootstrap-native replacement is available for 29 dropdown/collapse triggers. Semantic button candidates are 29, real navigation links 0, plugin-coupled cases 0 confirmed, Alpine-coupled cases 44, unknown remaining 0.
- The six in-scope executable inline blocks and five deferred handlers were not touched. CSP, Alpine, styles, and Report-Only remain unchanged. Status: `PARTIAL_JAVASCRIPT_VOID_PATTERN_MAPPED`.

## SEC-CSP-012-BOOTSTRAP-NATIVE-TRIGGER-REMEDIATION

- Reconciled targets: `DROPDOWN_TRIGGER=24` and `COLLAPSE_TRIGGER=5`; total 29. The counts match the current first-party view inventory.
- No implementation was applied. The targets are spread across shared Modernize layout markup and generated/module templates; `public/assets/js/theme/sidebarmenu.js` still relies on anchor selectors (`li.querySelector("a")` and `link.closest("a")`). A global anchor-to-button change therefore requires a shared selector/accessibility compatibility patch and representative browser validation.
- `DROPDOWN_TRIGGERS_REMEDIATED=0`; `COLLAPSE_TRIGGERS_REMEDIATED=0`; all 29 remain temporarily blocked by theme selector and cross-template behavior risk. Alpine-coupled (44) and placeholder-link (69) groups remain untouched.
- No custom JavaScript, Alpine, styles, CSP, or inline scripts were changed. Security suite remains green at 82 passed, 0 failed, 0 skipped. Status: `PARTIAL_BOOTSTRAP_TRIGGER_REMEDIATION`.

## SEC-CSP-012-PLACEHOLDER-LINK-REASSESSMENT

- Read-only mapping of the 69 remaining placeholder links; no code was changed. The 29 Bootstrap triggers and 44 Alpine-coupled triggers remain excluded.
- Principal pattern grouping: `BUTTON_ACTION=35`, `JS_SELECTOR_DEPENDENT=20`, `CSS_TAG_DEPENDENT=14`; total 69. No demonstrable real-navigation or pure inert-only group was confirmed without runtime/component review.
- Theme dependency is present in the repeated Modernize navigation/dropdown markup; shared selector dependency is present in `public/assets/js/theme/sidebarmenu.js`. Alpine-dependent cases remain deferred and no Alpine migration was attempted.
- Accessibility finding: these anchors are focusable placeholders, so replacing them requires confirming keyboard behavior, Bootstrap/Alpine selectors, and visual theme rules. No safe low-risk candidate was approved from static inspection alone.
- Classification: `PURE_PLACEHOLDER=0`, `BUTTON_ACTION=35`, `REAL_NAVIGATION=0`, `JS_SELECTOR_DEPENDENT=20`, `CSS_TAG_DEPENDENT=14`, `THEME_DEPENDENT=29`, `ALPINE_DEPENDENT=0` in this disjoint principal grouping, `INERT_BUT_FOCUSABLE=69`, `UNKNOWN=0`.
- Remediation priority: `LOW_RISK_REMEDIATION=0`, `MEDIUM_RISK_REMEDIATION=35`, `DEFERRED_COMPLEX=34`, `SHARED_FIX_CANDIDATES=29`. Recommended next step is a scoped semantic migration with browser/accessibility validation; no global `#` or `<button>` replacement.
- CSP, Alpine, styles, the six blocked inline scripts, and Bootstrap triggers remain unchanged. Security suite remains green: 82 passed, 0 failed, 0 skipped. Status: `PARTIAL_PLACEHOLDER_LINKS_MAPPED`.

## SEC-CSP-012-SHARED-INLINE-SCRIPT-DESIGN

- Read-only design inventory: six in-scope executable blocks are `main.php` CSRF/Axios bootstrap (head), `main.php` duplicate footer response/retry bootstrap, `sgm.php` CSRF/Axios bootstrap, `sgm.php` scroll-state bootstrap, `sasisopa.php` CSRF/Axios bootstrap, and `configuracion.php` CSRF/Axios bootstrap. The seventh block is the excluded `departamento-operativo` bootstrap.
- Shared concepts: `CSRF_BOOTSTRAP=6`, `AXIOS_CONFIGURATION=6`, `419_HANDLING=2`, `RETRY=2`, `SCROLL=1`, `DOM_TIMING=1`; no shared UI/error-display implementation was found. The four non-main blocks only configure request headers; main additionally retries once after a 419 and reloads on failure.
- `AXIOS_BOOTSTRAP_DUPLICATED=YES`. No central external Axios configuration currently exists; the current source is duplicated in the layout views. The CSRF meta tag is the correct shared source; no token should be copied to JSON, globals, or cookies.
- `AUTOMATIC_RETRY_SAFE=MIXED`: a generic retry could duplicate POST/PUT/PATCH/DELETE effects. The future helper must default to bounded reload/error handling and only retry explicitly safe/idempotent requests with a one-attempt guard.
- `SHARED_ABSTRACTION_JUSTIFIED=YES` for transport-only CSRF/Axios setup. It is not justified to create a god-helper for response UX, business actions, or scroll. Recommended components: `public/assets/js/core/http-security.js` for token/header setup and bounded 419 classification; a separate layout-specific scroll-state module for SGM; main-specific response policy retained outside the transport helper.
- Planned migration: main head block → `http-security.js` plus explicit response policy; main footer duplicate → consolidate only after proving it is redundant; SGM CSRF block → shared helper; SGM scroll block → layout-specific external file; SASISOPA and Configuración CSRF blocks → shared helper. All use existing meta CSRF and preserve current load order. Expected risk: medium for CSRF extraction, high for main retry consolidation, low/medium for SGM scroll extraction.
- `NONCE_REQUIRED_AFTER_REVIEW=0`; expected executable inline count after a future implementation is 0 in scope, with the department-operativo block still deferred. `javascript:void(0)` remains deferred because there are no low-risk candidates.
- No code was changed. Alpine, styles, CSP enforcement, Report-Only, and the six inline blocks remain unchanged. Security suite remains green: 82 passed, 0 failed, 0 skipped. Status: `PARTIAL_SHARED_INLINE_SCRIPT_DESIGN_READY`.

## SEC-CSP-012-SHARED-INLINE-SCRIPT-IMPLEMENTATION-A

- Created `public/assets/js/core/http-security.js` with one responsibility: read the existing CSRF meta tag and configure Axios defaults when Axios and a non-empty token exist. It contains no UI, retry, interceptor, navigation, or business logic.
- Created `public/assets/js/sgm/scroll-state.js` preserving SGM pathname/sessionStorage behavior and `DOMContentLoaded` timing.
- Externalized `MAIN_CSRF_HEAD`, `SGM_CSRF`, `SGM_SCROLL`, `SASISOPA_CSRF`, and `CONFIGURACION_CSRF`. Axios is loaded before the helper and the helper is loaded before module footer scripts/requests in each layout.
- `MAIN_RESPONSE_RETRY` remains inline and unchanged as the only in-scope executable block. The department-operativo inline block remains deferred. No automatic retry was added to the shared helper.
- Added `tests/http_security_regression.php` covering meta-token source, Axios header configuration, absence of hardcoded tokens/retry, and external asset loading. Updated logout regression expectations for the shared helper.
- In-scope executable inline scripts: `6 → 1`; application/json blocks remain 7. `javascript:void(0)` remains deferred at 142. Alpine, styles, enforced CSP, and Report-Only remain unchanged.
- Full local security suite is green: 86 passed, 0 failed, 0 skipped (including 4 new HTTP-security checks). Status: `PARTIAL_SHARED_INLINE_IMPLEMENTATION_A`.

## SEC-CSP-012-MAIN-RESPONSE-RETRY-REASSESSMENT

- The remaining inline block is at the footer of `app/Views/layouts/main.php` (approximately lines 281–313). Despite its historical label, the current code does not replay requests: it registers Axios request and response interceptors, refreshes the request header from the CSRF meta tag, and reloads the page on HTTP 419.
- `REQUEST_INTERCEPTOR=YES`; `RESPONSE_INTERCEPTOR=YES`; `GLOBAL_AXIOS_INTERCEPTOR=YES`. The response policy handles only 419; there is no 401/403/5xx/network UI policy, refresh endpoint, token-fetch request, or redirect.
- `RETRY_APPLIES_TO=NONE`; `NON_IDEMPOTENT_REQUESTS_CAN_RETRY=NO`; `DUPLICATE_SIDE_EFFECT_RISK=NONE` for the current code. `BOUNDED_RETRY=YES` with `MAX_RETRIES=0`; infinite retry risk is `NO`.
- `419_BEHAVIOR=RELOAD_PAGE`. `REFRESH_ENDPOINT=NONE`; `REFRESH_METHOD=NONE`; `REFRESH_FAILURE_BEHAVIOR=NOT_APPLICABLE`. CSRF and authentication expiration are not distinguished beyond the status code (`CSRF_AND_AUTH_EXPIRATION_DISTINGUISHED=NO`).
- `MULTIPLE_REGISTRATION_POSSIBLE=YES` if the layout is evaluated repeatedly in one document, because the interceptor registration is unconditional; normal full-page navigation registers it once. The existing shared `http-security.js` correctly owns only the default CSRF header and must not absorb this page-specific response policy.
- No idempotency-key infrastructure was identified for a generic retry. Recommended policy is: GET/HEAD no replay is currently needed; POST/PUT/PATCH/DELETE do not retry automatically; 419 reload remains the safest current UX until a dedicated response-policy implementation is approved.
- `PHP_DYNAMIC_DATA_REQUIRED=NO`; externalization is feasible to a main-specific file such as `public/assets/js/core/main-response-policy.js`, preserving footer timing after Axios and existing module assets. Implementation risk: medium due global interceptor scope and UX compatibility, not request duplication.
- No code was changed. `EXPECTED_IN_SCOPE_EXECUTABLE_INLINE_AFTER=0` after the future implementation. `javascript:void(0)` remains deferred; Alpine and CSP remain unchanged. Security baseline remains 86 passed, 0 failed, 0 skipped. Status: `PARTIAL_MAIN_RESPONSE_RETRY_REASSESSED`.

## SEC-CSP-012-MAIN-RESPONSE-RETRY-IMPLEMENTATION

- Externalized the remaining main response policy to `public/assets/js/core/main-response-policy.js`. The inline block was removed from `app/Views/layouts/main.php` and replaced with the external asset after Axios and `http-security.js`.
- Preserved behavior: request interceptor refreshes the current meta CSRF header; response success passes through; HTTP 419 calls `window.location.reload()`; no request replay, token refresh, or retry was added; other errors remain rejected.
- Added a small initialization guard to prevent duplicate interceptor registration. The guard stores only a boolean state and no sensitive data.
- Added `tests/main_response_policy_regression.php` covering externalization, single registration, 419 reload/no replay, and non-419 error propagation.
- In-scope executable inline scripts are now `0`; the one deferred `departamento-operativo` block remains untouched. `javascript:void(0)`, Alpine, styles, enforced CSP, and Report-Only remain unchanged.
- Full local security suite: 90 passed, 0 failed, 0 skipped. Status: `PARTIAL_NO_IN_SCOPE_EXECUTABLE_INLINE_SCRIPTS`.

## SEC-CSP-012-CURRENT-STATE-CLOSURE

- Intermediate checkpoint, no new implementation. Outside `departamento-operativo`, executable inline scripts are closed at `0` and inline event handlers at `0`. One executable inline block and five handlers remain deferred inside `departamento-operativo`.
- Enforced CSP remains present and unchanged. Report-Only remains present with `script-src` without `'unsafe-inline'` and temporary `'unsafe-eval'`.
- `javascript:void(0)` remains `142`, classified as `DEFERRED_ARCHITECTURAL_UI_REFACTOR`: 29 Bootstrap/Modernize triggers require coordinated selector changes, 44 Alpine-coupled triggers require Alpine migration, and 69 placeholder links have no low-risk bulk path.
- Alpine remains the standard build and requires a future CSP migration reassessment; inline styles remain pending. `http-security.js` owns only CSRF meta lookup/Axios default configuration; `main-response-policy.js` reloads on 419 without replay and is registration-guarded.
- `SEC-CSP-012` status: `PARTIAL_HARDENING_CHECKPOINT`. Completed: Report-Only, zero in-scope executable inline scripts/handlers, centralized CSRF/Axios bootstrap, and externalized main response policy. Pending: Alpine/unsafe-eval, javascript:void family, inline styles, deferred module, and eventual enforcement tightening.

## DEP-TEST-013-DEPENDENCY-AND-TEST-POSTURE-REASSESSMENT

- PHP dependency management is present and lock-backed: `composer.json` + `composer.lock`; PHP requirement is `^8.2`, with 17 direct production packages and 179 locked package records (including transitive dependencies). No custom repositories or Composer scripts were declared; no dev dependency section is present.
- Composer 2.10.3 is available. `composer audit` and `composer outdated --direct` could not query Packagist because DNS/network access is unavailable in this environment; therefore vulnerability/update counts are `UNKNOWN`, not zero. No update command was run.
- No `package.json`, npm/yarn/pnpm lockfile, or frontend package manager configuration exists. Frontend dependencies are served from committed assets and CDN references. 24 CDN references were found in views; no `integrity`/SRI attributes were found. CDN versions are mixed: Alpine uses floating `3.x.x`, while other URLs include explicit library versions or unversioned CDN paths. This is a reproducibility/supply-chain risk, not by itself a confirmed runtime vulnerability.
- Test inventory contains 17 files, including security regression, HTTP-router, CSRF, session, tenant/authorization, upload, XSS, CSP, logging, rate-limit, webhook, and data-validation coverage. Existing commands are individual PHP entrypoints; no Composer/NPM test script is defined.
- No CI workflow, pipeline definition, Dependabot configuration, or Renovate configuration was found. `CI_PRESENT=NO`, `SECURITY_TESTS_IN_CI=NO`, `DEPENDENCY_AUDIT_IN_CI=NO`, `DEPENDABOT_PRESENT=NO`, `RENOVATE_PRESENT=NO`.
- Test posture from the current harness: baseline remains 90 passed, 0 failed, 0 skipped. Tests use temporary router/session/storage paths in several suites; filesystem isolation is `PARTIAL`, DB isolation is `UNKNOWN`, and order dependency is `UNKNOWN` because the suite has no unified runner or CI orchestration.
- Reproducibility: PHP install `GOOD` due to composer.lock and explicit PHP constraint; frontend install `POOR` because there is no package manifest/lockfile and CDN assets include floating/unpinned references.
- Findings: `DEP-TEST-013-A` (MEDIUM, CONFIRMED): frontend dependency reproducibility/supply-chain posture is weak due to CDN-only, partly floating dependencies and absent SRI; production reachability depends on rendered layouts. `DEP-TEST-013-B` (LOW, CONFIRMED): dependency audit and security regression tests are not automated in CI. Composer vulnerability status is `NOT_REPRODUCED/UNKNOWN` pending network-capable audit, not a confirmed vulnerable package finding.
- Recommended small slices: `DEP-TEST-013-A-CDN-INVENTORY-AND-PINNING-PLAN` (no changes yet), then `DEP-TEST-013-B-CI-SECURITY-TESTS-DESIGN`, followed by a separately authorized Composer audit/update review when network access is available. No mass updates are authorized.

## DEP-TEST-013-A-CDN-INVENTORY-AND-PINNING-PLAN

- Inventory reconciled to the established `RAW_CDN_REFERENCES=24` view references, including shared/deferred layout references. Unique conceptual libraries: 4 — DOMPurify, Alpine, Axios, and Iconify.
- CDN domains: `cdn.jsdelivr.net` (18 references, HTTPS; DOMPurify, Axios, Iconify), `unpkg.com` (6 references, HTTPS; Alpine). No `cdn.ckeditor.com`, Google Fonts, cdnjs, or other remote domain was found in the current view inventory.
- Version styles: exact pins include DOMPurify `3.0.6` and Iconify `1.0.8`; Alpine `3.x.x` is a major float; Axios URLs are unversioned/latest-style. No SRI attributes were found. Approximate classification: `EXACT_PIN=10`, `MAJOR_FLOAT=6`, `UNVERSIONED=8`; no patch/minor/latest aliases were separately identified.
- Duplications: all four libraries are repeated across layouts; this is intentional shared-layout loading but creates update/SRI coordination. No conflicting versions were found (`VERSION_CONFLICTS=0`).
- Runtime scope: DOMPurify, Alpine, Axios, and Iconify are `GLOBAL_LAYOUT`; the department-operativo fallback references remain deferred. DOMPurify/Alpine/Axios are HIGH runtime criticality; Iconify is MEDIUM. Floating/unversioned references can alter production behavior without a repository change.
- Pinning feasibility: DOMPurify and Iconify are `P1_SAFE_PINNING` because the effective versions are explicit; Alpine and Axios are `P4_UNKNOWN_VERSION` until the currently effective artifacts are established. SRI is feasible only after exact immutable URLs are selected. Self-hosting is technically feasible for all four but requires license, asset, and update review; no assets were downloaded.
- Recommended strategy: exact pinning first without upgrades; runtime smoke validation of authenticated layouts and security suite; then SRI for immutable CDN resources; then evaluate self-hosting/CSP domain reduction independently. Alpine pinning must not be combined with its CSP-build migration.
- `CDN_URLS_CHANGED=NO`, `COMPOSER_CHANGED=NO`, `NPM_MANIFEST_CREATED=NO`, `SRI_ADDED=NO`, `ALPINE_CHANGED=NO`, `CSP_CHANGED=NO`. Status: `DEP-TEST-013-A: PINNING_PLAN_READY`.

## DEP-TEST-013-A-CDN-INVENTORY-RECONCILIATION

- Rebuilt the inventory from first-party view files with `departamento-operativo` excluded. The exact in-scope total is `20`, not 24. The previous 24 included the four deferred `departamento-operativo` layout references (DOMPurify, Alpine, Axios, Iconify once each).
- In-scope references by library: DOMPurify `4` at `https://cdn.jsdelivr.net/npm/dompurify@3.0.6/dist/purify.min.js` (`EXACT_PIN`); Iconify `6` at `https://cdn.jsdelivr.net/npm/iconify-icon@1.0.8/dist/iconify-icon.min.js` (`EXACT_PIN`); Alpine `5` at `https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js` (`MAJOR_FLOAT`); Axios `5` at `https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js` (`UNVERSIONED`).
- Domains reconcile exactly: `cdn.jsdelivr.net=15`, `unpkg.com=5`, total 20. `CDN_WITH_SRI=0`, `CDN_WITHOUT_SRI=20`. No missing or misclassified in-scope reference remains; the discrepancy was scope leakage from the excluded layout.
- Version-style totals: `EXACT_PIN=10`, `PATCH_FLOAT=0`, `MINOR_FLOAT=0`, `MAJOR_FLOAT=5`, `LATEST_ALIAS=0`, `UNVERSIONED=5`, `UNKNOWN=0`; total 20. Floating references are 10 and reconcile exactly.
- `REFERENCES_ALREADY_EXACT_PINNED=10`; `FLOATING_WITH_KNOWN_EFFECTIVE_VERSION=0`; `FLOATING_WITH_UNKNOWN_EFFECTIVE_VERSION=10`; `ALREADY_PINNED_SRI_CANDIDATES=10`; `SAFE_PINNING_CANDIDATES=0`; `NEEDS_VERSION_DISCOVERY=10`.
- No URLs, versions, SRI, Alpine, CSP, Composer, or npm files were changed. Security baseline remains 90 passed, 0 failed, 0 skipped. Status: `INVENTORY_RECONCILED`; next step is version discovery for the 10 floating references, not blind pinning.

## DEP-TEST-013-A-CDN-VERSION-DISCOVERY

- Confirmed five in-scope Alpine references at `https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js` and five in-scope Axios references at `https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js`. The four department-operativo fallback references remain excluded.
- Network resolution to both CDN domains failed in this environment (`curl` DNS error), so no redirect chain, served banner, or response version could be confirmed. No files were downloaded into the repository.
- Alpine effective version: `UNKNOWN`, evidence `UNKNOWN`; local `public/assets/libs/alpinejs/dist/alpinejs.min.js` has no unambiguous version banner. Build remains `STANDARD`; safe pinning: `NO`.
- Axios effective version: `UNKNOWN`, evidence `WEAK`; the local fallback `public/assets/libs/axios/dist/axios.min.js` contains an `Axios v1.7.9` banner, but this does not prove the unversioned CDN resource currently serves that version. Safe pinning: `NO`.
- Local API compatibility evidence only: Alpine uses `x-data`, `x-show`, `x-model`, `x-for`, `x-init`, event bindings, and `Alpine.data`; Axios uses `get`, `post`, defaults, and interceptors. These are compatibility hints, not version proof.
- `FLOATING_REFERENCES_BEFORE=10`, `FLOATING_REFERENCES_AFTER=10`; `SAFE_PINNING_CANDIDATES_AFTER_DISCOVERY=0`; `NEEDS_FURTHER_VERSION_DISCOVERY=10`. URLs, SRI, Alpine, CSP, and npm were unchanged. Status: `PARTIAL_VERSION_DISCOVERY`.

## DEP-TEST-013-A-RESOLVE-VERSION-DISCOVERY-ALPINE-AXIOS

- Axios local fallback: `public/assets/libs/axios/dist/axios.min.js`, unambiguous banner `Axios v1.7.9`, SHA-256 `9cf48244581d6cb6486d6702f7372292284faef2489a3be419ac1bc70606be72`. It is referenced by the excluded department-operativo fallback path and is a project-local runtime artifact; its runtime reachability for the five CDN layouts is `UNKNOWN` because those layouts load CDN first. Current first-party APIs (`get`, `post`, `defaults`, `interceptors`) are compatible with the local 1.7.9 artifact. Git history contains a 1.7.9 occurrence, but this does not prove the CDN served that version.
- Axios classification: `CDN_SERVED_VERSION=UNKNOWN`, `HISTORIC_CDN_VERSION_PROVEN=NO`, `LOCAL_COMPATIBILITY_TARGET=1.7.9`, `EVIDENCE_CLASSIFICATION=LOCAL_COMPATIBILITY_TARGET_CONFIRMED`, `SAFE_TO_PIN_CURRENT_EFFECTIVE_VERSION=NO`, `SAFE_TO_PIN_LOCAL_COMPATIBILITY_TARGET=YES` pending explicit approval and runtime validation.
- Alpine local fallback: `public/assets/libs/alpinejs/dist/alpinejs.min.js`, SHA-256 `3ed1eed252488921df65e363d6715deb04d7f92aaedb9e52199fdf73cb1e0ad3`. No version banner, package metadata, source map, or exact historical `alpinejs@3.x.y` reference was found. Alpine classification remains `CDN_SERVED_VERSION=UNKNOWN`, `LOCAL_COMPATIBILITY_TARGET=UNKNOWN`, `EVIDENCE_CLASSIFICATION=INSUFFICIENT_EVIDENCE`, and both current/equivalent pinning decisions are `NO`.
- Network remained unavailable, so no CDN final URL or redirect was observed. API usage remains compatibility evidence only; Alpine stays `STANDARD` and its CSP migration remains separate. No URLs, SRI, npm, Alpine, or CSP changed. Status: `PARTIAL_VERSION_DISCOVERY` with Axios local target resolved and Alpine unresolved.
## DEP-TEST-013-A-AXIOS-COMPATIBILITY-PINNING

- Five first-party Axios CDN references in scope were pinned to `https://cdn.jsdelivr.net/npm/axios@1.7.9/dist/axios.min.js`.
- This is a `COMPATIBILITY_PIN` / `LOCAL_COMPATIBILITY_TARGET`; the historic CDN-served version remains unproven.
- `departamento-operativo` remains excluded and its local-fallback/reference pair is unchanged.
- The local Axios fallback remains version `1.7.9` with SHA256 `9cf48244581d6cb6486d6702f7372292284faef2489a3be419ac1bc70606be72`.
- No SRI, Alpine, CSP, API, interceptor, CSRF, production, or package-manifest changes were made.
- Static pinning regression coverage passes; full executed security regression subset remains green (90 baseline plus 5 pinning checks).
- `DEP-TEST-013-A` remains partial: Alpine is still floating, SRI is absent, and later self-hosting/CSP domain reduction remain deferred.
## DEP-TEST-013-A-CDN-SRI-PLAN

- Inventory confirmed 15 exact-pinned in-scope CDN references: DOMPurify 3.0.6 (4), Iconify 1.0.8 (6), and Axios 1.7.9 (5). Alpine remains excluded because it is floating (`3.x.x`).
- All three libraries use one unique exact-version URL per library. Exact versions make SRI technically plausible, but CDN response bodies, redirects, content types, and CORS headers could not be verified because DNS/network access is unavailable.
- No SRI hashes were invented. All three resources therefore remain pending network verification; recommended algorithm is `sha384` and `crossorigin="anonymous"` is recommended pending CDN validation.
- Axios local fallback remains available and unchanged; DOMPurify and Iconify have no local fallback found. Iconify secondary remote loading remains unknown.
- SRI implementation is not ready for execution. No `integrity`, `crossorigin`, CSP, Alpine, version, dependency, production, or package-manifest changes were made.
- `DEP-TEST-013-A: SRI_PLAN_READY` with implementation gated on network retrieval and browser/runtime validation.
## DEP-TEST-013-B-CI-SECURITY-TESTS-DESIGN

- Repository host is GitHub (`github.com/DepAdmonGas/Portal3`); no CI provider configuration exists in the repository. Recommended provider/path: GitHub Actions at `.github/workflows/security.yml`, to be created only in the implementation slice.
- Runtime requirements are PHP `^8.2` and Composer lock-backed installation. No Composer scripts or dev dependencies exist; use `composer install --no-interaction --prefer-dist --no-progress` without `--no-dev`, then run the explicit PHP regression entrypoints. `composer.lock` is present in the working tree, although ignored by the current `.gitignore`; this should be resolved before CI implementation because reproducible CI requires the lockfile to be versioned.
- No single test entrypoint exists. The current suite consists of individual `tests/*_regression.php` scripts with reliable non-zero exits on failure. A future implementation should add a small fail-closed runner or explicit ordered command list; no runner was created in this design slice.
- Proposed minimal jobs: `php-syntax` (lint `app`, `public`, and `tests`, excluding `vendor`), `security-tests` (all current regression entrypoints, including CSP and dependency pinning checks), and independent `dependency-audit` (`composer audit --locked`). Audit network/DNS failure must be reported as `AUDIT_UNAVAILABLE`, never as zero vulnerabilities.
- Tests are currently local/static and use temporary session, storage, and router fixtures; no database connection is required by the regression suite. Filesystem isolation is partial, so the future runner must execute in a clean workspace and use writable temporary paths rather than broad permissions. Relevant writable areas are `storage/` and test-created temporary directories; uploads are not a CI artifact target.
- CI environment names should be limited to test-safe configuration such as `APP_ENV`, `APP_TIMEZONE`, and (only if bootstrap requires them) `DB_CONNECTION`, `DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`; production credentials, Telegram secrets, mail credentials, and real `.env` files must never enter CI.
- External network is not required for the local security tests; Composer install and audit require package-network access. No npm job is justified because there is no npm manifest. No deploy, SSH, Plesk, migration, or write permission is part of the design; recommended permissions are read-only repository contents.
- Suggested triggers are push and pull_request on the repository's existing branches, to be confirmed during implementation; no cron is proposed. Composer cache may be used only as an optimization while `composer.lock` remains authoritative.
- `DEP-TEST-013-B: CI_DESIGN_READY`. Implementation risk: LOW, contingent on first versioning/confirming `composer.lock` and documenting the runner order/isolation assumptions.
## DEP-TEST-013-B-COMPOSER-LOCK-CI-READINESS

- Portal3 is a deployable web application (`app/`, controllers/views, `public/index.php`), so `composer.lock` should be versionable for reproducible CI installs.
- The lockfile was not tracked because `.gitignore:8` explicitly ignored `composer.lock`; no repository-local or global ignore rule justified the exclusion.
- Removed only that ignore entry. `composer.lock` is now visible as an untracked versionable file; no `git add`, commit, push, reset, restore, clean, Composer install, or Composer update was performed.
- `composer validate --no-check-publish` passed with only the pre-existing missing-license warning. Lockfile SHA256 remained `489acf71dcf6a417681d59156e40c522734efdd2ce9ed2f3544c6a9b7818b9d2` before and after the change.
- Composer JSON and dependency versions were unchanged. The security regression suite remains green; all current `tests/*_regression.php` entrypoints completed successfully (95 PASS, 0 FAIL, 0 SKIPPED baseline).
- `DEP-TEST-013-B: COMPOSER_LOCK_CI_READY`; GitHub Actions and a unified runner remain deferred to the next implementation slice.
## DEP-TEST-013-B-CI-SECURITY-TESTS-IMPLEMENTATION

- Added `.github/workflows/security.yml` for GitHub Actions with read-only `contents: read` permissions and only `push`/`pull_request` triggers. No deployment, production secrets, SSH, migration, or write capabilities are present.
- Added `tests/run_security_suite.php`, an explicit deterministic runner for 13 current `*_regression.php` security scripts. Each script runs in its own PHP process and any non-zero exit fails the runner.
- Added `tests/ci_workflow_regression.php` for static workflow safety checks. It is not included in the security runner to avoid self-referential CI validation.
- Jobs implemented: `php-syntax` (PHP 8.2 lint over `app`, `public`, `tests`), `security-tests` (Composer lock install plus runner), and independent `dependency-audit` (`composer audit --locked`). Audit failures, including network unavailability, remain blocking and are not converted to success.
- Composer uses `composer install --no-interaction --prefer-dist --no-progress`; no update was run. `composer.lock` remains unchanged with SHA256 `489acf71dcf6a417681d59156e40c522734efdd2ce9ed2f3544c6a9b7818b9d2`.
- Local PHP lint passed, workflow static validation passed (6 checks), and the runner passed 99 assertions across 13 scripts. The historical 95-assertion baseline is exceeded because the current inventory includes all active regression checks; failures/skips remain 0/0. GitHub-hosted execution has not occurred because no push was made.
- `DEP-TEST-013-B: REMEDIATED_LOCAL_PENDING_REMOTE_CI_EXECUTION`.
## DEP-TEST-013-B-CI-REMOTE-READINESS-CHECK

- CI files are present and versionable: `.github/workflows/security.yml`, `tests/run_security_suite.php`, `tests/ci_workflow_regression.php`, `.gitignore`, and this progress log. `composer.lock` exists, is no longer ignored, remains untracked, and must be included in the future checkpoint (`SHA256=489acf71dcf6a417681d59156e40c522734efdd2ce9ed2f3544c6a9b7818b9d2`).
- Workflow path/command review found no broken references, no `composer update`, no secrets, no write permissions, no database services, and no deployment capability. Actions are `actions/checkout@v4` and `shivammathur/setup-php@v2` (major-tag references).
- The runner explicitly includes 13 regression scripts. The discovered set contains 14 because `tests/ci_workflow_regression.php` is a static self-check and is intentionally excluded; no security regression file is missing.
- Runner fail-closed behavior is confirmed: current green exit code is 0, and child non-zero exits increment failure status. Local runner result is 99 PASS / 0 FAIL / 0 SKIPPED; PHP syntax validation passes. YAML parser execution was unavailable locally, so validation is structural/static only.
- Composer lock packages are compatible with PHP 8.2; no `config.platform.php` override is defined. Required runtime extensions resolved by the lock include common PHP extensions (`ctype`, `dom`, `fileinfo`, `filter`, `gd`, `hash`, `iconv`, `json`, `libxml`, `mbstring`, `pcre`, `pdo`, `simplexml`, `xml`, `xmlreader`, `xmlwriter`, `zip`, `zlib`); setup-php supplies the standard runtime, with hosted execution still pending.
- `DEP-TEST-013-B: REMOTE_CI_READY_PENDING_VERSION_CONTROL`. No commit or push was made; GitHub-hosted execution remains unverified.
## RESOLVE-REMOTE-CI-PHP-VERSION-CONFLICT

- GitHub Actions run `35260755896` for commit `f2a7689` passed `php-syntax` but both Composer jobs failed before tests/audit because locked packages `symfony/clock v8.1.0` and `symfony/translation v8.1.1` require PHP `>=8.4.1`.
- Updated only `.github/workflows/security.yml`, changing all three jobs from PHP 8.2 to PHP 8.4. `composer.json`, `composer.lock`, dependency versions, application code, Alpine, CSP, and SRI were not changed.
- The project documentation and root constraint remain PHP 8.2+; this workflow adjustment targets the effective locked dependency runtime and does not assert production PHP compatibility.
## DEP-TEST-013-C-GUZZLE-ADVISORY-REMEDIATION

- Updated direct dependency `guzzlehttp/guzzle` from `7.15.1` to patched `7.15.2` using a targeted Composer update; related required updates were `guzzlehttp/promises 2.5.1 → 2.5.3` and `guzzlehttp/psr7 2.13.0 → 2.13.1`.
- `composer audit --locked` now reports no security vulnerability advisories. Local security suite remains 99 PASS / 0 FAIL / 0 SKIPPED.
- CI commit `6f9195d` completed remotely with `php-syntax`, `security-tests`, and `dependency-audit` all successful. No production or application code was changed.
## DEP-TEST-013-A-REASSESS-AFTER-CI-CLOSURE

- Current in-scope CDN inventory remains unchanged at 20 references: DOMPurify 3.0.6 (4 exact), Iconify 1.0.8 (6 exact), Axios 1.7.9 (5 exact), and Alpine `3.x.x` (5 major-floating). `departamento-operativo` remains excluded.
- No exact-pinned reference currently carries SRI (`SRI_PROTECTED_REFERENCES=0`, `SRI_MISSING_EXACT_PINNED_REFERENCES=15`).
- jsDelivr and unpkg are reachable. Double-download verification produced stable bytes and HTTP 200 JavaScript responses for all three exact resources. SHA-384 values are ready for a future implementation slice: DOMPurify `sha384-cwS6YdhLI7XS60eoDiC+egV0qHp8zI+Cms46R0nbn8JrmoAzV9uFL60etMZhAnSu`, Iconify `sha384-D4fI2O1dD9gQnn73J775jfm7LFa+lp87psAf0aiqjF9EQjnhGwZwkGm+2bffcJSF`, Axios `sha384-jLwhcmGu/RL8PSTUEl/559f8QVLL4QqM+HBvoZlt4F7XCdsdoDGAwW4nPFfoM7lU`.
- CDN responses include `Access-Control-Allow-Origin: *`, immutable caching for jsDelivr resources, and exact versioned final URLs. `crossorigin="anonymous"` is recommended for future SRI implementation; no attributes were added here.
- Alpine floating URL currently resolves to `3.17.3`; this is current CDN evidence only, not historical proof. The local Alpine fallback remains version-unknown and unchanged (SHA256 `3ed1eed252488921df65e363d6715deb04d7f92aaedb9e52199fdf73cb1e0ad3`). Iconify code contains runtime API provider URLs, so secondary remote loads remain `YES`.
- `DEP-TEST-013-A` remains `PARTIAL`: SRI hashes are ready, but SRI implementation and Alpine stabilization remain separate authorized slices. No code, CSP, dependency, or production changes were made.
## DEP-TEST-013-A-ALPINE-PINNING-REASSESSMENT

- The five in-scope Alpine references remain identical and all use `https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js`; no inventory regression was found. `departamento-operativo` remains excluded.
- Current unpkg resolution is HTTP 302 → `alpinejs@3.17.3/dist/cdn.min.js`, followed by HTTP 200 JavaScript with `Access-Control-Allow-Origin: *`. This is current resolution evidence, not historical runtime proof.
- Alpine 3.17.3 is a technically plausible compatibility-pin candidate, but implementation safety is `MEDIUM`: the project uses broad Alpine APIs (`x-data`, `x-init`, `x-show`, `x-model`, `x-bind`/`x-on`, `$dispatch`, and `Alpine.data`) across many views/assets and no browser smoke harness was identified.
- Local fallback remains present and unchanged with SHA256 `3ed1eed252488921df65e363d6715deb04d7f92aaedb9e52199fdf73cb1e0ad3`; its version remains unknown, so it is not evidence of CDN equivalence.
- Recommendation: pin the five references to exact Alpine 3.17.3 only in a separately authorized implementation slice, then add SRI from the exact pinned response and run representative Alpine/browser validation. No URL, SRI, CSP, JS, dependency, or production changes were made here.
- `DEP-TEST-013-A` remains `PARTIAL`; the remaining work can be split into Alpine compatibility pinning, Alpine SRI, and later CSP/build assessment.
## DEP-TEST-013-CLOSURE

- `DEP-TEST-013-A`, `DEP-TEST-013-B`, and `DEP-TEST-013-C` are formally closed as `REMEDIATED_REMOTE_VERIFIED`.
- Final dependency posture: 20 CDN references, 20 exact-pinned, 20 SRI-protected, 0 floating, 0 unversioned. Alpine 3.17.3 is exact-pinned and SRI-protected in all five in-scope layouts; `departamento-operativo` remains outside scope.
- GitHub Actions run `35267559938` passed `php-syntax`, `security-tests`, and `dependency-audit`. Composer lock is tracked; Guzzle 7.15.2 is free of the previously reported advisories.
- `DEP-TEST-013` is closed without production changes. Ongoing dependency monitoring remains recommended; no new remediation is initiated by this closure.
## AUTHZ-DL-002-REASSESSMENT

- The original finding concerns authenticated `/download` access selecting a pathname via `tipo` + `file` without resource-level owner/tenant authorization.
- Current `DownloadController` still exposes one authenticated `GET /download` route with a 58-type controlled folder map (plus 5 MIME entries). It now invokes `SensitiveDownloadAuthorizationService` before path resolution and fails closed for unknown/unresolved types.
- `SensitiveDownloadAuthorizationService` currently resolves and authorizes only the 14 `docs-personal-*` resource types through `RhPersonal`, module download permission, and allowed station IDs. Authorization precedes filesystem resolution and `readfile`; traversal checks remain in place.
- The remaining mapped types are not covered by a domain resolver and therefore are denied by the current central gate rather than authorized by resource metadata. This is a functional coverage gap and requires a type-by-type resolver inventory before enabling additional types; no IDOR closure is claimed for them.
- Separate authenticated PDF/Excel controller routes exist across RH, SGM, finance, operations, and reports. They are not automatically equivalent to `/download` and require independent ownership/tenant review; no broad remediation was started.
- `departamento-operativo` remains excluded. Current status: `PARTIAL_CONCURRENT_WORK_BLOCKED`. Recommended next low-risk slice: resolver inventory and one non-concurrent high-sensitivity type with A/B authorization tests, after module ownership is confirmed.
## AUTHZ-DL-002-RESOLVER-INVENTORY-AND-FIRST-HIGH-SENSITIVITY-TYPE

- Reconciled the generic `/download` map: 58 business types plus five MIME keys; 14 `docs-personal-*` types are protected by the central resolver and 44 mapped business types remain default-deny.
- Active references exist for `requisitos-legales` and independent PDF/Excel/export routes. Their resource-to-station and permission contracts are not uniform enough to select a low/medium-risk first resolver without inventing authorization semantics.
- `docs-personal-baja` and `docs-personal-incidencias` remain excluded because their active surfaces belong to `departamento-operativo`.
- No first high-sensitivity type was implemented; no application code or tests changed. Status remains `PARTIAL_CONCURRENT_WORK_BLOCKED`.
## AUTHZ-DL-002-RESOLVER-INVENTORY-PER-TYPE

- Reconciled exactly 44 unresolved business types from the controller map (58 business types minus the 14 protected `docs-personal-*` types). The five MIME keys are not business resources.
- Classification: `READY_FOR_REMEDIATION=0`, `NEEDS_AUTHZ_DECISION=35`, `INACTIVE_OR_ORPHANED=0`, `PUBLIC_BY_DESIGN=1` (`poliza-seguro`, whose public storage is an architectural constraint), `DEFERRED_DEPARTAMENTO_OPERATIVO=2` (`docs-personal-baja`, `docs-personal-incidencias`), `NOT_SENSITIVE=0`, `UNKNOWN=6`. Total reconciled: 44.
- `NEEDS_AUTHZ_DECISION` types: `bitacora-aditivo`, `analisis-riesgo`, `solicitud-gafetes`, `solicitud-tarjetas`, `procedimientos-actividades-tecnicas`, `procedimientos-visita-estacion`, `requisitos-legales`, `encuestas`, `representante-tecnico`, `comprobantes-clientes`, `documentos-ventas`, `control-volumetrico`, `aceites-documentos`, `aceites-facturas`, `aceites-diferencias`, `monedero-documentos`, `monedero-lista-documentos`, `embarques`, `solicitud-cheque`, `ingresos-facturacion`, `contratos`, `estimulo-fiscal`, `comparativo-xml`, `seguros-incidencias`, `seguros-polizas`, `aclaracion-voucher`, `solicitud-vales`, `lista-negra`, `bitacora-rrhh`, `factura-monedero`, `organigrama-documentos`, `dia-doble-firma`, `permisos-firma`, `formato-descarga-merma`, `formato-descarga-merma-firma`. For each, the repository does not expose a uniform resource identifier → station/owner → permission contract suitable for centralization without a product decision; several also have independent routes or direct links.
- `UNKNOWN` pending evidence: `empresa`, `manual`, `organigrama`, `lista-negra`, `lista-formatos`, `formatos-alta`. No type is marked ready and no authorization rule was invented. `requisitos-legales` is active, backed by `RequisitosLegalesMatriz` → `RequisitosLegalesCalendario.id_estacion`, but its permission contract and all download variants still require confirmation.
- Independent PDF/Excel/export routes and direct public links remain inventoried as separate surfaces; no bypass was remediated in this read-only slice. `AUTHZ-DL-002` remains open and partial.
## AUTHZ-DL-002-RESOLVE-REQUISITOS-LEGALES-AUTHZ-CONTRACT

- `/download?tipo=requisitos-legales&file=...` receives a client-controlled filename (`file`); views pass `acuse`, `requisito`, `acusepdf`, or `requisitolegalpdf` values returned by the legal-requirements data flow. The generic map targets `public/uploads/archivos/reuisitos-legales/` (public storage).
- The logical record is `RequisitosLegalesMatriz`; its `idcalendario` belongs to `RequisitosLegalesCalendario`, whose `id_estacion` is the tenant/station boundary. Matrix file metadata is `acusepdf` or `requisitolegalpdf` (legacy `acuse`/`requisito` values are also emitted by views/services).
- Existing backend permission evidence is `ModuloService::validaPermiso('sasisopa', 'descargar')` in `RequisitosLegalesController::calendarioRequisitosLegales`; station context is obtained through `ModuleStationService::getContext('sasisopa'|'sgm')`. This is module/station evidence, but no shared resolver currently binds every filename variant to one matrix row before file access.
- Independent authenticated PDF routes exist: `/requisitos-legales/calendario-pdf`, `/control-documentos-registros/pdf-requisitos-legales`, and `/monitoreo-verificacion-evaluacion/evaluacion-cumplimiento-requisitos-legales/pdf`. They generate reports and require separate resource-level review.
- Ownership is station/shared-resource based; no per-user owner field was identified. A safe central resolver is technically plausible only after deciding the canonical filename columns and enforcing the module permission plus authorized station set. Until then: `READY_FOR_REMEDIATION=NO`, `NEEDS_AUTHZ_DECISION=YES`.
## AUTHZ-DL-002-RESOLVE-REQUISITOS-LEGALES-FILE-MAPPING

- `RequisitosLegalesMatriz` declares only `acusepdf` and `requisitolegalpdf` as file columns; no `acuse`, `requisito`, `archivo`, `path`, or URL column exists in the model fillable/cast contract.
- `ReporteRequisitosLegalesService` maps persisted `acusepdf`/`requisitolegalpdf` into response aliases (`acuse`, `requisito`, and URL fields). Therefore the four frontend variants are aliases of two persisted columns, not four independent resources.
- The current client sends only `tipo` + `file`; no matrix or calendar identifier is included. A filename-only lookup is not proven globally unique (no uniqueness constraint or generated-name guarantee was found), so it is not a safe canonical resolver key.
- Required future context is at least matrix/calendar identity (with `id_estacion` derived through `RequisitosLegalesCalendario`) plus the `sasisopa` download permission and authorized station set. The mapping remains unresolved and no code was changed.
## AUTHZ-DL-002-DESIGN-REQUISITOS-LEGALES-CANONICAL-CONTRACT

- `RequisitosLegalesMatriz` has primary key `id`; `idcalendario` references `RequisitosLegalesCalendario.id`, whose `id_estacion` supplies the station boundary.
- The proposed canonical request is `tipo=requisitos-legales&matrix_id=<id>&variant=<acuse|requisito>`. `acuse` selects `acusepdf`; `requisito` selects `requisitolegalpdf`. No first-party caller was found that requires `acusepdf` or `requisitolegalpdf` as public variant values.
- `matrix_id` uniquely identifies one matrix row. Current `file` is filename-only, not guaranteed unique, and must never be the authorization identity. During migration it may only be a consistency check against the selected persisted filename; mismatch must deny.
- The proposed fail-closed flow (matrix lookup → calendar/station authorization → `sasisopa` download permission → persisted filename selection → path containment/existence → response) is valid in principle. It is not implemented in this design slice.
- Station context is currently dual (`sasisopa` and `sgm`) in callers, while the effective download permission is hard-coded to `sasisopa`; cross-module permission equivalence remains a decision. Storage remains the legacy public `public/uploads/archivos/reuisitos-legales/` path and must not be renamed.
- Status remains `PARTIAL_CONCURRENT_WORK_BLOCKED`; implementation requires explicit approval of the canonical identifier and SGM permission semantics.
## AUTHZ-DL-002-RESOLVE-REQUISITOS-LEGALES-SGM-PERMISSION

- The SGM caller is routed under `/sgm/normatividad-aplicable-mediciones` to `SgmNormatividadController`; it derives station context with `ModuleStationService::getContext('sgm')` and obtains `ModuloService::permisosSesion('sgm')`. Its datatable explicitly reads the `sgm` `descargar` permission.
- SASISOPA callers use `RequisitosLegalesController`, `ModuleStationService::getContext('sasisopa')`, and `ModuloService::validaPermiso('sasisopa', 'descargar')`. The repository therefore proves distinct module permissions and station contexts, not intentional sharing.
- The SGM and SASISOPA contexts can differ for one user; using the SASISOPA context for an SGM request risks both under- and over-authorization. A client-supplied `module` must not be trusted as authority; the server must bind module/context to the canonical resource or route.
- A single unified rule is not currently valid. The future resolver must carry a trusted module context (`sasisopa` or `sgm`) and apply the corresponding permission/station set. No implementation was made.
## AUTHZ-DL-002-DESIGN-REQUISITOS-LEGALES-TRUSTED-MODULE-BOUNDARY

- Active SASISOPA callers use `RequisitosLegalesController` routes and SGM uses `SgmNormatividadController` under `/sgm/normatividad-aplicable-mediciones`; both ultimately call the generic `/download` helper with legacy filename values.
- The safest boundary is module-specific server routes/actions (or an equivalent server-side dispatcher) that establish `sasisopa` or `sgm` before invoking one shared matrix/variant/path resolver. A client `module` parameter and HTTP `Referer` are not authorities; session module state is not reliable across tabs/context switches.
- Required authorization order is valid: establish trusted module → check that module's `descargar` permission → load matrix/calendar → authorize station in that module context → choose persisted filename → containment/existence → response. Missing matrix, station, permission, or filename must deny without filesystem probing.
- `sasisopa` and `sgm` permissions/contexts are distinct and may yield different station sets; no evidence supports intentionally sharing `sasisopa/descargar`. Canonical variants remain `acuse` and `requisito`.
- Design status remains `PARTIAL_CONCURRENT_WORK_BLOCKED`; no routes, services, views, tests, or production configuration were changed.
## AUTHZ-DL-002-IMPLEMENT-REQUISITOS-LEGALES-SGM-MODULE-BOUNDARY

- Added SGM-only route `/sgm/normatividad-aplicable-mediciones/requisitos-legales/download` handled by `SgmNormatividadController::downloadRequisitoLegal`; module and permission are server-side (`sgm/descargar`).
- The action requires positive `matrix_id` and strict `variant` (`acuse|requisito`), resolves `RequisitosLegalesMatriz` → calendar → `id_estacion`, requires the current SGM station, selects only persisted `acusepdf`/`requisitolegalpdf`, and applies containment before serving.
- Migrated only the SGM requisitos-legales view and datatable helper to the new contract. SASISOPA callers and generic `/download` remain unchanged/default-deny for this type. No `departamento-operativo` files were touched.
- Verification: targeted PHP lint passed; security suite remains 99 PASS / 0 FAIL / 0 SKIPPED. No commit, push, or production access performed.

## AUTHZ-DL-002-PRIVATE-STORAGE-PHASE2-IMPLEMENT-MIGRATION-TOOL

- Implemented a local-only PHP CLI migration tool with dry-run as the default and explicit `--apply` gating.
- The tool uses fixed legacy/private roots, strict `RequisitosLegalesStorageService::normalizeReference()` semantics, SHA-256 verification, JSON manifests, two-pass blocker checks, idempotent same-hash handling, and temporary-file cleanup.
- Database writes and legacy source deletion are unsupported. Orphans are report-only. Production inventory and migration remain pending; the legacy read fallback remains enabled.
- Targeted migration-tool regression: 15 PASS / 0 FAIL / 0 SKIPPED. Full security suite: 149 PASS / 0 FAIL / 0 SKIPPED. `AUTHZ-DL-002` remains `PARTIAL`.

## AUTHZ-DL-002-REMEDIATE-GESTORIA-REQUISITOS-LEGALES-CANONICAL-DOWNLOAD

- Gestoría requisitos legales now uses a canonical download route based on `matrix_id` and strict `acuse|requisito` variants.
- Authorization is server-side through `gestoria/descargar`, the current station context, the persisted matrix reference, and the shared private-first/legacy-fallback storage resolver.
- Generic `/download?tipo=requisitos-legales` remains default-deny. No historical files or database rows were migrated or changed.
- Targeted regression: 12 PASS / 0 FAIL / 0 SKIPPED. Full security suite: 161 PASS / 0 FAIL / 0 SKIPPED. `AUTHZ-DL-002` remains `PARTIAL` because deferred module surfaces remain.

## AUTHZ-DL-002-DEFERRED-CLOSURE-DOCUMENTATION

- Current status: `PARTIAL_DEFERRED`. No confirmed active non-deferred vulnerable download surface remains after the Gestoría canonical-download remediation.
- Gestoría evidence: `GET /gestoria/permisos/requisitos-legales/download`, `GestoriaPermisosController::downloadRequisitoLegal`, `matrix_id`, strict `acuse|requisito` variants, `gestoria/descargar`, server-side station context, and shared private-first/legacy-fallback resolution. Remote verification: commit `2e3bad2c89b353ebef9d8df0e15f7927b6ae54da`, Security workflow run `35377218278`, 161 PASS / 0 FAIL / 0 SKIPPED.
- Generic `/download` remains fail-closed: 53 types total, 14 authorized, 39 default-deny, 0 unknown. Default-deny and legacy/dead callers are not treated as active exposure; remaining active download callers belong to deferred `departamento-operativo` surfaces.
- Deferred concurrency includes Aceites, Ingresos Facturación, Comparativo XML and other `departamento-operativo` download/upload callers. This is `DEFERRED_CONCURRENCY`, not accepted risk or a statement that those surfaces are safe.
- Requisitos-legales new uploads remain private, historical fallback remains available, and migration tooling is remotely verified. No historical migration is required before the first production deployment; server verification remains deferred until that deploy.
- Reopen when `departamento-operativo` concurrent development is completed or explicitly released for security remediation. At that time reassess generic callers, canonical resource IDs, permissions, station boundaries, filename authority and private-storage coupling.

## SQL-001-CLOSURE-DOCUMENTATION

- `SQL-001` is `CLOSED_WITH_EVIDENCE` at commit `2e3bad2c89b353ebef9d8df0e15f7927b6ae54da`; no active SQL injection vulnerability was identified in the reviewed application code.
- Evidence: 4 direct parameterized `DB::select` queries, 34 raw-expression surfaces reviewed, 0 unsafe value interpolation, 0 unsafe identifier interpolation, 0 unsafe sort/LIKE/IN/LIMIT-OFFSET/raw-expression surfaces, and 0 real SQL injection candidates.
- `AnalisisCompraService` uses bound parameters for all four direct queries. The two dynamic identifiers in `KpiAceitesService` are selected from fixed internal application values and are not request-controlled.
- `departamento-operativo` SQL surfaces remain deferred/not fully assessed due to concurrent development. Re-run a SQL-focused review before first production deployment if that module introduces or materially changes raw SQL.
- Reopen if new raw SQL, request-controlled identifiers, user-controlled raw sorting/filtering, or new pre-deployment SQL construction surfaces appear.

## SEC-CSP-012-REMOTE-VERIFICATION-RECONCILIATION

- Report-Only CSP and the externalized highlight initialization are remotely verified at commit `2e3bad2c89b353ebef9d8df0e15f7927b6ae54da`, Security workflow run `35377218278`; `csp_report_only_regression.php` passed and the suite reported `161 PASS / 0 FAIL / 0 SKIPPED`.
- Enforced CSP remains unchanged and permissive (`unsafe-inline`, `unsafe-eval`, and explicit legacy CDN sources). Report-Only omits script `unsafe-inline`, keeps temporary `unsafe-eval` for Alpine, retains temporary inline styles, and includes boundary directives without wildcard sources.
- Remaining CSP work is not closed: Alpine/`unsafe-eval`, inline styles, `javascript:void(0)` migration, CDN/runtime verification, and eventual enforcement tightening remain pending. `departamento-operativo` remains outside the reviewed scope.

## SEC-CSP-012-RESIDUAL-ENFORCEMENT-REMEDIATION

- Replaced the four `javascript:void(0)` download pseudo-links in `app/Views/requisitoslegales/detalle.php` with semantic `button` elements retaining the existing Alpine `@click` download behavior.
- `departamento-operativo`, Alpine `unsafe-eval`, inline styles, CSP headers, and CDN dependencies were not changed. The enforced policy remains permissive and policy alignment is not claimed.
- Added a narrow structural regression asserting that this requisitos-legales view no longer contains those JavaScript URLs. Full security suite remains green at 161 PASS / 0 FAIL / 0 SKIPPED.
- Status: `SEC-CSP-012: PARTIAL_HARDENING_LOCAL`; production header verification remains `PENDING_FIRST_DEPLOY`.

## SEC-CSP-012-BOOTSTRAP-DROPDOWN-BUTTON-IMPLEMENTATION-MEJORES-PRACTICAS

- Converted exactly two non-deferred Bootstrap dropdown triggers in `app/Views/mejorespracticas/index.php` from `javascript:void(0)` anchors to semantic `button type="button"` elements.
- Preserved classes, dropdown IDs, `data-bs-toggle="dropdown"`, `aria-haspopup`, `aria-expanded`, menu relationships, and existing permission-controlled menu contents. Corrected the local `arial-explaned` typo to `aria-expanded`.
- Added a focused CSP regression. No CSP policy, Alpine, inline-style policy, CDN, or `departamento-operativo` changes were made. Security suite: 163 PASS / 0 FAIL / 0 SKIPPED.
- Status remains `SEC-CSP-012: PARTIAL_HARDENING_LOCAL`; remote verification and production header verification remain pending.

## DOC-001-REMOTE-VERIFICATION-RECONCILIATION

- Original scope: audit and security documents mixed prior results, TODOs, and stale figures, creating documentation drift; the audit explicitly required consolidation after remediation without deleting history.
- Remediation: commit `6448939` added the consolidated security status document and reconciled the remediation progress record while preserving historical entries. The current status snapshot links the technical audit, plan, and progress history rather than replacing them.
- `6448939` is an ancestor of current `HEAD` `2e3bad2c89b353ebef9d8df0e15f7927b6ae54da`; therefore the documentation remediation is present on `origin/Silvino`.
- Current status matrices are consistent with the reconciled states, including deferred findings and the documented CSP/production residuals. `DOC-001` is `CLOSED_WITH_EVIDENCE`; the Security workflow is supporting evidence only, not the primary documentation proof.
- Reopen if the matrix drifts materially from implementation, security decisions lack records, pre-deployment status changes are undocumented, or operational guidance becomes contradictory.
