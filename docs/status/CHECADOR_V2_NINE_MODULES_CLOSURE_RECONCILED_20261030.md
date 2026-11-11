# Checador V2 — reconciliación del cierre de nueve módulos

**TASK:** `CHECADOR_V2_MASTER_CLOSURE_EVIDENCE_RECONCILIATION_V2`
**Fecha de corte:** 2026-10-30
**Entorno:** Portal3 — Fedora local
**Fase:** consolidación de evidencia y plan; sin implementación.

## Dictamen

Se incorporan los resultados recientes proporcionados para M01–M09, se contrastan con el checkout y con los artefactos E2E que están fuera del repositorio. Los resultados de ejecución comunicados por la persona usuaria se conservan como evidencia reportada; no se les atribuye un log reproducible cuando el log no está archivado. La existencia de una prueba o captura no prueba por sí sola que la aserción pasó ni que la pantalla recibió aprobación visual.

Este documento no ejecutó pruebas, no abrió rutas, no llamó a API, no modificó datos y no cambió código. El worktree ya contenía numerosos cambios previos; se preservaron sin limpiar ni restaurar.

### Claves de procedencia

- **REPORTADO:** resultado reciente proporcionado para esta reconciliación; no se encontró el log de ejecución correspondiente en el checkout.
- **ARCHIVO VERIFICADO:** archivo fuente, test, especificación E2E o artefacto externo encontrado en el checkout/estado local. Verificar que existe no equivale a reejecutarlo.
- **CHROME REPORTADO + ARTEFACTO:** resultado autenticado informado y capturas compatibles encontradas. Las imágenes no se revisaron visualmente en este trabajo; siguen siendo candidatas.
- **NO DISPONIBLE:** no se encontró evidencia local suficiente o una dependencia impide completar el recorrido.

`VISUAL_APPROVED` no se deduce de ninguna prueba o captura. Requiere revisión humana explícita.

## Fuentes consultadas

- `AGENTS.md` y `.agents/skills/{portal3-native-module,checador-v2-functional-slice}/SKILL.md`.
- Contratos/freeze: `docs/architecture/CHECADOR_V2_ARCHITECTURE_FREEZE_V1.0.md`, `docs/architecture/CHECADOR_V2_PORTAL3_JOURNEY_INCIDENTS_FREEZE_V1.0.md` y los contratos P3-F/P3-H aplicables.
- Referencias históricas: `docs/status/PORTAL3_CHECADOR_V2_FUNCTIONAL_CLOSURE_MASTER_V8J.md`, `docs/status/PORTAL3_CHECADOR_V2_LOCAL_UAT_GATE_V8K.md` y `docs/status/CHECADOR_V2_PORTAL3_STATUS.md`.
- Fuentes locales pertinentes: `routes/web.php`, `app/Controllers/ChecadorController.php`, `app/Services/ChecadorDailyReadService.php`, `app/Services/WorkerWorkplaceAssignmentResolver.php`, `app/Services/ChecadorAttendanceRosterService.php`, `app/Services/ChecadorAccessService.php` y servicios específicos de los módulos.
- E2E vigente: `tests/e2e/specs/attendance.spec.mjs`, `incidences.spec.mjs` y `punch-report.spec.mjs`. Estas son las tres especificaciones Playwright actuales; no hay una especificación integrada de Playwright para cada uno de M01–M09.
- Evidencia de navegador fuera del repo: `/home/silvinol/.local/state/portal3-e2e/artifacts/`. Se comprobó el inventario y las fechas; las dimensiones se verificaron en muestras de PNG. No se inspeccionaron visualmente ni se aprobaron.

Los documentos V8J/V8K se mantienen como contexto fechado, no como fuente superior a las pruebas posteriores. V8K informó que su proceso CLI tenía cerrado el flag de POST en aquella ejecución; el valor del proceso actual no se verificó y no se debe extrapolar.

## Matriz M01–M09

