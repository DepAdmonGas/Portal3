# CHECADOR V2 — PORTAL3 EFFECTIVE SCHEDULE TEMPORAL ANCHORS ADDENDUM V1.0

**Status:** FROZEN ADDENDUM V1.0
**Date:** 2026-10-06
**Applies to:** P3-L3-4 Effective Schedule Resolver and later Level 3 Journey calculations

## 1. Purpose and governance

This addendum freezes only the temporal-anchor semantics that J1/J2 leave unspecified. It supplements, and does not replace, either freeze. J1/J2 remain canonical; this addendum governs only where they are silent. If implementation discovers an actual contradiction, stop and require architectural reconciliation rather than choosing a precedence locally.

This is an architecture decision only. It does not authorize runtime code, schema changes, API or Lector work, deployment, or production activity.

## 2. Resolution key and record-time perspective

The Effective Schedule conceptual key is:

```text
worker_id
workplace_id
local_workday_date
optional recorded_as_of_utc
```

- `worker_id` is the canonical `op_rh_personal.id`.
- `workplace_id` is the canonical `op_rh_localidades.id` and is explicit on every resolution request.
- `local_workday_date` is the civil `YYYY-MM-DD` schedule start date. Schedule valid-time remains local-date based; it is not converted to UTC for schedule-version selection.
- `recorded_as_of_utc`, when supplied, defines one consistent recorded-history view for worker↔Workplace assignments, Workplace timezone versions, and personal schedule versions. Do not mix current and historical perspectives. When omitted, use the latest recorded-history view.

The resolver evaluates only the requested Workplace. It never chooses a home/first/latest/lowest-ID Workplace and does not use `RhPersonal.id_estacion` as a fallback.

## 3. Governing timezone at local-day start

`LOCAL_DAY_START` means `local_workday_date 00:00:00` interpreted in a candidate Workplace IANA timezone. Because timezone versions have UTC effective intervals, select the governing version self-consistently:

1. Consider each valid timezone version for the requested Workplace that is visible in the selected `recorded_as_of_utc` perspective.
2. Interpret `local_workday_date 00:00:00` under that version's IANA timezone and convert that instant to UTC.
3. The candidate qualifies only if the resulting UTC instant lies in its half-open effective interval `[valid_from_utc, valid_to_utc)`; a null end is open-ended.
4. Exactly one candidate must qualify.

One qualifying candidate resolves the governing timezone. Zero candidates means `TIMEZONE_UNRESOLVED`; more than one means `TIMEZONE_AMBIGUOUS` / `DATA_INVALID`. Invalid IANA data or a local-day-start instant that is ambiguous or nonexistent under that candidate cannot produce a definitive resolution. Runtime enum spelling may be selected in P3-L3-4; the conservative semantics are frozen here.

Do not use PHP's default timezone, server timezone, `APP_TIMEZONE`, device/source timezone, an ambient current Workplace timezone, a country default, or a hardcoded IANA zone as fallback.

The timezone version selected at `LOCAL_DAY_START` governs that schedule local workday. Do not silently switch to a later version mid-resolution. For `DAY_OFF` and `UNSCHEDULED`, there are no segments, so timezone selection uses `LOCAL_DAY_START` only.

### 3.1 Intraday timezone transition

For a `SCHEDULED` day, materialize segment boundaries under the governing IANA timezone. That version's UTC effective interval must cover every full scheduled segment interval. If another timezone version becomes effective during a segment or otherwise prevents the selected version from covering a complete segment, do not switch timezones or partially accept the interval. Return a conservative unresolved/review result, such as `UNKNOWN / TIMEZONE_TRANSITION_WITHIN_WORKDAY`.

There is no definitive schedule materialization across an intraday timezone-version transition. For half-open intervals, a segment `[start_utc, end_utc)` is covered when its start is within the timezone version and its end does not exceed the version's exclusive end.

## 4. Worker↔Workplace assignment validity

P3-L3-2 assignment history is effective in UTC. Evaluate only membership for the explicitly requested canonical worker and Workplace, using the same recorded-history perspective as timezone and schedule history. Concurrent membership at other Workplaces is valid and does not invalidate this resolution.

### 4.1 Scheduled day: full segment coverage

For a `SCHEDULED` local workday, a single arbitrary instant is not sufficient to establish assignment. Every complete materialized schedule segment interval `[start_utc, end_utc)` must be covered by effective worker↔Workplace membership in the selected record-time view.

