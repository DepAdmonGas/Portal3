# Checador V2 — Portal3 Implementation Status

## P03 — Supervisor daily observed punch consultation

**P03_PORTAL3_STATUS:** `CLOSED_VERIFIED_DEVELOPMENT`

Portal3 now provides a read-only daily observed-punch consultation for an
authorized workplace and local calendar date. The controller validates the
existing Portal3 Checador ACL and workplace scope before making any
server-to-server request. The API independently validates the Portal3 JWT
and workplace scope.

The client is enabled only when `APP_ENV` is `dev`, `development`, or
`testing`, and requires the server-side `CHECADOR_API_BASE_URL` environment
value. There is no production/default host fallback.

The timezone endpoint is queried first:

`GET /api/v2/workplaces/{workplace_id}/timezone?date=YYYY-MM-DD`

The returned `from_utc` and `to_utc` values are passed unchanged to:

`GET /api/v2/punch-events`

The browser receives only Portal3-rendered data. No API token, signing
secret, Device Auth header, or direct API URL is exposed to JavaScript.

The screen displays observed PunchEvents and preserves API integrity states
(`VALID`, `INVALID`, `UNVERIFIABLE`, `LEGACY_UNSEALED`). Empty results do not
become `SIN MARCACIÓN`, `FALTA`, payroll conclusions, or other attendance
classifications. Unresolved, ambiguous, or invalid workplace timezone
configuration prevents the Punch Read request.

Development-only regression evidence:

- P301–P330: 30 PASS / 0 FAIL / 0 SKIPPED
- Checador access foundation: 22 PASS / 0 FAIL / 0 SKIPPED
- Portal3 security suite including P03: 237 PASS / 0 FAIL / 0 SKIPPED

No schema, migration, API endpoint, PunchEvent write, Telegram, payroll, or
production change is included. Production deployment remains unauthorized.

## P04A / P3-A — Level 1 snapshot consultation

**P04A_P3A_PORTAL3_STATUS:** `CLOSED_VERIFIED_DEVELOPMENT`

The daily consultation creates a server-side Level 1 snapshot using the
API-owned timezone window, reads pages from the same snapshot, revalidates
Portal3 authorization on page requests, and fails closed on expiration or
scope errors. Snapshot tokens and API credentials remain server-side.

Development-only regression evidence:

- P401–P432: 32 PASS / 0 FAIL / 0 SKIPPED
- Real fixture smoke: timezone 200, snapshot 201, snapshot page 200

## P04B / P3-B — Level 2 window completeness consultation

**P04B_P3B_PORTAL3_STATUS:** `CLOSED_VERIFIED_DEVELOPMENT`

Portal3 now reads `GET /api/v2/level2/window-status` through the same narrow
server-to-server boundary and the exact `from_utc`/`to_utc` window returned by
the timezone authority. The aggregate `window_level2_status` remains
authoritative (`VERIFIED`, `INCOMPLETE`, or `UNKNOWN`); individual dimensions
never promote an `UNKNOWN` aggregate. Level 2 is rendered as source evidence,
not attendance, absence, payroll, or journey status.

Development-only regression and smoke evidence:

- P3B01–P3B30: 30 PASS / 0 FAIL / 0 SKIPPED
- Real fixture smoke: Level 2 HTTP 200, aggregate `UNKNOWN`, delivery
  `VERIFIED`, operational coverage `UNKNOWN`, time attribution `VERIFIED`,
  zero governed events `true`, observed result count 0
- Responsive validation: 1366×768, 1024×768, 768×768, and narrow viewport;
  no horizontal overflow and explicit UNKNOWN/empty wording

## P04C / P3-C+D — Effective schedule and workplace-timezone integration

**P04C_P3C_PORTAL3_STATUS:** `CLOSED_VERIFIED_DEVELOPMENT`

The existing effective-schedule integration remains API-authoritative. Portal3
does not pair punches, calculate worked time, infer absence, or produce payroll
conclusions locally.

Development-only regression evidence:

- P4C01–P4C30: 30 PASS / 0 FAIL / 0 SKIPPED

## P04D / P3-E — On-demand Journey integration

**P04D_P3E_PORTAL3_STATUS:** `CLOSED_VERIFIED_DEVELOPMENT`

