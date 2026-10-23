# Checador V2 — V8G validación y cierre de periodos

**Fecha:** 2026-10-22
**Entorno:** Portal3 Fedora / desarrollo local
**Alcance:** revisión de contratos, rutas y capacidades existentes; no se crea un estado de cierre ni un flujo de periodo ficticio.

## Resultado

Portal3 ya ofrece consultas de marcaciones observadas por rango y estación, y consultas diarias de horario efectivo, Journey y administración. Esas capacidades no constituyen una validación agregada de un periodo de asistencia: no hay un padrón contractual completo de trabajadores para el periodo ni una respuesta canónica que agregue la completitud de todos los días, fuentes y trabajadores.

No se implementó un resumen que pudiera omitir trabajadores sin eventos o convertir una respuesta vacía en ausencia. La consulta respaldada por contrato que ya existe se conserva; no requiere una segunda pantalla o servicio paralelo. El cierre formal no puede implementarse en Portal3 hasta que exista un contrato API explícito.

## Inventario contractual

| Capacidad | Evidencia encontrada | Estado para periodo |
|---|---|---|
| Periodo de asistencia | La Freeze J2 distingue conceptualmente cierre de jornada, cierre administrativo, cierre de periodo y bloqueo asociado a nómina (Architecture Freeze §29). No define un recurso ejecutable de periodo. | Sin contrato operativo de periodo |
| Completitud | J2 y la reconciliación de completitud distinguen evidencia Level 1 (membresía del snapshot), Level 2 (ventana/dispositivos) y semántica Level 3. Los estados Level 2 `VERIFIED`, `INCOMPLETE` y `UNKNOWN` aplican al alcance/ventana evaluados, no a un periodo agregado. La reconciliación declara que no implementa un cliente Portal3 Level 3. | No existe endpoint/contrato agregado de completitud de periodo |
| Estados de periodo | No se encontró enum ni transición de periodo en Portal3 ni en las rutas/contratos API revisados. Los estados de horario, Journey, Level 2 e incidencias conservan su significado propio. | Ninguno definido; no introducir `CERRADO`, `APROBADO` o `REABIERTO` |
| Cierre y autorización | No hay ruta Portal3 ni endpoint público API de cierre de periodo, ni ACL específica de cierre. `POST /api/v2/shift-assignments/{id}/close` cierra una asignación de horario, no un periodo de asistencia. | No disponible; no mostrar acción de cierre |
| Reapertura e historial | No hay operación de reapertura, revisión/versionado de cierre de periodo ni historial de cierres de periodo. El historial de incidencias y la procedencia de Journey son recursos distintos. | No definido |
| Nómina | El contrato P3-G establece `PAYROLL_INTEGRATION = DEFERRED` y prohíbe authorizar o modificar lotes, periodos o recibos de nómina. | Dependencia diferida; sin bloqueo o efecto de nómina local |

## Funcionalidad que sí existe

- **Reporte raw:** `ChecadorController::punchReport()` y `punchReportData()` aplican autorización de estación en servidor; la fecha inicial/final se convierte a ventana UTC semiabierta, admite hasta 31 días, filtro canónico opcional de trabajador, paginación DataTables y CSV con comprobación independiente de `descargar`. El lector `ChecadorRawPunchReadService` consulta únicamente `op_rh_v2_marcaciones_crudas` con SQL `SELECT`, parámetros, estación obligatoria e identidad canónica. Su lectura es `LIVE_NON_SNAPSHOT`; Portal3 marca integridad como `NOT_VERIFIED_BY_PORTAL3` y no calcula hora local ni integridad criptográfica.
- **Consulta diaria avanzada:** `ChecadorController::index()` conserva un día laboral/localidad autorizados, solicita timezone, snapshot Level 1 y estado de ventana Level 2, y presenta resultados por trabajador canónico/fecha. No es una enumeración del padrón laboral del periodo.
- **Horario:** `getEffectiveSchedule()` y `getShiftAssignments()` consultan un trabajador canónico y una localidad; preservan vigencias, timezone y `end_day_offset`. El turno nocturno se asocia a su fecha laboral local, no a `DATE(ocurrido_at_utc)`.
- **Journey:** `evaluateJourney()` corresponde a `POST /api/v2/journeys/evaluate` para un trabajador, una localidad y una `fecha_local`; Portal3 presenta la respuesta y procedencia, sin parear ni calcular localmente.
- **Incidencias/evaluación administrativa:** las lecturas identifican trabajador, localidad y rango/fecha según sus contratos. Una evaluación administrativa pertenece a una jornada y puede exponer referencias de ajustes vinculados. Esto no da alcance de periodo ni autoriza operaciones de ajuste.
- **Ajustes:** V8F continúa parcial. El API no expone comandos/ACL específicos de ajustes en la integración examinada. En V8G no se convierten incidencias en ajustes ni se añaden escrituras.

## Por qué no se arma un agregado local

El conjunto de trabajadores derivado sólo de marcaciones observadas excluye necesariamente a cualquier persona sin PunchEvent en el rango. No existe en el límite consultado una fuente contractual que certifique el padrón aplicable al periodo y su vigencia histórica. Además, Journey y el horario se consultan por trabajador/día, el Level 2 por ventana gobernada y las incidencias por trabajador/rango; no comparten un `period_id`, una revisión de corte o una transacción/snapshot de periodo.

