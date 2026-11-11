# Portal3 UI view patterns

This note records patterns confirmed in the current Portal3 source. It is a practical reference for new views, not a new design system or a replacement for module-specific requirements.

## Runtime and shared stack confirmed in this checkout

- **PHP/MVC and routing:** `composer.json` requires PHP `^8.2` and `nikic/fast-route`; `app/Core/Router.php` dispatches the `FastRoute` collector defined in `routes/web.php`. Controllers render PHP views with `App\Core\View::render()` and the layout selected for the area.
- **Operations shell:** Recursos Humanos uses `app/Views/layouts/departamento-operativo.php`, the Modernize-based Portal3 shell. It loads shared `styles.css`, the Bootstrap bundle, theme scripts, SimpleBar, SweetAlert2, `public/assets/js/core/notify.js`, and `public/assets/js/core/actions.alpine.js`. Page assets belong in the controller's existing `links` and `scripts` arrays.
- **Bootstrap and icons:** Use the Bootstrap 5 classes and bundle already loaded by the area layout. Current views commonly use Tabler-compatible classes such as `ti ti-search`; keep the icon convention and loaded assets of the target module. Do not add an icon package or another component library.
- **Alpine and Axios:** The Operations layout loads local Alpine and Axios assets, configures Axios with the current CSRF token/interceptors, and loads shared actions. Use Alpine for reactive UI only where the module already follows that pattern; use Axios for module requests where established. Keep validation, permission checks, response handling, and endpoint contracts on the existing server/controller path.
- **DataTables:** Existing modules load the jQuery DataTables core and Bootstrap 5 adapter through controller-provided assets. Preserve its transport and `serverSide` mode according to the endpoint; a server-paginated source must remain server-side.
- **Feedback:** Keep the module's current Bootstrap alerts, field validation classes/messages, `Notify` toasts, and SweetAlert2 confirmations/alerts. A notification is feedback, not authorization or a substitute for a visible error/empty state.

Before writing a view or component, identify the canonical existing pattern of the same type and reuse its structure and classes. If no applicable reference exists, report the exception rather than inventing a new design. For every change, record one concrete functional reference and one visual reference; do not copy unrelated behavior or known defects merely because the markup looks similar.

## MVC, layout, title, and breadcrumbs

- Controllers render a view through `App\Core\View::render($view, $data, $layout)`. The view is inserted into the selected layout; `app/Views/layouts/departamento-operativo.php` provides the Operations shell, page title, breadcrumb output, shared assets, and optional per-view links/scripts.
- Pass a concise module title in the render data. For an Operations page, add breadcrumb items in the controller with `App\Core\Breadcrumb::add()` before rendering. The layout renders both above `$content`, so avoid duplicating the main page heading inside the view.
- Example references: `app/Core/View.php`, `app/Core/Breadcrumb.php`, `app/Views/layouts/departamento-operativo.php`, and `app/Controllers/DiaDobleController.php`.

## Views, forms, and filters

- Use Bootstrap grid and form classes already present in the layout: `.row`, `.g-3`, `.form-label`, `.form-control`, and `.form-select`.
- Pair each input with a visible `<label for="…">`; preserve submitted filter values from controller data when rendering results. Use GET for read-only filters where the module already follows that contract.
- Place related controls together in a `.card` / `.card-body`; use the existing primary and outline button variants for query and secondary actions.
- HR form reference: `app/Views/departamento-operativo/2-recursos-humanos/checador/schedule-week.php` shows a GET filter and a responsive POST form with linked labels, `.col-12 col-md-*` layout, Bootstrap controls/buttons, required fields, CSRF, and status feedback. Reuse its markup conventions only; preserve the target module's route/method and server-side validation. For inline invalid fields, follow an existing module's `is-invalid` / `invalid-feedback` pairing.
- Field-error reference: `app/Views/login/index.php` associates `is-invalid` with a visible `invalid-feedback` message. Reuse that Bootstrap field-state pairing where the target flow has field-specific errors; keep its own layout, validation rules, labels, and server as authoritative.
- Example references: `app/Views/personal/index.php`, `app/Views/puestos/index.php`, and `app/Views/departamento-operativo/2-recursos-humanos/dia-doble/index.php`.

## Tables and pagination

- **Complete response / client-side DataTables:** Día Doble uses `app/Views/departamento-operativo/2-recursos-humanos/dia-doble/index.php` with `public/assets/js/departamento-operativo/2-recursos-humanos/dia-doble.datatable.init.js` (`serverSide: false`). Keep this mode only while its endpoint returns the complete authorized result for that view; pagination, search and ordering then operate over that loaded result.
- **Server-paginated DataTables:** Checador's raw-punch report uses `app/Views/departamento-operativo/2-recursos-humanos/checador/punch-report.php` and `public/assets/js/departamento-operativo/2-recursos-humanos/checador.datatable.init.js` (`serverSide: true`) because SQL applies paging. Its authenticated JSON endpoint revalidates station access and date/worker filters on every draw, applies allowlisted search/order in SQL, and returns `draw`, `recordsTotal`, `recordsFiltered`, and one page of `data`. Keep DataTables as the only UI paginator; do not load a partial SQL page in client-side mode or export only visible rows. CSV is a separately authorized backend stream using active search/order.
- **Simple consultation table:** `app/Views/departamento-operativo/2-recursos-humanos/checador/schedule-week.php` renders its supplied weekly matrix as HTML, without DataTables. Preserve `.table-responsive`, the accessible caption and scoped headers, explicit empty state, escaped values, and horizontal overflow containment.
- Use the target module's existing table classes and component loading. Common classes include `table table-striped table-bordered mb-0 text-nowrap align-middle`, wrapped in `.datatables > .table-responsive` where the table uses DataTables. Do not force one class list onto every table or alter a server/client mode for visual similarity.
- Keep the hierarchy consistent: table header with `<th scope="col">`, row headers with `<th scope="row">` where useful, a caption when the table needs context, and compact cell alignment/spacing. Use badges for states only with the module's established label mapping; render navigation as links and non-navigation actions as buttons. Search and pagination belong to the active DataTables mode or existing server filter, not an extra parallel control. A simple table must not imply search or pages it does not implement.
- Server-side table errors must keep a visible error state with a non-success HTTP status; never encode a failed read as a successful empty `data` array. Load the local DataTables core, Bootstrap 5 adapter CSS/JS, language JSON, and jQuery bundle once through the controller's `links`/`scripts` arrays.
- Example references: `app/Views/departamento-operativo/2-recursos-humanos/dia-doble/index.php`, `public/assets/js/departamento-operativo/2-recursos-humanos/dia-doble.datatable.init.js`, and `app/Views/departamento-operativo/2-recursos-humanos/checador/punch-report.php`.

