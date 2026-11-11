# Componentes de referencia de Portal3

Catálogo breve de patrones confirmados en el checkout. Los componentes se adaptan desde el código fuente indicado; esta página no sustituye su implementación.

## Tabla de Solicitud de Tarjetas

- **HTML:** [`app/Views/tarjetas/index.php`](../../app/Views/tarjetas/index.php#L17-L23), tabla `#table-tarjetas` dentro de `.datatables` y `.table-responsive`.
- **JavaScript:** [`public/assets/js/tarjetas/tarjetas.datatable.init.js`](../../public/assets/js/tarjetas/tarjetas.datatable.init.js#L1-L53), inicialización jQuery DataTables al `DOMContentLoaded`, columnas explícitas, idioma es-MX y datos por AJAX.
- **Carga:** [`TarjetasController.php`](../../app/Controllers/TarjetasController.php#L165-L174) agrega `dataTables.bootstrap5.min.css`, `vendor.min.js`, el núcleo DataTables y el inicializador. El layout `main` aporta `styles.css` y Bootstrap Bundle.
- **Convenciones:** la vista contiene la tabla estructural; el inicializador define columnas, renderizado y acciones. Mantener IDs coordinados y recursos cargados por el controlador.

## Dropdown de acciones

- **HTML de referencia:** [`tarjetas.datatable.init.js`](../../public/assets/js/tarjetas/tarjetas.datatable.init.js#L96-L116) devuelve acciones con `.dropdown.dropstart`, disparador Tabler y `.dropdown-menu`.
- **Referencia adicional:** [`aditivo.datatable.init.js`](../../public/assets/js/bitacora/aditivo.datatable.init.js#L91-L140) usa el mismo patrón para Editar/Eliminar.
- **CSS y JavaScript:** clases Bootstrap/Tabler de `styles.css`; Bootstrap Bundle global del layout. Acciones específicas pasan por `actions()`/Alpine o funciones del módulo.
- **Adaptación:** conservar el disparador y la estructura de lista Bootstrap; sólo cambiar las acciones autorizadas y sus destinos. No mover el menú al `body` ni sustituir el comportamiento de Bootstrap.

## Modal institucional de Bitácora de aditivo

- **HTML:** [`app/Views/aditivo/index.php`](../../app/Views/aditivo/index.php#L89-L205), modal `#nuevo`; la composición directa de `.modal-content` es encabezado, cuerpo y footer.
- **Encabezado y cierre:** `.modal-header.modal-colored-header.bg-primary.text-white`, `.modal-title.text-white` y `.btn-close.btn-close-white`.
- **Campos y botones:** `.modal-body`, `.form-label`, `.form-control`, separaciones `mt-3`; `.modal-footer`, cancelar con `btn bg-danger-subtle text-danger` y acción principal con `btn btn-success`.
- **CSS:** `styles.css` del layout y estilos Bootstrap existentes; Aditivo añade además la hoja Bootstrap de DataTables para su tabla. No existe hoja CSS exclusiva para este modal.
- **JavaScript:** Bootstrap Bundle global controla `data-bs-dismiss`; Alpine (`actions()`/`aditivoForm()`) controla la lógica propia de Aditivo. Esa lógica de inventario no se copia a otros módulos.
- **Adaptación:** conservar jerarquía/clases del modal y sustituir sólo el contenido, textos, iconos y acciones propios. Si un flujo requiere Escape o descartar cambios, respetar el comportamiento solicitado aunque la instancia de Aditivo configure explícitamente otro comportamiento.

## Campos y botones del modal

- **Fuente:** [`app/Views/aditivo/index.php`](../../app/Views/aditivo/index.php#L121-L202).
- **Campos:** labels antes de los controles; `form-control`/`form-select`; `mt-3` separa los grupos posteriores. El cuerpo no añade rejillas o wrappers innecesarios.
- **Footer:** botones alineados por `.modal-footer`, iconos Tabler y color según acción. Adaptar el tipo y estilo de acción al comportamiento del módulo; mantener labels asociados con `for` e IDs cuando el flujo lo permita.

## Configuración visual de DataTables

- **Tablas de referencia:** [`tarjetas.datatable.init.js`](../../public/assets/js/tarjetas/tarjetas.datatable.init.js#L6-L53) y [`aditivo.datatable.init.js`](../../public/assets/js/bitacora/aditivo.datatable.init.js#L28-L68).
- **Dependencias:** jQuery/`vendor.min.js`, núcleo `datatables.net`, CSS `datatables.net-bs5` e idioma `/assets/libs/datatables.net/js/es-ES.json`, cargados desde cada controlador. Bootstrap Bundle se carga desde el layout.
- **Convenciones:** iniciar una vez al `DOMContentLoaded`; usar `autoWidth: false`, opciones de estado/orden propias de la pantalla, `language.url` común y columnas/renderizadores explícitos. Mantener la opción `serverSide` acorde al endpoint real.
- **Asistencia:** [`checador.attendance.datatable.init.js`](../../public/assets/js/departamento-operativo/2-recursos-humanos/checador.attendance.datatable.init.js#L1-L28) sigue este inicializador jQuery/DataTables, comprueba dependencia, DOM y `isDataTable` antes de iniciar. [`ChecadorController.php`](../../app/Controllers/ChecadorController.php#L1056-L1068) carga sus recursos para el workspace de asistencia.
