# PORTAL3-J1 — Jornada Diaria + Incidencias

## Architecture Freeze V1.0 — Companion de Portal3

```text
STATUS: FROZEN
CANONICAL: YES — único companion freeze oficial de Portal3 para Jornada Diaria + Incidencias
SOURCE: J0 + J0A + user approval
COMPANION_TO: CHECADOR_V2_ARCHITECTURE_FREEZE_V1.0.md
IMPLEMENTATION_AUTHORIZED: NO
```

> Este documento queda `FROZEN` por aprobación explícita del usuario. Es el único companion freeze oficial de Portal3 para Jornada Diaria + Incidencias; complementa el freeze de arquitectura/API de Checador V2, pero no lo sustituye, modifica ni amplía. Portal3 y la API no pueden reinterpretar unilateralmente este documento.

## 1. Propósito y límites

Este companion define la arquitectura de Portal3 para la capa derivada de **Jornada Diaria + Incidencias**, sobre los `PunchEvent` raw de Checador V2. Congela únicamente los contratos y límites expresamente aprobados; los puntos no verificados permanecen como gates.

Este documento no autoriza runtime, tablas, migraciones, cambios de permisos, cambios de API o Lector, ni operaciones en producción. No congela un esquema SQL ni enums finales para el dominio de incidencias.

## 2. Principio de capas

```text
PunchEvent Raw
    ↓
Identity / Station / Integrity Resolution
    ↓
Effective Schedule + Workplace Timezone
    ↓
Derived Daily Journey
    ↓
Administrative Incident
    ↓
Incident Resolution / Conflict
    ↓
Authorized Portal3 UI
```

Invariantes:

- `Incident` no ejecuta `UPDATE` sobre `PunchEvent`.
- `Journey` no fabrica `PunchEvent`.
- Una resolución administrativa modifica la consecuencia administrativa, no el hecho registrado.

## 3. Fuentes de verdad y coexistencia

| Categoría | Fuente/entidad | Contrato |
|---|---|---|
| Hecho raw | API V2.3 `PunchEvent` | Evidencia de marcación; se preservan todos los eventos. |
| Vista derivada | `DailyJourney` | Proyección calculada; no es fuente de verdad raw. |
| Registro administrativo | `AttendanceIncident` | Dominio V2 separado; no reemplaza ni reinterpreta el hecho raw. |
| Historial de decisiones | `IncidentResolution` | Historial append-only/versionado de decisiones administrativas. |
| Legacy | `op_rh_personal_asistencia` | Permanece legacy; no es fuente raw V2 ni destino de sincronización. |

`op_rh_personal_asistencia` continúa coexistiendo hasta una decisión explícita de cutover. No se sincronizan PunchEvents hacia ella, no se hace dual-write y no se hace backfill de V2 hacia legacy.

## 4. Contrato de identidad de trabajador

- Para un `PunchEvent` resuelto, `id_personal_resuelto` es la identidad interna autoritativa y referencia `op_rh_personal.id`.
- `external_worker_reference` permanece como alias opaco; no es una identidad interna ni un fallback.
- `no_colaborador` tampoco es fallback de resolución.
- `PENDING_MATCH` permanece sin atribución hasta una resolución futura, explícita y aditiva; mientras tanto requiere revisión según corresponda.

El origen exacto del alias en Lector sigue siendo un gate de evidencia.

## 5. Contrato de identidad de estación

- `id_estacion_resuelta` se interpreta en el espacio semántico de `op_rh_localidades.id`.
- El mapeo Portal3 hacia `tb_estaciones` sólo se realiza por un `numlista` válido y único.
- No se presume equivalencia numérica ni semántica entre IDs de catálogos.
- Un mapeo inválido o ambiguo produce `DATA_INCOMPLETE` / `REVIEW`; no se adivina la estación.

La validación de valores de estación emitidos por dispositivos y el inventario de unicidad de `numlista` permanecen como gates.

## 6. Workplace y timezone contractual

- El Workplace/localidad es dueño del timezone laboral contractual, que debe ser IANA.
- `source_timezone_reported` y `effective_timezone` no son el timezone contractual del Workplace; no se deriva de ellos la jornada laboral local.
- Los valores reales de timezone por Workplace requieren inventario de datos/despliegue y no se presumen en esta especificación.

## 7. Fecha laboral y turnos nocturnos

```text
LOCAL_WORKDAY_DATE = fecha local de inicio del turno resuelto
                     en el timezone contractual del Workplace
```

- No se usa `DATE(ocurrido_at_utc)` como fecha laboral.
- Los turnos nocturnos deben poder representar `local_start_date`, `local_start_time`, `local_end_time`, `end_day_offset` y timezone.
- `end_day_offset` resuelve el cruce de día del turno; no cambia la fecha local de inicio que determina `LOCAL_WORKDAY_DATE`.

La aplicación de esta regla requiere horario efectivo y timezone Workplace suficientemente resueltos.

## 8. Programación efectiva

La precedencia siguiente es sólo una candidata y queda `PENDING_BUSINESS_CONFIRMATION`; no debe tratarse como semántica legacy ya comprobada:

