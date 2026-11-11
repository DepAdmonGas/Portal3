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

## M11 administrative device inventory — functional approval record (2026-11-03)

- **Activity:** `CHECADOR_V2_M11_DEVICE_ADMIN_GOVERNANCE_APPROVAL_V1`
- **Status:** `FUNCTIONAL_APPROVAL_RECORDED; CROSS_PROJECT_APPROVAL_PENDING; IMPLEMENTATION_NOT_AUTHORIZED`
- **Approval evidence:** The user, acting as the responsible functional approver, expressly approved the development-only scope on 2026-11-03. The approval applies to `docs/v2/CHECADOR_V2_M11_DEVICE_ADMIN_READ_AUTHORIZATION_ADDENDUM_V1.0.md`, SHA-256 `2308c7f39378699822bab2d583193bdfac0246a97f1c1948ec5771458546a639`. The supplied approval did not identify the approver by personal name; none is inferred here.
- **Approved functional scope:** Read-only development inventory via backend Portal3 → API V2 with delegated user JWT; capability `checador-v2-dispositivos.leer` scoped to Encargado's authorized station and Dirección's institutionally authorized localities; M11 display with institutional components; persisted device state and last telemetry remain separate; station-to-group relation stays optional until verified.
- **Protected scope:** No device writes, reassignment, activation/deactivation, telemetry changes, production work, extra databases, or commits/pushes. Architecture Freeze V1.0 and J1/J2 are unchanged.
- **Cross-project gate:** The addendum remains `PROPOSED_FOR_CROSS_PROJECT_APPROVAL` with `Implementation authorized: NO`. No express approval by the Portal3 project owner or API V2 project owner for this exact SHA, and no completed cross-project approval record, was found. This entry records only the functional approval and does not attribute approval to either project owner. No endpoint, runtime integration, ACL grant, data query, or runtime change was started.
- **Required next step:** The authorized owners of Portal3 and API V2 must each approve this exact addendum SHA through the applicable cross-project governance process. Once that evidence is recorded, the specifically authorized development implementation can be considered without extending scope to writes or production.

## M11 device inventory — cross-project approval (2026-11-03)

- **Activity:** `CHECADOR_V2_M11_CROSS_APPROVAL_AND_IMPLEMENTATION_V1`
- **Status:** `CROSS_PROJECT_APPROVED; DEVELOPMENT_IMPLEMENTATION_AUTHORIZED`
- **Approver:** The user expressly states they are the authorized responsible owner for both Portal3 and API V2 and approves in both capacities. No personal name was supplied; none is inferred.
- **Approved source addendum:** `docs/v2/CHECADOR_V2_M11_DEVICE_ADMIN_READ_AUTHORIZATION_ADDENDUM_V1.0.md`, SHA-256 `2308c7f39378699822bab2d583193bdfac0246a97f1c1948ec5771458546a639`.
- **Current byte-identical addendum copies:** SHA-256 `5e95682af7d41740095bc1ec925de7a3c112d4cd52c0e16b6abe95b395251c21`. Only governance/authorization wording was updated after approval; the approved read contract scope remains unchanged.
- **Authorized development scope:** Read-only API endpoint, delegated backend Portal3 M11 integration, and dedicated `checador-v2-dispositivos.leer` grants for Encargado (position 6, own authorized station) and Dirección (position 13, authorized localities). Device status and last telemetry remain separate; station–group relation remains optional.
- **Excluded:** Device writes or assignment changes, activation/deactivation, credentials, telemetry mutation, schema/migrations, production, POST-gate changes, Level 2 evaluation, Architecture Freeze/J1/J2 changes, extra databases, commit, and push.

## M11 administrative device inventory — development implementation (2026-11-03)

