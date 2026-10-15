# CHECADOR V2 — Portal3 P3-H Presentation / Projection Contract V1.1

**Activity:** `CHECADOR_V2_PORTAL3_P3H_PRESENTATION_PROJECTION_CONTRACT_FREEZE_V1_1`
**Status:** `FROZEN_OWNER_APPROVED`
**Scope:** Portal3 presentation companion binding only
**Date:** `2026-10-14`

## 1. Purpose and supersession

This is the narrow V1.1 companion amendment to the frozen Portal3 P3-H
presentation contract V1.0. Portal3 P3-H V1.0 remains immutable historical
evidence at
`docs/v2/CHECADOR_V2_PORTAL3_P3H_PRESENTATION_PROJECTION_CONTRACT_V1.0.md`
(SHA-256:
`96a700731fe93173b6383effcd648cd4d785ce2178d32a0331cd0b9f2aa9b584`).

V1.1 supersedes V1.0 only for the companion API contract version/hash binding
and the canonical Journey endpoint reference. Every presentation, authority,
provenance, accessibility, and administrative semantic decision in V1.0 is
unchanged. No Portal3 runtime behavior was changed by this documentation
freeze.

## 2. Frozen governance bindings

```text
VERSION: V1.1
CONTRACT_STATUS: FROZEN_OWNER_APPROVED
OWNER_APPROVAL_RECORDED: YES
OWNER_APPROVAL_DATE: 2026-10-14
OWNER_DECISION_REQUIRED: NO
ARCHITECTURE_FREEZE_SHA256: 87bf3e714f7358cbdace5c4f099573d77d9ecb9033328c706df5c5295304fd85
```

The Architecture Freeze is the byte-identified authority referenced by this
companion contract. The approved API companion is:

```text
API_P3H_CONTRACT_VERSION: V1.1
API_P3H_CONTRACT_STATUS: FROZEN_OWNER_APPROVED
API_P3H_CONTRACT_SHA256: df6b0bec7c6977cb0c12bf5409c8243528af145036474aa3adcf237b21f4711c
API_P3H_PAYLOAD_RUNTIME_STATUS: CLOSED_VERIFIED_DEVELOPMENT
CANONICAL_JOURNEY_ENDPOINT: POST /api/v2/journeys/evaluate
RUNTIME_ROUTE_CHANGE_REQUIRED: NO
NEW_API_ENDPOINT_REQUIRED: NO
```

The endpoint is an existing API boundary. V1.1 corrects only the erroneous
historical `GET /api/v2/journeys/on-demand` reference; it does not authorize
a route, schema, ACL, or API implementation change.

## 3. Presentation semantics preserved without change

The following are copied as binding semantic requirements from V1.0:

| Compact slot | User label | Authoritative selection |
|---|---|---|
| `SLOT_1` | `Entrada 1` | `segment_ordinal=1` and `pairing_role=ENTRY` |
| `SLOT_2` | `Salida 1` | `segment_ordinal=1` and `pairing_role=EXIT` |
| `SLOT_3` | `Entrada 2` | `segment_ordinal=2` and `pairing_role=ENTRY` |
| `SLOT_4` | `Salida 2` | `segment_ordinal=2` and `pairing_role=EXIT` |

Pairing roles remain exactly `ENTRY`, `EXIT`, `SURPLUS`, and `UNMATCHED`,
labelled respectively `Entrada`, `Salida`, `Marcación adicional`, and `Sin
emparejar`. `AMBIGUOUS_PAIRING` remains a segment/Journey-level condition and
is never introduced as a punch-level pairing role.

Five or more PunchEvents remain supported. The complete authoritative
collection is disclosed as `Ver todas las marcaciones (N)`; no truncation or
first-four fallback is allowed. Segments with `ordinal >= 3` remain
authoritative and are disclosed as `+N segmentos adicionales`.

```text
EMPTY_SLOT_MEANS_SIN_MARCACION: NO
P3H_AUTOMATIC_FALTA: NO
FINAL_FALTA_SOURCE: P3G_ADMINISTRATIVE_EVALUATION
ADMIN_STATUS_OVERLAY_ONLY: YES
COMPACT_TIME_FORMAT: HH:mm
COMPACT_SECONDS: NO
COMPACT_TIMEZONE_ABBREVIATION: NO
CROSS_CALENDAR_DATE_DISCLOSURE: YES
```

An empty compact slot never independently means `SIN MARCACIÓN`, `FALTA`,
`AUSENCIA`, or `ERROR`. Administrative status remains separate from event
timestamps and primary slots. `FALTA` is displayed only from the authorized
P3-G administrative evaluation. Portal3 performs no local timezone/workday
recalculation. P3-F provenance access, the V1.0 authority hierarchy,
accessibility requirements, responsive semantic order, and all other
presentation decisions remain binding and unchanged.

```text
LEGACY_ATTENDANCE_IS_P3H_AUTHORITY: NO
PAYROLL_INTEGRATION: DEFERRED
PROVENANCE_ACCESS: P3-F_PRESERVED
```

## 4. Runtime truth and implementation boundary

```text
PORTAL3_P3H_RUNTIME: NOT_IMPLEMENTED_YET
PORTAL3_P3H_RUNTIME_STATUS: PENDING_RESUME
JOURNEY_ENDPOINT_CONTRACT_MISMATCH: RESOLVED_CONTRACTUALLY
```

The prior Portal3 implementation attempt was blocked by the Journey endpoint
contract mismatch. That block is resolved contractually by this V1.1 binding;
it is not evidence that Portal3 P3-H runtime is complete. Future work must
use the API P3-H V1.1 and this Portal3 V1.1 as the canonical contract pair,
without rebuilding API payload behavior or introducing local pairing,
calculation, incidence, timezone, or payroll authority.

## 5. Explicit non-scope

This freeze modifies no runtime files, API source, database, schema, ACL,
tests, views, JavaScript, CSS, Architecture Freeze, Portal3 V1.0 contract,
API P3-H V1.0 contract, or production system. Tests are not rerun for this
documentation-only activity.
