---
name: portal3-native-module
description: Create, modify, or repair Portal3 features using the current checkout's MVC, authorization, frontend, and visual conventions; not for general repository audits.
---

# Portal3 native module work

Use this skill when implementing or changing a Portal3 route, controller, service/model, view, script, or module interaction.

Before any UI edit, read [`docs/ui/PORTAL3_UI_CONTRACT.md`](../../../docs/ui/PORTAL3_UI_CONTRACT.md) and the current checkout's actual Modernize/Bootstrap assets. The contract is mandatory guidance and does not replace local source inspection. Use existing Modernize, Bootstrap 5 and Tabler Icons first. For table actions, use a direct button for one available action, a native Bootstrap dropdown for two or more, and no button when none are available; omit unauthorized options. Keep alignment confined to the action control/cell and leave table data and modal content normally aligned. Record the requested scope, protected elements, concrete pattern and validation plan before editing. If browser inspection is unavailable, report `VISUAL_NOT_VERIFIED`.

## Before changing code

Before writing a view or component, identify the existing canonical pattern of the same type and reuse its structure and classes. If no applicable reference exists, report the exception instead of inventing a new design. For each change, record one concrete functional reference and one concrete visual reference (one module may serve both when appropriate).

1. Find one to three comparable modules in the current checkout. Prefer the same department and workflow; examples currently present include `app/Controllers/DiaDobleController.php`, `app/Controllers/RecibosNominaController.php`, and `app/Controllers/IncidenciasNominaController.php`.
2. Trace the relevant route, controller, service/model, view/layout, JavaScript/CSS, and focused test. Check how the reference handles request parameters, validation, permissions, station context, errors, and persistence.
3. Reuse the project's real names, namespaces, route wrappers, layout, assets, Bootstrap classes, Tabler-compatible icons, forms, tables, modals, alerts, and responsive patterns. Do not add a parallel design system or dependency when Portal3 already supplies the needed behavior.

## Portal3 stack and visual consistency

- Use the stack actually loaded by the relevant layout: PHP MVC and FastRoute; the Modernize-based shell and shared assets; Bootstrap 5; Alpine.js for reactive components; Axios where that module uses it; and the existing jQuery/DataTables integration where present. Keep module-specific JavaScript and shared helpers in their established locations.
- In Recursos Humanos, start with `docs/UI_PORTAL3_VIEW_PATTERNS.md` and `app/Views/layouts/departamento-operativo.php`. Preserve the module's existing transport and data mode: DataTables `serverSide` must match whether the endpoint pages on the server. Do not replace a working transport or mode for visual uniformity.
- Reuse shared `actions.alpine.js`, Bootstrap modals, current icon classes (commonly `ti ti-*`), `Notify`/SweetAlert2, Bootstrap alerts, and existing validation presentation. Add no React, Vue, CSS framework, third-party component, or duplicate stylesheet.
- Keep labels associated with controls, use existing responsive Bootstrap grid classes, and maintain explicit loading, error, empty, and success states appropriate to the module. Escape dynamic values and preserve its server-side validation and authorization.

## Implement and verify

- Make the narrowest integrated change that completes the requested behavior. Prefer extending an existing service or view over duplicating it.
- Keep authorization and scope checks server-side on every read, action, and export. Apply CSRF where the established route/form pattern requires it; validate inputs and escape dynamic HTML.
- Preserve explicit error and empty states. A failed read is not a successful empty result; do not invent persistence, API success, or business conclusions.
- Run PHP lint and focused tests for the changed path. Verify rendering/navigation in the local browser when available; otherwise state that visual verification was not performed.
- For UI changes, compare structure and classes with the selected canonical component, then check responsive behavior at 375, 768, and 1440 px and browser-console errors when an authorized browser is available. Do not evade browser restrictions or claim visual evidence without running that check; name the remaining manual validation.
- Report the actual reference modules, changed files, validation, and any remaining dependency. Preserve unrelated worktree changes and do not commit unless requested.

## Read-only authenticated browser and close gates

- Before implementation, state the acceptance criteria, inspect the checkout's canonical functional and visual references, and identify which user-visible routes require a browser pass.
- After implementation, run focused checks, PHP/JavaScript syntax checks for changed code, the Portal3 UI Quality Gate, and the local authenticated E2E when the changed path needs browser evidence. Use `npm --prefix tests/e2e run quality` as the integrated gate.
- Authenticate only through the visible isolated Chrome login flow (`npm --prefix tests/e2e run auth -- <authorized-profile>`). Never request credentials in chat, read credentials from `.env`, reuse personal browser profiles, bypass authentication/CSRF/ACL, or put cookies/storage state inside the repository. Keep state under the private local E2E state directory.
- Treat E2E screenshots as candidate evidence only. A passing test may establish browser execution, but never approves visual design automatically; the user retains `VISUAL_APPROVED`.
- Close each task with explicit status labels: `CODE_VERIFIED` (source/static checks), `FUNCTIONAL_VERIFIED` (focused behavior checks), `BROWSER_VERIFIED` (authenticated browser path actually completed), `VISUAL_APPROVED` (explicit human approval of real screenshots), and `BLOCKED` (required evidence could not be obtained). Do not infer a later status from an earlier one; if an obligatory gate is blocked, the overall result remains pending.