Portal3 evaluates a selected canonical worker/day through the server-side
`POST /api/v2/journeys/evaluate` boundary. The request uses the existing
Checador authorization and workplace scope, and the downstream API JWT never
reaches the browser. Journey pairing, calculation, finality, and provenance
remain API-owned; Portal3 renders the returned source, schedule, timeline,
review, pairing, and calculation evidence without recomputation.

The verified contract SHA-256 is:

`9eda2b2c7174154d6524f7130ec197678958801df41004925a60928462876c9b`

The safe development smoke returned HTTP 200 with `evaluated=false`,
`UNSCHEDULED`, and `UNSCHEDULED_DAY` for worker 1, workplace 1, and local date
2026-10-12. No absence, `FALTA`, `SIN MARCACIÓN`, or payroll conclusion is
produced. A second scheduled case was not run because it could materialize
P1/P2 business data.

Development-only regression evidence:

- P4D01–P4D40: 40 PASS / 0 FAIL / 0 SKIPPED
- P04D + P04C + P04B + P04A + P03 + access foundation: all green
- Portal3 security suite: 237 PASS / 0 FAIL / 0 SKIPPED
- Browser automation was unavailable in this environment; no responsive
  result is claimed for this slice.

P3-G incidence/administrative evaluation and P3-H four-slot projection remain
deferred. No schema, migration, direct V2 DB write, legacy attendance,
payroll, ACL, Telegram, Lector, or production change is included, and
production deployment remains unauthorized.

## P3F — Journey audit and provenance integration

**P3F_PORTAL3_STATUS:** `CLOSED_VERIFIED_DEVELOPMENT`

Portal3 now consumes the additive Journey Contract V1.1 provenance envelope
without re-querying raw events or rebuilding pairing/calculation evidence.
The API remains the authoritative source for worker/workplace/date/timezone,
schedule, Level 2 evidence, P1/P2 revisions and algorithms, creation
timestamps, evaluated raw-event membership, segment UUID links, source
completeness, and response generation time. The view exposes only the safe
event fields and references; internal raw/pairing/calculation primary keys
are not rendered. Nullable device identifiers remain “dispositivo no
disponible”, and a null `policy_rule_version` remains “No aplica”.

The verified Journey Contract V1.1 SHA-256 is:

`7f71372d9deceb6820e6e71e1be38dadd12e87cd5bb4a5f69bcd250ee85c6f3f`

The read-only development smoke returned HTTP 200 with `evaluated=false`,
`UNSCHEDULED`, and `UNSCHEDULED_DAY` for worker 1, workplace 1, and local
date 2026-10-12. It returned null pairing/calculation, raw-event count/list,
P1/P2 creation timestamps, shift-template version, Level 2 policy reference,
and policy rule version; no synthetic provenance was fabricated. A second
evaluated UI case was not run because it could materialize business data.

Development-only regression evidence:

- P3F01–P3F35: 35 PASS / 0 FAIL / 0 SKIPPED
- P4D01–P4D40: 40 PASS / 0 FAIL / 0 SKIPPED
- P4C01–P4C30: 30 PASS / 0 FAIL / 0 SKIPPED
- P3B01–P3B30: 30 PASS / 0 FAIL / 0 SKIPPED
- P401–P432: 32 PASS / 0 FAIL / 0 SKIPPED
- P301–P330: 30 PASS / 0 FAIL / 0 SKIPPED
- Checador access foundation: 22 PASS / 0 FAIL / 0 SKIPPED
- Portal3 security suite: 237 PASS / 0 FAIL / 0 SKIPPED

The audit detail uses native collapsed `details/summary` disclosure. Browser
automation was unavailable in this environment, so responsive runtime
results are not claimed. API, Lector, database schema, audit-table,
administrative-action, Telegram, payroll, and production changes are not
included; production deployment remains unauthorized.

P3-G contract/governance audit is now ready. P3-G implementation and P3-H
UI projection remain not started.

## P3-G — Incidence and administrative evaluation contract freeze

The fields in this initial freeze checkpoint describe the state before the
subsequent P3G-A, P3G-C, P3G-D and cross-repository end-to-end closure
activities. They are preserved as historical checkpoint evidence; the
current reconciled state is recorded in the closure section at the end of
this document.