- **Status:** `IMPLEMENTED_AND_VERIFIED_DEVELOPMENT_READ_ONLY` under cross-project owner approval in Entry 058/059 of the API Activity Ledger. Approved source addendum SHA-256 is `2308c7f39378699822bab2d583193bdfac0246a97f1c1948ec5771458546a639`; reconciled identical current copies SHA-256 is `a2f0ad3980032bd992e9aade2aea5f51c9cf141027e7cde64a3d130506f36eb6`.
- **Implementation:** Portal3 M11 calls API V2 through the server-only delegated JWT boundary and displays inventory through its existing DataTables stack. The browser receives no API URL or JWT. The table separates persisted state, assignment history and latest telemetry; it does not derive connectivity. Group remains unavailable until an institutional relation is verified.
- **ACL:** Dedicated read grant `checador-v2-dispositivos.leer` is active for positions 6 and 13 in development only. Position 6 remains limited to its one authorized station; position 13 uses only its current institutional locality scope. No broad Biométricos capability or write gate was changed.
- **Real read evidence:** Portal and API target `bd_admongas` through loopback development configuration. API returned 2 Interlomas devices for user 33/position 6 and 3 devices within user 19/position 13 scope. An explicit unauthorized locality filter returned 403 with no device rows.
- **Chrome:** Authenticated Playwright read checks passed for Encargado and Dirección at 1440, 768 and 375 px. DataTables loaded real API data through Portal3 same-origin BFF; no direct API browser requests, writes, page errors or unauthorized locality rows were observed. Screenshots are retained under `/home/silvinol/.local/state/portal3-e2e/artifacts/candidate-screenshots/` in task-specific folders.
- **Validation:** API projection tests 11/11 PASS; focused M11 Playwright 2/2 PASS; PHP and JavaScript syntax checks PASS. The database returned `COMPLETE` for the current authorized reads; ambiguous/partial cases are covered by isolated projection tests.
- **Preserved:** No device/assignment/credential/telemetry mutation, DDL, production, global POST gate, Architecture Freeze, J1/J2, Lector, commit or push changes.

## M04 global-template station customization — cross-project approval (2026-11-04)

- **Activity:** `CHECADOR_V2_M04_GLOBAL_TEMPLATE_CUSTOMIZATION_APPROVAL_V1`
- **Status:** `CROSS_PROJECT_APPROVED; DEVELOPMENT_IMPLEMENTATION_AUTHORIZED`
- **Approver:** The user expressly identified themselves as responsible owner of Portal3 and API V2 and approved in both capacities; no personal name is inferred.
- **Approved source addendum:** `docs/v2/CHECADOR_V2_M04_GLOBAL_TEMPLATE_STATION_CUSTOMIZATION_ADDENDUM_V1.0.md`, SHA-256 `96744a64edecbde0a63fa5c55756b44e56d03dabbd00e61f8c89acc443a1846b`.
- **Scope:** Development-only local copy of an exact visible global version, immutable lineage and station/source uniqueness through existing API template write/version flow. Encargado remains restricted to their authorized station; Dirección remains read-only. No assignments, global source rows, production, Architecture Freeze/J1/J2, Legacy, Lector, Journey or PunchEvents are modified.
- **Reconciled copies:** The approval/status metadata was reconciled identically in both addenda after approval; current SHA-256 is `651a8b963f39d60663999e12c962fee0ec1ac4a6325b27ac55b7902d923a854e`. The approved source SHA remains recorded above; functional scope is unchanged.

## M04 global template customization — implementation (2026-11-04)

- **Status:** `IMPLEMENTED_AND_VERIFIED_DEVELOPMENT_QA`; this is a scoped M04 feature result, not module-wide acceptance.
- **Approval:** Cross-project implementation approved for the source addendum SHA `96744a64edecbde0a63fa5c55756b44e56d03dabbd00e61f8c89acc443a1846b`. Identical reconciled copies are SHA-256 `651a8b963f39d60663999e12c962fee0ec1ac4a6325b27ac55b7902d923a854e`. Freeze/J1/J2 were not modified.
- **Implementation:** M04 exposes a global-row action to create a local copy and retains the existing versioned edit action for a local row. Portal3 uses its existing authenticated server-side API client, CSRF-protected station-scoped route, Alpine/Axios handler and DataTables instance. Direction remains read-only.
- **QA evidence:** Chrome Encargado user 33 customized global template `LEGACY_IL1_200000_060000` version 1 for Interlomas. API returned 201; Portal3 verified exact-detail and catalog readback before reporting success. The repeated request returned the same local identity (200) without a second customization. A local version edit produced version 2 while retaining the same immutable source lineage. Foreign-station and Dirección writes returned 403. Chrome Dirección confirmed read-only presentation. Focused E2E passed for both profiles at 1440, 768 and 375 px; screenshots are in `/home/silvinol/.local/state/portal3-e2e/artifacts/candidate-screenshots/m04-global-template-customization-20261104`.
- **Preserved:** Global source remains unchanged; the existing six assignments were not changed; no original PunchEvent, production data, Legacy writer, global POST gate, or extra database was touched. Human visual approval remains pending.

## M04 Horarios y turnos — cierre formal de QA (2026-11-04)

