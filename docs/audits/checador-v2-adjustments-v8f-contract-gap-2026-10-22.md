# Checador V2 — V8F Auditable Attendance Adjustments

**Date:** 2026-10-22
**Scope:** Portal3 local development; contract/read-presentation review only.
**API, database, ACL, frozen contracts, production and preproduction:** unchanged.

## Finding

The V2 domain and physical model define administrative adjustments as a separate administrative interpretation of a scheduled shift/calculation. Adjustment types documented by the model are `OVERRIDE_STATUS`, `INTERPRETED_ENTRY_TIME`, `INTERPRETED_EXIT_TIME`, and `JUSTIFY_LATENESS`. The physical-model addendum describes append-only logical adjustment lineage and `ACTIVE`, `SUPERSEDED`, and `CANCELLED` revisions.

This is distinct from an attendance incidence. Incidences may provide administrative evidence or explanation, but their create/revise/resolve/authorize/reject/revoke lifecycle is not an adjustment command contract and must not be treated as one.

## Public integration available to Portal3

The frozen P3-G API boundary defines incidence operations and administrative-evaluation operations. It does not define a public endpoint for independently creating, reviewing, authorizing, rejecting, cancelling, or reading adjustment history. The API repository contains an internal `AdministrativeEvaluationService::createAdjustmentRevision()` method exercised by API runtime tests; the existence of that internal method does not make it a Portal3-callable API.

The current `GET /api/v2/administrative-evaluations` projection exposes linked adjustment references only:

- `uuid_ajuste`
- `uuid_ajuste_logico`
- `adjustment_type`
- `revision_number`
- `adjustment_status`

It does not provide a complete adjustment detail/history, actor, reason, interpreted timestamps, or adjustment-specific action permissions. Portal3 preserves and displays only those five fields, with Spanish labels, and explicitly marks the history limitation. No adjustment form or command route is offered.

## Scenario classification

| Case | Contractual resource/transition | Portal3 disposition |
|---|---|---|
| Missing entry/exit or incomplete punch set | Original PunchEvents plus the existing schedule/calculation/evaluation pipeline | Keep the observed facts unchanged; do not manufacture a punch or infer a correction. Any unsupported correction command remains blocked. |
| Incidence related to attendance | Existing V2 incidence category/revision and its dedicated lifecycle | Use the incidence lifecycle only for the incidence itself; it is not an adjustment authorization. |
| Evaluation has insufficient/conflicting source information | Existing administrative-evaluation read/execute contract | Render the API-owned state; Portal3 does not calculate or resolve the outcome locally. |
| Request a time/status correction or justify a difference | Adjustment domain is modeled, but no public adjustment create/revise contract is defined | No form or request is offered until the API publishes the command and its validation/ACL contract. |
| Resolve, authorize, reject, revoke | Public P3-G transitions apply to incidence revisions | Do not apply incidence transitions to adjustment records. Adjustment review/authorization/rejection/cancellation is not exposed as a Portal3 API workflow. |
| Read an administrative result | `GET /api/v2/administrative-evaluations` | Show evaluation and the five linked-adjustment fields returned for that evaluation. |
| Read complete adjustment decision history | Physical model has adjustment lineage; no public adjustment detail/history endpoint exists | Show the linked logical identifier/revision available in the evaluation projection and state that the history is incomplete. |

## Safety and approval needed

- Original raw PunchEvents remain immutable; an adjustment is not a replacement or synthetic raw punch.
- Do not reuse incidence transitions or their ACL as adjustment authorization.
- Do not expose the internal API service through direct SQL or a guessed URL.
- To support adjustment actions, the owner/API must approve and publish a separate public contract covering create, correction/revision, review/authorization/rejection/cancellation where applicable, readback/history, action-level ACL, concurrency/idempotency, and audit provenance.
- Until that contract exists, adjustment commands and complete history remain blocked; the safe Portal3 behavior is read-only presentation of the existing evaluation linkage.

## Validation scope

Focused Portal3 regression covers preservation and Spanish presentation of the five linked-adjustment fields, generic handling of unknown type/status values, and absence of an unsupported adjustment command route/form. No real API call, database access, or browser session was used.