Por tanto, combinar esas lecturas en Portal3 no demostraría que el periodo está completo. Un vacío, una página incompleta, un trabajador no enumerado, un timezone sin resolver o una fuente `UNKNOWN`/`INCOMPLETE` debe permanecer como no disponible/no verificable; nunca convertirse en `SIN MARCACIÓN`, `FALTA` o estado final.

## Contrato requerido antes de un cierre formal

El contrato API futuro deberá definir, como mínimo:

1. Identidad, calendario, zona horaria y ventana local/UTC del periodo, incluida la política de turnos nocturnos que lo cruzan.
2. Fuente autoritativa y versionada de trabajadores incluidos, localidad y vigencias laborales; definición de cobertura ante trabajadores sin eventos.
3. Modelo canónico de evaluación/completitud agregada y estados permitidos, incluyendo cómo se propagan datos no disponibles, `UNKNOWN`, `INCOMPLETE`, conflictos y errores de paginación.
4. Referencias/revisiones congeladas de snapshots, ventanas Level 2, horarios, Journeys, evaluaciones, incidencias y ajustes aplicables; reglas de consistencia y concurrencia del corte.
5. Endpoint de cierre, precondiciones, permiso funcional independiente, `Idempotency-Key`, control optimista de revisión, respuestas de conflicto y lectura canónica posterior.
6. Historial append-only de cierre con actor, instante UTC, motivo, evidencia/referencias, estado anterior/nuevo y política explícita de reapertura/invalidez.
7. Frontera de nómina: si el cierre no afecta nómina, declararlo; si afecta un periodo de nómina, contrato/ACL y procedimiento explícitos. La integración actual sigue diferida.

Hasta que ese contrato y endpoint existan, Portal3 no debe persistir cierres en sesión o base local, ni poner botones decorativos, ni usar el cierre de una asignación de horario como sustituto.

## Autorización e invariantes preservados

La consulta sigue dependiendo de `ChecadorAccessService` y del scope institucional de la localidad en cada solicitud; la UI no amplía alcance. Los permisos de lectura, descarga, acciones P3-G y autorización humana siguen siendo independientes. No se agregaron ACL.

No se alteró, recalculó, eliminó ni reescribió ningún PunchEvent. No se introdujo escritura de base de datos, migración, endpoint, estado local de cierre, operación API ni cambio a los documentos congelados.

## Validación focalizada ejecutada

| Prueba | Resultado | Evidencia/observación |
|---|---:|---|
| `checador_mvp_punch_report_regression.php` | 43 PASS / 0 FAIL / 0 SKIPPED | Contrato de reporte, UTC, paginación, exportación y scope |
| `checador_mvp_punch_report_local_readonly_integration.php` | 26 PASS / 1 FAIL / 0 SKIPPED | SELECT real validó `bd_admongas` en Fedora, estación 1, filtros, páginas y CSV. El único fallo espera exactamente un modal; la vista actual contiene dos modales distintos e intencionales: filtros y detalle. No indica fallo de consulta/CSV. |
| `checador_p04c_effective_schedule_regression.php` | 31 PASS / 0 FAIL / 0 SKIPPED | Horario efectivo, vigencias, timezone, turno nocturno y ausencia de cálculos locales |
| `checador_p04d_journey_regression.php` | 40 PASS / 1 FAIL / 0 SKIPPED | P4D40 espera que el código administrativo `RESOLVED_PENDING_AUTHORIZATION` aparezca literalmente en la vista. La plantilla no lo contiene ni contiene nombres de credenciales (`JWT`, `Authorization`, `snapshot_token`, `secret`); el estado se presenta por el formateador. Es una aserción estática desactualizada, no evidencia de secreto expuesto. |
| `checador_p3f_journey_audit_provenance_regression.php` | 35 PASS / 0 FAIL / 0 SKIPPED | Procedencia, pertenencia de eventos y turno nocturno |
| `checador_p3ga_administrative_read_regression.php` | 25 PASS / 0 FAIL / 0 SKIPPED | Incidencias/evaluaciones de lectura y fronteras |
| `checador_p3gd_administrative_evaluation_regression.php` | 47 PASS / 0 FAIL / 0 SKIPPED | Evaluación y proyección de ajustes sin comandos de ajuste |

Los dos fallos señalados son expectativas antiguas del harness ajenas a V8G; no se modificaron porque la implementación observada coincide con los contratos actuales y la tarea no reabre V8A–V8F sin una regresión funcional. La prueba SQL local fue estrictamente de lectura y no modificó datos.

PHP lint de `ChecadorController.php`, `ChecadorDailyReadService.php`, `ChecadorRawPunchReadService.php`, `ChecadorAccessService.php` y la vista principal: sin errores de sintaxis. `git diff --no-index --check /dev/null <documento>` no reportó whitespace (exit 1 sólo por comparar un archivo nuevo con `/dev/null`). No se modificó JavaScript; no se hizo validación de navegador en esta ejecución.

## Estado V8G

- **Consulta raw respaldada:** disponible como reporte de marcaciones observadas por rango; no declara completitud ni cierre.
- **Validación agregada de periodo:** bloqueada por ausencia de padrón histórico autoritativo y contrato de completitud/versionado de periodo.
- **Cierre/reapertura:** bloqueados por ausencia de endpoint, ACL, estados y auditoría de cierre.
- **Código funcional nuevo:** ninguno; no había cambio de comportamiento local seguro respaldado por contrato.
- **Siguiente módulo:** V8H (asistencia histórica y nómina), como slice independiente; no iniciado aquí. La integración de nómina permanece diferida por su contrato vigente.