| Módulo | Código y regresiones | QA sintético | Chrome autenticado | Validación canónica / externa | Visual y bloqueos actuales |
|---|---|---|---|---|---|
| **M01 — Horarios y programación semanal** | Se conserva la evidencia previa de interfaz, ACL y pruebas focalizadas. La prueba V8L y las regresiones de vigencia/presentación existen en el checkout. No se repitieron ni se asigna aquí un conteo no incluido en el reporte reciente. | Los fixtures no prueban cobertura laboral histórica real. | Hay evidencia previa de lectura/interfaz reportada; la matriz semanal completa no está validada. | La consulta sigue limitada por el tope de lectura y por cobertura histórica. El valor 250 y su fila centinela son un límite del mecanismo, no prueba del total real de trabajadores; no afirmar un total a partir de ese resultado. | **PENDING_VISUAL_APPROVAL. BLOCKED_DATA para la matriz.** Cierre funcional: padrón y horarios resueltos para cada fecha sin inferir membresías, con cobertura honesta y alcance autorizado. |
| **M02 — Incidencias** | **REPORTADO:** creación QA de Bruno (1005), 28/10/2026, completó alta, readback, búsqueda, detalle e historial; Playwright 4/4 y Quality Gate 30/0/0. **ARCHIVO VERIFICADO:** existen `ChecadorPersistentQaFixtureService.php`, `ChecadorPersistentQaIncidenceStore.php`, la integración QA y `tests/e2e/specs/incidences.spec.mjs`. No volver a crear el registro. | La incidencia de alta pertenece al almacén QA local aislado; no se afirma escritura canónica. El resultado de aislamiento viene del reporte de ejecución, no de un log archivado aquí. | **CHROME REPORTADO + ARTEFACTO:** hay capturas de Incidencias para ambos perfiles, escritorio y móvil, detalle y búsqueda en `m04-regression-and-navigation-closure-20261030/`. Son artefactos candidatos y no documentan por sí mismos el readback del registro creado. | Persistencia canónica no probada ni necesaria para el escenario QA. Mantener permisos de lectura/creación distintos. | **PENDING_VISUAL_APPROVAL.** Alta QA/readback y regresiones de alcance original salen del backlog; no duplicar el registro. Cierre operativo futuro requiere revisar flags/aislamiento si se repite otro escenario, no recrear este. |
| **M03 — Reportes de marcaciones** | **REPORTADO:** búsqueda por ID, alias y localidad corregida; regresión 51/51; E2E dedicado con ambos perfiles; CSV, paginación, detalle y responsive; Quality Gate integrado 6/6 recorridos. **ARCHIVO VERIFICADO:** `punch-report.spec.mjs` y los tests locales del reporte existen. | Las pruebas de lectura/CSV son locales; no convierten el reporte en verificación de la fuente de otro entorno. | **CHROME REPORTADO.** Hay capturas de Dirección a 1440, 1024, 768 y 375 px, detalle y un CSV en `qa-29-regression-remediation-20261030/`. No se encontró el mismo conjunto de capturas de Encargado; su recorrido se conserva como reportado, no como archivo de captura. El contenido del CSV no se leyó en esta reconciliación. | La UAT local informada se acepta como hecha; no equivale a prueba remota ni a liberación. Alcance de tabla, filtros y exportación debe mantenerse idéntico por perfil. | **PENDING_VISUAL_APPROVAL.** UAT local y tarea de búsqueda salen del backlog; no repetirlas automáticamente. Falta cualquier gate de otro entorno/fuente autoritativa que se pretenda habilitar. |
| **M04 — Jornadas / Journey** | **REPORTADO:** neutralidad QA, trazabilidad y modal corregidos; P04D 41/41, P3F 35/35, P3H 52/52 e integración QA completa PASS. Los tests P04D/P3F/P3H están presentes en el checkout. | QA sigue identificado como no canónico y no evaluado cuando corresponda; no fabricarlo como estado oficial. | **CHROME REPORTADO + ARTEFACTO:** hay capturas de ambos perfiles del 30/10, escritorios 1440, móviles 375, detalle y modal en `m04-regression-and-navigation-closure-20261030/`. | **EVALUACIÓN CANÓNICA REAL: PENDIENTE.** No invocar `POST /api/v2/journeys/evaluate` como parte de una lectura o de la reconciliación. | **PENDING_VISUAL_APPROVAL.** Correcciones/regresiones visuales M04 salen del backlog; permanece pendiente la evaluación oficial, con autorización y frontera segura separadas. |
| **M05 — Rol de comodines** | **REPORTADO:** mapeo físico/canónico corregido; pruebas aisladas de mapeo 9/9, seguridad 11/11 y Access Foundation 57/57. Los archivos de prueba/servicio están en el checkout. | La tabla y paginación con datos se probaron en un harness sintético, separado de los registros productivos/locales. Hay capturas `synthetic-table-*`. | **REPORTADO y ARCHIVO VERIFICADO:** la auditoría `m05-role-comodines-uat-20261030/audit-summary.json` confirma GET de Dirección 200 y Encargado 403 esperado. Dirección queda en estado vacío real, sin tabla ni filas; el resumen también confirma que no hubo solicitudes de mutación y que no hubo errores de consola. Se encontraron capturas responsive de ambos perfiles y de Dirección con datos vacíos. | No se verificaron creación real de comodines ni cobertura histórica. La lectura vacía no prueba que el mapeo tenga datos históricos disponibles. | **PENDING_VISUAL_APPROVAL.** Corrección de mapeo sale del backlog. La evidencia de tabla sintética no se presenta como datos reales; no crear una programación para completar el UAT. |
| **M06 — Configuración** | **REPORTADO:** título duplicado corregido; V8M 29/29, V6B1 15/15, Access Foundation 57/57 y Quality Gate 33/0/0. Los tests están en el checkout. | No aplica como evidencia de configuración canónica modificada; UAT fue de consulta. | **CHROME REPORTADO + ARTEFACTO:** capturas de Encargado y Dirección a 1440, 1024, 768 y 375 px, más navegación a Programación semanal con Interlomas, en `m06-configuration-fix-2026-10-30-1793377016228/`. | Alcance de plantillas API no verificado. Los riesgos legacy (cifrado reversible con clave fija y validaciones legacy) siguen siendo trabajos separados; no se corrigieron aquí. | **PENDING_VISUAL_APPROVAL.** Corrección visual/regresión V8M salen del backlog; no reabrirlas sin defecto nuevo reproducido. |
| **M07 — PIN temporal** | **REPORTADO:** hardening de feedback por `request_uuid`, presentación única y `no-store`; hardening 16/16, aislamiento JWT 9/9, Chrome aislado 7 PASS. Los tests de hardening/UI existen. | El harness/respuestas aisladas no constituyen emisión ni consumo real. | **CHROME REPORTADO + ARTEFACTO:** hay capturas para ambos perfiles a 1440/1024/768/375 en `m07-temporary-pin-uat-A5qIhX/`; la UI/harness tiene además capturas de menú/filas y estados vacíos en `candidate-screenshots/checador-v2-m07-native-ui-remediation-v1*`. Ambos perfiles operativos con 403 es el resultado esperado reportado; no es prueba del flujo de PIN. | **BLOCKED_LECTOR / BLOCKED_API para integración real.** No emitir, validar o consumir un PIN real. | **PENDING_VISUAL_APPROVAL.** Hardening/presentación aislada salen del backlog. Mantener bloqueado hasta que API/Lector entreguen evidencia autorizada de solicitud, validación, consumo único y captura. |
| **M08 — Ajustes administrativos** | Sin autorización de implementación demostrada. Se mantiene **BLOCKED_GOVERNANCE**; no inferir que un cierre de pruebas o un cambio compartido autorice M08. | No se generaron evaluaciones ni datos QA para llenar la vista. | No abrir el GET con snapshot mientras el riesgo P0 siga sin resolver. | No hay contrato autorizado para historial/detalle completo y comandos. La evaluación Journey no es lectura. | **BLOCKED_GOVERNANCE + BLOCKED_API.** No proponer como autorizado ningún cambio de M08; preservar los `NO` de los freezes. |
| **M09 — Comparación con Nómina** | **REPORTADO:** aislamiento de trabajadores corregido; regresión 48/48. El test `checador_payroll_comparison_readonly_regression.php` y el resolver de membresía están en el checkout. | No usar trabajadores sintéticos como cobertura histórica canónica. | No hubo consulta autenticada de M09 en la evidencia reciente comunicada. | **BLOCKED_HISTORICAL_COVERAGE:** preflight reportado con cinco membresías para localidad 1, vigentes desde 2026-10-28 UTC; no existe periodo histórico cerrado con cobertura completa para la UAT seleccionada. La comparación sigue sin cálculos económicos no autorizados. El resultado de preflight no fue guardado como informe específico en `docs/status/`. | **PENDING_VISUAL_APPROVAL. BLOCKED_DATA.** Corrección de aislamiento sale del backlog; no repetirla. Futuro periodo y mecanismo de BD realmente read-only requieren resolverse antes de UAT. |