- **P3F:** `CLOSED_VERIFIED_DEVELOPMENT`
- **P3G_GOVERNANCE_AUDIT:** `COMPLETE`
- **P3G_OWNER_DECISIONS:** `APPROVED`
- **P3G_CONTRACT:** `FROZEN_OWNER_APPROVED`
- **P3G_IMPLEMENTATION:** `NOT_STARTED`
- **P3G_API_BOUNDARY:** `REQUIRED_NOT_YET_FROZEN`
- **P3H:** `NOT_STARTED`

On 2026-10-14, the owner approved Architecture Option B, canonical V2 domain
authority (`AttendanceIncident` + revisions + evaluation), legacy vacation and
permission read-only adapters, blocking legacy incapacity automation, PunchEvent
immutability, fail-closed `REVIEW_REQUIRED` conflict policy, canonical worker
(`op_rh_personal.id`) and workplace (`op_rh_localidades.id`) scoping, append-only
revocation/cancellation, human authorization requirement for `FALTA`, dedicated
ACL namespaces (`checador-v2-incidencias`, `checador-v2-incidencias-resolucion`,
`checador-v2-incidencias-autorizacion`), no generic `recursos-humanos.editar`
authority, zero legacy dual-write, deferred payroll integration, and a dedicated
Portal3 backend -> API server-to-server boundary.

The companion contract is frozen at:
`docs/v2/CHECADOR_V2_PORTAL3_P3G_INCIDENCE_ADMINISTRATIVE_CONTRACT_V1.0.md`

Verified Contract SHA-256:
`e26e888015b27f9f1023e19cf4e005795836a99fd81a98c5638589fde5ca234d`

P3-G write runtime implementation remains unauthorized and P3-H UI projection
remains deferred. Next gate is `API_P3G_BOUNDARY_CONTRACT_FREEZE_V1_0`. No
runtime write, API, Lector, ACL, database or production change was included.

## P3G-A — Read-only incidence and administrative evaluation integration

**P3GA_PORTAL3_STATUS:** `CLOSED_VERIFIED_DEVELOPMENT`

Portal3 now consumes only the frozen API read boundary for the selected
canonical worker, workplace, and local date:

- `GET /api/v2/incidences` for applicable incidence summaries;
- `GET /api/v2/incidences/{uuid_incidencia}` for complete append-only
  revision history;
- `GET /api/v2/administrative-evaluations` for an existing administrative
  evaluation or a neutral no-evaluation result.

The three calls are server-to-server through the existing Portal3 JWT
boundary. The browser does not call the API directly and no Device Auth
credential is used. Portal3 performs envelope/identity validation only; it
does not create an incidence engine, calculate P1/P2, infer `FALTA` or
`SIN MARCACIÓN`, or apply administrative precedence. `REVIEW_REQUIRED` and
complete revision history remain visible as API-owned evidence. No write
controls, mutation routes, ACL rows, schema changes, or production changes
were introduced.

The companion contract SHA-256 is:

`e26e888015b27f9f1023e19cf4e005795836a99fd81a98c5638589fde5ca234d`

The API boundary contract SHA-256 is:

`e418687501fbdfdf89bcc6f11aab60e49226f265411c4590a39a5fa7799c374a`

Development-only evidence:

- P3GPA01–P3GPA25: 25 PASS / 0 FAIL / 0 SKIPPED
- P3F, P04D, P04C, P04B, P04A, P03 and access regressions: all green
- Portal3 security suite: 262 PASS / 0 FAIL / 0 SKIPPED
- PHP lint: PASS
- Real API smoke: not run because shared development configuration lacks
  the dedicated P3G ACL; no ACL configuration was created in this slice
- Responsive runtime validation: blocked by browser tooling in this
  environment

At the P3G-A checkpoint, P3G-C write operations remained separately
unauthorized. The later P3G-C lifecycle status is recorded below.

## P3G-C — Administrative lifecycle client integration

**P3GC_PORTAL3_STATUS:** `CLOSED_VERIFIED_DEVELOPMENT`

Portal3 now provides server-side, CSRF-protected POST actions for the four
closed API lifecycle endpoints:

- `resolve` / review;
- `authorize`;
- `reject`;
- `revoke` / cancel.

Each action is guarded locally by the dedicated lifecycle namespace and the
canonical Checador workplace scope, then independently authorized again by
the API. Resolution requires `editar` on
`checador-v2-incidencias-resolucion`; authorization and rejection require
`editar` on `checador-v2-incidencias-autorizacion`; revocation accepts only
the API contract's `eliminar`-or-`editar` rule in that authorization
namespace. Missing ACL configuration denies by default.

