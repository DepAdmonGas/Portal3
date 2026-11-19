# CHECADOR V2 — M04 Portal3 SQL Read/Write Addendum V1.0 (DRAFT)

**Status:** DRAFT — NOT APPROVED; implementation and deployment are not authorized by this document.  
**Applies to:** Portal3 and Checador V2 API, as one byte-identical cross-project contract.  
**Scope:** all Portal3 M04 administrative template reads and writes: catalog, exact-version/segment reads, create, immutable version, and authorized global-to-station customization.
**Does not change:** Architecture Freeze V1.0, the frozen shift-scheduling physical model, J1/J2, Lector, M03 assignment writes, API V2 behavior for other consumers, or any data/schema.

## 1. Governing decision and boundaries

This proposal assigns the Portal3 M04 administrative read and write path to Portal3 SQL services using the existing MariaDB connection and V2 tables. Portal3 M04 must not call API V2 over HTTP for catalog/detail reads, readback, create, version, or customization. The existing SQL read service remains the read boundary; the SQL write service appends immutable template versions, segments, lineage, and audit. API V2 may retain its existing template GET endpoints for non-Portal3 consumers, remains the writer for M03 shift assignments and other API-owned operations, and continues reader ingestion/processing responsibilities. No browser connects directly to MariaDB or API V2.

This is a narrow exception to the existing “API V2 is the only writer” rule. It is not authority to change the Architecture Freeze or the physical model. The addendum becomes effective only after the cross-project owner approves this exact document/hash and the deployment-specific database/schema/grant preflight passes. Until then, the current API writer remains authoritative and Portal3 must not write these rows.

The one-writer invariant applies across environments: at any instant, either the API M04 template mutation routes are enabled and Portal3 SQL writes are disabled, or those API routes are closed and the Portal3 SQL writer is enabled. There must be no overlap and no fallback writer.

## 2. Exact clauses supplemented or superseded

This addendum changes only the following provisions, and only for M04 template mutations:

| Existing source | Exact clause | Treatment under this draft |
|---|---|---|
| `CHECADOR_V2_SHIFT_SCHEDULING_SERVICE_API_CONTRACT_V1.0.md` | §4.1 endpoints 1–4 (`POST /api/v2/shift-templates`, `POST /api/v2/shift-templates/{clave}/versions`, catalog `GET`, and exact-version `GET`); §6.1 template versioning/mutation model | Superseded only for Portal3 M04 transport/ownership: Portal3 M04 does not invoke any of those HTTP endpoints. API endpoint definitions may remain available to other clients; they are not a Portal3 fallback. Payload validation, immutable version, segment, and error semantics remain, except where this addendum is stricter. §4.2 M03 assignment endpoints remain unchanged. |
| `CHECADOR_V2_M03_M04_LOCAL_TEMPLATES_AND_HABITUAL_TEMPORARY_SCHEDULE_ADDENDUM_V1.0.md` | §2 template scope/versioning; §5 actor/provenance where applicable; §6 permission `checador.turnos.estacion.administrar`; §7 API compatibility scope | Supplemented only for M04 template create/version/customization persistence. M03 assignment writes and their API/JWT/audit contract remain unchanged. The approved station permission does not authorize a department write. |
| `CHECADOR_V2_M04_GLOBAL_TEMPLATE_STATION_CUSTOMIZATION_ADDENDUM_V1.0.md` | §3 uniqueness/duplicate behavior; §4 API behavior and transaction; §5 Portal3 interaction; §6 recovery | Superseded only where it says API V2 creates the customized template or Portal3 calls the API for that mutation. Source authorization, immutable lineage, uniqueness, unchanged global source, read-only provenance, UI feedback, and recovery requirements remain and are implemented through the Portal3 SQL writer plus authorized readback. |
| `CHECADOR_V2_PREPRODUCTION_TARGET_DEFINITION_V1.0.md` | §5.4 “Cero Escrituras Directas de Portal3 en V2” | Narrow M04-only exception for template mutations, if and only if this addendum is approved for the target environment. The rule remains in force for every other `op_rh_v2_*` mutation, including M03. This draft does not grant preproduction credentials to production or relax database isolation. |