## Evidencia de Chrome y límites de verificación

Los artefactos externos hallados se conservan en su ubicación; no se movieron ni borraron:

- **M02/M03:** `qa-29-regression-remediation-20261030/artifacts/candidate-screenshots/`; capturas de Incidencias para Dirección, y Reportes/CSV para Dirección en cuatro tamaños. La ejecución M03 de ambos perfiles está reportada, pero no se encontró una serie equivalente de capturas de Encargado.
- **M04:** `m04-regression-and-navigation-closure-20261030/`; ambos perfiles, escritorio 1440, móvil 375, detalles y modales. Los archivos existen; no se revisaron píxeles aquí.
- **M05:** `m05-role-comodines-uat-20261030/` y `m05-native-ui-read-contract-20261030/`; el JSON de auditoría respalda los estados HTTP/permisos y vacío real descritos. Las capturas de tabla con filas son explícitamente sintéticas.
- **M06:** `m06-configuration-fix-2026-10-30-1793377016228/`; ambos perfiles, 1440/1024/768/375 y navegación semanal.
- **M07:** `m07-temporary-pin-uat-A5qIhX/` y `candidate-screenshots/checador-v2-m07-native-ui-remediation-v1*`; los tamaños existen como artefactos; la vista aislada/harness no acredita conexión real a Lector/API.
- **M01, M08 y M09:** no se encontró una captura específica de Horarios, Ajustes o Comparación con Nómina en el inventario revisado.

