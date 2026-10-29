# Badges históricos para solicitudes pendientes de firma

Esta guía explica cómo se calculan y se muestran los contadores de firmas pendientes en tarjetas, sidebar y el panel lateral **Solicitudes pendientes (Firma)**.

## Presentación visual

El estilo compartido del contador es `badge-historico`, un badge circular rojo. En las cards de módulo se usa junto con `badge-solicitud-home`. La vista lo presenta sólo cuando el conteo es mayor que cero; el contenido debe ser el número, sin recalcularlo en el HTML.

```php
<?php if ((int)($pendientesFirmaModulo ?? 0) > 0): ?>
    <span class="badge-historico badge-solicitud-home">
        <?= (int)$pendientesFirmaModulo ?>
    </span>
<?php endif; ?>
```

## Contador en una card

El patrón vigente es:

1. El servicio del módulo calcula pendientes y aplica permisos, estatus, etapa y alcance de estaciones/departamentos.
2. El controlador obtiene el conteo y lo pasa a la vista.
3. La vista muestra el badge sólo si el total es mayor a cero.

Para una card renderizada en `submodulos-index.php`, se identifica el submódulo por su `clave` y se usa un dato específico del controlador, por ejemplo `pendientesFirmaOrdenMantenimiento`. El controlador envía el total tanto desde `renderModulo()` como desde el hub de Mantenimiento cuando corresponda.

No consultes tablas directamente ni incluyas reglas de autorización en la vista. Mantén el conteo en el servicio para que card, listado y acceso a firma respeten el mismo alcance.

## Contador en el sidebar de módulos

`MenuDoController` obtiene los elementos autorizados mediante `MenuDptoOperativoService` y luego llama a `MenuBadgeService::aplicarContadores()`. El servicio resuelve el contador por `clave` y lo entrega como `badge_count`. El layout `departamento-operativo.php` muestra el badge cuando `badge_count > 0`.

Para agregar un contador a otro elemento del sidebar:

1. Confirma la `clave` real del elemento del menú.
2. Añade una rama para esa clave en `MenuBadgeService::contar()`.
3. Devuelve el conteo del servicio dueño de la regla; cuando no hay pendientes, devuelve `0` o `null` según el patrón de la rama existente.
4. No alteres los permisos ni la visibilidad del menú para habilitar un badge.

## Panel lateral «Solicitudes pendientes (Firma)»

El panel no es el mismo contador que una card individual: muestra una lista de solicitudes firmables y un selector por tipo.

- `SolicitudesFirmaDoService` decide quién puede consultarlo, cuáles tipos aparecen y construye la lista pendiente.
- `SolicitudesFirmaDoController::data()` protege el endpoint y responde con `total`, `items` y `tipos`.
- `View::globals()` entrega el acceso inicial y el conteo al layout.
- `layouts/departamento-operativo.php` dibuja el elemento del sidebar y el badge inicial.
- `_solicitudes-firma-do-offcanvas.php` contiene el panel y la tabla.
- `solicitudes-firma-do.init.js` carga el endpoint y actualiza el badge al abrir el panel y al volver a la ventana.
- La ruta de datos es `GET /departamento-operativo/solicitudes-firma/data`.

Al incorporar un tipo de solicitud, revisa conjuntamente `ETIQUETAS`, `RUTAS_FIRMA`, `tipos()`, el cálculo de pendientes y la validación de acceso al destino. La opción del selector debe corresponder a un tipo realmente autorizable; no basta con agregar una etiqueta o una URL.

## Ejemplo actual: Orden de Mantenimiento

El conteo se calcula en `OrdenMantenimientoService::pendingSignatureItems()` y se filtra por los contextos habilitados de `orden-mantenimiento` y por la etapa que le corresponde al usuario:

- Usuario `21`: VO.BO. pendiente, si la orden está en proceso y aún no tiene firma `B`.
- Usuario `19`: autorización pendiente, si la orden está en proceso, ya tiene firma `B` y aún no tiene firma `C`.
- Los demás usuarios no reciben órdenes de este flujo en el contador.

Los mismos elementos alimentan el panel general y `MenuBadgeService`; la card recibe su conteo desde `DptoOperativoController`. Al abrir un elemento del panel, el destino valida nuevamente la etapa y el alcance en el servidor.

## Lista de revisión

- [ ] Se calcula el conteo en un servicio, con estatus y etapa de firma correctos.
- [ ] La consulta está limitada a estaciones/departamentos permitidos.
- [ ] El usuario sólo ve tipos para los que tiene una firma pendiente.
- [ ] El endpoint y la página de firma vuelven a validar permisos en servidor.
- [ ] Card y sidebar usan el mismo cálculo para que sus cifras coincidan.
- [ ] El badge se oculta cuando el conteo es cero.

