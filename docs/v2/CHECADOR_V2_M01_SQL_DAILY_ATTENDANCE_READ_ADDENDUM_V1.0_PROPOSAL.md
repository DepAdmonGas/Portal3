# CHECADOR V2 — M01 SQL DAILY ATTENDANCE READ ADDENDUM V1.0

**Estado:** APROBADO POR EL PROPIETARIO PARA FEDORA Y PREPRODUCCIÓN — 24/11/2026

**Ámbito aprobado:** lectura de marcaciones observadas para Asistencia del día en Portal3, Fedora y preproducción autorizada

**Implementación:** perfil Fedora verificado; perfil de preproducción implementado con controles cerrados, activación condicionada al preflight runtime de conexión, esquema, ACL y SELECT indicado en §8

## Aprobación del propietario

El 24/11/2026 el propietario aprobó expresamente la lectura SQL M01 en Fedora. En una autorización posterior de la misma fecha, aprobó adaptar M01 y preparar su despliegue controlado exclusivamente a preproducción, cuya base objetivo es `bd_admongas_v2_preprod`. El perfil de preproducción acepta `APP_ENV` de preproducción (`preprod`, `preproduction`, `staging`, `prod` o `production`) sólo cuando el driver, la base configurada y `DATABASE()` efectivo coinciden exactamente con el destino aprobado, la identidad SQL/puerto son válidos y los SELECT de preflight sobre las seis tablas autorizadas funcionan. La publicación y activación siguen condicionadas a verificar el runtime PHP-FPM. No se autoriza producción, escrituras, cambios de esquema, grants ni usuarios SQL. Esta conciliación no modifica Architecture Freeze V1.0, J1/J2 ni otros contratos congelados.

## 1. Propósito y conciliación

El propietario aprobó únicamente a M01 para leer por SQL institucional las marcaciones persistidas que correspondan a la localidad y fecha autorizadas en Fedora. En este alcance, Portal3 no invoca API V2 por HTTP para resolver la ventana horaria ni leer estas marcaciones. API V2 conserva la ingesta, validación de integridad, resolución e interpretación de eventos; Lector conserva la captura.

La conciliación aprobada modifica el transporte únicamente para M01 y no reescribe ni altera los documentos congelados:

- `CHECADOR_V2_ARCHITECTURE_FREEZE_V1.0.md` permanece inmutable.
- Concilia el cliente server-to-server conceptual de J1 §19 con lecturas SQL directas limitadas a M01 conforme a la aprobación del propietario del 24/11/2026.
- Concilia J1 §§3–7 y §§10–11: el hecho `PunchEvent` persiste sin cambio; Portal3 lo presenta sin reinterpretarlo.
- Concilia J2 §§7.1–7.2 y §9: una consulta SQL viva no acredita snapshot estable, completitud de ingestión ni ausencia física.
- P3-H §2 sigue siendo autoridad de presentación: Portal3 no empareja, clasifica ni calcula eventos raw.
- M02 SQL Read Addendum V1.0 no se extiende por sí solo a M01; su autorización permanece exclusiva de Comparación con Nómina.

## 2. Fuentes y límite de lectura

Las lecturas implementadas usan exclusivamente la conexión existente `default`, consultas `SELECT` parametrizadas y el esquema canónico preexistente. El preflight local descrito en §8 confirmó las columnas utilizadas. Esta autorización no se extiende a otras conexiones o entornos.

Fuentes previstas:

| Fuente | Uso autorizado |
|---|---|
| `op_rh_v2_marcaciones_crudas` | Hechos raw por ventana UTC: UUID, dispositivo, localidad resuelta, trabajador resuelto, tiempos de ocurrencia/recepción, método, estado de procesamiento y campos de integridad existentes y confirmados. |
| `op_rh_localidades` y `op_rh_v2_localidades_zonas_horarias` | Resolver localidad canónica y zona horaria histórica aplicable a la fecha local para construir la ventana UTC. Debe resultar una única zona válida; no se sustituye por la zona del host. |
| `op_rh_personal` | Resolver el identificador canónico `id` a una etiqueta de trabajador. No se usa número de colaborador como identidad ni se fabrica un perfil. |
| `op_rh_personal_localidad_version` | Validar adscripción efectiva histórica del trabajador para la fecha. La estación actual no sustituye una adscripción histórica ausente o ambigua. |

La consulta revalida sesión, permiso M01 y localidad mediante servicios Portal3; el ID enviado por el navegador no amplía el alcance. El mapeo entre estación física y `op_rh_localidades.id` debe ser existente, único y verificable. El filtro de marcaciones usa el ID de estación física resuelto (`id_estacion_resuelta`), nunca intercambia ese identificador con el ID de localidad canónica. La consulta queda acotada además a la ventana UTC semiabierta `[inicio, fin)` derivada de la zona IANA históricamente vigente para la fecha local. Se excluyen filas sin `ocurrido_at_utc`: `recibido_at_utc` no sustituye el instante del evento para atribuir la fecha laboral. La zona del servidor, `APP_TIMEZONE` y la zona reportada por el dispositivo no sustituyen la fuente histórica. Si el mapeo, la zona o los límites UTC son inexistentes, ambiguos o no verificables, la lectura falla explícitamente.

