# CHECADOR V2 — PORTAL3 P3-G INCIDENCE AND ADMINISTRATIVE EVALUATION CONTRACT V1.0

**Status:** FROZEN_OWNER_APPROVED  
**Version:** 1.0  
**Owner Approval Date:** 2026-10-14  
**Architecture Option:** OPTION B  
**Companion Document To:** `docs/architecture/CHECADOR_V2_ARCHITECTURE_FREEZE_V1.0.md`  
**Implementation Authorized:** NO (Documentation and Governance Freeze Only)  
**Database Changes Authorized:** NO  
**Production Changes Authorized:** NO  

---

## 1. Executive Summary & Owner Authorization

On October 14, 2026, the project owner formally reviewed the P3-G contract/governance audit findings (`docs/status/CHECADOR_V2_PORTAL3_P3G_CONTRACT_GOVERNANCE_AUDIT.md`) and issued governing product decisions for Checador V2 administrative incidences and evaluation.

This document records and freezes those owner-approved architectural, functional, security, and lifecycle decisions. It establishes the authoritative functional contract for Portal3 P3-G integration and defines the constraints governing any future API boundary contracts.

### Relation to Architecture Freeze
This contract is a companion document to `docs/architecture/CHECADOR_V2_ARCHITECTURE_FREEZE_V1.0.md`. It does NOT amend, modify, or reinterpret the Architecture Freeze. The Architecture Freeze SHA-256 (`87bf3e714f7358cbdace5c4f099573d77d9ecb9033328c706df5c5295304fd85`) remains strictly frozen and inviolate.

---

## 2. Architectural Framework: OPTION B & Canonical V2 Domain

### Approved Architecture: OPTION B
The owner explicitly approved **OPTION B** for Checador V2 administrative incidence management:

1. **Legacy Administrative Sources as Read-Only Adapters:** Legacy administrative sources remain available strictly through read-only adapters where their source authority and lifecycle can be proven.
2. **Canonical V2 Administrative Domain:** The canonical source of truth for V2 attendance incidences is the V2 domain model (`AttendanceIncident` + version/revision history + administrative evaluation).
3. **Legacy Tables Are Not V2 Truth:** Legacy attendance and incidence tables (`op_rh_personal_asistencia`, `op_rh_personal_asistencia_incidencia`, etc.) are NOT the V2 source of truth and must never be treated as authoritative V2 records.
4. **V2.5 Canonical Foundation:** The existing V2.5 physical domain in the API (incident master, incident revisions, application links, administrative adjustments, and administrative evaluations) constitutes the canonical implementation foundation (`V2.5 canonical foundation`). Portal3 shall NOT construct a competing incident engine or alternative incidence store.

---

## 3. Immutable Principles: PunchEvent and Legacy Protection

### 3.1 PunchEvent Immutability
A raw `PunchEvent` represents an immutable historical fact captured by physical devices or authorized ingestion channels (`PunchEvent immutability`).

Under no circumstances shall an administrative incidence:
- alter or shift a punch timestamp;
- delete or physically remove a punch;
- hide, mask, or suppress a punch from query views;
- generate or fabricate a synthetic punch;
- substitute or insert a replacement punch for a missing mark.

Observed physical punches remain permanently visible in consultation and audit views, even when an approved administrative incidence covers the same date or shift interval.

### 3.2 Zero Legacy Dual-Write (`LEGACY_DUAL_WRITE = NO`)
Portal3 P3-G and downstream Checador V2 operations shall enforce no legacy dual-write (`no legacy dual-write`, `LEGACY_DUAL_WRITE = NO`). They shall never write administrative decisions, adjustments, or incident resolutions to:
- `op_rh_personal_asistencia`; or
- `op_rh_personal_asistencia_incidencia`.

Writing V2 attendance conclusions or incident states to legacy tables as part of normal V2 operations is strictly forbidden.

---

## 4. Identity & Workplace Scope Boundaries

### 4.1 Canonical Worker Identity: `op_rh_personal.id` identity
The sole canonical identifier for worker identity across Checador V2 attendance and administrative incidence processing is `op_rh_personal.id identity`:

```text
Worker = op_rh_personal.id
```

Any legacy adapter, input payload, or administrative workflow must resolve unambiguously to an active `op_rh_personal.id`.

**Strictly Forbidden Identity Fallbacks:**
- Employee full name or formatted string (`nombre`, `apellidos`);
- Email address;
- Portal3 user account identifier (`tb_usuarios.id`);
- Legacy employee number (`no_colaborador`);
- External system aliases or external codes;
- Fuzzy matching or heuristic text correlation.

**Failure Rule:** If a canonical worker identity cannot be proven with 100% mathematical certainty, the request must immediately fail closed with:
```text
UNRESOLVED_IDENTITY => REVIEW_REQUIRED
```

### 4.2 Canonical Workplace Identity & Workplace Scope
The canonical Checador V2 Workplace identifier is `op_rh_localidades.id` (`Workplace scope`):

```text
Workplace = op_rh_localidades.id
```

Portal3 station identifiers (`tb_estaciones.id`) bridge to the canonical workplace strictly via the authoritative unique station number:
```text
tb_estaciones.id -> unique valid numlista -> op_rh_localidades.id
```

**Scope Rules:**
- It is strictly forbidden to assume numeric equality between `tb_estaciones.id` and `op_rh_localidades.id`.
- Every P3-G operation must be evaluated and authorized server-side within the authenticated user's assigned Workplace scope.
- Client-provided station or workplace parameters in requests are treated as untrusted filter requests, never as authorization scope.
- If the resolved Workplace scope is empty, invalid, or ambiguous, the operation must immediately fail closed.

---

## 5. Legacy Administrative Source Adapters

### 5.1 Vacation: vacation read-only adapter (`READ_ONLY_ADAPTER`)
- **Approved Legacy Sources:** `op_rh_formatos`, `op_rh_formatos_vacaciones`, `op_rh_formatos_firma`, `op_rh_formatos_token`.
- **Classification:** `vacation read-only adapter` (`READ_ONLY_ADAPTER`).
- **Governance & Constraints:**
  - Legacy vacation forms may provide read-only administrative evidence to V2.
  - They are NOT the canonical V2 incident store.
  - Because legacy cancellation, revocation, and post-approval modification lifecycles are unproven and vulnerable to hard deletion, V2 shall not infer historical finality where lifecycle states are ambiguous.
  - V2 shall never rewrite, alter, or backport changes to legacy vacation records.
  - V2 shall not copy legacy records blindly into V2 tables.
  - Any ambiguous vacation lifecycle state must be classified as `REVIEW_REQUIRED`.

### 5.2 Permission: permission read-only adapter (`READ_ONLY_ADAPTER`)
- **Approved Legacy Sources:** `op_rh_permisos`, `op_rh_permisos_firma`, `op_rh_permisos_token`.
- **Classification:** `permission read-only adapter` (`READ_ONLY_ADAPTER`).
- **Governance & Constraints:**
  - Operates under the identical governance rules as the Vacation Adapter.
  - Legacy permission records may provide external administrative evidence.
  - Legacy workflow tables do not become the canonical V2 resolution store.
  - Legacy cancellation/revocation ambiguity remains strictly fail-closed (`REVIEW_REQUIRED`).
  - No blind duplication or overwriting of legacy data.

### 5.3 Incapacity / Disability: incapacity legacy automation blocked (`BLOCKED_BY_SOURCE_AUTHORITY`)
- **Legacy Source:** `op_rh_personal_asistencia_incidencia`.
- **Audit Findings:** Confirmed schema drift and unproven, vulnerable lifecycle mechanisms.
- **Owner Decision:** **DO NOT AUTOMATE THE LEGACY INCAPACITY ADAPTER YET.**
  - The conceptual category `DISABILITY` is formally defined in V2.
  - Automatic ingestion from legacy tables is classified as incapacity legacy automation blocked:
    ```text
    INCAPACITY_LEGACY_AUTOMATION = BLOCKED_BY_SOURCE_AUTHORITY
    ```
  - No temporary mapping, heuristic parser, or interim adapter shall be implemented.
  - Legacy `op_rh_personal_asistencia_incidencia` rows shall not be reused or accepted as authoritative V2 truth.
  - Automated ingestion remains blocked until a verifiable, auditable source authority is established and formally frozen.