No other clause is implicitly amended. The frozen documents remain byte-for-byte unchanged. If any provision outside the rows above conflicts, stop and obtain an explicit cross-project architecture decision before implementation.

## 3. Existing persistence and idempotency resolution

The checked-in physical definitions establish the following existing structures; no new table or column is proposed:

- Migration `2026_11_03_000005_m03_m04_local_templates_and_habitual_temporary_schedules.sql` adds stored generated `scope_id = COALESCE(id_estacion, 0)` and unique key `(scope_id, clave, version)` to `op_rh_v2_turnos_plantillas`. This prevents duplicate logical versions within a scope.
- The same migration creates `op_rh_v2_turnos_segmentos`, keyed uniquely by `(id_plantilla, ordinal)`, and `op_rh_v2_turnos_audit_log`. The audit row has `entity_type`, `entity_id`, `operacion`, `id_usuario_actor`, `id_estacion`, template/version fields, nullable JSON `detalle_json`, and `recorded_at_utc`; its indexes cover entity, station/time, and actor/time. It has no unique idempotency-key index.
- Migration `2026_11_04_000001_create_v2_shift_template_station_lineage.sql` creates immutable source/local identity fields and unique keys for `(id_estacion, source_global_clave)`, `(id_estacion, local_clave)`, and `local_template_id`.

Idempotency therefore uses the existing audit JSON plus single-writer serialization, not a new table, schema change, or claim that an absent unique index exists:

1. Every create, version, or customization request carries a cryptographically random idempotency key, scoped by operation, authenticated actor, and canonical station. Portal3 computes a canonical payload digest after validation.
2. Inside one InnoDB transaction, the writer locks the existing canonical `op_rh_localidades` row for the authorized station (`SELECT … FOR UPDATE`). All M04 writes for that station take this same lock. The API template mutation routes must already be closed, and no other M04 writer may bypass this lock protocol.
3. While holding the lock, look up the matching audit event by actor, station, operation, and idempotency key in `detalle_json`. If its digest matches, perform authorized SQL readback and return the already committed result without inserting a template, version, segment, lineage, or audit duplicate. If the key exists with a different digest, return conflict and make no change.
4. If no event exists, continue the mutation and write the key, digest, operation, actor, canonical station, logical key/version, and result identity into the audit JSON in the same transaction as the template, segments, and lineage. Audit insert failure is fatal and rolls back the transaction.
5. The database unique keys remain the final collision guard. A uniqueness race or lost response is resolved by re-reading the audit key and exact template/version/segments; never retry by issuing a new key automatically.

The audit JSON lookup has no dedicated index and may scan audit rows for the station/actor/operation. A pre-implementation capacity/latency check is required. If the existing JSON type/size, transaction engine, lock target, or effective grants cannot support this protocol, stop: do not approximate idempotency or add schema. A separately approved schema/contract revision would then be required.

## 4. Authorization and canonical scope

Portal3's authenticated session, active user, effective permission service, and server-resolved Checador scope are authoritative for this local writer. Every mutation rechecks, server-side and inside the write handler:

- active authenticated user and active effective permission `checador.turnos.estacion.administrar`;
- Encargado position 6 only, for the user's own active, canonical station scope; explicit deny takes precedence;
- canonical mapping and requested station equality; request parameters, hidden fields, modal state, and client-supplied actor/station do not establish authority;
- CSRF, payload/segment validity, global-source visibility and exact version, and station/source uniqueness for customization.

Dirección remains read-only. The existing read exception for Autolavado (department/locality ID 9) to view the Palo Solo (station/locality ID 2) catalog does not confer mutation rights. Autolavado is not translated into station 2 for writes and receives no inherited position-6 station grant. This draft denies M04 template writes requested in department scope 9. A future Autolavado writer requires an independently approved permission, explicit canonical department-to-template ownership model, and its own scope tests; none is implied here.

## 5. Atomic write semantics

