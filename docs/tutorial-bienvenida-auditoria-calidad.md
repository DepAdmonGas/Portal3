# Auditoría de calidad del tutorial de bienvenida

## Hallazgos comunes

- Las animaciones anteriores tienen 1200 × 605 píxeles, salvo estatus (1200 × 575) y checador (1200 × 600). El GIF limita cada fotograma a una paleta de 256 colores; el WebP anterior evitó parte de esa limitación, pero conservó los fotogramas ya reducidos.
- En el modal a pantalla completa, el panel visual ocupa aproximadamente el 65 % del ancho. En una pantalla de 1920 píxeles, tras los márgenes, se muestra alrededor de 1200 píxeles de ancho; la altura está limitada a `70vh`. La pantalla y el factor de escala del sistema pueden modificar esas cifras.
- Un acercamiento de 2.5× sobre una grabación de 1723 píxeles usa unos 689 píxeles del original para llenar el panel. Ningún formato recupera los detalles que no se capturaron en esa región. Para mejorar la legibilidad es necesario limitar el acercamiento o volver a capturar con los controles más grandes.
- Las grabaciones son VP8 con submuestreo de color 4:2:0; su compresión y el texto pequeño ya forman parte de la fuente. Las capturas PNG no tienen esa pérdida. Las máscaras de datos personales son intencionales.
- Las versiones reconstruidas se codifican en WebP sin pérdida desde el material recuperado, sin convertir primero a GIF. Esto evita una pérdida adicional, pero no elimina los defectos de la grabación original. Los archivos son más pesados y se cargan por paso.

## Revisión por recorrido

| Recorrido | Fuente disponible y límite observado | Estado |
| --- | --- | --- |
| Navegación | Se usa la imagen de bienvenida de 3750 × 1890; sus acercamientos muestran fragmentos del menú que ya están rasterizados. | Reconstruida con la imagen de bienvenida y un acercamiento más moderado; una captura nueva con el menú más grande permitiría recuperar más detalle. |
| Componentes | Imagen de bienvenida de 3750 × 1890; las tarjetas se muestran mediante recortes de esa misma imagen. | Reconstruida con la captura de bienvenida y un acercamiento más moderado; el detalle sigue limitado por la captura original. |
| Periodo | Capturas PNG de las vistas y los menús proporcionadas para reconstruir el recorrido; los indicadores de carga proceden del video original. | Reconstruida con las capturas, acercamientos moderados y las cargas reales; la definición de los menús sigue limitada por sus imágenes de origen. |
| Opciones de contexto | Capturas PNG de la tabla y el menú proporcionadas para reconstruir el recorrido. | Reconstruida desde las capturas con acercamiento moderado y recuadro azul para cada opción; el detalle del menú sigue limitado por la resolución de su imagen. |
| Alertas | Capturas PNG de la tabla, la confirmación y los mensajes de éxito proporcionadas para reconstruir el recorrido; el cargador procede de la grabación original. | Reconstruida con acercamientos moderados y el cargador real; el detalle sigue limitado por la resolución de las imágenes de origen. |
| Búsqueda en tablas | Capturas PNG de la tabla inicial y los resultados filtrados proporcionadas para reconstruir el recorrido. | Reconstruida con las capturas, escritura progresiva y acercamiento moderado; el nombre del usuario permanece desenfocado. |
| Estatus | PNG de 1865 × 894. El acercamiento anterior alcanzaba 2.35×. | Reconstruida desde el PNG con acercamiento menor. |
| Tema oscuro | Video VP8 de 1723 × 869. El acercamiento anterior alcanzaba 2.7×. | Reconstruida desde la fuente con acercamiento menor; el icono sigue limitado por el tamaño de la grabación. |
| Checador | PNG de 1867 × 933. La edición anterior se redujo a 1200 × 600. | Reconstruida directamente desde el PNG. |
| Contexto multiestación | Capturas PNG de la selección inicial y de los contextos Palo Solo y Autolavado. | Reconstruida con las capturas y acercamientos moderados; se ocultan el usuario y el contenido de cada plantilla, conservando sus títulos. |
| Todas las estaciones | Video VP8 de 2694 × 1358, reducido antes a 1200 × 605; el acercamiento anterior alcanzaba 2.8×. | Reconstruida desde el video con acercamiento menor y máscaras de datos. |
| Opciones de cuenta | Video VP8 de 2099 × 1058, reducido antes a 1200 × 605. | Reconstruida desde el video, con las máscaras existentes y un acercamiento menor. |
| Datos de perfil | Video VP8 de 2099 × 1058, reducido antes a 1200 × 605. | Reconstruida desde el video, manteniendo los recortes de tiempo y las máscaras. |
| Telegram | Video VP8 de 2099 × 1058, reducido antes a 1200 × 605. | Reconstruida desde el video con el segmento acelerado y las máscaras. |
| Firma | Video VP8 de 2099 × 1058, reducido antes a 1200 × 605. | Reconstruida desde el video con el segmento acelerado y las máscaras. |

## Recomendaciones para futuras capturas

Para recuperar más detalle se pueden proporcionar capturas PNG o grabaciones nuevas, preferentemente sin pérdida o con compresión de alta calidad, tomadas con un tamaño de navegador que haga legibles los elementos que se van a ampliar. La operación de eliminación debe realizarse únicamente con un registro de demostración y en un entorno autorizado. Deben conservarse las máscaras para datos personales.
