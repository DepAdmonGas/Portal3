# CHECADOR V2 — PORTAL3 SOURCE COMPLETENESS RECONCILIATION ADDENDUM V1.0

| Property | Value |
|---|---|
| Status | FROZEN ADDENDUM V1.0 |
| Date | 2026-10-06 |
| Scope | Portal3 Level 3 interpretation of API Level 1 snapshots and Level 2 device-to-API source completeness |
| Runtime implementation authorized | NO |
| Production changes authorized | NO |

## 1. Purpose and narrow scope

This addendum reconciles only the chronology of source-completeness implementation status recorded in J2. It does not rewrite J2, replace either Portal3 freeze, or supersede all of J2. J1 and J2 remain the foundational Portal3 Level 3 semantics; the effective-schedule temporal anchors addendum remains authoritative for its narrower temporal scope.

The later verified API/Lector work changes the current implementation-status picture for Level 1 and Level 2. It does not change the meaning or limitations of those levels, prove production deployment, or implement a Portal3 Level 3 completeness client.

## 2. Chronology reconciliation

At the time J2 was frozen, its statements were accurate status statements:

- `API_SNAPSHOT_COMPLETENESS: GAP` described the then-current V2.3 paginated read behavior and the absence of a stable snapshot membership contract.
- `DEVICE_TO_API_COMPLETENESS_SIGNAL: PARTIAL` described the then-current per-event queue/retry/ACK flow, which did not establish source-window completeness.
- J2 accordingly left API snapshot and device-to-API completeness as implementation/evidence gates.

Those statements remain in J2 as the historical record. Subsequent work produced the frozen Level 1 snapshot contract and implementation, the frozen Level 2 ingestion contract and API/Lector capabilities, and the cross-project AUTH-4 E2E verification report. These later artifacts supersede only the earlier **implementation-status chronology** for the capabilities they verify. They do not erase or retroactively alter the J2 record.

J2's semantic rules remain canonical unless explicitly supplemented. In particular, a missing or uncertain source fact remains uncertainty, raw `PunchEvent` facts remain immutable, and source completeness alone does not create an absence or payroll conclusion. Only J2's earlier completeness implementation-status statements are reconciled here; this addendum does not claim that all of J2 has been superseded.

## 3. Canonical Level 1 meaning

**Level 1 is API database snapshot-membership completeness.** Under the canonical Level 1 contract, a `READY` snapshot fixes the exact, ordered membership of governed raw `PunchEvent` records for its requested scope/filter and creation-time consistent database view. The consumer must retrieve the complete membership of that immutable snapshot.

Level 1 can establish which events belonged to that API snapshot and that all members of that snapshot were retrieved. It does not establish:

- that a person physically interacted with a biometric sensor;
- that a device captured every physical interaction;
- device operational coverage or device-to-API delivery completeness;
- employee presence or absence, `FALTA`, a payroll consequence, or a definitive `SIN MARCACIÓN` conclusion.

Portal3 must use the canonical snapshot contract. An ordinary current or empty paginated `GET /api/v2/punch-events` response is not equivalent to a Level 1 snapshot and is not proof that no `PunchEvent` existed. Portal3 must not regress to “no returned rows = no PunchEvents existed.”

## 4. Canonical Level 2 meaning

**Level 2 is governed device-to-API source-completeness evidence.** It evaluates independent source dimensions, including:

- `LOCAL_CAPTURE_DURABILITY`;
- `DELIVERY_COMPLETENESS`;
- `OPERATIONAL_COVERAGE`;
- source-time quality and time-window attribution as defined by the frozen Level 2 contract.

The runtime result states are exactly `VERIFIED`, `INCOMPLETE`, and `UNKNOWN`. `WINDOW_LEVEL2` is `VERIFIED` only when the relevant device set is known, the required local-capture durability, delivery, and operational-coverage guarantees satisfy the contract, time-window attribution is verified, and there are no unresolved resets. `SOURCE_TIME_QUALITY` or `TIME_WINDOW_ATTRIBUTION` being `UNKNOWN` or `DEGRADED` prevents `WINDOW_LEVEL2` from being `VERIFIED`. A `VERIFIED` window is evidence only for the governed scope and window evaluated; it is not a universal claim about every device or time.

The API must return the canonical Level 2 evaluation. Portal3 must not reconstruct Level 2 by counting raw rows or independently interpreting sequence, queue, or telemetry data. Every relevant governed device generation must satisfy its applicable delivery and coverage requirements. If the device set cannot be established, Level 2 remains `UNKNOWN`.

