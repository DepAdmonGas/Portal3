# M09 — Addendum de lectura local, dispositivos y clasificación J1

**Autorización:** propietario, desarrollo local en Fedora, 26/11/2026. No habilita preproducción o producción ni modifica Architecture Freeze o J1/J2.

Portal3 puede consultar por `default`, exclusivamente mediante SELECT y bajo ACL y localidad autorizadas, la zona horaria institucional históricamente vigente para el instante de captura, los dispositivos vinculados a la localidad y las asociaciones J1 persistidas que enlazan una marcación con una revisión vigente inequívoca. Se conservan el instante UTC, la procedencia y la fecha de recepción; el resultado local representa la fecha civil de captura, no la fecha laboral.

Los filtros de fechas locales deben resolverse como intervalos UTC semia­biertos usando las versiones aplicables, rechazando configuraciones ausentes, solapadas o ambiguas. El filtro por dispositivo debe limitarse al ámbito de la localidad y aplicarse idénticamente a conteos, páginas y CSV. Las asociaciones J1 deben validar marcación, trabajador, localidad, jornada, segmento y cadena de revisión; una asociación inexistente, antigua, contradictoria o no verificable conserva un resultado neutral. Portal3 no calcula pares, inferencias, jornadas o integridad, no modifica marcaciones ni invoca API/J1/J2.

La consulta y la exportación deben mantener controles independientes de acceso y descarga. La compatibilidad del esquema y de los permisos SELECT efectivos, así como la prueba del runtime local, deben verificarse antes de declarar el módulo validado. Este addendum no modifica los límites de destino de conexión previamente aprobados.
