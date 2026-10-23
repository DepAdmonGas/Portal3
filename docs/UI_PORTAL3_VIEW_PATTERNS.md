# Portal3 UI view patterns

This note records patterns confirmed in the current Portal3 source. It is a practical reference for new views, not a new design system or a replacement for module-specific requirements.

## MVC, layout, title, and breadcrumbs

- Controllers render a view through `App\Core\View::render($view, $data, $layout)`. The view is inserted into the selected layout; `app/Views/layouts/departamento-operativo.php` provides the Operations shell, page title, breadcrumb output, shared assets, and optional per-view links/scripts.
- Pass a concise module title in the render data. For an Operations page, add breadcrumb items in the controller with `App\Core\Breadcrumb::add()` before rendering. The layout renders both above `$content`, so avoid duplicating the main page heading inside the view.
- Example references: `app/Core/View.php`, `app/Core/Breadcrumb.php`, `app/Views/layouts/departamento-operativo.php`, and `app/Controllers/DiaDobleController.php`.

## Views, forms, and filters

- Use Bootstrap grid and form classes already present in the layout: `.row`, `.g-3`, `.form-label`, `.form-control`, and `.form-select`.
- Pair each input with a visible `<label for="…">`; preserve submitted filter values from controller data when rendering results. Use GET for read-only filters where the module already follows that contract.
- Place related controls together in a `.card` / `.card-body`; use the existing primary and outline button variants for query and secondary actions.
- Example references: `app/Views/personal/index.php`, `app/Views/puestos/index.php`, and `app/Views/departamento-operativo/2-recursos-humanos/dia-doble/index.php`.

## Tables and pagination

- Common institutional table classes are `table table-striped table-bordered mb-0 text-nowrap align-middle`, wrapped in `.datatables > .table-responsive` (some views use `overflow-x-auto`). Add scoped column headings and an accessible caption when useful.
- DataTables is used by some modules for client-side interaction. For example, the Día Doble script sets `serverSide: false`; this is appropriate only for the dataset that script actually loads.
- When the controller/service already fetches one SQL/API page, do not attach client-side DataTables pagination or filtering that makes that partial page look complete. Keep the server page metadata and links/forms as the sole pagination/filter layer, and ensure navigation retains the active filters.
- For the Checador raw-punch report, DataTables uses `serverSide: true` because the SQL result is paginated at the database. The authenticated JSON endpoint revalidates station access and date/worker filters on every draw, applies allowlisted search/order in SQL, and returns `draw`, `recordsTotal`, `recordsFiltered`, and one page of `data`. Keep DataTables as the only UI paginator; do not load a partial SQL page into client-side mode or export only visible rows. CSV remains a separately authorized, complete backend stream using the active table search and order.
- Server-side table errors must keep a visible error state with a non-success HTTP status; never encode a failed read as a successful empty `data` array. Load the local DataTables core, Bootstrap 5 adapter CSS/JS, language JSON, and jQuery bundle once through the controller's `links`/`scripts` arrays.
- Example references: `app/Views/departamento-operativo/2-recursos-humanos/dia-doble/index.php`, `public/assets/js/departamento-operativo/2-recursos-humanos/dia-doble.datatable.init.js`, and `app/Views/departamento-operativo/2-recursos-humanos/checador/punch-report.php`.

## Actions, modals, and accessible details

- Use real `<button type="button">` elements for actions that do not navigate. Keep navigation as links.
- Bootstrap modal markup is already used in Portal3. A trigger uses `data-bs-toggle="modal"` and `data-bs-target="#…"`; give the modal a unique ID, `aria-labelledby`, a labelled title, a close button with `aria-label`, and a scrollable dialog where detail content can be long.
- Keep display-only details in the modal when the main table should remain compact. Escape dynamic text with `htmlspecialchars($value, ENT_QUOTES, 'UTF-8')`; render numeric identifiers as integers. Do not inject raw database strings as HTML.
- Example references: `app/Views/personal/index.php`, `app/Views/puestos/index.php`, `app/Views/departamento-operativo/2-recursos-humanos/biometricos/index.php`, and the Checador report.

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
