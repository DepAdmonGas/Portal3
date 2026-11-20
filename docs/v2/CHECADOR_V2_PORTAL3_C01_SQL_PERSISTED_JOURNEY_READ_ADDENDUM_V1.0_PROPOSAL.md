# CHECADOR V2 — PORTAL3 C01 SQL PERSISTED JOURNEY READ ADDENDUM V1.0

**Estado:** APROBADO POR EL PROPIETARIO PARA LECTURA SQL C01
**Ámbito:** lectura de resultados de Jornada ya persistidos por API V2
**Repositorio consumidor:** Portal3
**Entorno revisado:** MariaDB local `bd_admongas`
**Escrituras:** ninguna
**Implementación autorizada:** SÍ, únicamente el lector SQL C01 en Portal3/Fedora según este addendum
**Aprobación registrada:** 2026-11-20, instrucción expresa del propietario

## 1. Decisión aprobada

Autorizar a Portal3 a consultar, mediante la conexión institucional MariaDB `default`, las proyecciones persistidas de Jornada, pareo y cálculo enumeradas en este addendum. API V2 conserva la propiedad de ingesta, integridad, resolución, emparejamiento, cálculo y persistencia de esos resultados. Lector conserva la captura de marcaciones.

Portal3 será un consumidor de lectura: no llamará API V2 por HTTP para este flujo, no escribirá en las tablas citadas, no materializará resultados, no emparejará marcaciones, no calculará tiempos, no completará estados y no generará FALTAS. El navegador seguirá comunicándose únicamente con el backend Portal3.

Este alcance autoriza sólo el lector C01. No autoriza escrituras, cambios de esquema, grants, API, Lector, contratos congelados, Plesk ni producción. Las autorizaciones de producción y los permisos SQL efectivos se revisarán por separado.

## 2. Conciliación documental limitada

Los siguientes documentos permanecen inalterados y `FROZEN`:

1. `docs/architecture/CHECADOR_V2_ARCHITECTURE_FREEZE_V1.0.md`.
2. `docs/architecture/CHECADOR_V2_PORTAL3_JOURNEY_INCIDENTS_FREEZE_V1.0.md` (J1), cuyo encabezado declara `IMPLEMENTATION_AUTHORIZED: NO` y cuyo §1 excluye autorización de runtime.
3. `docs/architecture/CHECADOR_V2_PORTAL3_SCHEDULE_COMPLETENESS_FREEZE_J2_V1.0.md` (J2), cuyo §14 niega autorización de cambios.

La autorización específica y limitada de este addendum prevalece únicamente para la lectura SQL C01 descrita aquí. No cambia otros límites ni autoriza los slices de J3–J8.

La conciliación necesaria se limita a J2 §13, paso 5 (J4), que recomienda un cliente API Portal3 para consumir completitud: para C01 aprobado, el transporte se sustituye por lectura SQL de las proyecciones existentes. J1 §1 y sus declaraciones de no autorización se mantienen; se les agrega una excepción explícita únicamente para este lector aprobado. El Freeze V1.0 no se reescribe ni se interpreta como autorización general de SQL.

Este addendum también implementa la directiva del propietario del 18/11/2026 sobre Portal3 → MariaDB y la prohibición de Portal3 → API V2 por HTTP, sin ampliar la autoridad de Portal3 sobre resultados calculados por API.

## 3. Esquema físico verificado

Se inspeccionaron con `SHOW CREATE TABLE`, `SHOW COLUMNS` y `SELECT` de sólo lectura las tablas existentes en `bd_admongas`. Los nombres y columnas siguientes son los hallados en ese esquema; no se proponen tablas ni columnas nuevas.