---

## 6. Conceptual Categories & Source Mapping Matrix

The following conceptual administrative categories are recognized by Checador V2:

| Conceptual Category | Description | Source Mapping Authority Status | Default Behavior |
|---|---|---|---|
| `VACATION` | Authorized annual vacation leave | `READ_ONLY_ADAPTER` (Legacy Form 6) | Read-only evidence; fail closed on ambiguous lifecycle |
| `PERMISSION` | Authorized personal/work permission | `READ_ONLY_ADAPTER` (Legacy Permisos) | Read-only evidence; fail closed on ambiguous lifecycle |
| `DISABILITY` | Medical disability / incapacity / maternity | `BLOCKED_BY_SOURCE_AUTHORITY` | Automatic adapter blocked; requires manual V2 entry |
| `AUTHORIZED_LATENESS` | Authorized justification for tardiness | `NO_AUTHORITATIVE_SOURCE_FROZEN` | Cannot produce final administrative effect; `REVIEW_REQUIRED` |
| `AUTHORIZED_EARLY_DEPARTURE` | Authorized early departure justification | `NO_AUTHORITATIVE_SOURCE_FROZEN` | Cannot produce final administrative effect; `REVIEW_REQUIRED` |
| `OFFICIAL_MISSION` | Authorized off-site official company assignment | `NO_AUTHORITATIVE_SOURCE_FROZEN` | Cannot produce final administrative effect; `REVIEW_REQUIRED` |

### Rules for Unresolved Categories
A category lacking a frozen, authoritative source mapping must never:
1. produce a final administrative resolution;
2. suppress or alter an observed PunchEvent;
3. infer or produce a `FALTA` (absence);
4. generate payroll consequences (`no payroll effect`).

Any unmapped or unproven category state must remain `NOT_AVAILABLE`, `UNKNOWN`, or `REVIEW_REQUIRED`.

---

## 7. Conflict Resolution Policy: REVIEW_REQUIRED conflicts

Whenever administrative evidence conflicts with another administrative evidence source, or conflicts with physical `PunchEvent` evidence:
```text
Conflict Default = REVIEW_REQUIRED (REVIEW_REQUIRED conflicts)
```

### Strict Invariants:
- **No Automatic Precedence:** The system must never assume an arbitrary hierarchy (e.g., Vacation does not automatically override a physical PunchEvent; physical punches do not automatically invalidate an approved Permission).
- **Preservation of Raw Facts:** Both the physical punch evidence and the administrative evidence must remain visible in their original form.
- **Mandatory Human Resolution:** Conflicting situations demand explicit review and resolution by an authorized administrative actor.

### Mandatory Conflict Scenarios:
1. `VACATION` + physical `PunchEvent` present on the same workday;
2. `DISABILITY` + physical `PunchEvent` present on the same workday;
3. `PERMISSION` + physical `PunchEvent` present during the excused period;
4. Temporal overlap between `VACATION` and `DISABILITY`;
5. Temporal overlap between `VACATION` and `PERMISSION`;
6. Temporal overlap between `DISABILITY` and `PERMISSION`;
7. Multiple overlapping approved administrative records for the same worker and interval.

---

## 8. V2 Incident Model & Lifecycle: Append-Only Revocation/Cancellation

### 8.1 Model Structure
Checador V2 adopts a normalized, append-only incident lifecycle model:
```text
Incident Structure = Incident Master (Period) + Append-Only Revisions/Resolutions
```

