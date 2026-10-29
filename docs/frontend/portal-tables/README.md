# Estilos compartidos de tablas en Portal3

Esta guía explica cómo funcionan los estilos compartidos de tablas, cómo conservar la selección de filas y cómo desactivarla para una tabla o una vista.

## Archivos que implementan el comportamiento

- `public/assets/css/portal-tables.css`: estilos compartidos de tablas, selección, menús de acciones y calendarios.
- `public/assets/js/core/portal-table-selection.js`: agrega la selección de fila al hacer clic y la selección de día en los calendarios.
- Los layouts `main`, `departamento-operativo`, `sasisopa`, `sgm` y `configuracion` cargan ambos recursos. El CSS se carga después de `styles.css`.

## Qué se activa por defecto

La selección de fila está habilitada para las tablas de esas páginas sin agregar una clase especial. La selección se mantiene de forma independiente por vista: la página principal tiene su propio alcance y cada modal u offcanvas tiene el suyo. Al hacer clic en una fila del `tbody`:

1. Se marca con la clase `selected` y el cursor `pointer`.
2. Se quita la selección anterior únicamente dentro del mismo alcance (página, modal u offcanvas).
3. Se aplica el fondo primario y el texto blanco definidos por el CSS compartido.

Al hacer clic fuera de una fila, se quita `selected` de las filas del mismo alcance y recuperan su estilo original. Esto incluye clics fuera de la tabla. Una interacción dentro de un modal u offcanvas no limpia ni modifica la selección de las tablas que quedan detrás; una interacción en la página tampoco cambia la selección de un diálogo abierto. Al cerrar el diálogo, se limpia sólo la selección de sus tablas. Los controles dentro de una fila mantienen la selección mientras el clic siga dentro de esa fila.

Las filas que tienen una celda con `colspan` se omiten. El comportamiento de la selección del día en FullCalendar también está en ese script, pero se configura por separado y no se desactiva con el atributo de tablas.

## Desactivar la selección de todas las tablas de una vista

Coloca `data-portal-table-selection="false"` en el contenedor que incluye las tablas:

```html
<div data-portal-table-selection="false">
    <table class="table table-striped table-bordered">
        ...
    </table>

    <table class="table table-striped table-bordered">
        ...
    </table>
</div>
```

El script revisa la tabla y sus contenedores ascendentes. Por eso el atributo en el contenedor desactiva la selección para todas las tablas dentro de él, incluidas tablas dentro de modales.

## Desactivar la selección de una tabla

Agrega el mismo atributo directamente a la tabla:

```html
<table class="table table-striped table-bordered"
       data-portal-table-selection="false">
    ...
</table>
```

Esta es la opción adecuada cuando otras tablas de la página sí deben conservar la selección.

## Desactivar la selección en todo el sitio

El comportamiento está habilitado por defecto. Para apagarlo en todo el sitio, se puede agregar el atributo al `<body>` de los layouts:

```html
<body data-portal-table-selection="false">
```

Esto desactiva la selección para todas las tablas del layout. Para volver a habilitarla globalmente, quita el atributo del `<body>` y de cualquier contenedor o tabla que aún tenga `data-portal-table-selection="false"`.

### Alcance del atributo

El mecanismo actual es de exclusión: ausencia del atributo significa habilitado; el valor `false` significa deshabilitado. Un `data-portal-table-selection="true"` en una tabla hija no anula un `false` que tenga su contenedor o el `<body>`. Si necesitas que algunas tablas sigan seleccionables, no desactives el `<body>` completo: coloca `false` solo en las tablas que deban quedar excluidas.

## Clases auxiliares

La selección al hacer clic no requiere estas clases; corresponden a detalles visuales específicos:

- `portal-table`: aplica el color compartido de borde a la tabla.
- `portal-table--selectable`: permite estilos de celdas específicas, por ejemplo el valor del día en tablas de horario.
- `portal-table-badge--white`: mantiene blanco el texto de un badge dentro de una tabla.

Las tablas de DataTables también reciben el estilo compartido de bordes mediante la clase `dataTable`. Los menús desplegables de acciones en tablas reciben los colores y espacios de `portal-tables.css` de forma global; `data-portal-table-selection="false"` desactiva solo la selección de fila, no esos estilos del menú.

## Variables de estilo compartidas

`portal-tables.css` define variables CSS para ajustar la selección y el espaciado/colores de los menús:

```css
:root {
    --portal-table-selection-bg: var(--bs-primary);
    --portal-table-selection-color: #fff;
    --portal-table-dropdown-gap: 0.5rem;
    --portal-table-dropdown-item-py: 0.625rem;
    --portal-table-dropdown-item-px: 1rem;
    --portal-table-dropdown-light-color: var(--bs-gray-500);
    --portal-table-dropdown-dark-color: #dfe5ef;
    --portal-table-dropdown-dark-disabled-color: #5a6a85;
}
```

Para cambiar un valor en una sola sección, se puede sobrescribir la variable en el contenedor de esa sección en vez de cambiar la variable global. El cambio de selección debe seguir probándose en tema claro y oscuro.

## Ejemplo de uso normal

No agregues `data-portal-table-selection` cuando la tabla deba usar el comportamiento predeterminado:

```html
<table id="tabla-modulo"
       class="table table-striped table-bordered mb-0 text-nowrap align-middle">
    ...
</table>
```