| Tabla | Columnas relevantes para C01 | Uso permitido |
|---|---|---|
| `op_rh_personal` | `id`, `nombre_completo`, `estado` | Join por `id` para identidad canónica y etiqueta visible; proyectar sólo los campos necesarios. |
| `op_rh_v2_jornadas_programadas` | `id`, `id_personal`, `id_estacion`, `fecha_jornada_local`, `id_asignacion_origen`, `id_plantilla_origen`, `tipo_jornada`, `scheduled_start_utc`, `scheduled_end_utc`, `timezone_local`, `payload_segmentos_json`, `creado_at_utc`, `actualizado_at_utc` | Fila de jornada y snapshot de horario persistidos. Único por trabajador, localidad y fecha local. |
| `op_rh_v2_pareos_jornada` | `id`, `id_jornada_programada`, `id_personal`, `id_estacion`, `fecha_jornada_local`, `revision_numero`, `version_algoritmo`, `estado_completitud_l2`, `estado_pareo`, `fingerprint_ejecucion`, `supercedido_por_id`, `creado_at_utc` | Revisiones P1 y estado de pareo/completitud persistidos. Único por jornada y revisión. |
| `op_rh_v2_pareos_segmentos` | `id`, `id_pareo_jornada`, `ordinal_segmento`, `id_marcacion_entrada`, `id_marcacion_salida`, `estado_segmento`, `creado_at_utc` | Segmentos P1 y sus referencias a marcaciones. |
| `op_rh_v2_pareos_marcaciones_asociadas` | `id`, `id_pareo_jornada`, `id_pareo_segmento`, `id_marcacion_cruda`, `rol_marcacion`, `creado_at_utc` | Asociación persistida entre P1, segmento y marcación, con su rol. |
| `op_rh_v2_calculos_jornada` | `id`, `id_jornada_programada`, `id_pareo_jornada`, `id_personal`, `id_estacion`, `fecha_jornada_local`, `revision_numero`, `version_algoritmo_calculo`, `estado_calculo`, `segundos_programados_totales`, `segundos_trabajados_totales`, `segundos_candidatos_fuera_horario`, `fingerprint_ejecucion`, `creado_at_utc` | Revisiones P2 persistidas y totales calculados por API. Únicos por jornada/revisión y fingerprint. |
| `op_rh_v2_calculos_segmentos` | `id`, `id_calculo_jornada`, `id_pareo_segmento`, `ordinal_segmento`, `tipo_segmento`, `estado_segmento`, `segundos_programados`, `segundos_trabajados`, `delta_llegada_segundos`, `delta_salida_segundos`, `segundos_candidatos_fuera_horario`, `creado_at_utc` | Componentes P2 persistidos por segmento. |
| `op_rh_v2_marcaciones_crudas` | `id`, `event_uuid`, `id_dispositivo`, `id_estacion_resuelta`, `id_personal_resuelto`, `ocurrido_at_utc`, `recibido_at_utc`, `metodo_captura`, `processing_status` | Trazabilidad acotada de eventos enlazados desde pareos. No es fuente para reconstruir una Jornada. |
| `op_rh_personal_localidad_version` | `id`, `worker_id`, `workplace_id`, `valid_from_utc`, `valid_to_utc`, `recorded_at_utc`, `recorded_by`, `reason`, `supersedes_id` | Historial de adscripción canónica, leído con vigencia y perspectiva de registro. |
| `op_rh_v2_localidades_zonas_horarias` | `id`, `id_localidad`, `timezone_iana`, `valido_desde`, `valido_hasta`, `registrado_at_utc` | Catálogo local de zonas horarias disponible; C01 no lo usará para recalcular snapshots de jornada. |
| `op_rh_v2_turnos_asignaciones` | `id`, `id_personal`, `id_estacion`, `id_plantilla`, `tipo_asignacion`, `dia_semana`, `valido_desde`, `valido_hasta`, `es_activo`, `id_cobertura_version` | Verificación referencial de la asignación origen cuando la jornada la contiene. No sustituye el snapshot almacenado. |

Las tablas de jornadas, pareos y cálculos son InnoDB y tienen índices únicos de identidad/revisión. Las relaciones declaradas en el DDL incluyen jornada→asignación/plantilla, P1→jornada, segmentos→P1/marcación y P2→jornada/P1. La columna `supercedido_por_id` no tiene FK declarada. Varias tablas no tienen FK directa a `op_rh_personal` o `op_rh_localidades`; C01 debe validar esas identidades por joins y coincidencia exacta, no inferir integridad que el DDL no garantiza.

El DDL revisado de `op_rh_v2_jornadas_programadas` contiene el horario efectivo como snapshot (`tipo_jornada`, límites UTC, timezone y JSON de segmentos). C01 no debe buscar otro horario vigente ni reconstruir ese snapshot con las tablas de M03/M04.

## 4. Identidad, autorización y alcance histórico