Only these existing M04 actions are in scope: create one station template at version 1; append one immutable next version to an authorized station template; or create one station-local customization from an exact authorized global source version with immutable lineage. There is no global-template mutation and no activation/deactivation operation unless separately authorized by the governing contract.

Each action executes in one transaction with strict error handling:

1. Validate session/CSRF and effective permission/scope; resolve actor and canonical station on the server.
2. Acquire the station serialization lock and check idempotency before mutation.
3. Validate source/template identity, scope, versions, name/type, and all `WORK`/`BREAK` segments, including ordinals, times, and day offsets. For customization, lock/read the exact global version and its persisted segments; the client cannot assert lineage.
4. Insert the template version, all segments, lineage when applicable, and mandatory audit event. Any failed prepare/execute, missing segment, unique conflict, audit error, or concurrency conflict rolls back all rows.
5. Commit, then read the exact result through the authorized SQL read path and compare identity, version, active state, all persisted segments, and lineage with the committed request. Return success only after this readback matches. Never construct confirmation from request data or an in-memory insert list.

Existing versions, global source rows/segments, assignments, and prior audit facts are immutable. Editing means appending a new version; it never updates an old template or segment row. Existing assignments continue to reference their original exact template/version. Customization preserves the global source and records its exact source key/version/template ID in the existing lineage table. No assignment is created or changed.

## 6. Database principal and DDL preflight

The migration files above are the repository's versioned DDL evidence, not proof of the deployed/local runtime schema. Before enabling the writer, an authorized operator must inspect (read-only) the target's `SHOW CREATE TABLE`, engines, generated column/index definitions, foreign keys, and lineage/audit constraints and compare them with the approved migrations and active physical-model contract. This task did not execute SQL; runtime DDL and grants are therefore **not verified here**.

The exact Portal3 runtime DB principal's effective privileges for INSERT/SELECT on template, segment, lineage, and audit tables, and SELECT/locking access to the canonical locality row, are also **not verified here**. Confirm them directly for the runtime account and target database. Do not broaden grants speculatively. Prefer a dedicated least-privilege principal; it must not have UPDATE/DELETE on immutable V2 template/history rows. If the platform's locking semantics require additional privilege, document and approve the minimum exact grant before proceeding. The API's DB principal must no longer be able to accept M04 template mutations after cutover, while it retains the reads and M03 writes required by contract.

The existing API implementation inspected in the repository starts transactions for template create/version/customization and writes segments/lineage. Its template create/version audit insert is conditionally skipped if `prepare()` fails, and returned segments are assembled from inserts. The Portal3 writer contract above deliberately makes audit mandatory and requires persisted readback. This is a requirement for the new writer; it does not authorize code changes to API or migrations in this draft.

## 7. Safe cutover and rollback

Cutover is an ordered, exclusive handoff; no dual-write period is permitted:

1. Approve this exact cross-project document and record its SHA-256 in both repositories.
2. Complete the schema, InnoDB, index/lineage, audit JSON, account-grant, locality-lock, and backup/rollback policy review. No DDL or data change is authorized by this addendum alone.
3. Deploy the Portal3 SQL reader and writer code with all write gates closed. Confirm all M04 catalog, detail, source, and post-write readback paths use SQL and contain no HTTP fallback. M03 assignment routes remain unchanged.
4. Close/disable API V2 M04 template mutation operations (create, version, customization) first. Their stable response must guarantee rejection without mutation. API GET endpoints may remain for other consumers; Portal3 must not invoke them. Keep M03 assignment mutation endpoints available.
5. Verify API M04 writes are rejected without mutation, inspect the target runtime schema, grants, locality-lock behavior, and audit lookup capacity/latency. Record operator attestations for the API cutoff, target preflight, and least-privilege grants. Only then open the four independent Portal3 gates `CHECADOR_M04_SQL_WRITER_ENABLED`, `CHECADOR_M04_API_WRITER_CLOSED`, `CHECADOR_M04_SQL_GRANTS_VERIFIED`, and `CHECADOR_M04_SQL_PREFLIGHT_VERIFIED`. They default to closed. The code rechecks the required schema, InnoDB engines, generated scope key, unique indexes, lineage constraints, and JSON support before each write. Confirm only one writer is active. If the order cannot be proven, keep the SQL writer disabled.
6. Run the isolated acceptance suite and readback checks. Monitor audit and error signals. A failed readback is an indeterminate result: return no success and require lookup with the original idempotency key before any retry.
7. Rollback disables the Portal3 SQL gate first. It does not re-enable API writes until the SQL writer is confirmed disabled and operations are reconciled. No row deletion, version rewrite, or automatic reverse migration is permitted.