La presencia y resolución de una captura no prueba por sí sola que sea una captura autenticada, que el contenido sea canónico ni que la interfaz esté aprobada. Los resultados Chrome por perfil de M02–M07 arriba indicados se mantienen como reportados, con el respaldo local descrito en cada caso.

## Trabajos completados que salen del backlog repetitivo

No volver a planear como tarea nueva —salvo que aparezca una regresión reproducible—:

1. **M02:** alta QA autorizada del 28/10/2026 con Bruno 1005 y readback/búsqueda/detalle/historial. No recrear la incidencia.
2. **M03:** corrección búsqueda por ID/alias/localidad y UAT local de Reportes con CSV, paginación, detalle y responsive.
3. **M04:** neutralidad QA/trazabilidad/modal y cierre reportado de P04D/P3F/P3H e integración QA.
4. **M05:** mapeo estación física→localidad canónica y pruebas de seguridad/mapeo. Esto no cierra programación real ni cobertura histórica.
5. **M06:** título único, navegación al contexto semanal y V8M/V6B1 reportados.
6. **M07:** hardening del feedback/PIN, aislamiento y UI/harness. Esto no desbloquea el flujo real de Lector/API.
7. **M09:** aislamiento de trabajadores y regresión 48/48. Esto no resuelve la historia de membresías.
8. **M01:** conservar las comprobaciones existentes de interfaz, ACL y pruebas focalizadas; no repetirlas solo por el documento anterior. La matriz semanal continúa sin aceptación por cobertura.

Los recuentos de ejecución de los ítems 1–8 provienen de los resultados recientes proporcionados. Los artefactos/test sources hallados los respaldan parcialmente, pero, salvo el JSON de auditoría M05 y las capturas listadas, los logs detallados no están archivados aquí.

## Dependencias y bloqueos abiertos

### Historial laboral: M01, M05 y M09

- Para M01, el límite de lectura de 250 y su fila centinela no permiten inferir el tamaño total del padrón ni declarar que existan solo 250 trabajadores. Se necesita saber si se truncó la consulta y una fuente versionada con cobertura diaria.
- Para M05, mapeo correcto no es igual a datos disponibles. La adscripción base permanece separada de la cobertura temporal, sin reconstruir vigencias antiguas desde la asignación actual.
- Para M09, cinco membresías de localidad 1 con inicio 2026-10-28 UTC no cubren retrospectivamente periodos completos. No insertar historias retroactivas automáticamente.

### API V2 / Journey: riesgo P0 GET