1. La identidad de trabajador es `op_rh_personal.id`; en las proyecciones se toma de `id_personal`. No usar número de colaborador, referencia externa, ID de usuario Portal3 ni nombre como identidad.
2. El workplace solicitado es el `op_rh_localidades.id` canónico. Antes de consultar, el controlador debe validar sesión, permiso efectivo de lectura Checador y alcance de localidad mediante los servicios Portal3 vigentes. La consulta SQL recibe sólo ese ID autorizado.
3. Toda fila devuelta debe satisfacer `j.id_estacion = :workplace_id`, y las claves de P1/P2 enlazadas deben coincidir con la misma jornada, trabajador, localidad y `fecha_jornada_local`. Cualquier discordancia se trata como dato inválido/no verificable y no se presenta como resultado confirmado.
4. La adscripción histórica se valida para el trabajador/localidad solicitados usando `op_rh_personal_localidad_version`, con vigencia UTC semiabierta `[valid_from_utc, valid_to_utc)`. Para una consulta C01, capturar una sola hora UTC al inicio y usarla como `recorded_as_of_utc` para todas las lecturas de esa solicitud. Esto significa “adscripción válida para la fecha laboral según lo registrado al momento de consultar”; no afirma qué adscripción conocía el motor cuando calculó la Jornada, porque el esquema no vincula esa revisión con una referencia de adscripción. No usar la adscripción actual como sustituto de vigencia ni `op_rh_personal.id_estacion` como fallback.
5. Si no existe una adscripción histórica única y verificable, la fila queda como no confirmada/no disponible; no se reasigna a otra localidad ni se filtra hacia el catálogo de otra estación.
6. Si `id_asignacion_origen` no es NULL, validar que la asignación referida corresponde al mismo trabajador y localidad y cubre la fecha local (`valido_desde` inclusivo, `valido_hasta` exclusivo). NULL no se transforma en una asignación inventada; el tipo de jornada persistido sigue siendo la autoridad para presentar el snapshot.

La consulta de listado debe estar acotada por localidad y fecha local; el filtro opcional de trabajador también usa el ID canónico. Usar consultas parametrizadas y límites de rango/paginación. La autorización no se delega a datos de la URL ni al navegador.

## 5. Fecha laboral, zona horaria y horario efectivo

- `fecha_jornada_local` es la fecha civil laboral autoritativa. No calcularla como `DATE(ocurrido_at_utc)` ni cambiarla según la zona horaria del servidor o del navegador.
- `timezone_local`, `scheduled_start_utc`, `scheduled_end_utc` y `payload_segmentos_json` se presentan como snapshot almacenado por el proceso propietario. Validar que la zona sea una IANA reconocida antes de formatear; si no, mostrar el valor persistido sin convertir o marcarlo no disponible. No usar `APP_TIMEZONE` ni la zona del host como sustituto.
- Mostrar `tipo_jornada` sólo mediante un mapeo aprobado. Los valores desconocidos se muestran como estado no reconocido/no disponible. No inferir `DAY_OFF`, `UNSCHEDULED` o falta a partir de la ausencia de una fila.
- JSON inválido o campos faltantes se muestran como detalle no disponible; no se corrigen ni se completan en Portal3.

## 6. Revisiones P1/P2 y selección vigente

1. P1 y P2 son resultados calculados por API V2; Portal3 sólo los lee.
2. P1 se consulta por `id_jornada_programada`. La revisión candidata actual es la de mayor `revision_numero` que tenga `supercedido_por_id IS NULL`. Debe existir exactamente una. Si hay varias, ninguna, una cadena rota o un puntero que no resuelva a una revisión posterior de la misma jornada, el pareo queda no verificable y no se elige una revisión por conveniencia.
3. Se valida la coincidencia exacta de `id_personal`, `id_estacion` y `fecha_jornada_local` entre jornada y P1. Los segmentos y asociaciones se leen únicamente para ese P1.
4. P2 se selecciona sólo si `id_pareo_jornada` apunta al P1 actual, y coinciden jornada, trabajador, localidad y fecha. Entre resultados válidos se aplica el mayor `revision_numero` de esa jornada conforme al índice único. Una revisión P2 ligada a un P1 supercedido no se combina con la revisión vigente.
5. El historial conserva todas las revisiones disponibles en modo lectura, ordenadas por revisión y `creado_at_utc`; no se actualiza ni elimina ninguna fila.
6. `estado_completitud_l2`, `estado_pareo`, `estado_calculo`, las versiones de algoritmo, fingerprints y timestamps se presentan desde sus columnas persistidas. `NULL` en segundos o referencia significa no disponible, nunca cero.

El esquema de P1 no tiene columnas `evaluation_ref` ni `source_completeness_ref` que vinculen directamente la revisión con la evaluación de Nivel 2. Por ello, C01 no puede afirmar que verificó la evaluación/watermark de origen ni obtener completitud independiente enlazando sólo por localidad/fecha. Esta regla limita la presentación al estado persistido en P1 y lo marca no verificable cuando no basta. No se añade ni se inventa una relación SQL.

## 7. Pareos, cálculos y trazabilidad