- **Activity:** `CHECADOR_V2_M04_FORMAL_CLOSURE_V1`
- **Status:** `M04_CLOSED_FUNCTIONAL_QA`
- **Approval:** El responsable aprobó formalmente el cierre funcional y visual de M04. `FUNCTIONAL_APPROVAL: YES`; `VISUAL_APPROVAL: YES`; `PRODUCTION_APPROVAL: NO`. Esta aprobación final sustituye las notas históricas de aprobación visual pendiente en las entradas de implementación anteriores.
- **Environment:** La aceptación se limita a desarrollo local `bd_admongas`; no constituye aprobación o verificación de producción.
- **Capabilities covered:** Consulta del catálogo global y local; creación de turnos locales; edición mediante nuevas versiones; personalización local de una plantilla global con referencia de origen inmutable y prevención de duplicados; segmentos de trabajo y descanso explícito; turnos nocturnos; permisos de Encargado limitados a su estación y Dirección en lectura; tabla DataTables, búsqueda, paginación, acciones y presentación institucional responsive.
- **Evidence retained:** Regresiones focalizadas M04 de personalización (13/13 en el cierre visual más reciente), regresión de versionado local (3/3), pruebas de API y autorización del registro de implementación (11/11 y 23/23), y Playwright autenticado reciente para Encargado y Dirección (2/2, 1440/768/375). Las capturas candidatas existentes se conservan en `/home/silvinol/.local/state/portal3-e2e/artifacts/candidate-screenshots/`; no se repitieron pruebas para este registro de aprobación.
- **QA and data preservation:** Se conservan las plantillas y asignaciones QA existentes con su trazabilidad. Las 12 plantillas globales originales, la procedencia de la personalización local y las asignaciones existentes permanecen intactas según los readbacks registrados. No se modificaron Freeze, J1/J2, API, migraciones, permisos ni datos durante este cierre.
- **Next module:** `M03_PROGRAMACION_SEMANAL`.

## M03 Programación semanal de turnos — cierre oficial (2026-11-05)

```yaml
PROJECT: CHECADOR_V2
MODULE: M03_PROGRAMACION_SEMANAL
STATUS: CLOSED
OWNER_APPROVAL: YES
OWNER_APPROVAL_DATE: 2026-11-05
FUNCTIONAL_STATUS: OWNER_APPROVED
VISUAL_STATUS: OWNER_APPROVED
ENVIRONMENT: DEVELOPMENT_ONLY
```

- **Aprobación:** El responsable del proyecto aprobó expresamente el diseño y funcionamiento actuales de M03 el 2026-11-05. El cierre cubre exclusivamente desarrollo y no representa aprobación de producción.
- **Funcionalidades aprobadas:** consulta del personal existente de Interlomas; asignación inicial habitual, incluso con inicio a mitad de semana; recurrencia semanal de lunes a domingo; descansos explícitos; cambios permanentes versionados; cambios temporales y retorno al horario habitual; conservación de historial y vigencias; aislamiento de permisos de Encargado y Dirección; interfaz responsive institucional; y etiquetas limpias en selectores de trabajador y turno, sin IDs visibles ni nomenclatura técnica `Legacy`, `v1` o `v2`.
- **Evidencia conservada:** se mantienen las referencias de validación y regresión ya registradas en los artefactos de M03, incluida `tests/checador_m03_schedule_capture_regression.php`. La aprobación funcional y visual de este registro es la aprobación expresa del responsable; no se generaron nuevas pruebas para efectuar este registro.
- **Seguimiento técnico separado:** este cierre no cambia ni declara resueltas observaciones técnicas registradas en sus documentos de seguimiento, incluidas las relacionadas con concurrencia/atomicidad y completitud de lecturas de asignaciones. Permanecen como seguimiento independiente y no alteran el estado de aprobación del módulo en el alcance expresamente aprobado.
- **Gobierno del módulo:** M03 queda cerrado para cambios funcionales o visuales no solicitados. Una nueva modificación requiere una solicitud específica del responsable. No se cambian aquí estados de otros módulos.

## M04 — Catálogo local independiente para Autolavado (2026-11-05)