## 8. Required acceptance evidence

Before the contract is marked approved/implemented, tests must prove, using isolated MariaDB data and authenticated Portal3 flows:

- create, version, and customization each persist template, exact segments, audit, and lineage atomically;
- rollback after injected failure at every insert stage leaves no partial rows;
- same key/same digest returns the existing exact readback; same key/different digest conflicts; simultaneous same-key and different-key version requests serialize without duplicate version numbers;
- missing/failed audit insert, missing `WORK`, invalid hours/offset, stale version, global shadow, source mismatch, and readback mismatch never report success;
- Encargado 6 can write only its authorized station; Dirección is read-only; forged/foreign IDs and explicit deny fail closed;
- Autolavado 9 cannot write via Palo Solo 2's permission/catalog mapping; its read behavior remains separately scoped;
- Portal3 M04 catalog/detail/readback paths use SQL with no HTTP fallback; M03 writes remain under their current API V2 contract; API M04 mutation endpoints are disabled before Portal3 SQL gate is enabled;
- DataTables refresh from the persisted/readback representation, F5 shows identical values, and M11, M03 weekly programming, and existing global/local catalog reads have no regression.

Fixtures and isolated writes must be labeled as such; they are not evidence of production grants, Plesk schema, or operational records. No operational turn data is to be created to satisfy acceptance.

## 9. Explicit approval gate

The following approvals are required before the M04 SQL writer can be activated or cut over. Local implementation and isolated tests may be reviewed with all gates closed:

1. **Cross-project architecture/contract owner:** approve the exact hash of this addendum for both Portal3 and API V2, including the M04-only direct Portal3 SQL writer exception and the exact four-clause supersession in §2. Architecture Freeze V1.0 remains unchanged.
2. **API V2 owner:** approve disabling only M04 template create/version/customization mutations while retaining all relevant GET routes and M03 assignment writes.
3. **Database/security owner:** approve the verified target-specific Portal3 runtime principal and minimum effective grants/locking access after the read-only DDL/grant preflight. No grant can be approved from repository DDL alone.
4. **Autolavado authority:** no approval is sought for department 9 writes in this draft because they are denied. Any such capability requires a separate explicit authorization and independent scope contract.

**Approval statement:** “I approve, for both Portal3 and Checador V2 API, the exact `CHECADOR_V2_M04_PORTAL3_SQL_TEMPLATE_WRITER_ADDENDUM_V1.0_DRAFT.md` SHA-256 [hash], authorizing the M04 Portal3 SQL read/write path, the §2 clause changes, and the §7 exclusive writer cutover after the target schema/grant preflight. Portal3 M04 must not call API V2 for catalog/detail reads, readback, or template mutations. API template GET endpoints may remain available to other consumers; M03 assignment reads/writes remain under their current contracts. Autolavado department 9 receives no inherited write permission. The Architecture Freeze V1.0, physical data model, J1/J2, Lector, and all other API/Portal3 contracts remain unchanged. This approval does not authorize schema migration, operational writes, or deployment until the separate environment gates are satisfied.”

**Evidence state:** Versioned DDL inspected in the repository; audit table includes JSON details and station/actor time indexes but no idempotency unique key. The active MariaDB runtime DDL and Portal3/API account grants remain unverified. The Portal3 implementation is being prepared behind default-off gates; no SQL was run and no operational data, API code, migration, Architecture Freeze, or deployment was changed.