**Required Conceptual Fields:**
- `canonical_worker_id` (`op_rh_personal.id`)
- `canonical_workplace_id` (`op_rh_localidades.id`, when applicable)
- `local_start_date` (`YYYY-MM-DD`)
- `local_end_date` (`YYYY-MM-DD`)
- `time_interval` (optional local time boundaries `HH:MM:SS`, when category requires intra-day precision)
- `category` (one of the defined conceptual categories)
- `reason` (textual justification)
- `evidence_reference` (reference to external adapter or attached proof)
- `lifecycle_state` (`ACTIVE`, `SUPERSEDED`, `CANCELLED`, `REVOKED`)
- `revision_number` / `version`
- `actor_id` (authorized user performing the action)
- `action_timestamp_utc` (UTC timestamp of operation)
- `correlation_id` / `version_uuid`

### 8.2 Period vs. Single-Day Modeling
For multi-day administrative events (e.g., a 14-day vacation, multi-day disability, or multi-day leave):
- **DO NOT** create 14 individual duplicate master incident rows for each calendar day.
- Create **one** incident master encompassing the entire authorized date range.
- Daily Journey evaluation processes and references the encompassing master period for each applicable day in the window.

### 8.3 Cancellation and Revocation: Append-Only Revocation/Cancellation
- Any cancellation, revocation, or amendment of an administrative incidence MUST create a new append-only revision record (`append-only revocation/cancellation`).
- Historical approved revisions must NEVER be mutated, overwritten, or physically deleted.
- Full historical Journey and evaluation provenance must remain permanently reproducible for any historical point in time.

---

## 9. Administrative Evaluation & Attendance Classification

### 9.1 Evaluation Pipeline Position
Administrative evaluation is executed strictly downstream of:
1. Raw `PunchEvent` ingestion;
2. Source completeness verification (Level 2);
3. Effective schedule resolution;
4. Level 1 snapshot pairing (P1);
5. Journey calculation and interval metrics (P2).

Administrative evaluation must never recalculate or reconstruct P1 pairing or P2 calculation metrics. It consumes the authoritative Journey and calculation provenance as read-only upstream facts.

### 9.2 SIN MARCACIÓN Gate
A day is not classified as `SIN MARCACIÓN` merely because zero `PunchEvent` rows are rendered in the consultation view (`SIN MARCACIÓN gate`).

To produce a valid `SIN MARCACIÓN` classification, the administrative evaluation gate must affirmatively establish:
1. Source completeness is fully verified (`window_level2_status = VERIFIED`);
2. An effective scheduled shift exists and requires attendance;
3. Zero valid physical punches occurred within the shift matching window; and
4. **No valid applicable administrative incidence** (vacation, permission, disability, mission) covers the shift.

If administrative applicability is ambiguous, unproven, or unresolved, the status must remain:
```text
REVIEW_REQUIRED or DATA_INCOMPLETE
```

### 9.3 FALTA: FALTA Human Authorization Invariant
```text
FALTA Rule: NEVER INFERRED AUTOMATICALLY (FALTA human authorization)
```

Under no circumstances shall the system automatically generate or infer a `FALTA` from:
- zero observed punches;
- verified Level 2 source completeness;
- a known active work schedule;
- a `SIN MARCACIÓN` classification;
- or any combination thereof.

Determining a `FALTA` requires explicit human review, authorized administrative action (`FALTA human authorization`), and compliance with the V2 administrative evaluation contract. Payroll consequences of a `FALTA` remain entirely deferred and outside P3-G.

---

## 10. Payroll Integration Separation: No Payroll Effect (`PAYROLL_INTEGRATION = DEFERRED`)

Checador V2 administrative incidences and evaluation operate strictly under parallel comparison principles with no payroll effect (`no payroll effect`):
```text
Operating Principle = "Comparación paralela sin afectar nómina" (PAYROLL_INTEGRATION = DEFERRED)
```

Portal3 P3-G and downstream evaluation must never:
- calculate employee compensation or wage deductions;
- modify payroll calculations, periods, or batches;
- authorize or calculate payable overtime;
- write or update `RecibosNomina` records;
- write legacy payroll incidence records;
- convert administrative reviews directly into automated payroll deductions or bonuses.

---

## 11. ACL Semantic Model & Security Architecture: Dedicated ACL Namespaces

