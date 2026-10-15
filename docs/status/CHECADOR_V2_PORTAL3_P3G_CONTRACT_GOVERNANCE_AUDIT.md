# Checador V2 Portal3 — P3-G contract/governance audit

**Activity:** `CHECADOR_V2_PORTAL3_P3G_INCIDENCE_VACATION_INCAPACITY_CONTRACT_GOVERNANCE_AUDIT`  
**Mode:** read-only forensic audit, development-schema verification and decision package  
**Date:** 2026-10-13  
**Result:** `OWNER_DECISION_REQUIRED`

This document records evidence only. It is not a P3-G implementation
contract and does not authorize runtime, schema, ACL, API, Lector or
production changes.

## 1. Evidence boundary and provenance

| Project | Branch | HEAD | Worktree observation |
|---|---|---|---|
| Portal3 | `security/hardening` | `39ffdea98322bd4ea3735556f08dd38dccd2f433` | Pre-existing dirty tracked files (`routes/web.php`, `tests/run_security_suite.php`) and pre-existing Checador untracked files were preserved. |
| API | `feature/api-level2-server` | `28c38918c1ac02900cfbc6b0eedd68719b2e128e` | Pre-existing modified/untracked V2.5 and test files were preserved. |

`WORKTREE_PROVENANCE_RECONCILED: YES`. No file from either runtime was
changed by this activity. The only database used was development
`bd_admongas`, through read-only `INFORMATION_SCHEMA`, `SHOW CREATE TABLE`,
`DESCRIBE`, aggregate and distinct-value queries. No production connection
was made.

## 2. Governance contradictions and unresolved boundaries

`P3G_GOVERNANCE_CONTRADICTIONS: 5`

1. The Portal3 J1 freeze leaves final SQL/enums and legacy authority gates
   open, while the API V2.5 contract/physical model is already frozen with
   concrete category strings and tables. The API model cannot silently close
   the Portal3 business-authority questions.
2. V2.5 requires append-only incidence/evaluation history, but the current
   physical protections are only partial: database triggers protect the
   evaluation header; the incidence master is updated on revision and no
   database triggers protect masters, revisions, applications, adjustments,
   or association rows.
3. The frozen V2.5 contract describes an incidence status as
   `ACTIVE/CANCELLED/SUPERSEDED`, while the physical/runtime split is
   `estado_actual` on the master and `estado_revision` on revisions. This is
   reconcilable, but it is not a byte-identical contract for a Portal3
   adapter until ownership and transition semantics are approved.
4. Current Portal3 workflows authorize create/edit/delete/sign through the
   parent `recursos-humanos` five-flag grant, while J1 explicitly requires
   conceptually separate `read`, `create`, `resolve`, and `authorize` actions
   for incidences.
5. The V2.5 contract is an internal domain layer with no public REST boundary,
   while P3-G integration requires a separately authorized Portal3/API
   boundary. The required boundary, JWT claims and idempotency contract do
   not yet exist.

## 3. Legacy source forensics

### Vacation

**Tables:** `op_rh_formatos`, `op_rh_formatos_vacaciones`,
`op_rh_formatos_firma`, `op_rh_formatos_token`,
`op_rh_formatos_vacaciones_comentarios`.

`FormatosService` maps form `6` to “Formato Vacaciones”. The detail row stores
`id_formulario`, `id_usuario` (used by the runtime as the selected worker),
`num_dias`, `fecha_inicio`, `fecha_termino`, `fecha_regreso` and
`observaciones`; the header stores `id_localidad`, `formato`, `fecha` and
`status`. Status labels are `0 Pendiente`, `1/2 En proceso`, `3/4 Finalizado`.
The signature chain is A (elaboración), B (Vo.Bo.) and C (autorización), with
D verification. Authorization changes the header status and invokes the
format-specific operation. The workflow can delete a non-finalized format,
and signatures/comments/tokens/detail are physically deleted with it. No
cancelled/revoked state or version-preserving amendment history is present.

**Decision:** `VACATION_SOURCE_AUTHORITY: READ_ONLY_ADAPTER_CANDIDATE`.
Approval evidence is present, but effective authority, rejection,
cancellation, revocation and immutable amendment semantics are not proven.
The `id_usuario`/`id_personal` naming and worker catalog bridge also require
an explicit canonical-worker decision before use as V2 input.

### Permission

