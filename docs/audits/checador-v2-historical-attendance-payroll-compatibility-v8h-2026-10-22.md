# Checador V2 — V8H asistencia histórica y compatibilidad con nómina

**Fecha:** 2026-10-22
**Entorno:** Portal3 Fedora / desarrollo local
**Alcance:** corrección de dos regresiones del harness V8G y verificación de fuentes locales/contratos para asistencia histórica y posible consulta de nómina.
**No se modificaron:** lógica de aplicación, API, Lector, Plesk, base de datos, ACL ni documentos congelados.

## Resultado

Las dos regresiones focalizadas de V8G eran expectativas desactualizadas de pruebas, no fallas funcionales:

1. El reporte contiene dos modales Bootstrap distintos: filtros y detalle de marcación. Se actualizó la aserción para exigir ambos IDs y la tabla, en vez de exigir un único modal.
2. El template no expone el código `RESOLVED_PENDING_AUTHORIZATION`; usa `ChecadorPresentationFormatter::incidenceStatusLabel()` y la etiqueta española `Resuelta; pendiente de autorización`. Se ajustó P4D40 para comprobar esa traducción y la ausencia de nombres de credenciales en la vista.

La lectura histórica existente de marcaciones observadas continúa disponible en el reporte raw, con SQL local de solo lectura, estación obligatoria, identidad canónica opcional, UTC, límite de 31 días, paginación DataTables y CSV separado por permiso `descargar`. La consulta local de desarrollo validó tres filas para estación 1 en el intervalo probado, paginación, proyección y exportación. Esto no es evidencia de producción, preproducción ni cierre/completitud de un periodo.

No se añadió una pantalla o motor de conciliación Checador–nómina. El repositorio existente de nómina no proporciona un camino de lectura pura reutilizable: el método que la pantalla usa para leer un periodo (`RecibosNominaService::getNominaData`) llama primero a `sincronizarPersonal`, que puede insertar filas en `op_recibo_nomina_v2` para un periodo vencido sin registros. Evitar ese efecto requeriría una nueva consulta de solo lectura y sus garantías de autorización; además, el contrato V2 mantiene la integración de nómina diferida y no define un modelo contractual de conciliación o corte por periodo. Por ello, reutilizar ahora el servicio o presentar una comparación interpretada no sería seguro ni respaldado.

## Fuentes y semántica comprobadas

### Marcaciones V2 y asistencia histórica

- `ChecadorController::punchReport()` / `punchReportData()` y `ChecadorRawPunchReadService::readPage()` / `readDataTablePage()` ya cubren la consulta histórica de registros crudos. La consulta usa `op_rh_v2_marcaciones_crudas`, filtro obligatorio por localidad autorizada, límites temporales semiabiertos UTC, paginación y filtro sólo por `id_personal_resuelto`.
- Identidad V2: `id_personal_resuelto` corresponde al ID interno canónico de `op_rh_personal.id`. La estación se resuelve al espacio de localidad canónica mediante las reglas de Checador; no se deben cruzar IDs numéricos de catálogos distintos por igualdad accidental.
- La proyección indica integridad `NOT_VERIFIED_BY_PORTAL3`, conserva la hora UTC disponible y no convierte un resultado vacío en confirmación de ausencia.
- La prueba SQL local comprueba filas de desarrollo y el flujo CSV actual. No se copian ni alteran marcaciones.

### Asistencia legacy y nómina local

- `app/Models/Operativo/RhPersonalAsistencia.php` representa `op_rh_personal_asistencia` con campos legacy de día, trabajador/localidad, entrada/salida, retardo e incidencia. El contrato congelado J1/J2 y P3-G lo mantiene separado de V2: no es la fuente de PunchEvents, no recibe dual-write y no se acepta como verdad V2.
- `app/Models/Operativo/RhPersonalAsistenciaIncidencia.php` representa la incidencia legacy. P3-G prohíbe aceptar esa tabla como registro canónico de incidencias V2; la automatización legacy de incapacidad sigue bloqueada por autoridad de fuente.
- `app/Models/Operativo/OpReciboNominaV2.php` declara `op_recibo_nomina_v2`: periodo (`year`, `mes`, `no_semana_quincena`, `descripcion`), localidad (`id_estacion`), colaborador (`id_usuario` relacionado con `RhPersonal.id`), importe y referencias documentales. Es un registro de nómina por persona/periodo, no una evaluación diaria de PunchEvents.
- `op_recibo_nomina_v2_puntaje` registra puntajes de actividades del flujo de recibos, no asistencia diaria ni decisión V2. `op_recibo_nomina` contiene además importes/retenciones; no se necesita ni se consultó en la prueba de marcaciones.
- `RecibosNominaService::getRangoFechasSemana()` y `getRangoFechasQuincena()` expresan los periodos del módulo actual; la modalidad semanal/quincenal depende de la localidad. No existe contrato que los convierta en `period_id` de Checador V2 o que congele una comparación con sus snapshots, evaluaciones y revisiones.

### Riesgos al reutilizar nómina