### 11.1 Prohibition of Generic Permissions
There is no generic recursos-humanos.editar authority (`no generic recursos-humanos.editar authority`). The generic legacy permission:
```text
recursos-humanos.editar
```
is strictly prohibited as an authorization grant for Checador V2 administrative operations.

### 11.2 Dedicated ACL Namespaces
P3-G establishes three purpose-separated functional dedicated ACL namespaces (`dedicated ACL namespaces`):

```text
checador-v2-incidencias
checador-v2-incidencias-resolucion
checador-v2-incidencias-autorizacion
```

#### 1. Namespace: `checador-v2-incidencias`
- **Purpose:** Read and create administrative incidence evidence and requests.
- **Allowed Semantic Capabilities:**
  - `READ`
  - `CREATE`
- **Explicit Exclusions:** Does NOT grant `RESOLVE`, `AUTHORIZE`, `REJECT`, `REVOKE`, payroll authority, or PunchEvent modification.

#### 2. Namespace: `checador-v2-incidencias-resolucion`
- **Purpose:** Review, investigate, and draft resolutions for administrative conflicts, overlaps, and unresolved states.
- **Allowed Semantic Capabilities:**
  - `RESOLVE` / `REVIEW`
- **Explicit Exclusions:** Does NOT grant final authorization. All resolution actions must be append-only.

#### 3. Namespace: `checador-v2-incidencias-autorizacion`
- **Purpose:** Final administrative approval, rejection, or revocation lifecycle management.
- **Allowed Semantic Capabilities:**
  - `AUTHORIZE`
  - `REJECT`
  - `REVOKE` / `CANCEL`
- **Scope & Constraints:** Executable strictly within the future frozen API contract and server-side workplace scope. Does NOT grant payroll authorization.

### 11.3 Physical Five-Flag ACL Warning
Portal3 physically implements ACL using five boolean flags (`leer`, `crear`, `editar`, `eliminar`, `descargar`).
- The semantic capabilities defined above are authoritative over physical flag names.
- No database ACL rows shall be created as part of this documentation freeze.
- When physical ACL modules are mapped in future implementation, generic `editar` in one namespace must never cross-authorize actions in another namespace.

---

## 12. API Boundary & Transport Architecture: Portal3 Backend → API Only

### 12.1 Transport Architecture: Portal3 Backend → API Only
All P3-G operations require a dedicated, authenticated server-to-server boundary (`Portal3 backend → API only`):
```text
Portal3 Backend <--- (HTTPS / JWT) ---> Checador V2 API
```

**Strict Prohibitions:**
- **No Browser Direct Access:** No browser direct (`no browser direct`, `BROWSER_DIRECT_API = NO`). Client-side JavaScript running in the browser shall never communicate directly with Checador V2 API endpoints.
- **No Device Auth:** No Device Auth (`no Device Auth`, `DEVICE_AUTH_ALLOWED = NO`). Device credentials (Bearer Bcrypt secret) are reserved exclusively for biometric hardware and shall not be used for administrative operations.

### 12.2 Authentication & Authorization Family
Every P3-G API request must satisfy:
1. HTTPS transport with TLS;
2. Portal3 user-delegated JWT token;
3. Validated active user account (`tb_usuarios.estatus = 0`);
4. Dedicated P3-G functional ACL permission matching the requested action;
5. Validated server-side Workplace scope assignment.

### 12.3 Required Future API Capabilities
The future Checador V2 API boundary contract must support the following capabilities:
1. `READ APPLICABLE INCIDENTS` (scoped by worker, workplace, date range);
2. `CREATE INCIDENT` (create master period + initial revision);
3. `CREATE APPEND-ONLY REVISION` (amendment / state update);
4. `RESOLVE / REVIEW` (conflict resolution draft);
5. `AUTHORIZE / REJECT / REVOKE` (lifecycle finality);
6. `READ ADMINISTRATIVE EVALUATION` (retrieve downstream evaluation results);
7. `EXECUTE / REQUEST ADMINISTRATIVE EVALUATION` (explicit command if required by V2.5 engine).