**Tables:** `op_rh_permisos`, `op_rh_permisos_firma`,
`op_rh_permisos_token`.

`PermisosRrhhService` stores `id_estacion`, `id_personal`,
`fecha_inicio`, `fecha_termino`, `dias_tomados`, `cubre_turno`, `motivo`,
`observaciones`, `estado` and `estacion_cubre`. The runtime labels state
`0 Pendiente`, `1 En Proceso`, `2 Finalizado`; A is requester, B is the
covering person and C is RH Vo.Bo./authorization. Editing is blocked after
state 2; deleting a non-authorized permission physically deletes the master,
signatures and tokens. There is no cancellation/revocation revision or
append-only history. Station scope is checked through the legacy module
selector and special cases.

**Decision:** `PERMISSION_SOURCE_AUTHORITY: READ_ONLY_ADAPTER_CANDIDATE`.
The request interval, worker and approval chain are visible, but the
post-approval lifecycle and historical correction semantics are not safe as
V2 source-of-truth semantics.

### Disability / incapacity / maternity

**Tables:** `op_rh_personal_asistencia_incidencia`,
`op_rh_personal_asistencia`, `op_rh_lista_incidencias`.

The catalog contains distinct runtime labels `Incapacidad`, `Incapacidad
IMSS` and `Maternidad` (document flag `1`). The create path inserts a row
with `id_asistencia`, free-text `incidencia`, `comentario`, `documento` and
`estado=1`; the document path later mutates the same row and may write a
numeric `sueldo_dia` value into `op_rh_personal_asistencia.incidencia`.
There is no approval/validation chain, overlap rule, cancellation/revocation
history or append-only revision model, and the three labels are not
interchangeable by evidence.

**Decision:** `INCAPACITY_SOURCE_AUTHORITY: INSUFFICIENT`.

### Legacy attendance incidence

`ControlDocumentosPersonalService::agregarIncidencia` requires an existing
attendance row, rejects a second active row for that attendance day, inserts
one active incidence, and updates `op_rh_personal_asistencia.incidencia`.
`BiometricosService::eliminarIncidencia` sets the incidence `estado=0` and
resets the attendance value to `0`; it does not append a cancellation event.
The report layer derives `Falta`, `Falta fin de semana`, `Retardo`, `OK` and
other labels from attendance clocks and catalog IDs. This is a mutable,
attendance-coupled legacy projection, not a V2 administrative source.

`LEGACY_INCIDENT_SAFE_AS_V2_SOURCE_OF_TRUTH: NO`.

## 4. Incapacity physical schema gate

`INCAPACITY_TABLE: op_rh_personal_asistencia_incidencia`  
`INCAPACITY_REQUIRED_COLUMNS: id_asistencia, fecha, incidencia, comentario, documento, fecha_inicio, fecha_fin, sueldo_dia, estado`  
`INCAPACITY_ACTUAL_COLUMNS: id, id_asistencia, fecha, incidencia, comentario, documento, estado`  
`INCAPACITY_SCHEMA_MATCHES_CODE: PARTIAL`  
`INCAPACITY_SCHEMA_DRIFT: YES`

The PHP model/service accesses `fecha_inicio`, `fecha_fin` and `sueldo_dia`,
but those columns are absent in the verified development table. This drift
was documented, not repaired, and is a blocker to treating legacy incapacity
as an authoritative period source.

## 5. V2.5 internal domain audit

Development contains the additive V2.5 tables, all currently with zero rows:

- `op_rh_v2_incidencias_maestro`: master, worker, optional station, category,
  scope, inclusive local range and current state;
- `op_rh_v2_incidencias_revisiones`: revision number, state, motive, evidence
  folio and creating/approving actor;
- `op_rh_v2_incidencias_aplicaciones`: daily link to a scheduled shift;
- `op_rh_v2_ajustes_administrativos`: interpreted administrative adjustment,
  motive, creator/approver and adjustment lineage;
- `op_rh_v2_evaluaciones_administrativas`: P2-linked immutable evaluation,
  policy/algorithm versions, status, human-review state and fingerprint;
- `op_rh_v2_evaluaciones_incidencias` and
  `op_rh_v2_evaluaciones_ajustes`: exact consumed input-set links.

