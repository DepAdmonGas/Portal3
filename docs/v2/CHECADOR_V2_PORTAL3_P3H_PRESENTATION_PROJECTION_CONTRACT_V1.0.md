# CHECADOR V2 — Portal3 P3-H Presentation / Projection Contract V1.0

**Activity:** `CHECADOR_V2_PORTAL3_P3H_PRESENTATION_PROJECTION_CONTRACT_FREEZE_V1_0`
**Status:** `FROZEN_OWNER_APPROVED`
**Scope:** Portal3 presentation contract only
**Date:** 2026-10-14

This owner-approved contract defines a compact four-slot presentation of
authoritative Checador V2 data. It does not authorize runtime implementation,
an API runtime change, a schema change, an ACL change, or a production
deployment.

## 1. Gate and frozen references

The Portal3 status gate is reconciled:

```text
P3G_END_TO_END_STATUS: CLOSED_RECONCILED
P3G_OVERALL_HEALTH: GREEN_WITH_DEBT
P3H_READINESS: YES
P3H_UI_PROJECTION_READY: YES
P3H_UI_PROJECTION_READY_MEANING: permitted to begin P3-H work
P3H_IMPLEMENTATION: NOT_STARTED
```

The following frozen documents were verified byte-for-byte by SHA-256:

| Document | SHA-256 |
|---|---|
| Architecture Freeze | `87bf3e714f7358cbdace5c4f099573d77d9ecb9033328c706df5c5295304fd85` |
| API P3-G boundary contract | `e418687501fbdfdf89bcc6f11aab60e49226f265411c4590a39a5fa7799c374a` |
| Portal3 P3-G contract | `e26e888015b27f9f1023e19cf4e005795836a99fd81a98c5638589fde5ca234d` |
| API P3-H event-classification contract | `c710165953f455352cf5675b7581a581be03a632a493e6a0c939276865d6658f` |

The API P3-H event-classification contract is
`/home/silvinol/Proyectos/portal-api-v.1.0.1/docs/v2/CHECADOR_V2_P3H_EVENT_CLASSIFICATION_PUBLIC_PAYLOAD_CONTRACT_V1.0.md`.
Its status is `FROZEN_OWNER_APPROVED`; its runtime additive fields remain
`NOT_STARTED`.

Historical P3-G audit records that predate the closure reconciliation remain
historical evidence and are not rewritten.

## 2. Presentation boundary and authority hierarchy

P3-H is a projection only. It is not a canonical event store, pairing,
calculation, administrative evaluation, payroll record, or four-event cap.

Authority is ordered as follows:

1. `PunchEvent`: immutable recorded fact.
2. P1: authoritative pairing revision and segment linkage.
3. P2: authoritative Journey calculation revision.
4. P3-G: administrative interpretation/evaluation and human-gated final
   classifications.
5. P3-H: presentation projection only.

Portal3 must consume the existing server-to-server Journey/P3-G responses. It
must not pair raw events, calculate worked time, infer absence, assign
incidences, or replace API-owned status with a local conclusion.

## 3. Actual response fields used by this draft

The current Journey V1.1 response exposes:

- `data.context.id_personal`, `id_estacion`, `fecha_local`, `timezone`;
- `data.schedule.estado_jornada`, `scheduled_shift_id`, and
  `horario_local.segmentos[]` (`ordinal`, `tipo_segmento`, local times and
  day offsets);
- `data.pairing.segments[]` with `ordinal`, `status`,
  `entry_event_uuid`, and `exit_event_uuid`;
- `data.evaluated_raw_event_count` and the complete
  `data.evaluated_raw_events[]` collection, each containing the public fields
  `event_uuid`, `id_dispositivo`, `ocurrido_at_utc`, `metodo_captura`,
  `pairing_role`, and `segment_ordinal`;
- `data.provenance` with P1/P2 timestamps and algorithm versions, source
  completeness, Level 2 policy reference, response generation time, and the
  mirrored `evaluated_raw_events[]` collection.

The current Portal3 adapter validates and preserves these fields. It does not
use internal punch or database primary keys.

P3-G administrative responses expose `administrative_status`,
`human_review_status`, linked incidence/adjustment references, and complete
incidence revision history through the existing read boundary.

## 4. Frozen four-slot projection

The following mapping and labels are owner-approved.

| Slot | Visible label | Frozen semantic | Authoritative source |
|---|---|---|---|
| `SLOT_1` | Entrada 1 | `segment_ordinal = 1` and `pairing_role = ENTRY` | `data.evaluated_raw_events[]`, mirrored identically in `data.provenance.evaluated_raw_events[]` |
| `SLOT_2` | Salida 1 | `segment_ordinal = 1` and `pairing_role = EXIT` | Same authoritative event collections |
| `SLOT_3` | Entrada 2 | `segment_ordinal = 2` and `pairing_role = ENTRY` | Same authoritative event collections |
| `SLOT_4` | Salida 2 | `segment_ordinal = 2` and `pairing_role = EXIT` | Same authoritative event collections |

