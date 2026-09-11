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
- Status: partial. The pattern is likely reusable for resource-specific adapters; other services remain unreviewed.