- `RecibosNominaService::getNominaData()` invoca `sincronizarPersonal()` antes de consultar recibos. Esa sincronización puede crear filas para personal activo cuando el periodo ya acepta inserciones y todavía no existe un conjunto de filas. Por tanto, no es una operación de solo lectura y no debe invocarse desde una vista de Checador que prometa consulta sin escrituras.
- El acceso Checador y el de Recibos son fronteras distintas. Un futuro flujo combinado tendría que volver a comprobar permiso de lectura y localidad en ambos dominios en cada petición. No se considera que `biometricos.leer` autorice importes, documentos o recibos.
- El modelo disponible no demuestra por sí solo un vínculo de conciliación entre incidencias/Journey V2 y un importe o deducción de nómina. No se deducen pagos, descuentos, faltas, horas extra ni discrepancias financieras.

## Integraciones V2 conservadas

- Horario efectivo: lectura API por trabajador canónico/localidad, con vigencias históricas, zona laboral y offsets nocturnos; Portal3 no reconstruye el calendario.
- Journey: evaluación on-demand por trabajador, localidad y fecha laboral; se presenta la respuesta y procedencia de API, sin pareos/cálculos locales.
- Incidencias/evaluación: listas, detalle, historial y evaluación son recursos V2 separados, con alcance propio. Una incidencia no es una marcación ni un ajuste de nómina.
- Ajustes: V8F permite mostrar sólo las referencias enlazadas que devuelve la evaluación administrativa. La API no publica contrato de Portal3 para consulta completa de ajustes o ciclo de escritura.
- Completitud/cierre: permanece la conclusión V8G. No existe padrón histórico contractual que permita enumerar trabajadores sin PunchEvents ni una fuente agregada y versionada que pruebe cobertura completa del periodo. No se crea estado local `cerrado`, finalización de nómina ni reapertura.

## Contratos y decisiones aplicados

- J1/J2 congelan `JOURNEY_PHASE_1: ON_DEMAND`, sin materialización a `op_rh_personal_asistencia`, y `PAYROLL_INTEGRATION: DEFERRED`.
- P3-G conserva las tablas legacy fuera de la verdad V2 y prohíbe efectos de nómina, incluyendo calcular pagos/descuentos o modificar recibos, periodos y lotes.
- V8G deja pendientes el padrón histórico, completitud agregada y cierre versionado de periodo; V8F deja parcial el ciclo independiente de ajustes.
- No se modificaron documentos congelados. La decisión de V8H es mantener separadas las consultas existentes hasta que exista contrato de lectura comparativa, autorización en ambos dominios y repositorio de nómina estrictamente de solo lectura.

## Validación focalizada

| Prueba | Resultado | Evidencia |
|---|---:|---|
| `checador_mvp_punch_report_local_readonly_integration.php` | 27 PASS / 0 FAIL / 0 SKIPPED | SELECT local en `bd_admongas`; estación/rango, paginación SQL, DataTables, dos modales y CSV completo. |
| `checador_mvp_punch_report_regression.php` | 43 PASS / 0 FAIL / 0 SKIPPED | Autorización, consultas raw, UTC, paginación, CSV y estados no concluyentes. |
| `checador_p04c_effective_schedule_regression.php` | 31 PASS / 0 FAIL / 0 SKIPPED | Horario histórico, timezone y turno nocturno; sin conclusiones locales de nómina. |
| `checador_p04d_journey_regression.php` | 41 PASS / 0 FAIL / 0 SKIPPED | Journey y P4D40: estado formateado en español, sin nombres de credenciales en template. |
| `checador_p3ga_administrative_read_regression.php` | 25 PASS / 0 FAIL / 0 SKIPPED | Lecturas de incidencia y evaluación preservan contrato y separación de nómina. |
| `checador_p3gd_administrative_evaluation_regression.php` | 47 PASS / 0 FAIL / 0 SKIPPED | Evaluación/P3-G, readback, permisos, sin cálculo local ni efecto de nómina. |

**Total de las seis pruebas distintas:** 214 PASS / 0 FAIL / 0 SKIPPED.
PHP lint de los dos tests corregidos: PASS. `git diff --check` focalizado: PASS.
No se modificó JavaScript; navegador autenticado no verificado en este slice.

## Estado V8H

- **Regresiones V8G:** corregidas en las aserciones, con protección funcional intacta.
- **Consulta histórica raw:** ya implementada y validada localmente; mantiene el alcance y las limitaciones de fuente documentadas.
- **Consulta comparativa asistencia–nómina:** no implementada porque el flujo existente de nómina puede escribir al leerse y no existe contrato de conciliación, corte/completitud o autorización combinada suficiente para presentar diferencias como resultado de negocio.
- **Cambios funcionales Portal3:** ninguno.
- **Datos/ACL:** sin cambios; no hubo SQL de escritura.
- **API real / navegador / Plesk:** no verificados en esta tarea.
- **Cierre:** parcial respecto a conciliación con nómina. Siguiente paso: aprobar un contrato de consulta comparativa read-only con permisos explícitos Checador + Nómina, definición de periodos/identidad/localidad, y una fuente de nómina cuyo read no cree filas; no añadir cálculos ni cierre de periodo hasta resolver V8G.