Slot population uses `pairing_role` and `segment_ordinal`, never chronology
or selecting the first four raw events. Display timestamps come from the
matched public event's
`ocurrido_at_utc`; the UI performs display conversion only with
`data.context.timezone` / the authoritative Workplace timezone context.

If a segment link is `null`, its slot is empty with the segment's authoritative
status retained. A missing counterpart is not replaced by a scheduled time or
another raw event.

## 5. Multiple segments and additional events

- One segment: expose its entry/exit in slots 1–2; slots 3–4 are not
  applicable, not absence.
- Two segments: expose ordinals 1 and 2 in slots 1–4.
- Three or more segments: ordinals 1 and 2 occupy the four compact primary
  positions. Segments with `ordinal >= 3` remain fully authoritative and are
  disclosed as `+N segmentos adicionales`, with access to the existing Journey
  and provenance detail. No segment may be discarded from canonical evidence.

All `evaluated_raw_events` remain available through the existing detail
experience. When the evaluated count exceeds the four compact positions, the
disclosure is `Ver todas las marcaciones (N)`, where `N` is the authoritative
`evaluated_raw_event_count`. The projection must not use `array_slice` or
equivalent truncation.

## 6. Unmatched, ambiguous, surplus and incomplete evidence

The P1/P2 status is authoritative. `EXACT_PAIR`,
`PARTIAL_MISSING_IN`, `PARTIAL_MISSING_OUT`, `UNMATCHED_NO_PUNCHES`,
`AMBIGUOUS_PAIRING`, `WORKPLACE_MISMATCH`, `PENDING_SYNC_COMPLETION`, and
calculation states such as `PARTIAL_DATA`, `AMBIGUOUS`, `WORKPLACE_MISMATCH`
remain visible as source status.

The frozen public event-classification contract defines `pairing_role` values
`ENTRY`, `EXIT`, `SURPLUS`, and `UNMATCHED`, plus nullable
`segment_ordinal`. `SURPLUS` is displayed as `Marcación adicional` and
`UNMATCHED` as `Sin emparejar`; neither role enters a normal primary slot.
`AMBIGUOUS_PAIRING` is never a punch-level role. It remains authoritative at
segment or Journey scope, while each punch retains its upstream role.

## 7. Empty slots, schedule states and FALTA

```text
EMPTY_SLOT_MEANS_SIN_MARCACION: NO
P3H_AUTOMATIC_FALTA: NO
```

An empty slot may mean not applicable, a missing counterpart, an absent
second segment, an unevaluated Journey, or unknown/incomplete evidence. It
never independently means `SIN MARCACIÓN`.

`UNSCHEDULED_DAY`, `DAY_OFF`, `UNKNOWN`, `INCOMPLETE`, and `DATA_INCOMPLETE`
are displayed as source/schedule states. They must not be converted to
`FALTA` or `SIN MARCACIÓN` by projection logic.

Final `FALTA` may be displayed only when returned by the authoritative P3-G
administrative evaluation after its explicit human authorization gate.

## 8. Administrative overlay

Administrative status is an overlay and explanation. It does not rewrite
timestamps or pairing links:

- `VACATION`, `PERMISSION`, and `DISABILITY` do not synthesize punches;
- `AUTHORIZED_LATENESS` does not alter `ocurrido_at_utc`;
- `REVIEW_REQUIRED` and `PENDING_REVIEW` remain visible;
- `ACTIVE`, `REJECTED`, `CANCELLED`, and `REVOKED` remain administrative
  lifecycle states;
- a human-authorized final `FALTA` remains an administrative interpretation,
  not a synthetic PunchEvent.

## 9. Overnight and time display

Journey local-workday ownership, DST handling, and midnight assignment are
backend authorities. Portal3 does not recalculate them. The compact display
uses:

- the canonical `fecha_local` and Workplace IANA timezone;
- the UTC event timestamp converted for display in that timezone;
- 24-hour `HH:mm` format;
- no seconds and no timezone abbreviation;
- a clearly displayed calendar date when an event belongs to the next calendar
  date relative to the displayed workday context.

Detailed/provenance view retains full timestamp precision and timezone context.

## 10. Provenance and accessibility

The compact view must link to or disclose the existing P3-F detail for:

- every evaluated raw event;
- every Journey segment;
- P1 revision and algorithm metadata;
- P2 revision and calculation metadata;
- Level 2 completeness and schedule provenance;
- P3-G evaluation and incidence revision history.

No new Portal3 audit table is introduced. Status must not rely on color alone;
every state has readable text, an accessible name, visible focus, keyboard
access to detail, sufficient contrast, and readable long-form reason text.

