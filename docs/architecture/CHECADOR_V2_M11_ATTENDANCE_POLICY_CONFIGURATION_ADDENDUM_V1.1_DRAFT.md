# Checador V2 — M11 Attendance Policy Configuration Addendum V1.1 (Draft)

**Status:** Draft reflecting explicit Portal3 owner decision; API/Lector compatibility review pending  
**Supersedes:** Only the Portal3 M11 transport and persistence clauses in Addendum V1.0, if approved  
**Freeze:** `CHECADOR_V2_ARCHITECTURE_FREEZE_V1.0.md` remains unchanged  
**Implementation reference:** Portal3 commit `85880f7748484882afe67a9080d4712414e6c65d`

## 1. Decision and scope

Portal3 M11 reads and creates attendance-policy versions directly through its authorized SQL connection. This flow must not issue HTTP requests to API V2 at `/api/v2/attendance-policies`.

This amendment is limited to Portal3's M11 configuration read/write path. It does not change API V2 endpoints, Lector behavior, attendance evaluation, Journey/P2 classification, payroll, PunchEvents, legacy policy storage, or other M11 configuration operations.

Production use is not authorized by this draft. The target MariaDB schema, grants, and connection identity must first be confirmed in Plesk using the read-only queries delivered with this change. This text authorizes no schema change, migration, data correction, policy version, or operational write.

## 2. Canonical persistence and SQL behavior

The policy history remains in the existing append-only table `op_rh_v2_attendance_policy_versions`, using its existing columns and uniqueness constraints:

- `(workplace_id, policy_version)`;
- `(workplace_id, effective_from_local_date)`;
- `(workplace_id, idempotency_key)`.

Portal3 resolves local dates through `op_rh_v2_localidades_zonas_horarias`, requires exactly one applicable IANA timezone interval, and selects versions by canonical `op_rh_localidades.id`. New versions are prospective and append-only. Portal3 must not update/delete historical versions or write `op_rh_localidades_retardo_incidencia`.

The writer uses a transaction, locks the canonical locality row, checks idempotency and effective-date conflicts, inserts the new version plus its existing M11 audit event, and verifies persisted values by SQL readback. Existing uniqueness constraints remain the final duplicate guard. No new table, column, index, or migration is introduced.

The relevant existing sources are `op_rh_v2_attendance_policy_versions`, `op_rh_v2_localidades_zonas_horarias`, and `op_rh_v2_m11_configuration_audit`. Positions and reader profiles continue to use their existing Portal3 SQL models and stores.

## 3. Authorization

Every Portal3 request must continue to validate the authenticated user, CSRF token for writes, effective M11 read/edit grants, and canonical locality scope on the server. The SQL service repeats the effective authorization check before writing. A browser-supplied locality ID is never sufficient authorization.

Autolavado remains canonical locality 9 and a department. Its existing v1 policy (15 minutes / 5 days) is read-only under this amendment. The current M11 policy authorizer accepts only `station_workplaces`, so locality 9 is excluded even where the general selector lists the department. In local MariaDB on 2026-11-18, position 6 has no `departamentos_localidades` scope and no policy audit events exist for locality 9. Preserve the stored version unchanged; do not expose policy editing or infer that it is operationally applied by another module. Any M11 policy read/edit for Autolavado requires an explicit department-scope and permission decision plus an implementation review; this draft adds no grant and does not widen the Palo Solo–Autolavado exception.

## 4. Audit and visibility

Successful new policy versions append `attendance_policy` / `CREATE_VERSION` events to the existing `op_rh_v2_m11_configuration_audit` table. The configuration history reader filters events by the currently authorized locality and the effective policy read grant. The audit records the actor, permission key, locality, policy row ID, action, and UTC timestamp; it does not expose secrets.

## 5. Compatibility and Architecture Freeze review

This draft conflicts with M11 Addendum V1.0, which requires all policy writes to pass through the API policy writer and limits its authorization to local development `bd_admongas`. V1.0 also specifies API readback. This draft changes those Portal3 M11 clauses to direct SQL and records no production authorization.

The Architecture Freeze V1.0 is scoped to API + Lector + Journey engine and remains immutable. It declares implementation unauthorized until its governance process is followed and requires architecture changes to be proposed, evaluated against API and Lector, and approved before revising the Freeze. The Freeze does not explicitly define Portal3's M11 SQL boundary, so compatibility cannot be claimed from the Freeze alone. API/Lector owners must review whether a second writer to the shared policy table is acceptable before this draft is treated as a synchronized cross-project contract.

No change is made to the Freeze by this draft.

## 6. Verification boundary

Portal3 commit `85880f7748484882afe67a9080d4712414e6c65d` passed isolated SQLite regression tests. The service was also exercised against MariaDB 11.8.8 temporary tables matching the physical schema: SQL insert/readback, audit insertion, idempotent replay, effective-date conflict, and transaction rollback passed without writing operational rows. On the real local tables, schema/index metadata, effective-dated reads, and a `SELECT ... FOR UPDATE` transaction followed by rollback were verified read-only. Production schema and grants remain unverified until the supplied read-only Plesk queries are run.
