---
name: portal3-native-module
description: Create, modify, or repair Portal3 features using the current checkout's MVC, authorization, frontend, and visual conventions; not for general repository audits.
---

# Portal3 native module work

Use this skill when implementing or changing a Portal3 route, controller, service/model, view, script, or module interaction.

## Before changing code

1. Find one to three comparable modules in the current checkout. Prefer the same department and workflow; examples currently present include `app/Controllers/DiaDobleController.php`, `app/Controllers/RecibosNominaController.php`, and `app/Controllers/IncidenciasNominaController.php`.
2. Trace the relevant route, controller, service/model, view/layout, JavaScript/CSS, and focused test. Check how the reference handles request parameters, validation, permissions, station context, errors, and persistence.
3. Reuse the project's real names, namespaces, route wrappers, layout, assets, Bootstrap classes, Tabler-compatible icons, forms, tables, modals, alerts, and responsive patterns. Do not add a parallel design system or dependency when Portal3 already supplies the needed behavior.

## Implement and verify

- Make the narrowest integrated change that completes the requested behavior. Prefer extending an existing service or view over duplicating it.
- Keep authorization and scope checks server-side on every read, action, and export. Apply CSRF where the established route/form pattern requires it; validate inputs and escape dynamic HTML.
- Preserve explicit error and empty states. A failed read is not a successful empty result; do not invent persistence, API success, or business conclusions.
- Run PHP lint and focused tests for the changed path. Verify rendering/navigation in the local browser when available; otherwise state that visual verification was not performed.
- Report the actual reference modules, changed files, validation, and any remaining dependency. Preserve unrelated worktree changes and do not commit unless requested.