- If Level 2 is `INCOMPLETE`, absent `PunchEvent` rows cannot be converted to definitive absence.
- If Level 2 is `UNKNOWN`, absent rows cannot be converted to definitive absence; unknown is neither false nor complete.
- Rejected/unaccepted events and sequence gaps affect delivery completeness under the Level 2 contract and must not be hidden.
- Pre-cutover events without required generation/sequence provenance remain unknown for Level 2; Portal3 must not fabricate that history.

## 5. Physical-interaction limit and safe zero-event meaning

Neither Level 1 nor Level 2 proves that a human physically interacted with the biometric sensor. Even a complete Level 1 snapshot combined with a Level 2 `VERIFIED` zero-event window must not be described as “the employee definitely did not attempt to punch,” or as an equivalent physical-world claim.

The safe Level 2 zero-event statement remains exactly:

> No existe ningún PunchEvent gobernado y capturado dentro del contrato de origen verificado de Nivel 2 para la ventana especificada.

That statement is permitted only when the relevant Level 2 window is `VERIFIED`. It describes governed and captured source events within the verified Level 2 contract; it does not assert that no physical interaction occurred.

## 6. Separation of levels and Portal3 use

| Level | What it establishes | What it does not establish |
|---|---|---|
| Level 1 | Exact API snapshot membership completeness for its frozen scope/filter at snapshot creation. | Device capture, device-to-API delivery, operational health, physical interaction, or an employment/absence conclusion. |
| Level 2 | Governed device-to-API source completeness and operational/time-attribution evidence for its evaluated device set and window. | Physical sensor interaction or a Level 3 schedule/administrative conclusion. |
| Level 3 | Portal3 interpretation across canonical identity, Workplace, timezone, effective schedule, source completeness, incidents, and later approved administrative rules. | Any certainty that a required upstream level or domain has not established. |

P3-L3-4 resolves effective schedule context independently of `PunchEvent` data. A later P3-L3-5 client may combine that context with Level 1 snapshot evidence and Level 2 source-completeness status, but this addendum does not authorize or implement Journey consequences.

Portal3 must preserve traceability between the Level 1 snapshot/window/filter and the Level 2 evaluated source window and device scope. It must not combine unrelated windows, filters, or device sets and label the result complete. Exact client-side correlation mechanics remain for P3-L3-5 design/implementation.

## 7. Conditions before any definitive `SIN MARCACIÓN`

Level 1 complete plus Level 2 `VERIFIED` does **not** equal `FALTA` and does not by itself authorize definitive `SIN MARCACIÓN`. Any future absence conclusion requires all applicable gates, including:

- canonical worker and canonical Workplace resolved;
- effective Workplace timezone and effective schedule/expected-to-work context resolved;
- the relevant expected slot known under approved Journey rules;
- Level 1 complete snapshot for the exact governed scope/window;
- Level 2 `VERIFIED` for the relevant governed device set and corresponding window;
- acceptable source-time attribution and no unresolved device-set ambiguity;
- no unresolved event identity/attribution, station, time, or integrity issue;
- no source conflict capable of changing the conclusion;
- applicable incidence/absence context resolved where required.

If any required gate is missing, Portal3 must remain conservative, using appropriate states such as `DATA_INCOMPLETE`, `NO_DISPONIBLE`, `UNKNOWN`, or `REVIEW`. It must not invent a `PunchEvent`, infer `FALTA`, or convert uncertainty into `SIN MARCACIÓN`.

## 8. Raw-fact immutability and server-side integration boundary

Level 1/Level 2 completeness does not authorize rewriting raw `PunchEvent` timestamps, fabricating generation/sequence history, or creating synthetic punches. Raw facts remain immutable.

P3-L3-5 must consume the canonical APIs/contracts through server-side integration. It must not scrape legacy attendance, infer Level 2 from raw counts, reconstruct Level 1 completeness from ordinary pagination, or expose API secrets/device credentials to JavaScript or Alpine.

## 9. Later verified status and evidence boundary

The current status below is based on the canonical AUTH-4 E2E report dated 2026-10-05 and its stated isolated `DEMO` environment. It is implementation/E2E evidence, not production-deployment evidence:

