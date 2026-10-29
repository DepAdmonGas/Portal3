# Loader global para acciones de Portal3

Este documento describe el loader compartido que muestra `logo-empresaMov.gif` cuando una acción del usuario inicia una petición o cambia de vista.

## Archivos y carga

- `public/assets/js/core/request-action-loader.js`: detecta acciones, peticiones y descargas.
- `public/assets/js/loader.min.js`: controla la visibilidad del elemento del loader.
- Los layouts `main.php`, `departamento-operativo.php`, `sasisopa.php`, `sgm.php` y `configuracion.php` incluyen ambos scripts.
- Cada uno de esos layouts incluye el contenedor `.loader-admongas` con la imagen `images/logos/logo-empresaMov.gif`.

El script global tiene una protección para inicializarse una sola vez. Usa un contador de peticiones activas: si hay varias simultáneas, no oculta el loader hasta que termine la última.

## Acciones que detecta

El listener global revisa clics en botones, enlaces, controles de envío y elementos con `role="button"`. El texto se toma de `aria-label`, `title`, el texto visible o `value`; se normalizan espacios, mayúsculas y los símbolos `+`, `✓` y `✔`.

Reconoce estas etiquetas:

- `Eliminar` — también detecta peticiones `DELETE` y rutas con `eliminar`, `delete`, `destroy` o `borrar`.
- `Guardar` y variantes como `Guardar cambios`.
- `Actualizar` y `Actualizar estatus`.
- `Finalizar` y variantes como `Finalizar y firmar`.
- `Firmar` y variantes como `Firmar solicitud`.
- `De acuerdo`.
- `Generar token` y variantes como `Generar token vía Telegram`.
- `Descargar PDF` y `Descargar Excel`.
- `Nuevo` o `Nuevo registro` cuando navegan a otra URL del mismo sitio.

Para las acciones de envío, el click registra la intención y el loader aparece al comenzar la petición correspondiente, no simplemente al pulsar el botón. `fetch` y `XMLHttpRequest` del mismo origen se observan; las mutaciones se reconocen por método y ruta. El loader se oculta al terminar la respuesta, incluso si la petición falla.

### Excepción: envío de comentarios

En todos los modales, offcanvas y secciones de comentarios, el envío debe usar su indicador local **“Enviando mensaje…”** dentro del body. No debe aparecer encima el loader global de AdmonGas.

El loader global omite las peticiones `POST`, `PUT` y `PATCH` del mismo origen cuya ruta contiene `comentario(s)` o `comment(s)`. Esta regla se aplica antes de revisar rutas genéricas como `guardar`, `agregar`, `store` o `update`, por lo que funciona aunque el endpoint tenga alguno de esos nombres. Las rutas claramente destinadas a eliminar comentarios conservan el loader de eliminación.

Al integrar otro comentario, conserva el progreso local dentro del body y usa una ruta que identifique el recurso como comentario. No agregues el overlay global manualmente a `sendComment` ni al botón de enviar.

## Descargas

Los enlaces de descarga PDF/Excel del mismo origen se procesan como `Blob`. El loader permanece activo mientras llega el archivo, se valida la respuesta y se dispara la descarga en el navegador. Para detectar el tipo de archivo, el enlace debe tener el texto `Descargar PDF` o `Descargar Excel`; el nombre puede ponerse en `download` y el servidor puede proporcionar `Content-Disposition`.

```html
<a href="/ruta/reporte.pdf" download="reporte.pdf">Descargar PDF</a>
<a href="/ruta/reporte.xlsx" download="reporte.xlsx">Descargar Excel</a>
```

El indicador se quita cuando el navegador recibe el contenido y se inicia la descarga. La página no puede confirmar cuándo el usuario termina de guardar el archivo en disco.

## Uso en vistas y botones

Usa los textos de acción reconocidos en el control visible. Los enlaces `Nuevo` deben navegar a otra vista del mismo origen. Si abren un modal, el listener los omite intencionalmente. También ignora controles deshabilitados y enlaces que sólo abren un fragmento.

```html
<button type="submit" class="btn btn-success">
    <i class="ti ti-check"></i> Guardar cambios
</button>

<a href="/modulo/nuevo" class="btn btn-primary">
    <i class="ti ti-plus"></i> Nuevo
</a>
```

Los checks, radios y botones que sólo actualizan valores inmediatamente no coinciden con las etiquetas de acción anteriores, así que no muestran el loader global. No les agregues textos de acción reconocidos si su operación debe ocurrir sin loader.

## Peticiones programáticas y exclusión

Las peticiones de mutación cuyo método/ruta coincide con los patrones globales muestran loader automáticamente. No hace falta agregar un loader local en cada módulo.

En `XMLHttpRequest` se puede excluir explícitamente una petición enviando:

```js
xhr.setRequestHeader('X-Portal3-Skip-Action-Loader', '1');
```

La exclusión por header está implementada para `XMLHttpRequest`. La versión actual no lee ese header en `fetch`; para `fetch`, evita que la ruta/método coincidan con un patrón de mutación si esa petición no debe activar el loader.

## Si no aparece

1. Confirma que el layout incluye `loader.min.js` y `core/request-action-loader.js`.
2. Confirma que existe `.loader-admongas` en el HTML.
3. Revisa que la etiqueta visible coincida con una acción reconocida, o que el método/ruta de la petición sea una mutación.
4. En descargas, confirma el texto exacto, que sea una URL del mismo origen y que la respuesta tenga el tipo de contenido esperado.
5. Verifica que el control no esté deshabilitado ni abra un modal.