**Boundary Invariants:** Every capability must be server-authorized, workplace-scoped, actor-traceable, append-only, and idempotent.

---

## 13. Audit & Provenance Invariants

Every administrative operation and evaluation outcome must persist complete, tamper-evident provenance including:
1. `canonical_worker_id` (`op_rh_personal.id`);
2. `canonical_workplace_id` (`op_rh_localidades.id`);
3. `local_workday` or date range (`YYYY-MM-DD`);
4. `incident_id` and `revision_number`;
5. `actor_id` (authenticated Portal3 user ID);
6. `action_timestamp_utc` (authoritative UTC time);
7. `reason` and justification text;
8. `evidence_references` (pointer to legacy adapter record or document);
9. `previous_state` and `new_state`;
10. `correlation_id` / `version_uuid`;
11. Related Journey ID and P2 calculation revision reference (where applicable).

Administrative provenance must reference but never duplicate or clone raw `PunchEvent` authority.

---

## 14. Comprehensive Failure Matrix

The system must consistently fail closed under any anomalous condition:

| Failure Condition | System Action | Resulting Status |
|---|---|---|
| Unresolved worker identity | Reject request; log audit warning | `UNRESOLVED_IDENTITY` / `REVIEW_REQUIRED` |
| Invalid workplace mapping (`tb_estaciones` -> `localidades`) | Reject request; abort operation | `INVALID_WORKPLACE_SCOPE` / Fail Closed |
| Empty or unauthorized workplace scope | Deny access (HTTP 403) | `FORBIDDEN` |
| Legacy lifecycle ambiguity (unproven status) | Do not infer approval; isolate record | `REVIEW_REQUIRED` |
| Conflicting administrative records | Flag conflict; preserve evidence | `REVIEW_REQUIRED` |
| Missing or unproven authoritative source | Block automated ingestion | `BLOCKED_BY_SOURCE_AUTHORITY` / `UNKNOWN` |
| API boundary or JWT failure | Abort operation; log error | `AUTHENTICATION_FAILURE` / Fail Closed |
| Unavailable administrative evidence | Halt evaluation for interval | `DATA_INCOMPLETE` |
| Inconsistent provenance / revision race | Abort transition; require reload | `CONCURRENCY_CONFLICT` |

**Invariant:** Under no circumstances shall any failure condition listed above be converted into a `FALTA`.

---

## 15. Explicit Non-Goals (Out of Scope for P3-G): No P3-H Implementation

The following activities are explicitly declared out of scope for P3-G (`no P3-H implementation`):
1. **No P3-H Implementation:** Materialization of morning/afternoon 4-slot UI projection remains deferred to P3-H (`no P3-H implementation`).
2. **Payroll Integration:** Calculating wages, approving payable overtime, or updating payroll tables.
3. **Automatic FALTA Generation:** Automated creation of unexcused absence penalties.
4. **Bulk HR Operations:** Batch modification or bulk imports of historical records.
5. **Production Deployment:** Rolling out P3-G features to production environments.
6. **Legacy Data Migration:** Mass migration or reformatting of historical legacy tables.
7. **Incapacity Schema Cleanup:** Structural DDL modifications to `op_rh_personal_asistencia_incidencia`.
8. **Portal + Telegram Capture:** Mobile or bot capture channel changes.
9. **Lector Checador V2:** Physical biometric reader modifications.
10. **PunchEvent Protocol:** Any change to raw punch ingestion contracts or storage.

---

## 16. Governance, Freeze Status & Next Steps

### Contract Status: `FROZEN_OWNER_APPROVED`
This contract is frozen and authoritative as of October 14, 2026. Any future modification requires explicit owner authorization.

### Implementation Status: `NOT_STARTED`
P3-G runtime implementation remains unauthorized until all prerequisites and API boundary contracts are formally frozen.

### Next Gate: `API_P3G_BOUNDARY_CONTRACT_FREEZE_V1_0`
The immediate next recommended action is to freeze the minimal API P3-G boundary contract against the exact SHA-256 hash of this document, prior to implementing any runtime code.
