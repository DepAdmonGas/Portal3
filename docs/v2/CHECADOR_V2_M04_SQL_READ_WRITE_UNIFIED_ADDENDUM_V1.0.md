# CHECADOR V2 — M04 SQL Read/Write Unified Addendum V1.0

**Status:** Approved by the Portal3 owner for M04 on 2026-11-19; environment activation gates remain mandatory.
**Applies to:** Portal3 and Checador V2 API; both repositories must contain byte-identical copies.
**Scope:** M04 station-template catalog/detail reads, segments, create, station customization, immutable versioning, audit, and readback.
**Does not change:** Architecture Freeze V1.0, J1/J2 semantics, Lector, M03 assignments, M11, or API ingestion and calculation responsibilities.

## 1. Binding transport and ownership

Portal3 reads M04 templates, exact versions, segments, lineage, and audit-relevant confirmation directly from canonical MariaDB through Portal3's existing institutional SQL connection. Portal3 does not communicate with API V2 over HTTP for any M04 read, readback, or mutation. The browser communicates only with Portal3; it never connects to MariaDB or API V2.

After the exclusive cutover in §7, Portal3 is the sole administrative writer of station-scoped M04 templates, using the existing V2 tables. API V2 retains device ingestion, persistence integrity, interpretation, and journey calculations. API V2 does not accept station-template administrative mutations after cutover. Its GET routes may remain for other consumers; Portal3 never invokes them.

Portal3 must not duplicate API-owned calculations or rewrite original punch events. A missing or unavailable persisted projection remains unavailable; it is not a reason to call API over HTTP or synthesize a result.

## 2. Contract clauses reconciled

This addendum supersedes or supplements only M04 template behavior in these clauses:

| Source | Clause | M04 treatment |
|---|---|---|
| `CHECADOR_V2_SHIFT_SCHEDULING_SERVICE_API_CONTRACT_V1.0.md` | §4.1 template catalog/detail GETs and create/version POSTs; §6.1 template ownership/versioning | For Portal3 M04, all reads and writes use the SQL boundaries in this agreement. API route definitions may remain for non-Portal3 GET consumers; station-template mutation routes reject without mutation after cutover. M03 §4.2 is unchanged. |
| `CHECADOR_V2_M03_M04_LOCAL_TEMPLATES_AND_HABITUAL_TEMPORARY_SCHEDULE_ADDENDUM_V1.0.md` | §§2, 5, 6, 7 | Supplemented only for M04 template persistence and transport. M03 assignment ownership and permissions are unchanged. |
| `CHECADOR_V2_M04_GLOBAL_TEMPLATE_STATION_CUSTOMIZATION_ADDENDUM_V1.0.md` | §§3–6 | The Portal3 SQL writer creates local copies and lineage; the GLOBAL source remains immutable. Existing uniqueness, provenance, and recovery requirements remain. |
| `CHECADOR_V2_PREPRODUCTION_TARGET_DEFINITION_V1.0.md` | §5.4 | A narrow M04 station-template exception applies only after target-specific approval and cutover. All other direct Portal3 V2 writes remain governed by their own contracts. |

No clause outside this list is amended. The Architecture Freeze remains byte-for-byte unchanged.

## 3. GLOBAL templates and allowed operations

GLOBAL templates and their segments are read-only in M04 station administration. M04 cannot create, edit, version, activate, or deactivate a GLOBAL template. The allowed station operations are:

1. Create a new station template at version 1.
2. Append an immutable next version to a station template.
3. Create a station-local customization from an exact, authorized GLOBAL version, preserving its source lineage.

No operation changes assignments or rewrites historical versions.

## 4. Authorization and scope

Every mutation requires an active authenticated Portal3 user, valid CSRF, effective `checador.turnos.estacion.administrar`, Encargado position 6, and the user's own server-resolved canonical station scope. The handler rechecks authorization and scope; client-supplied actor, station, hidden fields, or modal state confer no authority. Explicit user-level denial overrides a position grant.

