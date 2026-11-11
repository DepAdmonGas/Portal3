# Checador V2 — Persisted Journey Read Contract Proposal V1.0

**Status:** `DRAFT_NOT_APPROVED`
**Implementation authorized:** `NO`
**Purpose:** define the read-only contract needed to preserve the current Portal3 persisted Journey workspace without authorizing implementation, a database reader, or a new API route.

## 1. Decision boundary

This is a proposal only. The Architecture Freeze V1.0 remains frozen and says implementation is not authorized. J1 freezes Journey semantics, but does not define a persisted-result list/detail read or physical schema. J2 remains frozen with runtime implementation unauthorized and does not establish a persisted Journey read contract. The M05 authorization addendum explicitly permits the scoped raw `GET /api/v2/punch-events`, workplace timezone read, and separately authorized effective-schedule read; it explicitly does **not** add a GET for persisted Journey evaluation results. P3H V1.1 binds the existing `POST /api/v2/journeys/evaluate` presentation projection contract, but does not authorize a persisted-result GET or an API/schema/ACL change. Its Portal3 runtime status is still `PENDING_RESUME`.

The code currently observed in `ChecadorPersistedJourneyReadService::getPersistedJourneys()` reads MariaDB tables directly, selects the latest calculation per scheduled journey, and enriches it with segments, raw punches, shift data, and applied incidences. `ChecadorController` invokes it after Portal3 workplace and Journey-read checks. This observation documents current behavior; it does not establish architectural approval for direct SQL or prove that every returned row is authorized by a canonical API boundary.

`POST /api/v2/journeys/evaluate` is not a substitute for this read: it requests evaluation and is not a read of an existing persisted result. The M05 addendum also rules out using GET to retrieve persisted evaluation results under its current authorization.

## 2. Current Portal3 behavior observed

- Inputs: workplace ID, `dateFrom`, `dateTo`, optional worker ID, optional calculation status. Invalid workplace/date returns an error. Reversed dates are swapped. Ranges longer than 62 days are truncated to 62 days from the start.
- Query scope: `c.id_estacion = workplaceId`; optional `c.id_personal`; optional `c.estado_calculo`. Latest calculation is selected by maximum calculation row ID per scheduled journey inside the requested workplace/date window.
- Ordering: local work date descending, worker name ascending, calculation ID descending. The service fetches all matching rows; it has no page/cursor contract or separate count query. `total` is returned-row count, not a declared global count when a future paginated source is used.
- Displayed identity/context: calculation ID, canonical worker ID, employee number/name, workplace ID/name, local work date, schedule/journey type, shift template name and scheduled UTC bounds, workplace timezone (with a current code fallback), calculated state, durations, revision, algorithm version, execution fingerprint and calculation creation timestamp.
- Evidence/detail: segment IDs/order/type/state, scheduled/worked seconds, deltas, paired entry/exit raw event IDs/timestamps/UUIDs; associated raw PunchEvents with role, event ID/UUID, capture time, source timestamp, device, capture method and processing status; applied incidence UUID/type/status/application status per scheduled journey.
- Empty result: `{ok:true, journeys:[], total:0}`. Validation/query errors return `ok:false`, empty rows, and an error key. The controller converts read failure to a neutral workspace error. Current SQL catch includes exception text; a future API contract must not expose SQL or internal exception details.
- Time display: raw UTC values are rendered in the schedule row timezone; current service defaults missing timezone to `America/Mexico_City` and may fall back to a raw source timestamp. That fallback is observed behavior, not an approved rule for determining local-workday identity. J1 requires authoritative workplace timezone and says a missing/ambiguous schedule or timezone must not be interpreted as absence.
- Pagination/completeness: no pagination, stable snapshot token, `has_more`, or completeness marker exists in this SQL service. No response contract currently proves that all persisted calculations or all linked evidence were returned.
- Authorization: controller rechecks authenticated user, selected workplace scope, and `authorizeJourneyRead(workplace)`. The SQL reader itself accepts a numeric workplace ID and has no user/ACL parameter; the API boundary would need to derive/revalidate caller identity and workplace scope independently. Worker filter must narrow, never replace, authorized workplace scope.