Every request sends the submitted `expected_current_revision` and one
server-generated `Idempotency-Key`. Transport recovery retains that key;
stale revision, idempotency conflict, invalid transition, authorization,
not-found, validation, and unavailable responses fail closed. Successful
actions redirect through the canonical P3G-A reads, so state and complete
append-only history are refreshed from the API rather than patched locally.
No optimistic `ACTIVE`, `REJECTED`, `CANCELLED`, `FALTA`, `SIN MARCACIÓN`,
payroll, P1/P2, or administrative-evaluation result is synthesized.

Development-only evidence:

- P3GPC01–P3GPC35: 35 PASS / 0 FAIL / 0 SKIPPED
- P3GA01–P3GA25: 25 PASS / 0 FAIL / 0 SKIPPED
- P3F, P04D, P04C, P04B, P04A, P03 and access regressions: all green
- Portal3 security suite: 297 PASS / 0 FAIL / 0 SKIPPED
- PHP lint: PASS
- Real API smoke: not run because shared development lacks the dedicated
  resolution/authorization ACL namespaces; no ACL rows were created
- Responsive runtime validation: blocked by browser tooling in this
  environment

At the P3G-C checkpoint, the Portal3 client did not call administrative
evaluation execution. The later P3G-D status is recorded below.

## P3G-D — Administrative evaluation execution integration

**P3GD_PORTAL3_STATUS:** `CLOSED_VERIFIED_DEVELOPMENT`

Portal3 now exposes one authenticated, CSRF-protected POST command that
delegates administrative evaluation to the closed API boundary:

`POST /api/v2/administrative-evaluations/evaluate`

The browser never calls the API directly. Before the server-to-server call,
Portal3 validates the canonical Checador workplace scope and requires
`editar` in either `checador-v2-incidencias-resolucion` or
`checador-v2-incidencias-autorizacion`. Explicit final `FALTA` authorization
is a separate control requiring the authorization namespace, an explicit
confirmation, and `authorize_falta=true`; normal evaluation omits that flag.
Resolution-only users cannot submit the final FALTA action.

The command sends only the canonical worker, workplace, local date, optional
persisted calculation reference, and one `Idempotency-Key`. Stale calculation
references, idempotency conflicts, authorization failures, missing
calculations, validation errors, timeouts, and server errors fail closed.
An uncertain retry retains the same key. Successful POSTs immediately perform
the canonical `GET /api/v2/administrative-evaluations` read-back before the UI
reports success. `REVIEW_REQUIRED`, `PENDING_REVIEW`, nullable
`policy_rule_version`, linked incidence/adjustment references, calculation
reference, fingerprint, algorithm version, and UTC creation time remain API
provenance; no local P1/P2, raw-membership, schedule, timezone, incidence
precedence, FALTA, SIN MARCACIÓN, payroll, legacy, or direct V2 database logic
is introduced.

The verified hashes remain:

- Portal3 P3-G contract: `e26e888015b27f9f1023e19cf4e005795836a99fd81a98c5638589fde5ca234d`
- API P3-G contract: `e418687501fbdfdf89bcc6f11aab60e49226f265411c4590a39a5fa7799c374a`
- Architecture Freeze: `87bf3e714f7358cbdace5c4f099573d77d9ecb9033328c706df5c5295304fd85`

Development-only evidence:

- P3GPD01–P3GPD40: 40 PASS / 0 FAIL / 0 SKIPPED
- P3GC01–P3GC35: 35 PASS / 0 FAIL / 0 SKIPPED
- P3GA01–P3GA25: 25 PASS / 0 FAIL / 0 SKIPPED
- P3F: 35 PASS / 0 FAIL / 0 SKIPPED
- P04D: 40 PASS / 0 FAIL / 0 SKIPPED
- P04C: 30 PASS / 0 FAIL / 0 SKIPPED
- P04B: 30 PASS / 0 FAIL / 0 SKIPPED
- P04A: 32 PASS / 0 FAIL / 0 SKIPPED
- P03: 30 PASS / 0 FAIL / 0 SKIPPED
- Checador access: 22 PASS / 0 FAIL / 0 SKIPPED
- Full Portal3 security suite: 337 PASS / 0 FAIL / 0 SKIPPED
- PHP lint: PASS; `git diff --check`: PASS

