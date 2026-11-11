# Temporary Access Operation Permission Split Addendum V1.0 — DRAFT

**Status:** DRAFT — NOT APPROVED; IMPLEMENTATION NOT AUTHORIZED  
**Scope:** Portal3 and Checador API V2, development configuration only  
**Base contract:** `TEMPORARY_ACCESS_API_CONTRACT V1.0` (frozen)  
**Existing security addendum:** `TEMPORARY_ACCESS_SECURITY_ADDENDUM_V1.0.md`  
**Architecture authority:** `CHECADOR_V2_ARCHITECTURE_FREEZE_V1.0.md` (unchanged)

## 1. Purpose and compatibility boundary

This proposal separates the human permissions for the existing Temporary Access V2 supervisor transitions. It does not add a module, endpoint, request field, response field, state, table, column, migration, PIN-generation path, or Lector capability. Existing endpoint paths, JWT identity, station scope, transitions, audit fields, PIN rules, and device authentication remain unchanged.

This draft would supplement only the functional-permission mapping in Section 3 of the existing Temporary Access Security Addendum. It does not amend the frozen API contract or Architecture Freeze. It has no effect until approved through the required cross-project governance procedure and implemented in both Portal3 and API V2.

## 2. Existing ACL fields reused for this module

The existing `modulos_usuarios_do` and `modulos_puestos_do` flags are reused only for the `acceso-temporal` module:

| Operation | Required capability | Legacy compatibility |
|---|---|---|
| List requests | `leer = 1` | Unchanged |
| Authorize a pending request | `crear = 1` | Existing `editar = 1` continues to authorize this transition for already configured profiles |
| Cancel an eligible request | `eliminar = 1` | Existing `editar = 1` continues to authorize this transition for already configured profiles |
| Reject a pending request | `editar = 1` | Unchanged |

An explicit operation-specific flag does not grant the other operations. A grant with `crear = 1`, `eliminar = 1`, and `editar = 0` permits authorization and cancellation while denying rejection. Existing profiles whose effective `editar` flag is `1` retain the legacy authorization for all three transitions until their ACL is deliberately reviewed; this preserves their current behavior during migration.

The existing global direct-user-over-position precedence remains unchanged. A direct grant in any module continues to select the direct-user grant for `acceso-temporal`; user and position grants are not merged. Missing grants, zero flags, invalid identity, or unavailable ACL data fail closed.

This mapping is scoped strictly to `acceso-temporal`. It must not replace, clear, copy, or otherwise change effective permissions for Recursos Humanos or any other module. Existing direct-user-versus-position precedence remains in force, and no grants are copied from another position or user as part of this addendum.

## 3. Required server checks

Portal3 and API V2 must independently resolve the authenticated active user and validate the capability for the specific requested action on every request. Neither layer may treat authorization for one transition as authorization for another. API V2 continues to derive identity from the delegated Portal3 JWT `sub`, require issuer `portal3_auth`, and resolve permissions and station scope from current server-side data. JWT role, permission, and station claims must not be added.

Each transition must retain the existing request-state and station checks. Authorization and cancellation must not call rejection logic, and a rejected request must not issue or expose a PIN. In particular, `crear = 1` and/or `eliminar = 1` with `editar = 0` must not authorize rejection in either Portal3 or API V2. The browser only omits unavailable actions; independent server-side authorization remains mandatory for direct HTTP attempts.

## 4. Palo Solo development policy proposed for this task

After approval, and only in `bd_admongas` development:

| Profile | Read | Authorize | Cancel | Reject |
|---|---:|---:|---:|---:|
| Encargado, user 52, Palo Solo locality 2 | Yes | Yes | Yes, when the contract allows the transition | No |
| Dirección, user 19 | Yes | No | No | No |
| Other profiles | Preserve their currently effective grants | Preserve | Preserve | Preserve |

The proposed Encargado grant is `leer=1`, `crear=1`, `editar=0`, `eliminar=1`, `descargar=0`. Dirección remains `leer=1` with all mutation flags `0`. No other user or position grant is changed by this task-specific configuration. Autolavado locality 9 remains outside the `acceso-temporal` allowlist.

## 5. Unchanged lifecycle and data rules

The existing PENDING, AUTHORIZED, REJECTED, CANCELLED, EXPIRED, LOCKED, and USED states and valid transitions remain unchanged. Request TTL (30 minutes), PIN TTL (15 minutes), one-time PIN use, and the maximum of three failed attempts remain API-owned. Portal3 must not generate requests or PINs, write Temporary Access tables directly, disclose PINs through logs or URLs, or emulate a successful API transition.

## 6. Required implementation and verification after approval

After express cross-project approval by the authorized Portal3 and API V2 project owners, implementation must:

1. Update the API authorization helper and the existing authorize, reject, and cancel handlers to require their mapped capability while preserving legacy `editar` behavior.
2. Update Portal3's server-side capability resolver, handlers, and existing view so each action is independently authorized and only permitted actions are rendered.
3. Keep the current endpoint contract, JWT delegation, CSRF, station isolation, state validation, one-time PIN handling, and audit behavior.
4. Add focused tests for the three operation capabilities, legacy compatibility, explicit denial, direct-user precedence, scope isolation, and dependency failures.
5. Validate read-only queue behavior and, only with a legitimate QA request and approved write environment, validate each transition and readback.

No mutation, ACL change, production rollout, or implementation is authorized by this draft.

## 7. Approval record

**Portal3 approval:** PENDING  
**API V2 approval:** PENDING  
**Cross-project status:** PENDING  
**Technical implementation authorization:** PENDING

Approval must explicitly cover this addendum's operation-to-capability mapping and the independent enforcement requirements above. Until both project approvals and the required technical implementation authorization are recorded, the existing contract and permissions remain in force and this draft authorizes no code, ACL, database, or production change.