## 3. Field and behavior correspondence

| Current Portal3 field/behavior | Current source observed | Existing contract | Gap | Proposed contract coverage |
|---|---|---|---|---|
| Workplace and worker identity/name/employee number | Calculation row joined to `op_rh_personal`, `op_rh_localidades` | J1/J2 require canonical worker/locality distinction; M05 server-side station scope | No persisted Journey read response contract or API-authoritative identity envelope | Return canonical `worker_id`, `workplace_id`; labels/employee number as presentation attributes; API validates membership and scope per request |
| Local work date and date filter | `fecha_jornada_local`, inclusive range; reversed dates swapped; max 62-day truncation | J1 defines local work date and timezone rules; J2 is frozen but does not set this endpoint's filters | Filter normalization, max range, and truncation are not a read API contract | Specify exact inclusive date semantics, reject or explicitly bound oversized ranges, echo normalized range; never silently imply omitted dates were queried |
| Optional worker/status filters | `id_personal`, `estado_calculo` | M05 raw PunchEvent read uses worker only as an additional filter to server-derived workplace scope | Persisted-result filter names, accepted statuses, and invalid-filter behavior are unspecified | Contract status enum and worker filter; preserve workplace authorization as the mandatory outer scope |
| Latest result, state and empty/unavailable distinction | Maximum calculation row ID per `id_jornada_programada`; `estado_calculo`; empty rows map to success | J1 on-demand Journey; M05 states no persisted-result GET and unavailable/not-evaluated must remain distinct | Current query's `MAX(id)` selection and meaning of no row are not frozen | API-owned selection/finality semantics; response must distinguish no evaluation, no matching row, incomplete data, unavailable source, and a verified empty result |
| Schedule/shift and overnight interval | `jornadas_programadas`, template, UTC start/end, type, local timezone | J1 defines workplace timezone and `local_start_date`, times, `end_day_offset`; schedule precedence has pending business confirmation in J1 | Service response does not fully expose the frozen overnight tuple; timezone fallback may mask missing authoritative data | Include effective schedule identity/version, local start date/time, local end time, day offset, IANA workplace timezone, and explicit unresolved status, if API owner confirms these are available |
| Calculation metrics and revision | Totals, state, revision, algorithm version, fingerprint, created UTC | P3H V1.1 covers evaluation presentation/provenance envelope on evaluate boundary | Existing evaluate projection is not a persisted-result list/detail read and cannot be treated as such | Provide persisted evaluation identity, revision, status, metrics, algorithm/version and calculation time with source-owned semantics; do not trigger evaluation during read |
| Segments and paired events | `op_rh_v2_calculos_segmentos`, `pareos_segmentos`, raw event joins | J1 preserves immutable raw events and derived Journey distinction; M05 permits raw PunchEvent reads | M05 raw events do not provide persisted segment pairing/calculation relationship; P3H evaluate is not a history/list contract | Return read-only segment projections and exact event references/provenance, preserving raw events separately from derived calculation |
| Incidences applied to a journey | Application rows joined to incidence revisions/master | P3G-A provides incidence summaries/detail; P3G contract makes V2 incidence domain canonical | Existing incidence read does not contractually provide the persisted Journey's application decision/link in this shape | Include API-owned applied-incidence references and application state, or explicitly mark unavailable; never derive/administer locally |
| Ordering, pagination, total and completeness | SQL ordering; `fetchAll`; returned-row count | J1 includes pagination integrity rules for raw events, not this persisted projection | No persisted read pagination or snapshot/completeness envelope | Define stable order, cursor/page bounds, matching count semantics, snapshot consistency, `has_more`/completion and explicit partial/error metadata |
| Timezone and displayed timestamps | Stored schedule timezone or current Mexico City fallback; UTC conversion/raw fallback | J1 assigns contractual timezone to Workplace and prohibits inferring workday from raw timestamps | Fallback and response-time semantics not frozen for this projection | Return UTC instants plus authoritative IANA timezone and local civil values as needed; missing timezone must be explicit, not inferred from raw event/device timezone |
| User/workplace authorization | Portal3 authenticated ACL and `authorizeJourneyRead`; query by numeric workplace | Freeze, M05 and J1 require API-side authorization/scope and canonical ID mapping | The current SQL reader has no user-bound API authorization and no contract for API caller permission | Shared API/Portal3 approval of capability, station scope, canonical mapping, worker-membership validation and deny/error semantics |
| Error and audit trace | `workplace_invalid`, `dates_invalid`, `query_failed:*`; calculation IDs/fingerprint | M05/P3H error and provenance envelopes; no direct SQL error payload authorization | SQL exception text may expose internals; no contract-level correlation/reference | Define stable safe error kinds, request/correlation ID, source snapshot/reference and provenance without leaking SQL or secrets |

