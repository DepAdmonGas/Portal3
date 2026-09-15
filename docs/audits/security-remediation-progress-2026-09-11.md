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
- Production verification: required. Confirm the configured Telegram webhook uses the same secret, verify private replay storage is shared/atomic for the deployment topology, and send an approved non-production/controlled Telegram update. No production service, secret, webhook, database, or deployment was touched.
- Status: remediated locally.

## SEC-RATE-006

- Date: 2026-09-11.
- Confirmed affected surface: `POST /login/acceso` (credential stuffing/brute force). The repository has no password-reset route; authenticated Telegram token generation, authenticated polling, and the already-authenticated/replay-protected Telegram webhook were inventoried but are not part of this login-specific finding.
- Root cause: the former limiter stored counters in the PHP session, so a new session reset the limit. It also preferred client-supplied forwarding headers, allowing trivial key spoofing.
- Changed: `RateLimiter` now uses a fixed 300-second window with a maximum of 10 attempts. Its key is `login + normalized account identifier + REMOTE_ADDR`, hashed before it becomes a private-storage filename. Counter updates use an exclusive file lock in `storage/private/rate-limits`, so concurrent requests sharing the storage cannot both consume the same remaining attempt.
- Behavior: requests over the limit receive the existing generic failure shape with HTTP 429 and `Retry-After`; the login response remains independent of whether an account exists. A fully successful login clears its own account/IP counter. Failed credentials and incomplete authentication continue to consume attempts.
- Storage failure policy: fail open, to avoid a private-storage outage becoming a global login outage. The failure is logged without account credentials; production monitoring and writable private storage are required.
- IP/proxy assumption: only `REMOTE_ADDR` is used. It is not client-header spoofable, but a reverse proxy may cause several clients to share its address unless production preserves the client address through a trusted proxy configuration.
- Tests: dedicated `tests/rate_limiter_security_regression.php` covers requests under the limit, limit exceeded, account/IP isolation, window reset without sleeping, and post-success counter clearing. Webhook regression (7) and the existing security harness (26) remain green.
- Production verification: required. Verify that `storage/private/rate-limits` is writable and shared by all application instances, and that the web/proxy layer sets a trustworthy `REMOTE_ADDR` value. No production configuration, cache, database, deploy, or host was touched.
- Status: remediated locally.

## PRIV-LOG-010

- Date: 2026-09-11.
- Root cause: authentication events recorded the account identifier and IP address directly; the custom Monolog facade did not centrally redact sensitive context. The production error handler also bypassed the facade through a nonexistent `Logger::getLogger()` call.
- Affected logging surfaces: authentication login/logout events, token refresh events, the shared `Logger` facade, and the production error/exception handler. The baseline evidence confirmed PII (username and IP); no baseline direct logging of raw passwords, JWT values, cookies, or Telegram secrets was found in these paths.
- Changed: `AuthenticationService` retains only event outcome and internal `user_id` where available. `Logger` now centrally redacts secrets, authentication/session values, request/response bodies, headers, document paths, SQL/query content, and common PII keys recursively. It also neutralizes line breaks in messages/context values. The error handler now uses the facade and records bounded exception metadata (class, source file, line) rather than the exception message or full trace.
- Retained operational/audit context: event name, outcome, internal user ID where known, error code, exception class, source file, and line. No raw password, auth token, cookie/session value, Telegram secret, account identifier, or IP is retained by these logger calls.
- Tests: dedicated `tests/privacy_logging_security_regression.php` verifies redaction of dummy password/token/cookie/Telegram-secret/username/IP values and confirms useful context remains while newline log injection is neutralized. Rate limiting (5), webhook (7), and existing security regression (26) suites remain green.
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
- Final status: `SEC-SESSION-009` remains remediated locally; functional regression resolved. Production still requires the previously documented HTTPS/proxy/session-storage verification.

## SEC-SESSION-009-CSRF-POST-LOGIN-REGRESSION

- Date: 2026-09-11.
- Reproduction: after login, `public/assets/js/departamento-operativo/1-corporativo/solicitud-cheque.crear.js` constructs `POST /departamento-operativo/solicitud-cheque/store` with native `fetch` and `FormData`, but supplies neither `X-CSRF-TOKEN` nor `_csrf_token`. The CSRF middleware correctly returns 419 before `SolicitudChequeController::store` runs.
- Root cause: this is a protected concurrent front-end transport omission, not a session-ID or CSRF-token desynchronization. `SessionService::start()` correctly rotates the anonymous token, and an authenticated GET form exposes the resulting current token. Native `fetch` is not covered by the Axios interceptor.
- Scope decision: no protected `departamento-operativo` file or its direct dependency was modified. The required local correction is to include the current token in that protected fetch request (or a protected-module scoped fetch wrapper) once concurrent work is released; CSRF validation must remain fail-closed.
- Tests: `tests/csrf_post_login_regression.php` independently proves the shared contract: anonymous and authenticated tokens differ, the authenticated token authorizes one and multiple mutations, and missing, invalid, and pre-login tokens receive 419. This confirms that the shared CSRF/session remediation remains sound.
- Status: blocked only for the protected module correction; `SEC-CSRF-001` and `SEC-SESSION-009` remain remediated locally.

## DOC-001 — documentation consolidation

- Snapshot: [security-remediation-status-2026-09-11.md](./security-remediation-status-2026-09-11.md) consolidates the current local, production-pending and concurrent-work status without replacing this progress log, the original audit or the remediation plan.