1. asignación especial fechada y finalizada;
2. programación semanal personal válida;
3. horario predeterminado de estación;
4. falta de resolución o conflicto → `NEEDS_REVIEW`.

La semántica de fecha/vigencia de la programación especial debe confirmarse antes de congelar los puntos 1–3 o emitir clasificación de ausencia basada en esa precedencia.

## 9. Journey — fase 1

- Fase 1 calcula `DailyJourney` **ON_DEMAND**.
- Materialización y caché quedan diferidas hasta demostrar necesidad mediante mediciones.
- Una eventual proyección materializada debe ser regenerable, no ser fuente de verdad y, si hace falta, versionarse por la versión del motor de interpretación.
- No hay dual-write a `op_rh_personal_asistencia`.

## 10. Slots estándar y preservación de eventos

La proyección estándar de jornada contiene cuatro slots:

```text
ENTRY
LUNCH_OUT
LUNCH_IN
EXIT
```

Estos slots no son un máximo universal de `PunchEvent`. Todos los eventos raw se preservan; cinco o más eventos pueden producir ambigüedad o `REVIEW_REQUIRED`. Los eventos intermedios y múltiples segmentos deben seguir siendo representables.

Cada slot asignado contiene conceptualmente:

- `slot_type`;
- `source_event_uuid`;
- timestamp observado;
- estado de integridad de la fuente.

Para un slot no asignado, `source_event_uuid = NONE`. Nunca se usa la hora programada como hora observada ni se inventa una hora faltante. La presentación `SIN MARCACIÓN` sólo es válida si se estableció la completitud de la fuente para el ámbito consultado; de lo contrario debe mostrarse incertidumbre (`DATA_INCOMPLETE` / `NO_DISPONIBLE` / `REVIEW`).

La necesidad operativa real de proyectar más de cuatro slots estándar permanece como gate; no limita la preservación ni representación de los eventos raw.

## 11. Completitud de la fuente

Una respuesta exitosa o vacía de `GET /api/v2/punch-events` **no demuestra por sí sola ausencia de marcaciones**. Hasta que exista una garantía suficiente de fuente completa/snapshot, se aplica fail-closed.

Los siguientes casos producen incertidumbre, no ausencia:

- fallo de API o JWT;
- paginación incompleta o inconsistente;
- estación o tiempo sin resolver;
- problema de integridad;
- completitud desconocida o falta de garantía de snapshot.

El resultado debe ser `DATA_INCOMPLETE`, `NO_DISPONIBLE` o `REVIEW`, según corresponda. Nunca convertir incertidumbre en `FALTA` o `SIN MARCACIÓN`.

## 12. Propagación de integridad

La Journey conserva y expone el estado de integridad de los eventos fuente, incluyendo:

```text
VALID
INVALID
UNVERIFIABLE
LEGACY_UNSEALED
```

Los estados `INVALID` y `UNVERIFIABLE` permanecen visibles y nunca se descartan silenciosamente. La clasificación final de una jornada debe conservar la relación con la evidencia y sus estados de integridad.

## 13. Dominio V2 de incidencias

La implementación futura usa conceptualmente un dominio separado con:

- `AttendanceIncident`;
- `IncidentPeriod`;
- `IncidentResolution`;
- `JourneyConflict`.

No se congela aquí el esquema SQL. `op_rh_personal_asistencia_incidencia` no se extiende como nueva fuente de verdad V2.

## 14. Periodo de incidencia

Conceptualmente, un periodo puede incluir trabajador, Workplace, fecha local inicial y fecha local final; puede incluir un intervalo local acotado si la política lo requiere.

Una incapacidad de 15 días es un incidente/periodo fuente, no 15 incidentes fuente fabricados. Las vistas diarias pueden referenciar ese periodo.

## 15. Historial de resolución

Las resoluciones son append-only/versionadas y conservan, como mínimo:

- actor;
- timestamp UTC;
- motivo;
- estado previo;
- estado nuevo;
- referencia a fuente/evidencia;
- correlación/versión.

Las decisiones reemplazadas permanecen auditables. El historial no modifica ni elimina el evento raw.

## 16. Consecuencia administrativa y conflictos

Una incidencia modifica la consecuencia administrativa, no el hecho registrado. Ejemplo: una entrada observada a las 09:04 sigue siendo 09:04 aunque el horario sea 08:00 y una resolución la marque `JUSTIFIED`; la hora observada no cambia.

El modelo conceptual debe poder mantener visibles conflictos como:

- marcación durante vacaciones, incapacidad o permiso autorizado;
- eventos extra;
- slot faltante o ambiguo;
- ambigüedad entre días;
- integridad inválida o no verificable;
- periodos de incidencias superpuestos.

Los nombres y estados finales de estos conflictos no quedan congelados aquí. No se elimina evidencia raw para resolver un conflicto.

## 17. Adaptadores administrativos e incapacidad