## 4. Proposed logical contract (not an endpoint declaration)

The API contract owner should specify an API-owned **read of existing persisted Journey evaluations**, with list and, if required for the current modal, detail/provenance coverage. HTTP method, route, query names, ACL identifier, pagination representation, and physical implementation remain `TBD_BY_API_CONTRACT_OWNER`; this proposal does not create or authorize a route. The read must not evaluate, create a snapshot, recalculate, mutate, or consult Portal3-owned SQL as an alternate authority.

Minimum response envelope to review: canonical workplace and query range; applied filters; stable ordered items; count/cursor/completeness metadata; per-item evaluation and schedule identity/version, statuses, metrics, segment projections, raw event references, applied incidence references, timezone and provenance; distinct unavailable/partial/not-evaluated/empty states; safe errors. Exact field names and source ownership require shared contract approval.

## 5. Approvals required before implementation

1. **Architecture/API:** explicitly approve the persisted-result read boundary or approve an amendment to the Architecture Freeze/J1. M05's current prohibition cannot be bypassed by treating `POST /journeys/evaluate` as GET-equivalent.
2. **API owner:** freeze authoritative result-selection/finality, schedule and timezone payload, segment/event and incidence-application provenance, pagination/completeness, error envelope, and per-request canonical worker/workplace ACL behavior.
3. **Portal3 owner:** approve the read capability and mapping from authenticated user/active ACL to canonical workplace and worker filters; preserve not-evaluated, incomplete and error states rather than implying no attendance.
4. **Lector:** approval is required only if this persisted Journey projection is exposed to or consumed by Lector, or if the decision changes a shared API/Lector contract. Otherwise no Lector behavior is proposed here.
5. **Business/J2:** confirm any schedule-precedence or historical effective-schedule semantics before a result is presented as complete. J1 records precedence confirmation as pending.

No approval in this proposal authorizes code, SQL access, schema/migration, ACL grant, evaluation POST, production gate, or deployment.

## 6. References

- `docs/architecture/CHECADOR_V2_ARCHITECTURE_FREEZE_V1.0.md`
- `docs/architecture/CHECADOR_V2_PORTAL3_JOURNEY_INCIDENTS_FREEZE_V1.0.md` (J1)
- `docs/architecture/CHECADOR_V2_PORTAL3_SCHEDULE_COMPLETENESS_FREEZE_J2_V1.0.md` (J2)
- `docs/v2/CHECADOR_V2_M05_JOURNEY_READ_AUTHORIZATION_ADDENDUM_V1.0.md`
- `docs/v2/CHECADOR_V2_PORTAL3_P3H_PRESENTATION_PROJECTION_CONTRACT_V1.1.md`
- `docs/status/CHECADOR_V2_PORTAL3_STATUS.md` (implementation/status evidence, not authorization to expand the read boundary)
- Observed code: `app/Services/ChecadorPersistedJourneyReadService.php`, `app/Controllers/ChecadorController.php`, `app/Views/departamento-operativo/2-recursos-humanos/checador/workspace-journeys.php`
