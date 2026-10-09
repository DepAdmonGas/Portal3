# CHECADOR V2 — PORTAL3 SCHEDULE & SOURCE COMPLETENESS FREEZE J2 V1.0

**Status:** FROZEN
**Scope:** Portal3 — Effective Schedule, Workplace Timezone, Night Shift and Source Completeness
**Runtime implementation authorized:** NO
**Production changes authorized:** NO

Este documento formaliza los contratos conceptuales de J2 aprobados explícitamente por el usuario. Complementa, no reemplaza, los siguientes documentos:

- `CHECADOR_V2_PORTAL3_JOURNEY_INCIDENTS_FREEZE_V1.0.md`
- `CHECADOR_V2_ARCHITECTURE_FREEZE_V1.0.md`

## 1. Gobierno y alcance

Este documento es la fuente oficial de verdad para los contratos J2 dentro de su alcance. Ningún proyecto puede reinterpretarlo unilateralmente. Cualquier cambio requiere una propuesta y aprobación explícita; una modificación aprobada debe publicarse como nueva versión o addendum.

Este freeze fija arquitectura y semántica. **No autoriza** implementación en Portal3, API o Lector; cambios de base de datos, migraciones, permisos, despliegue ni producción. Las verificaciones operativas indicadas aquí siguen siendo gates y no se presumen satisfechas por este documento.

## 2. Horario efectivo y fuente actual

`op_rh_personal_horario` es únicamente `CURRENT_CONFIGURATION_SOURCE`. Es editable, se sobrescribe y carece de vigencia histórica confiable; no es `HISTORICAL_SOURCE_OF_TRUTH` para Journey V2. La programación especial legacy y sus reportes no son un resolver de horario efectivo V2.

### 2.1 Versionado histórico

`SCHEDULE_VERSIONING_REQUIRED: YES`.

El modelo conceptual de una versión efectiva conserva, como mínimo:

- `worker_id` y `workplace_id`;
- `valid_from_local_date` y `valid_to_local_date`;
- definición de día/semana;
- entre uno y N segmentos de turno;
- descanso explícito;
- `recorded_at_utc`, actor, motivo, fuente y versión.

No se fija SQL ni esquema físico en J2.

### 2.2 Tiempo bitemporal

`BITEMPORAL_SCHEDULE_REQUIRED: YES`.

- **Effective time:** cuándo aplica el horario al negocio.
- **Recorded time:** cuándo fue registrado o corregido en el sistema.

La historia debe permitir consultar tanto lo que se consideraba vigente originalmente como una corrección retroactiva posterior. Las correcciones no borran la versión previamente registrada.

### 2.3 Programación especial legacy

`SPECIAL_PROGRAMMING_V2_POLICY: EXCLUDED_FROM_RESOLVER_PHASE_1`.

No se reinterpretará `op_rh_personal_horario_programar.fecha` como fecha efectiva, inicio de semana ni vigencia. Una asignación especial futura sólo podrá participar cuando tenga periodo efectivo explícito, aprobado y versionado.

### 2.4 Horario predeterminado de estación

`STATION_DEFAULT_SCHEDULE_ASSUMPTION: PROHIBITED`.

`op_rh_localidades_horario` es un catálogo de turnos; no se presume que asigne un default a cada trabajador. Sin horario personal efectivo, el resultado será `UNSCHEDULED`, `UNKNOWN` o `NEEDS_REVIEW`, según la evidencia. No habrá fallback implícito de estación. Un default futuro exige configuración y contrato de negocio explícitos.

## 3. Descanso y expectativa de trabajo

Los estados de programación no son intercambiables:

- `DAY_OFF`: existe evidencia explícita de descanso.
- `UNSCHEDULED`: no existe turno asignado; esto no prueba descanso.
- `UNKNOWN`: información insuficiente o en conflicto.

Una fila de horario faltante nunca equivale a `DAY_OFF`.

El resolver conceptual produce:

```text
EXPECTED_TO_WORK: YES | NO | UNKNOWN
```

Considerará, cuando existan fuentes autorizadas, horario efectivo, descanso explícito, vacaciones, incapacidad, permiso autorizado y cierre/feriado aprobado. `UNKNOWN` nunca se transforma en falta.

## 4. Timezone contractual del Workplace

`TIMEZONE_VERSIONING_REQUIRED: YES`.

El Workplace es dueño del timezone contractual. El modelo conceptual versionado conserva `workplace_id`, `timezone_iana`, `valid_from`, `valid_to`, `recorded_at_utc` y actor/motivo cuando aplique. La historia debe admitir cambios efectivos y correcciones registradas posteriormente.

Debe existir un identificador IANA válido antes de clasificar Journey. Si falta o no es válido:

```text
DATA_INCOMPLETE
TIMEZONE_UNRESOLVED
```

No usar como fallback silencioso `APP_TIMEZONE`, timezone del dispositivo, `source_timezone_reported`, `effective_timezone` ni un offset UTC fijo. El inventario de valores reales por Workplace es un gate de producción, no una inferencia de este freeze.

## 5. Turnos nocturnos y fecha laboral