- Pareos, cálculos, segmentos y asociaciones se presentan en los términos y estados ya persistidos. Portal3 no vuelve a emparejar, seleccionar, descartar o clasificar eventos.
- Una asociación puede enlazar con `op_rh_v2_marcaciones_crudas.id`. Para mostrarla, C01 sólo proyecta UUID público, hora `ocurrido_at_utc`, dispositivo, método, estado de procesamiento y rol persistido. La unión exige que la marcación resuelta pertenezca al trabajador y estación de la Jornada; si no coincide, se marca como no verificable y no se revela.
- No exponer `referencia_externa_personal`, `source_timestamp_raw`, `external_event_id`, `metadata_json`, `validation_flags`, `integrity_key_id`, `integrity_seal` ni secretos de transporte.
- La trazabilidad presenta IDs internos sólo cuando el usuario tiene permiso y son necesarios para soporte; los fingerprints pueden abreviarse como referencia, sin utilizarlos como prueba de completitud.
- `creado_at_utc`, `version_algoritmo`, `version_algoritmo_calculo`, revisión, estado y vínculos de segmentos son procedencia legible. La auditoría no autoriza a reconstruir las reglas del motor.

## 8. Resultados ausentes, incompletos y errores

El lector debe distinguir al menos:

- consulta correcta con filas;
- consulta correcta sin filas persistidas;
- fila persistida con jornada no programada, descanso u otro estado reconocido;
- pareo/cálculo incompleto, desconocido, nulo o inconsistente;
- identidad/adscripción no verificable;
- falta de autorización;
- error SQL o de conexión.

Sin fila de jornada no significa ausencia laboral, descanso, falta ni «sin marcación». `NULL` en totales no significa cero. No se genera `FALTA` ni se alteran datos. Los errores SQL no se convierten en estado vacío.

## 9. Contrato de consulta autorizado

El endpoint/controlador Portal3 C01 (sin ruta SQL desde navegador) acepta una localidad canónica autorizada, rango de fechas locales acotado, filtro opcional de trabajador canónico y paginación limitada. Ejecuta sólo `SELECT` sobre las tablas existentes indicadas, mediante `default` y parámetros preparados. Todas las lecturas de snapshot, P1/P2, segmentos, asociaciones, adscripción e identidad se mantienen dentro del mismo alcance de localidad.

La respuesta del servicio conserva separadamente el estado de consulta y el estado persistido; no convierte error, permiso denegado, dato incompleto ni falta de fila en otro estado. No se llama a API V2 por HTTP y no hay fallback a marcaciones crudas para calcular una Jornada.

## 10. Evidencia de esquema y límites observados

La inspección de sólo lectura en `bd_admongas` encontró:

- `op_rh_v2_jornadas_programadas`: 0 filas.
- `op_rh_v2_pareos_jornada`: 0 filas.
- `op_rh_v2_pareos_segmentos`: 0 filas.
- `op_rh_v2_pareos_marcaciones_asociadas`: 0 filas.
- `op_rh_v2_calculos_jornada`: 0 filas.
- `op_rh_v2_calculos_segmentos`: 0 filas.
- `op_rh_v2_marcaciones_crudas`: 89 filas.
- `op_rh_personal_localidad_version`: 87 filas.
- `op_rh_v2_localidades_zonas_horarias`: 9 filas.

Por lo tanto, el DDL y las uniones pueden especificarse, pero en esta base no hay resultados persistidos para demostrar una lectura funcional real. Las 89 marcaciones crudas no autorizan a reconstruir jornadas. Además, el esquema consultado no contiene `op_rh_personal_horario_version` ni `op_rh_localidad_timezone_version` bajo esos nombres; C01 no puede usarlas para re-resolver retrospectivamente el horario o la zona. El snapshot de jornada guardado evita ese cálculo local; la ausencia de referencias directas a evaluación L2 limita la validación de completitud señalada en §6.

## 11. Aprobación y límites vigentes

El propietario aprobó expresamente C01 V1.0 el 2026-11-20 para lectura SQL de jornadas persistidas, conforme a las secciones 1–10 de este documento. La aprobación comprende la selección P1/P2 de §6, la presentación de L2 únicamente como estado persistido no revalidado y la proyección limitada de trazabilidad de §7.

Esta aprobación es la excepción limitada registrada para J1 §1 y J2 §13, paso 5 (J4); no modifica esos documentos ni `CHECADOR_V2_ARCHITECTURE_FREEZE_V1.0.md`. No autoriza escrituras, cambios de esquema/grants, API, Lector, Plesk ni producción. Cualquier activación fuera de Fedora requiere autorización y preflight separados.
