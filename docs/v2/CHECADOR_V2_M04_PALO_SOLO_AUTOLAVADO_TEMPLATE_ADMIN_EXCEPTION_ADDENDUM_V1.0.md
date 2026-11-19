# CHECADOR V2 — M04 Palo Solo → Autolavado administration exception

**Status:** Owner-approved, M04-only implementation addendum (2026-11-19).
**Applies to:** Portal3 M04 station-template administration and catalog/detail reads required by that administration.
**Does not change:** Architecture Freeze V1.0, API, Lector, M03 scheduling permissions or writes, M11, database schema, or the M04 SQL/API exclusive-writer cutover.

## 1. Narrow authorization exception

An active authenticated Portal3 Encargado may administer M04 templates for canonical Autolavado department/locality `9` only when all of the following are true on every request:

1. The user is reloaded from the institutional database and is active (`estatus = 0`).
2. The user's current position is Encargado (`id_puesto = 6`).
3. The effective permission `checador.turnos.estacion.administrar` allows the operation; an explicit user-level denial continues to prevail.
4. The server-resolved active Checador scope contains exactly one station locality, canonical Palo Solo `2`, after the established unique physical-station mapping. It must not be inferred from numeric equality, Autolavado's shared reader, or physical station `11`.
5. The target is explicitly canonical Autolavado department/locality `9`, and the active Checador configuration and `op_rh_localidades` row continue to identify it as that department.

The exception grants no M04 write access to any other locality. An Encargado authorized only for another station, an inactive user, a user without the effective permission, or a user whose resolved Palo Solo scope is absent, multiple, ambiguous, or inactive is denied. Dirección remains read-only.

## 2. Canonical persistence and global templates

Create, station customization, and immutable version operations selected for Autolavado persist the template's `id_estacion = 9`. Their ordered segments reference that template; lineage, when a GLOBAL source is customized, uses `id_estacion = 9`; and the audit row records `id_estacion = 9`. No part of an Autolavado operation is stored under Palo Solo `2` or physical station `11`.

The GLOBAL source template and its segments remain immutable. The authorized M04 administrator may read the exact GLOBAL version needed for customization, but the operation creates a separate station-local copy and lineage at locality `9`. No GLOBAL mutation is permitted. Palo Solo's station-local catalog and histories remain unchanged and separate from Autolavado's station-local catalog.

## 3. M03 and other access boundaries

This exception is implemented only in M04 catalog selection, template administration, and the M04 save handler. It does not grant Autolavado scheduling, worker, assignment, or history permissions. M03 continues to require its own `authorizeScheduling`/assignment ACL and canonical department scope. A saved selector context of department `9` does not authorize M03 by itself.

## 4. Contract reconciliation boundary

This addendum supplements only §4 and the related negative acceptance statements in §§8–9 of `CHECADOR_V2_M04_SQL_READ_WRITE_UNIFIED_ADDENDUM_V1.0.md`, replacing the statement that Autolavado has no write authority under that agreement with the exact exception in §1 above. All other unified-addendum clauses, including SQL ownership, transactions, immutable versions, audit, idempotency, readback, gates, and exclusive cutover, remain unchanged.

No FROZEN contract is edited by this addendum. No HTTP writer is reopened, and no schema change or deployment is authorized.