`V25_INCIDENT_MASTER_EXISTS: YES`  
`V25_INCIDENT_REVISIONS_EXIST: YES`  
`V25_APPLICATION_LINK_EXISTS: YES`  
`V25_ADMIN_ADJUSTMENTS_EXIST: YES`  
`V25_ADMIN_EVALUATION_EXISTS: YES`  
`V25_APPEND_ONLY_PROTECTIONS: PARTIAL`

The API `AdministrativeEvaluationService` implements the V2.5 internal
create/revise/apply/evaluate operations. It supports the frozen categories
`VACATION`, `DISABILITY`, `PERMISSION`, `AUTHORIZED_LATENESS`,
`AUTHORIZED_EARLY_DEPARTURE`, and `OFFICIAL_MISSION`; stores actor/motive /
evidence inputs; links evaluations to exact application/adjustment sets; and
keeps raw/P1/P2 records separate. More than one applicable incidence is
currently emitted as `OVERLAP_CONFLICT`/`PENDING_REVIEW`, and a punch during
VACATION, DISABILITY or PERMISSION is `CONTRADICTION_DETECTED`/`PENDING_REVIEW`.
Those safe outcomes do not constitute an owner-approved business precedence
for every category combination.

`V25_ACTOR_PROVENANCE: PARTIAL` (revision and adjustment actors exist; the
evaluation header itself has no decision actor).  
`V25_REASON_EVIDENCE: PARTIAL` (revision motive/evidence and adjustment
motive exist; legacy category evidence and evaluation decision evidence are
not a single approved contract).  
`V25_WORKPLACE_SCOPE_MODEL:` `id_estacion` is nullable on the master,
`alcance_incidencia` is `WORKER`, `WORKPLACE` or `SCHEDULED_SHIFT`, and
applications bind to `op_rh_v2_jornadas_programadas`; the schema does not
itself encode Portal3 ACL or an explicit FK to `op_rh_localidades`.

## 6. HTTP and Portal3 boundary

The API router exposes device, Level 2, scheduling, timezone and Journey
evaluation routes. It does not route `AdministrativeEvaluationService` or
V2.5 incidence tables.

`V25_PORTAL3_READ_BOUNDARY_EXISTS: NO`  
`V25_PORTAL3_WRITE_BOUNDARY_EXISTS: NO`  
`V25_ADMIN_EVALUATION_BOUNDARY_EXISTS: NO`

The smallest future package must be separately authorized and server-side:

1. read applicable incidence masters/revisions and provenance for a canonical
   worker, local date and canonical Workplace;
2. create a master period with idempotency key and explicit worker/scope;
3. append a revision for correction/cancel/revoke, never mutate history;
4. apply/reconcile a revision to scheduled shifts with idempotent linkage;
5. evaluate a P2 revision and return status, human-review state, exact input
   links, policy/algorithm versions and correlation/fingerprint.

Every operation needs Portal3 JWT identity, a distinct functional action,
server-side `op_rh_localidades.id` scope and fail-closed behavior when the
station bridge or ACL is unavailable. Final URLs, claims and token minting
are not frozen by this audit.

## 7. Category matrix

| Category | Current runtime value/source | V2 mapping proven | Lifecycle proven | Implementation ready |
|---|---|---:|---:|---:|
| `VACATION` | Formato RH `formato=6`, detail text “Formato Vacaciones”; `op_rh_formatos_vacaciones` | NO | PARTIAL | NO |
| `DISABILITY` | Catalog/detail “Incapacidad” and “Incapacidad IMSS” in legacy attendance | NO | NO | NO |
| `PERMISSION` | `op_rh_permisos` state workflow; also legacy catalog “Permiso con/sin goce de sueldo” | NO | PARTIAL | NO |
| `AUTHORIZED_LATENESS` | Catalog “Retardo” and derived attendance label; approval authority not proven | NO | NO | NO |
| `AUTHORIZED_EARLY_DEPARTURE` | NONE found | NO | NO | NO |
| `OFFICIAL_MISSION` | NONE found | NO | NO | NO |

`Maternidad` remains a distinct legacy runtime label and must not be silently
mapped to `DISABILITY`. `MAPPING_OWNER_DECISION_REQUIRED: YES` for exact
category mapping, evidence requirements and authority of each legacy source.

## 8. Range, conflict and Journey gates

`RANGE_INCIDENCE_MODEL_READY: PARTIAL`. V2.5 can represent one worker-wide
or scoped master period with inclusive local dates and daily application
links. It is not yet ready for Portal3 because legacy authority and category
mapping are unresolved.

