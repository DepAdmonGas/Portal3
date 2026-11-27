# CHECADOR V2 — PORTAL3 M02 SQL PAYROLL COMPARISON READ ADDENDUM V1.0

**Estado:** APROBADO POR EL PROPIETARIO PARA DESARROLLO LOCAL FEDORA
**Aprobación registrada:** 2026-11-20
**Ámbito:** lectura SQL de comparación individual con Nómina en Portal3/Fedora
**Base autorizada:** `bd_admongas`, conexión institucional `default`
**Escrituras, evaluaciones nuevas y efectos económicos:** NO AUTORIZADOS
**Plesk/producción:** NO AUTORIZADOS

## 1. Decisión y límite

El propietario aprueba expresamente para desarrollo local las consultas `SELECT` parametrizadas de zona horaria histórica, jornadas persistidas, incidencias, evaluaciones administrativas existentes, ajustes vinculados y marcaciones crudas del trabajador, localidad y periodo autorizados.

Portal3 no se comunica por HTTP con API V2 para esta comparación. API V2 conserva la propiedad de ingesta, interpretación, cálculo, evaluación y persistencia. Lector conserva la captura. Portal3 no calcula jornadas, pareos, horas, FALTAS, descuentos ni importes, y no modifica Nómina.

## 2. Conciliación contractual

Este addendum establece una excepción únicamente para la lectura de Comparación con Nómina y no modifica `CHECADOR_V2_ARCHITECTURE_FREEZE_V1.0.md`, J1 ni J2.

- Para M02, sustituye el transporte descrito en P3-G §12.1 por lecturas SQL de las proyecciones autorizadas aquí. Ningún otro módulo hereda esta excepción.
- Extiende C01 únicamente para que M02 consuma sus lecturas persistidas ya autorizadas; no autoriza cálculo ni resolución de jornadas desde M03/M04.
- Extiende los alcances M06 y Ajustes/M07 únicamente para lectura en M02 de incidencias, evaluaciones y ajustes relacionados.
- La autorización operativa se limita a Fedora. Requiere comprobar base `bd_admongas`, conexión `default`, ACL y permisos efectivos antes de usar cada fuente.
- Plesk requiere autorización expresa separada y preflight de su propio runtime, esquema, principal SQL y ACL. Este addendum no autoriza despliegue ni lectura en producción.

## 3. Fuentes autorizadas para M02

| Fuente | Proyección autorizada |
|---|---|
| `op_rh_v2_localidades_zonas_horarias` | `id_localidad`, `timezone_iana`, `valido_desde`, `valido_hasta`, `registrado_at_utc`; debe resolver una única zona aplicable a cada fecha local. |
| `op_rh_v2_jornadas_programadas` | Snapshot C01 de `id_personal`, `id_estacion`, `fecha_jornada_local`, `tipo_jornada`, `scheduled_start_utc`, `scheduled_end_utc`, `timezone_local` y `payload_segmentos_json`. Sin fila no se re-resuelve un horario usando asignaciones M03 o plantillas M04. |
| P1/P2 C01 | Revisiones, estados, segmentos y asociaciones persistidos seleccionados conforme al addendum C01. Portal3 los presenta; no recalcula ni completa resultados. |
| `op_rh_v2_incidencias_maestro`, `op_rh_v2_incidencias_revisiones` | Incidencia, `id_localidad`, trabajador, categoría, alcance, periodo, estado/revisión persistidos y datos de historial que expone el lector M06. No aplica la incidencia a una jornada. |
| `op_rh_v2_evaluaciones_administrativas` | Evaluaciones existentes, sólo cuando identidad, localidad, fecha, jornada y cálculo relacionados coincidan exactamente. Se muestran revisión, versiones y estados persistidos; no se ejecuta evaluación. |
| `op_rh_v2_ajustes_administrativos`, `op_rh_v2_evaluaciones_ajustes` | Ajustes y referencias de evaluación sólo cuando el vínculo persistido y la cadena de revisiones son verificables conforme al lector M07. |
| `op_rh_v2_marcaciones_crudas` | Eventos observados del trabajador y localidad autorizados dentro de las ventanas UTC que corresponden a las fechas locales consultadas. Se proyectan UUID de evento, dispositivo, `ocurrido_at_utc`, `recibido_at_utc`, método y estado de procesamiento. Eventos sin jornada asociada pueden mostrarse sólo como eventos observados. No se exponen metadatos ni referencias externas. |
| `op_rh_personal`, `op_rh_localidades`, `op_rh_personal_localidad_version` | Identidad canónica (`op_rh_personal.id`), etiqueta, localidad canónica y adscripción histórica requerida para validar cada fecha. No se usa la localidad actual como sustituto. |