Real development smoke remains `NOT_RUN_MISSING_DEV_ACL_CONFIGURATION`; no
shared ACL rows were inserted. Responsive runtime validation remains blocked
by browser tooling. No schema, database, ACL, API source, Lector source,
production, commit, or push change was made in this slice. P3-H UI
implementation remains not started; the later end-to-end closure
reconciliation below records when its preparation became unblocked.

## P3-G end-to-end closure reconciliation

**Activity:** `CHECADOR_V2_PORTAL3_P3G_CLOSURE_STATUS_RECONCILIATION`
**Status:** `CLOSED_DOCUMENTATION_RECONCILED`
**Date:** 2026-10-14

The completed cross-repository `CHECADOR_V2_P3G_END_TO_END_RECONCILIATION_AUDIT`
is now the current status authority for the P3-G gate:

- `P3G_END_TO_END_STATUS: CLOSED_RECONCILED`
- `P3G_OVERALL_HEALTH: GREEN_WITH_DEBT`
- `P3H_READINESS: YES`
- `P3H_UI_PROJECTION_READY: YES`
- `P3H_IMPLEMENTATION: NOT_STARTED`
- `DEC10_FUNCTIONAL_BLOCKERS: NONE`
- `P0_FINDINGS: NONE`
- `P1_FINDINGS: NONE`

`P3H_UI_PROJECTION_READY` is used here with the semantic meaning “the
project is permitted and ready to begin P3-H presentation/projection
contract work”; it does not mean that the four-slot UI has already been
implemented. Its reconciled current value is `YES`, while
`P3H_IMPLEMENTATION` remains `NOT_STARTED`.

The closure is development-only. The following debt remains explicit and
does not reopen P3-G or make P3-H readiness false:

- `REAL_SHARED_DEV_E2E: NOT_RUN_MISSING_DEV_ACL_CONFIGURATION`
- `RESPONSIVE_RUNTIME_VALIDATION: BLOCKED_BY_BROWSER_TOOLING`
- known API UAT harness debt in
  `tests/v2_snapshot_read_api_tests.php` (cleanup ordering versus the
  foreign-key constraint);
- known API UAT harness debt in
  `tests/v2_l2_false_success_controller_integration_tests.php`
  (persistent database / duplicate-migration harness behavior).

Previously verified evidence from the completed audit is recorded here
without rerunning tests in this documentation-only reconciliation:

- API P3-G: 142 PASS / 0 FAIL;
- API core: 297 PASS / 0 FAIL;
- Portal3 P3-G: 100 PASS / 0 FAIL;
- Portal3 security: 337 PASS / 0 FAIL.

Production remains `NOT_DEPLOYED / NOT_MODIFIED`. This reconciliation made
no runtime, API, database, schema, ACL, or production changes. Historical
P3-G governance records retain the earlier `OWNER_DECISION_REQUIRED` and
`P3H_UI_PROJECTION_READY: NO` checkpoint as historical evidence; they are
not current contradictions.

## P3-H — Presentation / projection contract freeze

**P3H_CONTRACT_STATUS:** `FROZEN_OWNER_APPROVED`
**OWNER_APPROVAL_DATE:** `2026-10-14`
**OWNER_DECISION_REQUIRED:** `NO`

The owner-approved Portal3 P3-H contract is:

`docs/v2/CHECADOR_V2_PORTAL3_P3H_PRESENTATION_PROJECTION_CONTRACT_V1.0.md`

Its SHA-256 is:

`96a700731fe93173b6383effcd648cd4d785ce2178d32a0331cd0b9f2aa9b584`

The companion API event-classification contract is frozen at
`FROZEN_OWNER_APPROVED` with SHA-256
`c710165953f455352cf5675b7581a581be03a632a493e6a0c939276865d6658f`.
That paragraph records the V1.0 checkpoint and remains historical evidence;
the V1.1 reconciliation below is the current companion binding.

## P3-H V1.1 companion binding reconciliation

**Activity:** `CHECADOR_V2_PORTAL3_P3H_PRESENTATION_PROJECTION_CONTRACT_FREEZE_V1_1`
**Status:** `FROZEN_OWNER_APPROVED`
**Owner approval:** recorded 2026-10-14; no further owner decision required

Portal3 P3-H V1.1 is the canonical companion amendment:

`docs/v2/CHECADOR_V2_PORTAL3_P3H_PRESENTATION_PROJECTION_CONTRACT_V1.1.md`