## Actions, modals, and accessible details

- Use real `<button type="button">` elements for actions that do not navigate. Keep navigation as links.
- Bootstrap modal markup is already used in Portal3. A trigger uses `data-bs-toggle="modal"` and `data-bs-target="#…"`; give the modal a unique ID, `aria-labelledby`, a labelled title, a close button with `aria-label`, and a scrollable dialog where detail content can be long.
- HR modal reference: the per-worker detail modal in `app/Views/departamento-operativo/2-recursos-humanos/checador/daily-attendance.php` uses `.modal.fade` → `.modal-dialog.modal-dialog-scrollable` → `.modal-content`, with `.modal-header`, labelled close button, `.modal-body`, and `.modal-footer`. Preserve this Bootstrap structure and the target view's appropriate dialog sizing/actions.
- Keep display-only details in the modal when the main table should remain compact. Escape dynamic text with `htmlspecialchars($value, ENT_QUOTES, 'UTF-8')`; render numeric identifiers as integers. Do not inject raw database strings as HTML.
- Example references: `app/Views/personal/index.php`, `app/Views/puestos/index.php`, `app/Views/departamento-operativo/2-recursos-humanos/biometricos/index.php`, and the Checador report.

## Alpine actions and Axios requests

- Shared actions live in `public/assets/js/core/actions.alpine.js` as `Alpine.data('actions', ...)`; the Operations layout loads them once. Reuse methods such as `showAlert`, `notify`, `handleResponse`, or `deleteAction` instead of rebuilding common prompts and response handling.
- `app/Views/departamento-operativo/2-recursos-humanos/dia-doble/form.php` demonstrates combining shared and local state with `x-data="{ ...actions(), ...diaDobleForm() }"`; the module's `dia-doble.form.init.js` registers its own component. Keep view bindings (`x-model`, `@change`, `@click`, `x-show`, `x-text`) consistent with the component and avoid duplicate state/listeners.
- `public/assets/js/departamento-operativo/2-recursos-humanos/dia-doble.actions.init.js` is an Axios example: it calls existing same-origin routes, guards loading state, consumes the established response shape, and reports errors through `Notify`. The layout supplies the CSRF header/interceptor. Preserve the target module's endpoint and error contract; do not replace DataTables' configured AJAX transport with Axios.

## Statuses, empty states, and permissions

- Existing views use Bootstrap alerts and badges, such as `.alert`, `.badge`, and `text-bg-*`. Preserve source status values unless the owning service defines a documented label mapping.
- Render explicit idle, error, and empty states. An empty query should not be described as proof of absence or completeness unless the data contract guarantees that conclusion.
- A conditionally visible button is presentation only. The controller/service must enforce authorization again for the requested station and action, including downloads/exports. The Checador report rechecks station scope and the effective `descargar` permission before CSV streaming.
- Example references: `app/Views/departamento-operativo/2-recursos-humanos/checador/index.php` and `app/Controllers/ChecadorController.php`.

## Shared and per-view assets

- The Operations layout loads the shared CSS, Bootstrap bundle, Tabler-compatible icon setup, and common JavaScript. Do not add another framework or duplicate shared bundles in a view.
- The layout accepts `links` and `scripts` arrays from the controller for page-specific assets. `DiaDobleController` demonstrates adding its DataTables CSS and scripts there. Public asset URLs in views/controller data are rooted at `/assets/…`, not relative to the current route depth.
- A view that only uses Bootstrap's shared behavior needs no custom JavaScript or extra asset entry. If custom behavior is required, keep it in a page/module JS file and include it through the controller's existing `scripts` convention.
- Example references: `app/Views/layouts/departamento-operativo.php`, `app/Controllers/DiaDobleController.php`, and `public/assets/js/departamento-operativo/2-recursos-humanos/`.

## Responsive behavior

- Use Bootstrap breakpoint columns for forms and actions, and place wide tables inside `.table-responsive` so any horizontal overflow stays contained to the table rather than the page.
- Keep primary table content compact; put technical fields in a scrollable modal or secondary section. Verify keyboard operation and narrow-screen behavior in a browser when one is available.
- Example references: the HR views listed above and `app/Views/departamento-operativo/2-recursos-humanos/checador/punch-report.php`.

## Focused UI quality check

- For a changed PHP view/controller, run PHP syntax validation and the focused functional/permission test for that route. For changed JavaScript, run a syntax check and the focused interaction test where available. Do not repeat global suites without new evidence.
- Compare the changed DOM structure, classes, asset loading, empty/error states, and permission-gated actions with the named canonical source. In an authorized browser, inspect keyboard use, console/errors, and widths 375, 768, and 1440 px. If no authorized browser is available, report exactly that manual visual validation is pending; never bypass the restriction or infer a browser pass from tests.