```text
LEVEL1_IMPLEMENTATION_STATUS: CLOSED / VERIFIED
LEVEL2_API_STATUS: CLOSED / VERIFIED
LEVEL2_LECTOR_STATUS: CLOSED / VERIFIED
AUTH4_CROSS_PROJECT_STATUS: CLOSED / VERIFIED
PORTAL3_LEVEL3_COMPLETENESS_CLIENT: NOT_YET_IMPLEMENTED
PRODUCTION_DEPLOYMENT_STATUS: NOT_ESTABLISHED_BY_THIS_EVIDENCE
```

The report records a Level 1 snapshot membership check with five expected and five actual matching events; Level 1 API regression results of 21/21 PASS; Level 2 server capability results of 16/16 PASS; and cross-project cases covering physical Live20R capture, durable queue/offline replay, idempotent lost-ACK handling, generation discontinuity, sequence-gap blocking, telemetry/coverage, source-time preservation, multi-device evaluation, and safe zero-event `VERIFIED` versus `UNKNOWN` behavior. These statements are limited to the evidence in that report and its isolated demo run.

The API reference repository was read-only verified at HEAD `1de70d42d4f6c0a758fc8c9aba8188d77d100d07`; the AUTH-4 report itself records `API_HEAD: a351f852d00f9193837296901b53d51dc7c8da33`. Neither identifier is a claim about production deployment.

The implementation-status fields embedded in earlier frozen contracts are preserved as freeze-time metadata. This addendum records later verification; it does not edit those contracts, weaken their semantics, or infer a production rollout.

## 10. Precedence and canonical external references

Precedence is deliberately narrow:

1. J1 and J2 remain Portal3's foundational Level 3 semantics.
2. The Effective Schedule Temporal Anchors Addendum governs its specified schedule/timezone/assignment anchors.
3. This Source Completeness Reconciliation Addendum governs only the current Level 1/Level 2 status chronology and Portal3 interpretation described here.
4. The canonical API Level 1 and Level 2 contracts govern wire/source semantics.
5. The AUTH-4 report is the cited E2E evidence; it is not a replacement contract.

If a status summary and a canonical contract appear to conflict, do not silently reinterpret the contract. Preserve its semantics and resolve status/evidence scope explicitly. No addendum may silently contradict a canonical contract.

Canonical external artifacts verified by exact SHA-256 in the API reference repository; these documents are not copied into Portal3 by this activity:

| Artifact | Canonical API repository path | SHA-256 |
|---|---|---|
| Level 1 snapshot contract | `docs/v2/PUNCH_EVENTS_SNAPSHOT_READ_API_CONTRACT_V2.4_LEVEL1.md` | `65e989f2611661fd47c80844ca427fafc146eece8f37025cab3c0917c78f7c3d` |
| Level 2 ingestion contract | `docs/architecture/CHECADOR_V2_LEVEL2_INGESTION_CONTRACT_V1.1.md` | `7176c45aceb2bb59dba5470cd3b74f729a9bf5e3c797860e1dd752be3fb51a1c` |
| AUTH-4 E2E validation report | `docs/v2/AUTH4_E2E_VALIDATION_REPORT.md` | `117a6c3d72d610882c987ad5b030ae1e374714af164d04f5cc7323583f647924` |

Portal3 governance references preserved unchanged:

- `CHECADOR_V2_PORTAL3_JOURNEY_INCIDENTS_FREEZE_V1.0.md` (J1);
- `CHECADOR_V2_PORTAL3_SCHEDULE_COMPLETENESS_FREEZE_J2_V1.0.md` (J2);
- `CHECADOR_V2_PORTAL3_EFFECTIVE_SCHEDULE_ANCHORS_ADDENDUM_V1.0.md` (temporal anchors).

## 11. Freeze and non-authorization

```text
J2_HISTORICAL_GAP_STATUS_RECONCILED: YES
SOURCE_COMPLETENESS_CHRONOLOGY_RECONCILED: YES
DEFERRED_DOCUMENT_RECONCILIATION_REQUIRED: NO
PORTAL3_LEVEL3_COMPLETENESS_CLIENT_IMPLEMENTED: NO
SIN_MARCACION_IMPLEMENTED: NO
PRODUCTION_DEPLOYMENT_CLAIMED: NO
RUNTIME_IMPLEMENTATION_AUTHORIZED: NO
```

This is a documentation/architecture reconciliation only. It authorizes no PHP, JavaScript, route, test, migration, database, API, Lector, or production changes. P3-L3-5 remains a separate implementation slice and is not started or authorized automatically by this addendum.