## 3. Semántica de presentación

1. Una fila leída se presenta sólo como **marcación observada** y conserva su procedencia y estado persistido.
2. El sello/envelope HMAC almacenado, la validez de formato del envelope y el resultado de verificación criptográfica son conceptos diferentes. La presencia de bytes o JSON HMAC persistidos no demuestra que el formato sea válido ni que el sello haya sido verificado. Portal3 no genera ni verifica HMAC. Sólo puede mostrar un estado de formato o verificación cuando sea un resultado autoritativo persistido o expuesto por API bajo contrato; nunca lo deduce de la forma del valor almacenado. La verificación criptográfica corresponde a API V2.
3. `RECEIVED`, `es_governado_nivel2` u otra marca persistida se presentan como valores del origen; Portal3 no afirma completitud L2 ni continuidad de dispositivo.
4. Un evento raw no se convierte en Entrada/Salida ni ocupa slots por orden cronológico. Los eventos observados pueden mostrarse como eventos sin clasificación en el listado/detalle existente. Sólo una proyección P1/P2 persistida y autorizada puede asignar las cuatro posiciones; esta autorización no permite cálculo ni reconstitución local. Si no existe esa proyección, las cuatro posiciones quedan sin dato y no se sustituye el evento raw en ellas.
5. Si la marcación raw ID 8 confirma en SQL `id_estacion_resuelta` de Interlomas, el trabajador resuelto 558 y queda dentro del alcance autorizado y de la ventana UTC, se propone presentarla como evento observado. Esto no incorpora por sí solo al trabajador al padrón ni confirma su adscripción.
6. Un evento sin trabajador resuelto o con identidad no confirmable no se atribuye a una persona. Un evento con identidad confirmada pero sin adscripción histórica acreditada puede mostrarse aparte como “marcación observada; adscripción histórica no acreditada”, siempre que la localidad del evento y el alcance del usuario sí estén verificados. No se agrega ni se inserta al trabajador en el padrón. Sólo con adscripción efectiva verificable se le presenta como integrante del padrón histórico de esa localidad.
7. Cero filas significa únicamente “sin marcaciones observadas en la lectura ejecutada”. No demuestra ausencia física, cobertura completa, descanso, falta ni cero horas. No se genera `FALTA`.
8. Error SQL, mapeo o zona horaria inexistente/ambigua, identidad dudosa, adscripción no verificable y resultado vacío son estados distintos. Un error nunca se transforma en lista vacía.

## 4. Límite de integridad y consistencia

La lectura es `LIVE_NON_SNAPSHOT` salvo que exista y se verifique en el esquema un mecanismo canónico de snapshot estable. La paginación, aunque termine correctamente, no prueba que no hubiera inserciones concurrentes ni que el dispositivo haya sincronizado todo el periodo. M01 no afirmará cobertura completa a partir de un `COUNT`, `MAX(id)`, secuencia aislada o respuesta vacía.

La presencia de una fila raw confirmada permite presentarla como observación persistida; no permite a Portal3 validar su integridad criptográfica ni reemplazar la interpretación del motor API. Si el esquema conserva el sello, su almacenamiento se informa como tal. “Formato válido” sólo se atribuye a una validación autoritativa documentada; la verificación HMAC es responsabilidad de API y Portal3 no la declara a partir de un `SELECT` ni de validaciones locales de forma.

## 5. Límite de acceso SQL M01

La autorización aprobada es una capacidad de lectura M01 delimitada por su propio flujo, no una autorización para debilitar el lector compartido:

- Las comprobaciones de entorno, identidad de base, conexión y alcance que protegen Reportes/M02 u otros consumidores de `ChecadorRawPunchReadService` se conservan intactas.
- El método M01 explícito recibe sólo la localidad ya autorizada por sesión, fecha local, ventana UTC validada y filtros acotados. Ejecuta `SELECT` parametrizados por `default` y no admite IDs de estación/trabajador como autoridad del navegador.
- No se amplían globalmente `authorizedReadConnection()`, los métodos de Reportes ni sus límites ambientales. La implementación debe aislar la política de acceso M01 y probar que M02 conserva sus restricciones.
- El permiso M01 autoriza sólo lectura de los campos listados y confirmados en el preflight; no concede `SELECT *`, acceso a otra localidad ni acceso SQL desde el navegador.

## 6. Exclusiones

Esta autorización no permite:

- escrituras, DDL, grants, cambios de API o Lector;
- fallback HTTP hacia API, o nuevas llamadas Portal3 → API;
- emparejar marcaciones, calcular horarios, horas o jornadas;
- inferir `FALTA`, `SIN MARCACIÓN`, asistencia completa o resultados económicos;
- exponer datos de otra localidad o usar el padrón actual como padrón histórico;
- lectura o despliegue en producción; preproducción sólo dentro de la aprobación del 24/11/2026 y después de satisfacer su preflight específico.

## 7. Implementación autorizada y archivos

- `app/Services/ChecadorDailyAttendanceSqlReadService.php`: lector exclusivo M01 para `default`, con perfiles de destino explícitos Fedora/preproducción, identidad efectiva comprobada, SELECT de privilegios sobre las tablas M01, mapeo canónico/estación única, zona histórica y SELECT parametrizados. No amplía `ChecadorRawPunchReadService`. El perfil de preproducción está implementado; su conexión y privilegios reales de PHP-FPM siguen sin verificarse.
- `app/Controllers/ChecadorController.php`: el flujo operativo M01 sustituye la lectura HTTP de zona horaria y marcaciones por el lector SQL; no solicita ni genera proyecciones no disponibles por contrato. Los eventos raw no ocupan slots sin una proyección persistida autorizada.
- `app/Views/departamento-operativo/2-recursos-humanos/checador/daily-attendance.php`: ajuste PHP acotado para separar eventos observados de personas no incluidas en el padrón acreditado; se conserva el diseño actual y no se modifica CSS ni JavaScript.
- pruebas focalizadas M01 con fixtures aislados para lectura positiva, mapeo donde los IDs canónico y físico difieren, vacío, error SQL, ACL/localidad, identidad sin membresía y ausencia de clasificación de eventos raw.

No se modificó CSS ni JavaScript. La lectura SQL local y la ausencia local de raw ID 8/trabajador 558 se comprobaron en §8. El propietario reporta que ambos registros existen en `bd_admongas_v2_preprod`; todavía no se ha comprobado su lectura mediante la cuenta efectiva PHP-FPM.

## 8. Preflight y alcance de entorno

La aprobación del 24/11/2026 cubre la lectura SQL M01 descrita en §§1–6 exclusivamente en Fedora. El preflight local de sólo lectura siguiente no acredita preproducción ni producción:

| Verificación | Fedora (`portal3.test`) | Plesk |
|---|---|---|
| Conexión efectiva | Verificado por lectura: `default` conecta a `bd_admongas`, servidor `fedora`, puerto 3306; principal `dev@localhost`. La causa previa `HY000/2002` fue el aislamiento del proceso de inspección al socket; el servicio y la conexión normal del entorno Fedora responden. | Preproducción autorizada para `bd_admongas_v2_preprod`, pero identidad efectiva de PHP-FPM, principal, host y puerto pendientes de comprobación. No usar la cuenta de phpMyAdmin como sustituto. |
| Estructura | Verificadas por `SHOW COLUMNS`/`SHOW CREATE TABLE` las tablas citadas, columnas e índices requeridos; todas las tablas fuente examinadas son InnoDB. No se requiere ni se ejecuta DDL. | Pendiente de comprobar desde la conexión efectiva `default`; no se reutiliza evidencia Fedora. |
| Permisos | SELECT funciona sobre las fuentes requeridas. El principal local también tiene privilegios amplios preexistentes; no se cambian. Este lector sólo ejecuta SELECT. | Pendiente `SHOW GRANTS`/SELECT con la cuenta PHP-FPM efectiva; no se modifican grants. |
| Localidad/tiempo | Interlomas tiene localidad canónica 1, mapeo único a estación física 1 por `numlista`, estación activa y zona histórica `America/Mexico_City` para 2026-11-23. Ventana UTC: `[2026-11-23 06:00:00, 2026-11-24 06:00:00)`. La consulta usa el ID físico resuelto para filtrar las filas raw. | No verificado. Requiere autorización y preflight independiente. |
| Marcación | En `bd_admongas`, `SELECT` por ID confirma que raw ID 8 no existe. No se fabrica ni se atribuye el evento a Fedora. | El propietario reporta raw ID 8 en preproducción, Interlomas, dispositivo 2, fecha laboral 2026-11-23 y secuencia L2 6. La visibilidad con PHP-FPM sigue pendiente. |
| Trabajador | En `bd_admongas`, `SELECT` confirma que `op_rh_personal.id=558` no existe. No se inventa una adscripción local. | El propietario reporta al trabajador 558 y su adscripción en preproducción. La lectura y adscripción temporal aún deben verificarse mediante SQL del runtime autorizado. |

La aprobación cubre M01 en Fedora y autoriza preproducción sólo con el preflight arriba pendiente. La lectura permanece cerrada si el destino efectivo no coincide exactamente con el esquema aprobado, falta SELECT o no se valida el mapeo, identidad, zona y adscripción. No se crearán columnas ni se inferirá identidad, integridad o membresía. Producción no está autorizada.