La inspección estática del checkout confirma las siguientes rutas y condiciones en el código actual (`routes/web.php:586–592,595`; `ChecadorController.php:44,94,122,231–243,734–738,911–932,1062–1071,4031–4038,4310–4321`; `ChecadorDailyReadService.php:88–95,490–512,991–1012`):

1. `GET /departamento-operativo/recursos-humanos/checador` (workspace raíz, sin snapshot) llama a `createDailyPunchSnapshot()`. El servicio implementa esa operación como `POST /api/v2/punch-events/read-snapshots` y el controlador guarda estado de snapshot en sesión. En ese workspace el procesamiento puede continuar hasta Journey. Esto es distinto de evaluar una Journey; la semántica del snapshot y del estado de sesión debe documentarse aparte.
2. `GET /departamento-operativo/recursos-humanos/checador/jornadas` y `.../ajustes` exigen un snapshot existente. Con un snapshot válido, el procesamiento compartido recorre trabajadores observados y llama `evaluateJourney()`, que envía `POST /api/v2/journeys/evaluate`.
3. `GET /departamento-operativo/recursos-humanos/checador/asistencia` usa `readDailyRawAttendance()` para leer marcaciones. Su procesamiento tiene otra llamada a `evaluateJourney()` condicionada por `realApiPostOperationsEnabled()`.
4. El servicio bloquea los POST reales salvo que `APP_ENV` sea dev/development/local y `CHECADOR_API_REAL_POSTS_ENABLED` esté habilitado. Ese gate fue observado como cerrado en V8K el 23/10; el valor del proceso actual no se comprobó. La condición de código estática no demuestra que el despliegue actual esté cerrado.

La fase 1 de Journey está descrita como `ON_DEMAND` en `CHECADOR_V2_PORTAL3_JOURNEY_INCIDENTS_FREEZE_V1.0.md`; el endpoint oficial de evaluación sigue siendo POST y la evaluación es autoridad de API. El Freeze no se modifica aquí. Tampoco se encontró en las rutas actuales un handler Portal3 independiente para evaluación Journey; las llamadas visibles proceden del render GET compartido.

### Alcance mínimo propuesto para P0 — sin autorización de implementación

**Prioridad:** primero impedir que un GET inicie una evaluación canónica. El cambio compartido del controlador no se considera autorizado por ser de seguridad.

1. **Inventario/guard de regresión:** preparar una prueba con transporte simulado para `/checador`, `/asistencia`, `/jornadas` y `/ajustes`, con/sin snapshot según lo permita cada workspace; verificar métodos y destinos exactos, y que no se envíe `POST /api/v2/journeys/evaluate` desde GET. Esta comprobación debe ser aislada; no abrir rutas ni activar flags reales.
2. **Corrección Portal3 candidata:** separar lectura de evaluación; mientras no exista una lectura canónica autorizada de resultados persistidos, el GET solo puede mostrar datos que ya leyó y un estado neutral de Journey no evaluado/no disponible. No inventar P1/P2 ni crear un botón, permiso o ruta nuevos dentro de esta autorización.
3. **Snapshot Level 1:** tratar `POST /api/v2/punch-events/read-snapshots` desde GET como una cuestión aparte. Confirmar con el contrato/API owner si el snapshot idempotente está autorizado como lectura iniciada por navegación. Si no, no trasladarlo a otra ruta o modo silenciosamente; pedir decisión sobre la interacción de consulta.
4. **Compatibilidad:** revisar P04D/P3F/P3H, en particular las protecciones de envolvente/procedencia/eventos completos. Cambiar una expectativa solo con equivalencia contractual y conservar pruebas positivas para una respuesta canónica evaluada y negativas para QA/no evaluada.
5. **M08:** permanecer `BLOCKED_GOVERNANCE`; no abrir su GET con snapshot mientras la ejecución de Journey siga acoplada al render.

**Aprobaciones necesarias antes de cambiar el controlador:** responsable de API/dominio debe confirmar si existe una forma aprobada de leer evaluaciones ya persistidas; producto debe aceptar el estado UI neutral cuando no exista resultado; el owner del contrato debe decidir si la creación de snapshot Level 1 puede seguir originándose por navegación. Cualquier endpoint o cambio al Freeze requiere aprobación arquitectónica específica. Esta reconciliación no otorga esas autorizaciones.