El turno efectivo se representa conceptualmente mediante:

```text
local_start_date
local_start_time
local_end_time
end_day_offset
workplace_timezone
```

Ejemplo: `22:00 → 06:00`, `end_day_offset=1`. El instante UTC se deriva para comparar eventos; no es la definición contractual del turno.

```text
LOCAL_WORKDAY_DATE = fecha local de inicio del turno efectivo resuelto
                     en el timezone contractual del Workplace
```

No usar `DATE(ocurrido_at_utc)` como fecha laboral.

### 5.1 Asociación antes/después del turno

Los parámetros futuros serán `pre_shift_window_minutes` y `post_shift_window_minutes`. Sus valores requieren confirmación de negocio. Hasta aprobarlos, no asociar automáticamente punches fuera del intervalo exacto del turno.

### 5.2 Segmentos múltiples

`MULTI_SEGMENT_SUPPORT_IN_MODEL: YES`: el contrato permite 1..N segmentos y no limita eventos raw. `MULTI_SEGMENT_OPERATIONAL_USE: UNKNOWN` hasta confirmación del negocio. La compatibilidad del modelo con segmentos múltiples no implica su implementación en Phase 1.

## 6. Proyección de slots y comida

Los slots estándar son:

```text
ENTRY
LUNCH_OUT
LUNCH_IN
EXIT
```

Son una proyección de presentación, no un límite de PunchEvents ni una regla universal basada en el orden/posición del evento. Todos los eventos raw se preservan. El motor puede producir candidatos; una asignación ambigua es `REVIEW_REQUIRED`.

En Phase 1, `ENTRY`/`EXIT` sólo se evalúan cuando horario, timezone y completitud de fuente están resueltos.

`LUNCH_OUT` y `LUNCH_IN` quedan `NO_EVALUABLE / NOT_CONFIGURED` hasta que negocio apruebe si la comida requiere checada, ventanas, duración, excepciones y turnos sin comida. Nunca inferirlos como segundo/tercer punch ni emitir “sin marcación de comida” sólo porque falte una posición.

## 7. Completitud de fuente: tres niveles independientes

1. **API snapshot completeness:** todas las páginas representan el mismo conjunto estable de eventos.
2. **Ingestion completeness:** el API acredita haber recibido todos los eventos relevantes de cada dispositivo hasta un límite de origen verificable.
3. **Journey domain completeness:** Portal3 tiene datos suficientes de trabajador, Workplace, timezone, horario, ausencias autorizadas, integridad y conflictos para concluir.

Cumplir un nivel no implica cumplir los otros.

### 7.1 Estado actual de lectura API

`API_SNAPSHOT_COMPLETENESS: GAP` para el endpoint V2.3 actual: el conteo se ejecuta por separado, las páginas usan `LIMIT/OFFSET` y no existe snapshot token ni watermark estable. Inserts concurrentes pueden cambiar conteos o desplazar filas durante la paginación.

El query actual también filtra por estación resuelta y `ocurrido_at_utc`; por ello no devuelve eventos cuyo `id_estacion_resuelta` o tiempo de ocurrencia sean nulos. Una respuesta vacía no cuenta esos eventos ni prueba ausencia.

`API_V2_4_EXTENSION_RECOMMENDED: YES`.

La extensión futura debe ser aditiva, backward compatible, contar con contrato/addendum y pruebas propios y requerir autorización explícita. No se modifica ni reinterpreta V2.3 mediante este freeze.

### 7.2 Contrato futuro de snapshot API

Recomendación arquitectónica: **watermark/snapshot del servidor + cursor determinista opaco**. El snapshot fija el límite de eventos raw comprometidos que formarán el conjunto de lectura; todas las páginas consultan bajo ese mismo límite con orden estable. El identificador interno puede servir para ordenar/cursar, pero no se expone como campo de negocio.

El watermark debe representar un límite realmente comprometido y visible. Un `MAX(id)` o `received_at_utc` aislado no constituye garantía por sí solo sin demostrar su semántica de commit frente a inserciones concurrentes.

## 8. Completitud de ingestión y responsabilidad del Lector

`DEVICE_TO_API_COMPLETENESS_SIGNAL: PARTIAL`.

La arquitectura documenta cola durable, reintentos offline y ACK API antes de marcar un evento como sincronizado. Esto acredita intención/flujo por evento, no que se hayan recibido todos los eventos que el dispositivo produjo. `device_sequence` opcional tampoco demuestra continuidad ni ausencia de eventos pendientes.

`LECTOR_COMPLETENESS_EXTENSION_RECOMMENDED: YES`, como slice separado. No se implementa en J2. El contrato futuro de watermark por dispositivo debe contemplar:

- identidad del dispositivo y generación/época;
- límite de origen declarado y límite aceptado por API;
- pendientes y gaps explícitos;
- reinicios, reemplazos, resets de secuencia y cola offline.

Un heartbeat sólo indica actividad/conectividad; por sí solo no prueba completitud. La durable queue existente debe preservarse. El futuro slice debe reconciliarse con su implementación antes de cambiarla.

## 9. Contrato para afirmar ausencia