Dirección remains read-only. Autolavado department 9 does not inherit Palo Solo station 2 permissions and has no write authority under this agreement. Any future Autolavado writer requires a separately approved permission and scope contract.

## 5. Atomicity, versioning, audit, idempotency, and readback

Writes use the existing MariaDB V2 schema; this agreement authorizes no DDL, migration, or data repair. Each create, customization, or version operation is one InnoDB transaction covering the template row, all ordered segments, lineage when applicable, and mandatory audit. Any validation, uniqueness, audit, or concurrency failure rolls back the complete operation.

The writer serializes M04 mutations by locking the canonical `op_rh_localidades` row for the authorized station. A cryptographically random idempotency key and canonical payload digest are recorded in the same audit transaction. A replay with the same key and digest returns the existing persisted result; reuse with a different digest conflicts. Existing unique keys remain the collision guard. No new writer may bypass this lock protocol. The audit table has no dedicated unique idempotency index; if the row lock, audit lookup capacity, JSON validity, or transaction behavior cannot provide the specified guarantee, M04 writes stay closed and a separately approved schema/contract revision is required.

Success is returned only after SQL readback confirms template identity, station scope, version, segments, and lineage. Confirmation cannot be assembled from the request payload or an in-memory insert list. Historical versions, GLOBAL rows, and existing assignment references remain unchanged.

## 6. Database principal and preflight

Before enabling writes in an environment, verify its actual runtime database and principal. Compare `SHOW CREATE TABLE`, engines, generated columns, unique indexes, foreign keys, audit JSON constraints, and lineage against the approved model. Verify effective grants for that exact principal and database; the principal must have the required SELECT/INSERT and row-lock access and must not have UPDATE/DELETE capability over immutable template/history rows. A development principal's grants are not evidence for Plesk.

Repository DDL, isolated fixtures, and a schema-only check do not establish runtime grants, user permissions, or a successful operational write.

## 7. Exclusive cutover and rollback

All Portal3 M04 write gates default closed. Cutover order is mandatory:

1. Install the identical approved addendum in both repositories and preserve its SHA-256.
2. Verify the target DDL, engines, indices, constraints, lock behavior, grants, and audit lookup capacity with read-only checks.
3. Deploy/prepare the Portal3 SQL code with its writer gate closed; verify every Portal3 M04 read and readback is SQL-only.
4. Close the three API station-template mutations: create, GLOBAL-to-station customization, and immutable version append. Their responses must prove rejection without mutation. Do not affect template GETs, authorized GLOBAL administration, M03 assignments, or other modules.
5. Verify the API cut, target grants/preflight, and Encargado station permission. Only then enable the four Portal3 M04 gates for that environment: `CHECADOR_M04_SQL_WRITER_ENABLED`, `CHECADOR_M04_API_WRITER_CLOSED`, `CHECADOR_M04_SQL_GRANTS_VERIFIED`, and `CHECADOR_M04_SQL_PREFLIGHT_VERIFIED`.
6. Run the acceptance checks in §8. No simultaneous writers or HTTP fallback are permitted.
7. Rollback closes the Portal3 SQL writer first. API station writes remain closed until Portal3 is verified closed and outstanding operations are reconciled. Never delete or rewrite history as rollback.

## 8. Acceptance evidence

For each environment, verify authorized create, GLOBAL customization, immutable version append, exact segments, audit, idempotent replay/conflict, concurrency/version conflict, rollback on injected failure, SQL readback, F5 persistence, and denial of foreign station scope. Confirm Dirección cannot write, Encargado can write only its authorized station, GLOBAL remains unchanged, and Autolavado 9 does not inherit station 2 rights. Confirm Portal3 M04 makes no HTTP request to API and remains independent of API availability. Label isolated, local, and remote evidence separately.

## 9. Approval and environment boundary

The Portal3 owner approved this M04 agreement on 2026-11-19. This approval does not itself attest API runtime closure, database grants, target schema, authenticated acceptance, production deployment, or Plesk readiness. Those remain environment-specific gates. This document grants no production/Plesk access, migration, SQL grant change, or deployment authorization.