**Pruebas mínimas después de una autorización futura:** GET simulado no efectúa evaluación; snapshot contractualmente autorizado se comprueba separadamente; evaluación permanece bajo su POST explícito autorizado; respuesta canónica evaluada conserva estado, eventos, provenance/references; QA o `evaluated=false` no se presenta como resultado oficial; ACL/localidad/CSRF permanecen intactos; regresiones P04D/P3F/P3H y Quality Gate solo después de demostrar con transporte stub que sus GET no harán POST real.

## Secuencia de cierre reconciliada

### Próximas tres prioridades

1. **P0 — `CV2_GET_SIDE_EFFECT_GUARD`:** obtener decisión de API/producto sobre la frontera anterior y autorizar un alcance mínimo. Hasta entonces no ejecutar Quality Gate/rutas que puedan activar el flujo.
2. **M01 — `CV2_M01_SCHEDULE_COVERAGE`:** resolver con fuente RH el alcance de lectura, la interpretación del límite/fila centinela y la cobertura histórica; mantener sin inferencias. Solo después validar la matriz semanal.
3. **M09 — `CV2_M09_HISTORICAL_COVERAGE`:** encontrar un periodo cerrado cubierto por membresías versionadas y preparar DB de solo lectura efectiva. El manejo de periodos futuros necesita regla contractual separada.

**En paralelo:** mantener M08 en revisión de gobierno sin implementación; pedir a API/Lector evidencia de integración M07 sin PIN real; resolver el alcance de plantillas de M06 con su responsable; conservar aprobación visual como revisión humana separada.

### Dependencias externas en paralelo

- **RH/datos:** publicar cobertura versionada por fecha/localidad; sin retroactividad automática.
- **API V2:** definir una lectura segura para resultados Journey existentes si se requiere mostrarlos sin evaluarlos; cualquier endpoint es sujeto a aprobación contractual.
- **Lector/API:** preparar entorno y protocolo para integración PIN, sin credenciales reales hasta tener gate autorizado.
- **Infraestructura:** evaluar identidad DB de solo lectura y alcance de datos para M09.
- **Gobierno:** resolver permiso/frontera M08. No modificar el Architecture Freeze unilateralmente.

## Criterios de aceptación

### Aceptación local por módulo

- Separar y registrar `CODE_VERIFIED`, `FUNCTIONAL_VERIFIED`, `QA_VERIFIED`, `BROWSER_VERIFIED`, `CANONICAL_VERIFIED`, `EXTERNAL_INTEGRATION_VERIFIED` y `VISUAL_APPROVED`; ningún nivel se hereda de otro.
- Los conteos reportados se enlazan a su log/prueba y fecha cuando se archiven; la fuente de datos queda rotulada como mock, fixture QA, base local o API real.
- Cada recorrido autenticado prueba permisos efectivos por perfil y localidad. `403` esperado cuenta como evidencia de denegación, no como acceso funcional al contenido.
- Los GET no originan evaluación Journey canónica. Los resultados `evaluated=false`, cobertura incompleta y ausencia de datos permanecen diferenciados.
- M01/M05/M09 usan membresías históricas comprobables; ninguna asignación, horario o nómina se infiere para cerrar una tabla.
- M02 QA es readback de almacenamiento aislado y no una incidencia oficial; la incidencia ya creada no se duplica.
- M03 conserva coincidencia de alcance entre consulta, paginación y CSV.
- M07 no se marca integrado hasta contar con evidencia autorizada del flujo Lector/API completo y manejo de PIN de un solo uso.
- M08 no se modifica ni se abre para UAT con operaciones persistentes hasta resolver gobierno/API.
- Capturas de escritorio/móvil son candidatas; la aprobación visual la da la persona usuaria, no la herramienta.

### Gate de liberación

No marcar listo para producción hasta que estén resueltos los bloqueos de datos/API/Lector/gobierno aplicables al despliegue, el usuario de base tenga mínimo privilegio, el alcance ACL sea comprobado en el destino, las regresiones focalizadas no tengan fallos funcionales abiertos y haya UAT autenticada y aprobación visual explícita. La UAT local y QA no certifican otro entorno. No se autoriza despliegue en este plan.

## Resumen de reconciliación