It binds Portal3 to API P3-H V1.1 (`FROZEN_OWNER_APPROVED`, SHA-256
`df6b0bec7c6977cb0c12bf5409c8243528af145036474aa3adcf237b21f4711c`) and
the existing canonical endpoint `POST /api/v2/journeys/evaluate`. The
previous V1.0 contract remains immutable historical evidence and is superseded
only for that API binding and endpoint reference. All presentation semantics
remain unchanged.

```text
PORTAL3_P3H_V1_1_CONTRACT: FROZEN_OWNER_APPROVED
API_P3H_V1_1_BOUND: YES
JOURNEY_ENDPOINT_CONTRACT_MISMATCH: RESOLVED_CONTRACTUALLY
PORTAL3_P3H_RUNTIME: PENDING_RESUME
```

The API P3-H payload runtime is closed and verified in development; Portal3
P3-H runtime implementation remains pending. This documentation-only
reconciliation made no runtime, schema, ACL, database, test, or production
change.

## P3-H V1.1 presentation projection implementation

**Activity:** `CHECADOR_V2_PORTAL3_P3H_PRESENTATION_PROJECTION_IMPLEMENTATION_RESUME_V1_1`
**Status:** `CLOSED_VERIFIED_DEVELOPMENT`
**Journey endpoint parity:** `PASS`

Portal3 now projects the frozen four compact slots from the authoritative
`pairing_role` plus `segment_ordinal` fields returned by API P3-H V1.1.
`SURPLUS` and `UNMATCHED` remain in the complete evidence detail, while
`AMBIGUOUS_PAIRING` remains scoped to the segment/Journey. No chronology,
first-four fallback, local P1/P2 calculation, automatic `FALTA`, or automatic
`SIN MARCACIÓN` inference was introduced. Context changes clear the prior
projection.

Development verification:

- P3-H focused projection: 43 PASS / 0 FAIL / 0 SKIPPED
- P3-G-D: 40 PASS / 0 FAIL / 0 SKIPPED
- P3-G-C: 35 PASS / 0 FAIL / 0 SKIPPED
- P3-G-A: 25 PASS / 0 FAIL / 0 SKIPPED
- P3-F: 35 PASS / 0 FAIL / 0 SKIPPED
- P04D: 40 PASS / 0 FAIL / 0 SKIPPED
- P04C: 30 PASS / 0 FAIL / 0 SKIPPED
- P04B: 30 PASS / 0 FAIL / 0 SKIPPED
- P04A: 32 PASS / 0 FAIL / 0 SKIPPED
- P03: 30 PASS / 0 FAIL / 0 SKIPPED
- Checador access: 22 PASS / 0 FAIL / 0 SKIPPED
- Portal3 security suite: 380 PASS / 0 FAIL / 0 SKIPPED

Real development rendering smoke:
`NOT_RUN_MISSING_DEV_ACL_CONFIGURATION`. Responsive runtime validation remains
`BLOCKED_BY_BROWSER_TOOLING`. These remain explicit UAT debt; no fake punches,
ACL rows, database writes, schema changes, API changes, or production changes
were made.

## P3-H end-to-end reconciliation final closure

**Activity:** `CHECADOR_V2_P3H_END_TO_END_RECONCILIATION_AUDIT_FINAL_CLOSURE`
**Status:** `CLOSED_RECONCILED`

The Architecture Freeze, API P3-H V1.1, and Portal3 P3-H V1.1 hashes remain
matched. The canonical Journey boundary remains `POST /api/v2/journeys/evaluate`.
The API regression evidence is complete: P3-H 32/32, Journey provenance 33/33,
Journey 30/30, and P3G-A/B/C/D 32/35/35/40 PASS, for 237/237 PASS.

```text
P3H_END_TO_END_STATUS: CLOSED_RECONCILED
P3H_OVERALL_HEALTH: GREEN_WITH_DEBT
NEXT_MILESTONE_READINESS: YES
REAL_SHARED_DEV_E2E: NOT_RUN_MISSING_DEV_ACL_CONFIGURATION
RESPONSIVE_RUNTIME_VALIDATION: BLOCKED_BY_BROWSER_TOOLING
```

The remaining shared-development ACL and browser-tooling items are P2/UAT
debt only. No P0/P1 finding, schema change, ACL mutation, database change, or
production action was introduced by this closure.
