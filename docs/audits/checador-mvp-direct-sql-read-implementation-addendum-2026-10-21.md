# Checador V2 MVP — direct SQL read implementation addendum

**Activity:** `CHECADOR_V2_MVP_DIRECT_SQL_READ_IMPLEMENTATION_V1`
**Date:** 2026-10-21
**Owner authorization:** `APPROVED` for local development implementation only

## Approved boundary

This addendum records the approved implementation decision for the raw-punch MVP report. It does not amend or replace the J1/J2 architecture freezes.

The data path remains Lector → API V2 → authoritative database; Portal3 reads persisted raw events for this report only. Portal3 still does not receive events from the browser and does not write to the raw table. The MVP report no longer calls `GET /api/v2/punch-events` or `GET /api/v2/workplaces/{id}/timezone`. Existing API clients and API-backed Checador features for snapshots, Level 2, Jornada, incidents, and evaluations remain unchanged.

## Local reader and safeguards

- New repository: `App\Services\ChecadorRawPunchReadService`.
- The repository contains parameterized `SELECT` statements only against `op_rh_v2_marcaciones_crudas`; its projection excludes metadata JSON, validation details, integrity seals, and key identifiers.
- Every query requires the locality/station ID already authorized by `ChecadorAccessService`, uses `[from_utc, to_utc)` boundaries, accepts at most 31 days, caps page size at 200, and optionally filters only by canonical `id_personal_resuelto`.
- Rows with `ocurrido_at_utc IS NULL` are surfaced only when `recibido_at_utc` lies in the requested UTC interval; the UI labels them as occurrence-time unresolved and separately labels their receipt time. No source/device timezone is used to infer workplace time.
- The report is a live, non-snapshot read. Pagination and CSV explicitly disclose that concurrent arrivals can change the result. Empty results mean only “no records observed in this query”; they do not prove absence, synchronization completeness, or a payroll conclusion.
- Integrity is rendered as **“Integridad no verificada por Portal3”**. Portal3 does not verify HMAC and does not select or copy integrity key/seal data.
- The current general Portal3 connection may be used only when `APP_ENV` is local development and its configured target plus server identity match schema `bd_admongas` on Fedora:3306. This is a code-level development guard and use of the existing connection, **not** proof that the credentials are read-only.
- Production/preproduction are deliberately blocked by the reader. Before deployment, provide a separately configured private connection with least-privilege `SELECT` grants for the required raw table, and verify environment/database identity and credential isolation. No user, grant, database, or migration was created in this activity.

## Authorization retained

- The report first resolves the current Portal3 user’s active Checador scope and rejects a URL station outside the returned server-side workplace list. SQL is not queried until this check succeeds.
- Dirección de Operaciones (puesto 13) remains limited to the existing active Checador allowlist; this change does not expand it.
- Encargado (puesto 6) remains limited to the user’s primary `id_gas`, only when that station is in the same Checador allowlist. User 470 does not inherit a general multi-station exception; station 8 remains unavailable until the existing Checador station configuration authorizes it.
- CSV re-runs the authorization lookup for the requested station and requires the effective parent-module `descargar` capability, in addition to the report’s read and station-scope checks. The existing ACL represents submenu assignment as membership; its capability flags are on the parent module.

## Local evidence and limitations

The development target was verified by read-only checks as `APP_ENV=dev`, local host `fedora`, port `3306`, schema `bd_admongas`. At verification time the raw table contained seven rows. Four rows were in locality/station 1; all four had unresolved canonical identity and `ocurrido_at_utc IS NULL`. The other three rows were outside the current Checador station scope and were not queried through the report reader. The previously reported remote pilot for worker 558 on 2026-10-19 is not present in this Fedora development database and was not used as evidence.

No authoritative workplace timezone configuration was established for this Portal3 local DB. The report therefore accepts and labels UTC calendar dates only, displays available UTC timestamps, and does not convert device/source timestamps. A missing Workplace timezone authority remains a deployment/product dependency.

## Operational and security gates still open

1. Provision and independently verify the dedicated private read-only database connection for the eventual deployment environment; never reuse the application’s write-capable credential for the deployed report reader.
2. Resolve the authoritative versioned Workplace timezone source before enabling local-time input or local-time display.
3. Configure only approved active station IDs in Checador’s existing allowlist before granting a role access to those stations; this implementation does not change ACL/configuration records.
4. Real authenticated browser verification of role-specific UI and CSV remains separate from the direct local database integration test.
