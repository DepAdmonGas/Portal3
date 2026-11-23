# CHECADOR V2 — M04 SQL Read/Write Addendum V1.0 (Portal3 authorization)

**Status:** Owner-approved Portal3 implementation amendment, 2026-11-23. It supersedes the prior M04 environment, grant, preflight, and API-cut gates for Portal3 writes.
**Scope:** Portal3 M04 reads and administrative creation, station customization, immutable version append, audit, and SQL readback.
**Connection:** Portal3's existing MariaDB connection `default` in Fedora and Plesk.
**Does not modify:** Architecture Freeze V1.0, J1/J2 semantics, API V2 code, Lector, M03, M11, or MariaDB schema/permissions.

## 1. Portal3 transport and writer

Portal3 reads and administers M04 through parameterized SQL on its existing `default` connection. The browser communicates only with Portal3. Portal3 does not call API V2 over HTTP for M04 reads, writes, or readback.

The owner authorizes the Portal3 M04 writer in Fedora and Plesk without requiring `APP_ENV`, host, database-name, SQL-principal, API-cut, or environment-attestation checks. The writer relies on the configured `default` connection; connection or SQL errors are returned as failed operations, never as successful writes.

The four prior Portal3 attestations are retired for M04: `CHECADOR_M04_SQL_WRITER_ENABLED`, `CHECADOR_M04_API_WRITER_CLOSED`, `CHECADOR_M04_SQL_GRANTS_VERIFIED`, and `CHECADOR_M04_SQL_PREFLIGHT_VERIFIED`. `writeGateOpen()`, `isOperationallyReady()`, and the environment-specific schema/grant preflight are not prerequisites.

This Portal3 authorization does not claim that API V2's station-template POST routes are disabled, and Portal3 no longer checks their runtime state before writing. API V2 code is not changed by this amendment. If an API runtime still accepts station-template mutations, concurrent writers can conflict; Portal3's transaction and idempotency guarantees apply to Portal3 requests and do not coordinate with an independent API writer.

## 2. Scope and protected templates

GLOBAL templates and their segments remain read-only through Portal3 station administration. Portal3 may:

1. Create a station template at version 1.
2. Append a new immutable version to a station template.
3. Create a station-local customization from an exact authorized GLOBAL version, preserving source lineage.

Portal3 does not update or delete existing template versions, segments, lineage, or audit rows. It does not change assignments or rewrite historical data.

## 3. Authorization and locality

Every mutation requires an active authenticated Portal3 user, valid CSRF, effective `checador.turnos.estacion.administrar`, Encargado position 6, and server-resolved authorization for the canonical selected locality. The controller and writer recheck identity, permission, and scope; request fields alone confer no authority. Dirección remains read-only. The Palo Solo–Autolavado exception follows the existing explicit authorization and canonical locality rules; it does not grant access to other localities.

The existing demonstration/read-only mode remains read-only. It is not an environment or deployment attestation; operational M04 writes otherwise depend on the authenticated Portal3 authorization and the actual SQL operation.

## 4. Transactions, versioning, audit, idempotency, and readback

Each create, customization, or version operation runs in one transaction on `default`, including the template row, ordered segments, lineage where applicable, and mandatory audit. SQL or constraint failures roll back the transaction and return failure.

The writer locks the canonical `op_rh_localidades` row for the authorized locality, validates duplicate names and expected version, and records a cryptographically random idempotency key hash plus canonical payload fingerprint in the audit JSON. Identical retries return the persisted result; reuse with different content conflicts. Version changes append a new row and preserve the prior version.

Success requires readback from SQL confirming template identity, locality, version, segments, and lineage. It cannot be assembled from request data or an in-memory insert list. No DDL, migration, data repair, or grant change is authorized.

## 5. Database privileges and accepted risk

Portal3 uses the current effective privileges of the institutional `default` account in Fedora and Plesk. The owner expressly accepts that UPDATE/DELETE/ALL privileges mean MariaDB cannot enforce append-only history against that account or another process using the same credentials. Append-only behavior remains mandatory in the Portal3 writer, but is an application rule rather than a database-enforced guarantee.

The writer does not run a separate `information_schema` grant check or a manual schema/host/environment preflight. Missing tables, columns, indexes, constraints, or permissions surface as errors from the actual transactional SQL operations; they must never produce a success response. The existing readback and transaction checks remain mandatory.

## 6. Superseded M04 conditions

For Portal3 M04 only, this owner authorization supersedes the previous requirements that Portal3:

- require four environment attestations;
- verify a closed API writer before accepting its own write;
- require Fedora/Plesk `APP_ENV`, host, document-root, database-name, or SQL-principal matches;
- reject broad SQL grants; or
- pass the separate structural and effective-grant preflight before attempting the transaction.

This addendum does not amend Architecture Freeze V1.0, J1/J2, API V2 code, or Lector. It authorizes no deployment by itself. The API repository's copy was not changed in this Portal3-only task; synchronize the contractual copy separately if cross-repository publication is required.
