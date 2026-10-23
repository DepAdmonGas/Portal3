---
name: checador-v2-functional-slice
description: Implement one verified Checador V2 functional slice in Portal3 while preserving its frozen contracts, canonical scope, source ownership, and focused validation; not for deployment or broad repeated audits.
---

# Checador V2 functional slice

For Checador V2 implementation, also follow `../portal3-native-module/SKILL.md` whenever Portal3 code or UI changes. Continue from the latest available V8A inventory and subsequent verified work; do not repeat a global audit or assume a previous status without checking the relevant evidence.

## Contract invariants

- Resolve access from authenticated server-side identity and the active Portal3 ACL. Puesto 13 (Dirección de Operaciones) may read only supported, active, authorized stations; locality departments such as Autolavado require explicit authorization. Puesto 6 (Encargado) remains limited to its own authorized station/locality. Do not infer global access for puestos 3 or 14.
- Keep `tb_estaciones.id`, `numlista`, and canonical `op_rh_localidades.id` distinct. Translate catalogs only through a valid, unique mapping; missing or ambiguous mappings deny access. Never use numeric ID equality as a substitute.
- Preserve the Palo Solo–Autolavado exception only as explicitly authorized: Palo Solo is locality 2, Autolavado locality 9, and inactive physical station 11 is not a substitute.
- Keep read, download, create, resolve, authorize, reject, revoke, and other administrative permissions distinct as defined by the active contract. Recheck scope and permission in the server handler, including CSV/export routes.
- Original PunchEvents are immutable. Portal3 must not calculate or invent API-owned schedule, journey, integrity, incidence, or payroll outcomes; retain provenance and distinguish unavailable/incomplete data from an empty or verified result.
- Do not modify `docs/architecture/CHECADOR_V2_ARCHITECTURE_FREEZE_V1.0.md` or frozen API/Lector contracts without explicit architectural approval.

## One-slice workflow

1. Locate the latest V8A result and identify one actually incomplete flow. Separate Portal3 responsibilities from API and Lector responsibilities; choose a local implementation only when it can comply with the frozen contract.
2. Trace one vertical path: route/authentication → permission and canonical scope → controller → existing service/data source → view and interaction → feedback/recovery → focused test.
3. Reuse existing Portal3 and Checador code. Do not add parallel services/routes or simulated persistence to make an external operation appear complete. If an endpoint, ACL grant, authoritative source, or migration is unavailable or unauthorized, implement only the independent local portion and report the exact blocker.
4. Validate success, denied scope/permission, invalid input, empty/error state, and retry/idempotency where relevant. Run only focused tests plus necessary lint. Label mocked/fixture results separately from real API, database, or browser verification.
5. Finish one slice, preserve pre-existing changes, report changed files/dependencies and select the next functional slice. Do not move work to Plesk, preproduction, production, or deployment unless specifically authorized.