`RAW_SOURCE_CAN_PROVE_ABSENCE_CURRENTLY: NO`.

Sólo puede emitirse `SIN MARCACIÓN` si se cumplen **todas** las condiciones siguientes:

1. trabajador resuelto;
2. Workplace resuelto;
3. timezone válido;
4. horario efectivo resuelto;
5. `EXPECTED_TO_WORK=YES`;
6. fuentes aplicables de ausencias autorizadas resueltas;
7. ventana UTC correcta para horario y Workplace;
8. snapshot API completo;
9. completitud de ingestión satisfecha para dispositivos relevantes;
10. todas las páginas completas y sin errores;
11. ningún evento no atribuido, conflicto o dato no resuelto puede cambiar la conclusión.

Si falla una condición, el resultado es `DATA_INCOMPLETE` y/o `REVIEW`; nunca `SIN MARCACIÓN` por defecto.

`SIN MARCACIÓN` es el hecho derivado de que un slot esperado no tiene evento coincidente bajo esas condiciones. No es una hora, `00:00`, una hora programada ni un PunchEvent; no crea ni modifica eventos raw.

`FALTA CANDIDATA` es un resultado tentativo de reglas Journey después de demostrar ausencia de marcación y expectativa de trabajo. No es resolución humana ni implica nómina.

`RESOLUCIÓN ADMINISTRATIVA` es una decisión autorizada y auditable posterior. Conserva actor, timestamp, motivo, evidencia y estado previo/nuevo. No se deduce automáticamente de punches. Las consecuencias de nómina permanecen diferidas.

## 10. Eventos sin atribución, estación, tiempo o integridad concluyentes

- **`PENDING_MATCH`:** PunchEvent real con trabajador sin resolver. No atribuir por alias. Si puede afectar la jornada individual: `UNATTRIBUTED_EVENT_PRESENT` → `REVIEW / DATA_INCOMPLETE`.
- **Estación/tiempo nulos o sin resolver:** no se pueden ignorar para probar ausencia absoluta. La futura capa de completitud debe contabilizarlos o cerrarlos con estado verificable.
- **Integridad:** `VALID`, `INVALID`, `UNVERIFIABLE` y `LEGACY_UNSEALED` son estados de un evento existente. No equivalen a ausencia y no autorizan descartarlo; la incertidumbre relevante produce revisión.

## 11. Reproducibilidad histórica y cálculo

Para reproducir una jornada histórica se debe poder identificar:

- versión del horario y del timezone;
- versión del motor de interpretación;
- UUIDs raw considerados;
- referencia de snapshot API y watermark de ingestión;
- versiones aplicables de incidencias/ausencias y sus resoluciones.

Se mantiene J1: `JOURNEY_PHASE_1: ON_DEMAND`, `MATERIALIZATION: DEFERRED`. J2 no modifica esta decisión. No habrá dual-write ni sincronización hacia `op_rh_personal_asistencia`. `PAYROLL_INTEGRATION: DEFERRED`.

## 12. Gates pendientes

### Confirmación de negocio

- valores de `pre_shift_window_minutes` y `post_shift_window_minutes`;
- uso operativo de turnos con múltiples segmentos;
- política de comida;
- fuentes/semántica de vacaciones, incapacidad y permisos autorizados;
- calendario confiable de cierres/feriados;
- tratamiento de horas locales ambiguas/inexistentes por DST;
- reglas definitivas de ausencia.

### Verificación de producción

- inventario IANA por Workplace;
- grants/ACL reales;
- mapeos de estación/dispositivo;
- estado efectivo de durable queue y sincronización;
- capacidad snapshot/completitud de la versión desplegada del API;
- fuentes autorizadas reales de vacaciones, permisos e incapacidad.

### Gates de implementación

- No implementar clasificación de ausencia hasta contar con timezone y horario versionados, snapshot API, completitud de ingestión y fuentes autorizadas de ausencias.
- No implementar faltas de comida hasta aprobar su política.
- No asociar eventos fuera del turno hasta aprobar ventanas.
- No recalcular histórico sin versionado de horario y timezone.
- No inferir cierre, descanso o ausencia desde información faltante.

## 13. Secuencia futura recomendada

Esta secuencia es orientación; no autoriza iniciar ningún slice:

1. **J3:** preflight/implementación del dominio de horario y timezone en Portal3, sólo con autorización separada.
2. **API V2.4-A:** arquitectura de snapshot/completitud.
3. **API V2.4-B:** implementación API autorizada, con contrato, compatibilidad y pruebas propios.
4. **Lector C1:** preflight de completitud/watermark; reconciliar con la cola durable existente.
5. **J4:** cliente API Portal3 y consumo de completitud.
6. **J5:** motor de interpretación Journey.
7. **J6:** adaptadores y resoluciones de incidencias/ausencias.
8. **J7:** UI autorizada.
9. **J8:** regresión, carga y revisión de materialización/nómina.

## 14. No autorización de cambios

Este documento no autoriza runtime, DB, migraciones, permisos, pruebas de implementación, commits, push, despliegue ni cambios de producción. Cada actividad requiere un slice y autorización propios.