- **Activity:** `CHECADOR_V2_M04_AUTOLAVADO_ACTIVACION_FUNCIONAL`
- **Alcance autorizado:** adaptar exclusivamente la lectura y administración de plantillas M04 para Autolavado, localidad canónica 9 (`numlista=10`), mediante las tablas y el modelo `STATION` existentes. No se amplían las asignaciones M03, los demás departamentos, los permisos efectivos ni la administración de plantillas globales.
- **Decisión funcional registrada:** Autolavado conserva un catálogo local separado de Palo Solo (localidad 2, `numlista=1`). No se copian turnos entre ambos ámbitos. La selección departamental se valida por alcance explícito; no se interpreta `tb_estaciones.id=9` como Autolavado.
- **Implementación:** Portal3 mantiene la selección institucional, aplica autorización de lectura M04 para el departamento autorizado y enruta la administración local al escritor existente. API V2 revalida permiso y alcance para lista, detalle, creación y versionado; en el catálogo departamental filtra estrictamente `STATION` de localidad 9. No se habilita personalización de plantillas globales en este ámbito. Dirección conserva lectura.
- **Esquema y datos:** no se crearon tablas, columnas ni migraciones. Lectura de desarrollo observada: 12 plantillas globales, 7 locales en Palo Solo y 0 locales en Autolavado antes de la prueba; no se ejecutaron escrituras ni se alteró ningún turno.
- **Validación:** sintaxis PHP y regresiones estáticas focalizadas Portal3 7/7 y API 12/12 PASS; autorización API 23/23 y personalización global 11/11 PASS; regresiones Portal3 de autorización 57/57 y M03 53/53 PASS. Chrome autenticado como Dirección mostró Autolavado seleccionado, el estado vacío del catálogo local y ninguna acción de escritura. La creación/edición real desde Chrome y el readback posterior permanecen sin verificar porque no hubo sesión Encargado disponible; no se presenta como persistencia probada.
- **Protegido:** Palo Solo, M03, asignaciones, ACL/grants, datos, producción, Architecture Freeze, J1/J2 y M04 layout. Sin commit ni push.

## Autolavado — acceso Checador M04 y precondiciones M03 (2026-11-05)

- **Activity:** `CHECADOR_V2_AUTOLAVADO_M03_M04_ACCESS_FIX`
- **M04 card:** La tarjeta utiliza `authorizeM04TemplateRead()` para el departamento seleccionado. El contexto Autolavado se conserva al abrir la ruta institucional M04. Las operaciones de administración siguen detrás del permiso específico `checador.turnos.estacion.administrar` y el alcance del departamento explícito.
- **M03 card/backend:** La tarjeta M03 no se abre para Autolavado mientras falte la precondición de fecha/localidad del backend. La ruta muestra el estado no disponible para departamentos; no consulta ni mezcla las asignaciones de Palo Solo. El API permite resolver únicamente localidad 9 si existe el permiso efectivo M03 y el scope departamental explícito, y valida que un eventual horario asignado pertenezca a una plantilla `STATION` de localidad 9.
- **Bloqueo real observado:** La lectura local de `op_rh_v2_localidades_zonas_horarias` no encontró una fila para localidad 9. Sin zona horaria laboral autoritativa no se puede validar membresía y vigencias por fecha local. No se asumió `America/Mexico_City`, no se creó la fila y no se habilitó el acceso operativo M03.
- **Validación:** Portal3 focalizado: regresión Autolavado 9/9, autorización Checador 57/57 y M03 capture 53/53 PASS; sintaxis PHP y `git diff --check` PASS. API focalizado: autorización 23/23, plantilla global local 11/11 y alcance Autolavado/M03 19/19 PASS; sintaxis y diff check PASS. Chrome autenticado como Dirección abrió M04 con Autolavado seleccionado y catálogo local vacío, sin acción de escritura. No hubo sesión Encargado disponible, por lo que el botón Nuevo/POST de Encargado no se validó en navegador y no se ejecutó ninguna escritura.
- **Datos y módulos protegidos:** cero cambios de base de datos, plantillas, membresías o asignaciones. Palo Solo conserva su catálogo y flujo actuales. Dirección continúa en solo lectura. Sin cambios a M04 de Palo Solo, Architecture Freeze, J1/J2, producción o permisos globales; sin commit ni push.

## 2026-11-06 — M07 Temporary Access Operation Permission Split Approval

- **Activity:** `CHECADOR_V2_PIN_TEMPORAL_IMPLEMENTACION_FINAL_APROBADA`
- **Contract approval:** Cross-project approval expressly granted for Portal3 and API V2 by the responsible user for addendum SHA-256 `87916221327e3f36195288213e5d941792d5a5e70bd90ef450fa9b87f34f7cc5`.
- **Authorized scope:** Development only. `leer` reads; `crear` authorizes; `eliminar` cancels; `editar` rejects. Existing `editar` compatibility remains only for profiles already effectively authorized. Encargado Palo Solo is authorized to read/authorize/cancel and not reject; Dirección is read-only; Autolavado remains out of scope.
- **Implementation authorization:** YES for the exact approved addendum and development environment. No production, schema, migration, M02/M03/M04, or unrelated ACL change is authorized.
- **Evidence:** Approval was recorded without changing the substantive addendum content. Implementation and validation results are to be added after the authorized development slice completes.