- One assignment version or the union of multiple adjacent assignment versions for that same worker↔Workplace may cover a segment, provided there is no gap.
- Every segment must be fully covered. A membership gap within a segment makes assignment unresolved for definitive schedule use.
- Membership is not required during a break between separate schedule segments unless a later Journey rule explicitly requires it.
- Do not shorten a segment, select only its covered portion, or infer a partial schedule.

For traceability, `ASSIGNMENT_PRIMARY_ANCHOR` is the UTC instant corresponding to the earliest scheduled segment start. This is a diagnostic/provenance anchor only; it does not replace full-segment coverage.

If full coverage is absent or indeterminate, return a conservative result such as `UNKNOWN / ASSIGNMENT_NOT_COVERING_SCHEDULE` or `REVIEW`.

### 4.2 Day off and unscheduled day

For explicit `DAY_OFF` and `UNSCHEDULED`, no scheduled intervals exist. Validate assignment at `LOCAL_DAY_START` converted to UTC using the resolved governing timezone. If the requested Workplace assignment is not active at that anchor, do not claim authoritative `DAY_OFF` or `UNSCHEDULED`; return a conservative result such as `UNKNOWN / NOT_ASSIGNED`.

`UNSCHEDULED` remains distinct from `DAY_OFF` and never implies absence.

## 5. Schedule selection, materialization, and conservative results

Select the P3-L3-3 schedule version by canonical worker, explicit Workplace, `LOCAL_WORKDAY_DATE`, and optional `recorded_as_of_utc`. Preserve all 1..N segments in deterministic ordinal order, including local start/end times and explicit `end_day_offset`. `LOCAL_WORKDAY_DATE` remains the segment start date; an overnight offset does not change it.

After the schedule version, weekday definition, and governing timezone are resolved, local boundaries are formed as:

```text
start = local_workday_date + start_local_time
end   = local_workday_date + end_day_offset + end_local_time
```

Interpret both under the governing IANA timezone. Any derived UTC boundary must remain traceable to the timezone version, local date, schedule/segment, and offset. Do not persist derived schedule results in this phase.

If a local-day-start or segment boundary is ambiguous or nonexistent under the applicable IANA rules, do not guess or silently normalize it. Keep the result conservative (`UNKNOWN`, `REVIEW`, `TIME_AMBIGUOUS`, or `TIME_NONEXISTENT`, as appropriate). If a stored `end_day_offset` cannot be safely represented by the runtime date library, return `DATA_INVALID / REVIEW`; never overflow, wrap, or clamp it, and do not add a business maximum.

Definitive `SCHEDULED`, `DAY_OFF`, or `UNSCHEDULED` is permitted only when every prerequisite for that result is deterministically resolved. No schedule version yields `UNKNOWN / SCHEDULE_UNRESOLVED`; no valid timezone yields `UNKNOWN / TIMEZONE_UNRESOLVED`; invalid or ambiguous persisted history remains conservative. There is no fallback to station defaults, legacy personal schedules/history, special programming, or current `id_estacion`.

## 6. Normative examples

### A. Normal day

For `LOCAL_WORKDAY_DATE = 2026-10-05`, timezone `America/Mexico_City`, and a `08:00–16:00` segment, the timezone version selected at Oct 5 local midnight must cover the full materialized segment. Worker↔Workplace membership must also cover that entire segment. A definitive schedule result is eligible only when both coverages and the schedule fact are resolved.

### B. Night shift

For `LOCAL_WORKDAY_DATE = 2026-10-05` and `22:00 → 06:00`, `end_day_offset = 1`, select timezone at Oct 5 local midnight. The selected timezone version and requested Workplace membership must cover the complete UTC interval corresponding to Oct 5 22:00 through Oct 6 06:00. The `LOCAL_WORKDAY_DATE` remains Oct 5.

### C. Intraday timezone change

Timezone A qualifies at local-day start, but timezone B becomes effective during a scheduled segment. Do not switch to B midway. The result is unresolved/review; do not definitively materialize the schedule.

### D. Multiple Workplaces

A worker has memberships at Workplaces A and B. A request for A evaluates only A's membership, timezone, and schedule; a request for B evaluates only B's. Both may resolve independently for the same local workday.

## 7. Exclusions and deferred reconciliation

This addendum does not define or authorize PunchEvent attribution, Level1 completeness, Level2 coverage, Journey, four-slot projection, `SIN MARCACIÓN`, `FALTA`, lateness, meal policy, payroll, or incidents. Special programming remains excluded.

`DEFERRED_DOCUMENT_RECONCILIATION_REQUIRED: YES` remains in force. J2's separate Level1/Level2 completeness chronology is not reconciled here and must be handled before definitive absence conclusions are authorized.