- Formato RH 6 y `op_rh_permisos` podrán evaluarse posteriormente como adaptadores administrativos de sólo lectura.
- No se convierten en verdad automática de Journey hasta confirmar semántica de negocio y comportamiento de aprobación, cancelación y revocación.
- El modelo/esquema legacy de incapacidad continúa `UNRESOLVED`; no se declara su tabla actual fuente de verdad.
- El futuro dominio V2 debe poder representar de forma independiente un periodo de incapacidad.

## 18. Autorización

- La lectura de Jornada puede regirse por `biometricos.leer` y requiere scope de estación validado en servidor.
- La administración de incidencias requiere permisos conceptualmente separados: `read`, `create`, `resolve` y `authorize`, con station scope.
- No se sustituye esta separación por un permiso genérico `editar`.
- El module key definitivo de incidencias no se congela.
- Grants operativos/producción deben verificarse; no se presumen.

## 19. Cliente de API y delegación de identidad

La integración de Portal3 con la API es server-side mediante un componente conceptual `PunchEventApiClient`. Credenciales o JWT de API no se exponen al navegador.

El diseño debe ser compatible con la identidad delegada:

```text
iss = portal3_auth
sub = tb_usuarios.id
```

El mecanismo exacto de emisión/minting del token se difiere a implementación y requiere autorización específica. Esta sección no autoriza cambios en la API ni en Lector.

## 20. Paginación y completitud del cliente

El cliente conceptual debe:

- respetar el máximo de 31 días;
- usar `per_page <= 200`;
- recuperar todas las páginas;
- validar `current_page` y `total_pages`;
- detectar UUIDs de evento duplicados;
- fallar de forma cerrada ante páginas, metadatos o resultados faltantes, erróneos o inconsistentes.

Una paginación incompleta nunca se presenta como asistencia completa. Estas condiciones describen el contrato objetivo; la garantía efectiva de completitud/snapshot de la API sigue siendo gate de evidencia.

## 21. Nómina

```text
PAYROLL_INTEGRATION: DEFERRED
```

Ninguna salida nueva de Journey/Incidents se convierte en entrada de nómina sin validación separada de negocio y reglas. La tabla legacy continúa sin sincronización raw V2 y sin dual-write.

## 22. Gates no resueltos

Los puntos siguientes son gates explícitos, no supuestos ni decisiones ya verificadas:

- origen exacto de `external_worker_reference` en Lector;
- validación de valores de estación realmente emitidos por dispositivos;
- inventario de `numlista` válidos y únicos;
- inventario de timezone IANA por Workplace;
- semántica de vigencia/fecha de programación especial;
- precedencia definitiva de programación;
- necesidad operativa real de proyectar más de cuatro slots;
- schema real de incapacidad en DEV y producción;
- semántica de cancelación/revocación de vacaciones;
- semántica de cancelación/revocación de permisos;
- grants ACL reales operativos/producción;
- garantía de completitud y snapshot del API raw.

Los resultados deben respaldarse con evidencia del entorno correspondiente. No se infiere estado de producción a partir de configuración local.

## 23. Bloqueos de implementación

- No implementar clasificación de ausencia/falta de Journey hasta que el horario efectivo y el contrato de timezone Workplace estén suficientemente resueltos.
- No emitir `SIN MARCACIÓN` o `FALTA` definitivos sólo por ausencia en la respuesta raw hasta que exista garantía de completitud para el ámbito consultado.
- Si identidad, estación, integridad, horario, autorización o completitud son inciertos, preservar esa incertidumbre y revisar; no fabricar certeza.

## 24. Fases futuras (no autorizadas por este documento)

| Slice | Alcance propuesto |
|---|---|
| J2 | Horario efectivo/timezone y contratos de mapeos no resueltos. |
| J3 | Cliente API server-side, JWT/ACL y paginación completa. |
| J4 | Motor de Journey derivada. |
| J5 | Dominio V2 de incidencias e historial. |
| J6 | Conflictos y adaptadores administrativos. |
| J7 | UI de Portal3 autorizada. |
| J8 | Regresión, carga, revisión de materialización y nómina. |

La lista ordena trabajo futuro; no implementa ni autoriza ninguno de esos slices.

## 25. Gobernanza del documento

- **Status actual:** `FROZEN`.
- **Canonicidad:** único companion freeze oficial de Portal3 para Jornada Diaria + Incidencias.
- **Fuente:** J0 + J0A + aprobación del usuario de las decisiones J1.
- **Companion de:** `CHECADOR_V2_ARCHITECTURE_FREEZE_V1.0.md`.
- Este documento no modifica ni reemplaza el freeze de arquitectura/API de Checador V2.
- Portal3 y la API no pueden reinterpretar unilateralmente este companion.
- Todo cambio futuro requiere propuesta, revisión y aprobación explícita; se registra en una nueva versión o addendum según corresponda.

## 26. Control de cambios y autorización

```text
RUNTIME_MODIFIED: NO
DATABASE_MODIFIED: NO
MIGRATIONS_CREATED: NO
PERMISSIONS_MODIFIED: NO
API_MODIFIED: NO
LECTOR_MODIFIED: NO
PRODUCTION_MODIFIED: NO
IMPLEMENTATION_AUTHORIZED: NO
```