```yaml
TASK: CHECADOR_V2_MASTER_CLOSURE_EVIDENCE_RECONCILIATION_V2
MODULES_RECONCILED: 9

PREVIOUS_PLAN_OMISSIONS:
  - Trataba M02 y M03 como UAT pendiente aunque se reportó cierre reciente.
  - No incorporaba cierres recientes de regresión/navegador M04, M05, M06 y M07.
  - No incorporaba la corrección de aislamiento M09 ni su preflight histórico posterior.
  - Interpretaba el límite de M01 como afirmación del total de trabajadores; el límite/centinela no demuestra el total real.
  - No separaba adecuadamente resultado de ejecución reportado, archivo presente, evidencia canónica y aprobación visual.

RECOVERED_EVIDENCE:
  - M02 creación QA Bruno 1005 con readback, 4/4 Playwright, Quality Gate 30/0/0 (reportado; no repetir).
  - M03 regresión 51/51, ambos perfiles, CSV/paginación/detalle/responsive, Quality Gate 6/6 (reportado; artefactos de Dirección encontrados).
  - M04 P04D 41/41, P3F 35/35, P3H 52/52, QA integration y Chrome ambos perfiles (reportado; capturas desktop/mobile ambos perfiles encontradas).
  - M05 mapeo 9/9, seguridad 11/11, Access Foundation 57/57; Dirección GET 200, Encargado 403; estado real vacío y tabla sintética separada (reportado y parcialmente verificado en audit-summary.json/capturas).
  - M06 V8M 29/29, V6B1 15/15, Access Foundation 57/57, Quality Gate 33/0/0; Chrome/contexto semanal (reportado; capturas de ambos perfiles en cuatro tamaños encontradas).
  - M07 hardening 16/16, JWT isolation 9/9, Chrome aislado 7 PASS y perfiles 403 esperado (reportado; capturas de ambos perfiles en cuatro tamaños y harness encontradas).
  - M09 aislamiento 48/48 y cobertura histórica limitada a cinco membresías con inicio 2026-10-28 UTC (reportado; código/test presentes).
  - M01 evidencia previa de interfaz/ACL/tests conservada, con matriz bloqueada y total de plantilla no inferido (reportado).

EVIDENCE_NOT_VERIFIABLE:
  - No se encontró log de ejecución por tarea para los conteos recientes; se preservan como reportados.
  - No se revisó visualmente el contenido de las capturas; no existe aprobación visual humana registrada aquí.
  - La serie de M03 archivada presenta Dirección; el E2E de ambos perfiles está reportado.
  - No hay UAT canónica de M04, integración API/Lector de M07, ni UAT histórica de M09.

COMPLETED_TASKS_REMOVED_FROM_BACKLOG: M02-QA-create-readback, M03-local-UAT, M04-visual-regressions, M05-mapping-fix, M06-title/V8M, M07-local-hardening/UI, M09-worker-isolation.
REMAINING_FUNCTIONAL_BLOCKERS: M01-weekly-coverage; M04-canonical-evaluation-boundary; M05-real-programming/history; M06-template-scope; M07-external-integration; M09-historical-period.
REMAINING_GOVERNANCE_BLOCKERS: M08 remains BLOCKED_GOVERNANCE; GET-side-effect remediation requires explicit scope/approval.
REMAINING_VISUAL_APPROVALS: M01-M09 (candidate files are not human approval).

P0_GET_RISK: Confirmed in current source: GET /checador can initiate POST /punch-events/read-snapshots; GET /jornadas and /ajustes with a valid snapshot can reach POST /journeys/evaluate; GET /asistencia can evaluate under its gate. Current runtime flag was not checked.
P0_REQUIRED_AUTHORIZATION: API/product decision on snapshot and Journey read/evaluation flow; owner approval before shared controller behavior changes; no M08 authorization.

NEXT_THREE_PRIORITIES:
  - CV2_GET_SIDE_EFFECT_GUARD
  - CV2_M01_SCHEDULE_COVERAGE
  - CV2_M09_HISTORICAL_COVERAGE

DOCUMENT_CREATED: docs/status/CHECADOR_V2_NINE_MODULES_CLOSURE_RECONCILED_20261030.md
FILES_MODIFIED: only this authorized document
CANONICAL_DATA_WRITES: NONE
API_CALLS: NONE
FINAL_RESULT: PLAN_RECONCILED_WITH_P0_DATA_AND_GOVERNANCE_BLOCKERS
```