### M07 — PIN temporal, permisos por operación (2026-11-06)

- **Aprobación:** Addendum cruzado aprobado para ambos proyectos; SHA-256 `87916221327e3f36195288213e5d941792d5a5e70bd90ef450fa9b87f34f7cc5`. El contenido sustantivo se conservó.
- **Portal3:** autorización de servidor por operación y formularios visibles solo para `crear` (autorizar) y `eliminar` (cancelar); `editar` (rechazar) no aparece para el Encargado con `editar=0`. Se añadió `CHECADOR_TEMPORARY_ACCESS_POSTS_ENABLED`, gate local exclusivo de las rutas M07; la configuración `.env` de desarrollo lo habilita sin activar `CHECADOR_API_REAL_POSTS_ENABLED` ni el gate de M03.
- **API V2:** validación independiente de `leer`, `crear`, `eliminar` y `editar`; compatibilidad de `editar` se conserva solo conforme al addendum. No hubo cambios de ruta, esquema, migración, autenticación de dispositivo ni contratos congelados.
- **ACL local:** se actualizó únicamente el grant preexistente de usuario 52 en `acceso-temporal` a `leer=1, crear=1, editar=0, eliminar=1, descargar=0`. Readback: user 19 Dirección conserva `leer=1` y los demás flags en 0. Configuración conserva allowlist `[2]`, `allow_all=0`; Autolavado sigue excluido. Los diez apartados RH y los módulos existentes comparados no cambiaron.
- **Validación:** Portal3 M07 regresiones 16/16, UI 8/8, feedback PIN 16/16, access foundation 57/57 y POST-gate isolation 18/18 PASS. API `sec_l03_authorization_regression.php` 40/40 PASS y sintaxis PHP PASS. Lectura API local real: HTTP 200, cola vacía para Encargado 52 y Dirección 19. POST directo real de rechazo con JWT de Encargado: HTTP 403 `FUNCTION_PERMISSION_DENIED`.
- **Pendiente de evidencia:** no había solicitud legítima del Lector en la cola; por ello autorizar/cancelar con readback de estado y auditoría no se ejecutaron. Chrome autenticado tampoco estuvo disponible. No se simularon solicitudes ni se realizaron transiciones.
- **Protegido:** sin cambios en M02/M03/M04, producción, permisos de otros perfiles, grants de puesto, tablas o migraciones; sin commit ni push.

### M07 — alcance general de estaciones y separación de IDs (2026-11-06)

- **Corrección focalizada:** la autorización de Portal3/API V2 conserva las localidades canónicas para decisiones de alcance; al filtrar filas de solicitud M07, API V2 utiliza los IDs de estación física del lector, que es el espacio persistido en `op_rh_v2_solicitudes_acceso_temporal.id_estacion`. El mapeo se obtiene de la relación institucional vigente y falla cerrado si no es unívoco o la estación no está autorizada.
- **Reutilización general:** el mecanismo sigue configurado por ACL y allowlist efectiva por módulo. No se habilitaron todas las estaciones ni se añadieron IDs fijos al flujo. Ninguna otra concesión de permisos o cambio de configuración se realizó en esta corrección.
- **Evidencia:** `tests/sec_l03_authorization_regression.php` 42/42 PASS; PHP lint y `git diff --check` PASS. La lectura API local reportada para Encargado 52 y Dirección 19 respondió HTTP 200 con cola vacía; los POST de rechazo no autorizado para Encargado y autorización para Dirección respondieron 403. No hubo solicitudes legítimas pendientes, por lo que no se ejecutaron autorización/cancelación ni auditorías de transición.
- **Límite contractual:** una solicitud conserva dispositivo, estación física anfitriona, referencia externa e identidad personal resuelta; no persiste una localidad efectiva del trabajador. La identidad externa está ligada al dispositivo y no resuelve localidad. El lector compartido Palo Solo/Autolavado no puede atribuir de forma inequívoca una solicitud a localidad 2 o 9 bajo el contrato/modelo actual. Autolavado permanece excluido; habilitarlo requiere aprobación cruzada de una extensión contractual que defina y persista esa atribución, no basta con ampliar el ACL.
- **Verificación de interfaz:** no se encontró código fuente ejecutable del proyecto Lector en el checkout inspeccionado y Chrome no tenía una sesión autenticada abierta para este recorrido. El E2E completo Lector→API→Portal3 y la presentación Chrome quedan `NOT_TESTED`; no se generaron solicitudes ni PIN sintéticos.