Se reutilizan `ChecadorWeeklyScheduleSqlReadService`, `ChecadorJourneySqlReadService`, `ChecadorAdministrativeIncidenceSqlService`, `ChecadorAdjustmentSqlReadService`, `ChecadorRawPunchReadService` y los resolutores existentes de identidad/adscripción. Cualquier campo fuera de las proyecciones anteriores requiere revisión y autorización antes de exponerse.

## 4. Autorización, periodo y consistencia

1. El usuario debe contar con permiso efectivo de lectura tanto en Checador como en Nómina; las localidades consultables son la intersección de ambos alcances, revalidada en cada solicitud.
2. El trabajador se valida por `op_rh_personal.id`. Toda fila debe coincidir con el trabajador y `op_rh_localidades.id` solicitado; parámetros del navegador son filtros no confiables.
3. La adscripción se resuelve por fecha efectiva local usando la zona horaria persistida aplicable y una referencia `recorded_as_of_utc` estable durante la consulta. Adscripción ausente, ambigua o incompatible excluye el dato como no verificable.
4. El periodo queda limitado a los periodos Nómina válidos y a un máximo de 16 fechas. Las consultas usan `default`, límites acotados y parámetros preparados.
5. C01 conserva la selección coherente P1/P2. El horario se muestra únicamente desde el snapshot de la Jornada. Marcaciones crudas no se ordenan ni clasifican como entrada o salida.
6. Evaluaciones se presentan únicamente desde filas persistidas y vínculos canónicos. Los ajustes se presentan sólo cuando su relación persistida con la evaluación sea verificable.

## 5. Estados y prohibiciones

Cada fuente distingue: filas disponibles, consulta válida sin filas, datos incompletos/inconsistentes, denegación de ACL y fuente/error SQL no disponible. La ausencia de una fila nunca equivale a descanso, falta, cero horas ni inexistencia fuera de la consulta ejecutada. Una marcación observada no confirma que el periodo esté completo.

Se prohíbe en M02: cualquier escritura; crear evaluaciones; inferir `FALTA`; clasificar marcaciones; recalcular jornada, pareos u horas; calcular consecuencias económicas; modificar Nómina; llamar API V2 por HTTP o recurrir a legacy como fallback.

## 6. Aprobación y entorno

**Aprobación del propietario (2026-11-20):** SQL read-only de las fuentes descritas en M02, exclusivamente Fedora/desarrollo local, sujeto a preflight de esquema, conexión `default`, ACL y permisos efectivos. Se autoriza lectura de marcaciones no vinculadas sólo como eventos observados. **Plesk/producción, cambios de esquema, grants, escrituras y despliegue: no autorizados.**

## 7. Preflight local de implementación (2026-11-20)

La conexión `default` resolvió `bd_admongas`. Una lectura de `information_schema` confirmó las 11 tablas enumeradas en las secciones 3 y auxiliares de adscripción presentes con motor InnoDB, y las columnas requeridas por las proyecciones implementadas. `SELECT COUNT(*)` fue aceptado por la cuenta de aplicación en cada tabla. Conteos observados: zonas horarias 9; jornadas, pareos, cálculos, evaluaciones y ajustes 0; incidencias y revisiones 1 cada una; marcaciones crudas 89; versiones de adscripción 87. Estos conteos son evidencia local al momento del preflight, no una garantía de contenido futuro ni de Plesk.