The following require explicit policy decisions rather than inference:

- vacation vs permission;
- vacation vs incapacity;
- permission vs incapacity;
- same-category overlapping approvals;
- PunchEvent during vacation, incapacity or permission;
- out-of-scope worker/Workplace records;
- a previously applied record that is later cancelled or revoked.

`CONFLICT_PRECEDENCE_OWNER_DECISION_REQUIRED: YES`.

The API V2.5 engine already fails safely for multiple applications and punch
contradictions, but that implementation is not evidence that a business
precedence or human resolution policy has been approved.

`SIN_MARCACION_ADMIN_GATE_READY: NO`. The current system cannot prove that
no valid approved legacy/V2 incidence applies for a worker/local workday
through an authorized Portal3 boundary.  
`FALTA_DECISION_CONTRACT_READY: PARTIAL`. V2.5 has deterministic technical
prerequisites (`SCHEDULED`, verified completeness, P1/P2 no-punch status,
no active incidence and no authorized adjustment), but source authority,
category mapping and final human-action policy remain open.

Journey/P1/P2/raw provenance remains downstream-safe: P3-G must add
administrative context/evaluations only; it must never update raw punches,
P1/P2 rows, `op_rh_personal_asistencia` or payroll.

## 9. ACL and Workplace governance

Development confirms these physical ACL structures:

- `modulos_dpto_operativo`: parent module identity only;
- `modulos_puestos_do` and `modulos_usuarios_do`: parent-module flags
  `leer`, `crear`, `editar`, `eliminar`, `descargar`;
- `modulos_sub_dpto_operativo`: submodule identity/route only;
- `modulos_sub_puestos_do` and `modulos_sub_usuarios_do`: submodule
  assignment only, with **no action flags**;
- `tb_modulos_config`: station/departments configuration and `allow_all`, not
  an incidence ACL;
- `tb_multiestacion_puesto` and `tb_multiestacion_usuario`: station/dept
  scope JSON, not action authorization.

Current relevant keys are `recursos-humanos`, `biometricos`,
`control-documentos-personal`, `incidencias-nomina`, `permisos` and the
`checador` submodule. No runtime key named `checador-v2-incidencias`,
`checador-v2-vacaciones` or `checador-v2-vacaciones-revision` exists.

`EXISTING_FIVE_FLAG_ACL_SUFFICIENT: NO`. Parent `editar` is currently used
for unrelated edits and cannot safely mean resolve/authorize/reject/revoke;
submodule assignment has no flags; and no explicit action exists for review,
authorize, reject or cancel/revoke. `MODULE_KEY_OWNER_APPROVAL_REQUIRED: YES`.

`WORKPLACE_SCOPE_MODEL:` canonical scope is `op_rh_localidades.id`; a Portal3
station is bridged through a unique `numlista` to that canonical locality.
Client station values must be revalidated server-side.

Observed legacy fail-open or broadening risks:

- `PermisosRrhhService` falls back to hardcoded locality arrays when module
  scope is empty and `puedeVerLocalidad()` returns true for an empty allowed
  set;
- `FormatosService` uses the same fallback/empty-scope pattern;
- `IncidenciasNominaService` has a static `ESTACIONES_IDS` list and derives
  `Falta`/`Retardo` from attendance rather than a separate administrative
  authorization;
- `BiometricosService::getDatos()` applies `whereIn` only when permitted IDs
  are non-empty, so an empty scope can broaden the query; its action flags
  come from parent `recursos-humanos`;
- `ControlDocumentosPersonalService` is safer for resource resolution (empty
  scope returns no authorized person), but its list paths still rely on
  optional scope filtering and its parent flags are not P3-G actions.

These paths must not be reused as P3-G authorization boundaries without a
separate fail-closed design.

## 10. Source-of-truth matrix

