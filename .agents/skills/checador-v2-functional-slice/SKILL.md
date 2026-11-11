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

## Authenticated read-only E2E and close gates

- Before implementation, identify the exact acceptance path and canonical references, distinguish synthetic QA fixtures from canonical API/database evidence, and list the data and actions the browser test must not mutate.
- After implementation, run the focused Checador regression, relevant PHP/JavaScript syntax checks, `tests/portal3_ui_quality_gate.php`, and the authenticated local browser gate `npm --prefix tests/e2e run quality` when the route requires browser validation.
- The local E2E may use only the explicitly authorized Encargado and Departamento Operativo QA profiles. Authenticate manually through the isolated visible Chrome window; do not receive passwords in chat, inspect secrets in `.env`, copy personal sessions, or bypass login, CSRF or ACL. Keep browser state outside the repository and do not commit it.
- E2E fixtures remain synthetic local evidence. The test must not create or resolve incidences, insert events, change schedules, or present informational fixture labels as canonical API V2 evaluations. Permit only the existing module-context selection request when needed to choose the authorized station; otherwise keep the attendance/incidence flow read-only.
- Screenshots are real candidate evidence, not approved references. Do not declare visual approval automatically.
- Report separate gates: `CODE_VERIFIED`, `FUNCTIONAL_VERIFIED`, `BROWSER_VERIFIED`, `VISUAL_APPROVED`, and `BLOCKED`. Source checks and fixture tests cannot be promoted to browser evidence. If authorized login or browser execution is unavailable, the full acceptance remains pending with the precise missing prerequisite.

## Portal3 implementation and visual references

- Before writing a view or component, identify the canonical existing pattern of the same type and reuse its structure and classes. If no reference applies, report the exception instead of inventing a design. Record concrete functional and visual references for every slice; consult `docs/UI_PORTAL3_VIEW_PATTERNS.md` and `docs/ui/PORTAL3_COMPONENTES_REFERENCIA.md`.
- Use the Recursos Humanos layout and the stack it already loads: PHP MVC/FastRoute, Modernize-based assets, Bootstrap 5, Alpine.js, Axios where used, and the existing jQuery/DataTables integration. Reuse shared `actions.alpine.js`, Bootstrap modals, current `ti ti-*` icon conventions, `Notify`/SweetAlert2, alerts, and form validation. Do not add another framework, UI system, dependency, or duplicate CSS.
- For tables, preserve the source's data contract and mode. Día Doble is the client-side DataTables reference for its complete response; the approved raw-punch report is server-side because SQL paginates; Programación semanal is a simple responsive HTML table. Never switch modes to imitate another table.
- For HR forms and modals, use the existing responsive Bootstrap labels/controls and modal composition recorded in the UI patterns. Keep the flow's validation, loading/error/empty states, permissions, CSRF, and responsive behavior; do not copy unrelated business logic or known defects from an example.
- Validate only the changed path and permissions, PHP/JavaScript syntax, and the relevant focused tests. When a browser is authorized, compare markup/classes with the reference and inspect 375, 768, and 1440 px plus console errors. If the browser is restricted, do not bypass it or claim visual validation; report the manual check still needed.