Desktop, tablet and mobile layouts preserve the same semantic slot order.
Mobile may stack slots vertically but must not make horizontal overflow the
only way to understand the day. Provenance/detail remains accessible at every
viewport.

## 11. Terminology and user-facing labels

Runtime terms remain unchanged. The physical ACL uses `leer`; the API
contract describes the corresponding capability as `READ`. The API lifecycle
term is `RESOLVED_PENDING_AUTHORIZATION`; Portal3 must consume that exact
runtime term. These are terminology mappings, not runtime changes.

Recommended user-facing labels, subject to owner approval:

| Runtime state | User-facing label |
|---|---|
| `REVIEW_REQUIRED` | Revisión requerida |
| `PENDING_REVIEW` | Pendiente de revisión |
| `ACTIVE` | Activa |
| `REJECTED` | Rechazada |
| `CANCELLED` / `REVOKED` | Cancelada / Revocada |
| `ENTRY` | Entrada |
| `EXIT` | Salida |
| `SURPLUS` | Marcación adicional |
| `UNMATCHED` | Sin emparejar |
| `AMBIGUOUS` | Marcación ambigua |
| `SURPLUS` | Marcación excedente |
| `PARTIAL_MISSING_IN` | Segmento incompleto: falta entrada |
| `PARTIAL_MISSING_OUT` | Segmento incompleto: falta salida |
| `UNSCHEDULED_DAY` | Sin horario asignado |
| `DAY_OFF` | Descanso programado |
| `UNKNOWN` / `INCOMPLETE` | Información no concluyente |

## 12. API sufficiency and future scope

```text
NEW_API_ENDPOINT_REQUIRED: NO
NEW_SCHEMA_REQUIRED: NO
API_PRESENTATION_PREREQUISITE_CONTRACT: SATISFIED
API_RUNTIME_PREREQUISITE_IMPLEMENTED: NO
API_RUNTIME_PREREQUISITE_PENDING: YES
PORTAL3_RUNTIME_IMPLEMENTATION_READY_AFTER_API: YES
FUTURE_IMPLEMENTATION_SCOPE: API_PREREQUISITE_REQUIRED
```

The companion API P3-H event-classification contract now satisfies the
presentation contract prerequisite. Its additive runtime fields are not yet
implemented, so the implementation order is: API adds `pairing_role` and
`segment_ordinal` to both evaluated-event collections; Portal3 then implements
the projection. No Portal3 workaround may reconstruct these fields from
timestamps or event order.

## 13. Owner decisions

All P3-H presentation decisions in this document are explicitly approved on
2026-10-14. No additional owner decision is required for V1.0. This approval
does not authorize runtime implementation and does not reopen the frozen
authority hierarchy, P3-G human authorization gate, raw immutability,
Workplace timezone authority, or provenance requirements.

## 14. Future implementation surface

The future implementation order is mandatory: first the API additive payload
prerequisite, then the Portal3 projection. After the API change, the Portal3
surface is narrow:

- reuse `ChecadorDailyReadService::evaluateJourney()` and the existing
  administrative read methods;
- extend the existing `ChecadorController` view-model only for projection
  presentation;
- update
  `app/Views/departamento-operativo/2-recursos-humanos/checador/index.php`;
- add focused projection regression coverage alongside the existing P3F/P04D
  tests;
- add only presentation CSS/JS if the existing template requires it.

No local pairing, calculation, incidence, timezone, or payroll service is
authorized.

## 15. Explicit exclusions and debt

```text
PAYROLL_INTEGRATION: DEFERRED
REAL_SHARED_DEV_E2E: NOT_RUN_MISSING_DEV_ACL_CONFIGURATION
RESPONSIVE_RUNTIME_VALIDATION: BLOCKED_BY_BROWSER_TOOLING
```

Known API UAT harness debt remains visible in
`tests/v2_snapshot_read_api_tests.php` (cleanup ordering versus the foreign
key constraint) and
`tests/v2_l2_false_success_controller_integration_tests.php` (persistent DB /
duplicate-migration harness behavior). These do not reopen the reconciled
P3-G gate.

## 16. Governance status

```text
P3H_CONTRACT_STATUS: FROZEN_OWNER_APPROVED
OWNER_APPROVAL_RECORDED: YES
OWNER_DECISION_REQUIRED: NO
API_PRESENTATION_PREREQUISITE_CONTRACT: SATISFIED
API_RUNTIME_PREREQUISITE_IMPLEMENTED: NO
API_RUNTIME_PREREQUISITE_PENDING: YES
RUNTIME_IMPLEMENTATION_AUTHORIZED: NO
PRODUCTION_DEPLOYMENT_AUTHORIZED: NO
```

This V1.0 contract is frozen by owner approval. The companion API runtime
payload and the Portal3 four-slot UI remain unimplemented.