| Domain | Current source | Worker key | Range/lifecycle/history | Evidence/scope | Safe read adapter | Safe write target | V2 source of truth | Blocker |
|---|---|---|---|---|---|---|---|---|
| Vacation | RH format header/detail/signatures | detail `id_usuario` (semantic worker, naming mismatch) | date range; status/signature; delete; no revocation/version | evidence in signature/document/comment tables; locality header | Candidate | V2 master + revision after owner mapping | V2 incidence master/revisions | authority and cancel/revoke semantics |
| Permission | `op_rh_permisos` + signatures/tokens | `id_personal` | date range; 0/1/2; physical delete/edit before final; no revocation history | motive/observations/signatures; station + cover station | Candidate | V2 master + revision | V2 incidence master/revisions | approval/cancellation semantics and ACL |
| Incapacity | attendance incidence + catalog | via `id_asistencia` → attendance worker | intended dates absent physically; active flag only; no revision | document/text; schema drift | No, evidence-only until repaired/verified | V2 master + revision | V2 incidence master/revisions | physical schema and medical authority |
| Legacy incident | attendance + incidence child | `id_asistencia` → worker | one active child; mutable attendance; soft inactive | free text/catalog/document | No as authority | None | None | mutable payroll-coupled legacy |
| V2 incident | V2.5 master/revision/application | `id_personal` | inclusive master period, revision and application | motive/evidence folio/actors, scope enum | N/A | V2 master/revision service | V2 incident domain | no approved HTTP/ACL boundary |
| V2 admin evaluation | V2.5 evaluation/link tables | worker snapshot + scheduled shift | append-only header/link intent; partial DB protection | P2 ID, policy/algorithm, fingerprint, exact links | N/A | V2 evaluation service | V2 administrative evaluation | human decision actor/boundary not exposed |

`PAYROLL_INTEGRATION: DEFERRED`. `LEGACY_DUAL_WRITE: NO`.

## 11. Architecture options

| Option | Alignment | Duplication | Auditability/revocation | Fail-closed | Recommendation |
|---|---|---|---|---|---|
| A — continue legacy workflow | Conflicts with J1/V2 separation | Low initially | Weak; deletes/mutations lose history | Weak due legacy scope/flags | NO |
| B — read-only legacy adapters, V2 canonical | Matches J1 and V2.5; preserves coexistence | Controlled, read-only comparison | Strong once V2 revisions are used | Achievable with new boundary | **YES, pending owner decisions** |
| C — copy/migrate all legacy records to V2 | Requires migration semantics not approved | High and risks semantic fabrication | Potentially strong only after authoritative mapping | Not safe before source cleanup | NO for current slice |
| D — other | No evidence for a different frozen option | — | — | — | UNRESOLVED |

Option B is the only direction supported by the current freezes: legacy
records remain references, V2 masters/revisions/evaluations become the
administrative authority for new decisions, and no raw/payroll dual-write is
introduced. This is a recommendation, not an approval.

## 12. Owner decisions and physical evidence still required

`OWNER_DECISIONS_REQUIRED:`

1. Approve the canonical source and exact mapping for VACATION, PERMISSION,
   INCAPACITY, INCAPACITY IMSS, MATERNIDAD, RETARDO and any official mission
   concept; do not infer from labels.
2. Approve canonical worker identity bridges for RH formats and legacy
   attendance, including the `id_usuario`/`id_personal` mismatch.
3. Approve cancellation, revocation, rejection and amendment semantics for
   vacations, permissions and incapacity evidence.
4. Approve overlap precedence or the mandatory review outcome for every
   category pair and for punch-during-incidence contradictions.
5. Approve the distinct ACL action model (`read`, `create`, `review/resolve`,
   `authorize`, `reject`, `cancel/revoke`) and its module-key namespace.
6. Approve the canonical Workplace scope and the fail-closed behavior when
   ACL or station mapping is absent.
7. Approve the smallest API boundary, JWT identity claims, idempotency and
   actor/audit response contract.

`PHYSICAL_EVIDENCE_MISSING:`

- verified development/production incapacity schema and authoritative date /
  document workflow;
- authoritative vacation rejection/cancellation/revocation records;
- authoritative permission revocation/correction records;
- explicit category-to-V2 mapping and evidence contract;
- production grants and station scope rows;
- approved actor model for evaluation resolution/authorization;
- API guarantee for read/write boundary and idempotent revision application.

## 13. Final status

`P3F_STATUS: CLOSED_VERIFIED_DEVELOPMENT`  
`P3G_AUDIT_RESULT: OWNER_DECISION_REQUIRED`  
`P3G_IMPLEMENTATION_READY: NO`  
`P3H_UI_PROJECTION_READY: NO`

No controllers, services, routes, models, views, migrations, ACL rows,
business data, API source, Lector source or production systems were changed.
