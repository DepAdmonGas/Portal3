# Checador V2 — Propuesta de integración canónica de Rol de Comodines

**Estado:** `PROPUESTA TÉCNICA ACTUALIZADA — REGLAS DE NEGOCIO APROBADAS; ADDENDUM TÉCNICO PENDIENTE DE APROBACIÓN`
**Proyectos:** Portal3 y API V2
**Propósito:** preparar el contrato mínimo para que una cobertura de Rol de Comodines pueda aportar programación efectiva a Journey y sus vistas derivadas.
**Implementación autorizada:** NO
**Cambios de código, ACL, esquema o datos realizados por esta propuesta:** NO

Esta propuesta no cambia Architecture Freeze V1.0, J1, J2, M03, M04, PIN temporal ni las incidencias existentes. No autoriza un escritor, migración, grant ni despliegue. Los acuerdos que se exponen como recomendación requieren aprobación expresa antes de implementarse.

## 1. Decisión ejecutiva

**La programación M03 por sí sola no puede expresar una cobertura de otro centro de forma segura.** Se pueden reutilizar las plantillas/versiones M04, el contrato de asignaciones de turnos y el resolver de API V2, pero primero se necesita una relación efectiva y auditable que distinga la adscripción habitual del trabajador de una cobertura temporal en otro Workplace.

El responsable aprobó el 2026-11-09 que Dirección de Operaciones autorice las coberturas y que el turno autorizado prevalezca temporalmente sobre el horario habitual, conservando la adscripción. La alternativa recomendada sigue siendo **B, con reutilización del motor existente**: ampliar contractualmente el modelo canónico de `WorkerWorkplaceAssignment` para registrar la cobertura temporal; después, usar las plantillas M04 y las asignaciones `TEMPORAL` existentes, ampliando su autorización y comprobación de pertenencia de forma explícita. No se propone una segunda tabla de horarios ni un segundo evaluador de turnos.

La autoridad aprobadora y la precedencia temporal ya no están pendientes. El permiso para solicitar continúa siendo opt-in y requiere autorización/grant explícito. Los detalles contractuales y la propuesta física están en [CHECADOR_V2_COMODINES_WORKPLACE_COVERAGE_ADDENDUM_DRAFT_V1.0.md](CHECADOR_V2_COMODINES_WORKPLACE_COVERAGE_ADDENDUM_DRAFT_V1.0.md); este addendum prevalece sobre las recomendaciones de autoridad/precedencia anteriores de esta propuesta, sin modificar los contratos congelados.

## 2. Contratos aprobados y límites

### 2.1 Contrato verificado

- **Architecture Freeze V1.0, §17 `WorkerWorkplaceAssignment`:** requiere conceptualmente una relación histórica de trabajador y Workplace para cambios de centro, coberturas, asignaciones temporales y trabajo en múltiples centros. Su diseño físico definitivo está expresamente pendiente. §14 separa `ShiftAssignment` de `ScheduledShift`; §18 asigna al Motor de Jornada el consumo del horario programado junto con eventos, incidencias y reglas. §37 permite políticas de asistencia en otro centro, pero deja su política definitiva pendiente.
- **J1 canónico, §§2, 4–8, 9, 13–16 y 18–20:** mantiene capas separadas para evento raw, programación efectiva, Journey e incidencia; usa `op_rh_personal.id` como identidad interna; interpreta `id_estacion_resuelta` en el espacio canónico de `op_rh_localidades.id`; define Journey fase 1 como `ON_DEMAND`; separa incidencias y resoluciones; y requiere autorización con alcance de estación. J1 §8 deja pendiente la precedencia de una asignación especial frente a la semanal.
- **J2 V1.0, §§2.1–2.4, 3–5 y 11:** exige horario histórico/bitemporal, descanso explícito y estados neutrales para falta de evidencia. §2.3 excluye la programación especial Legacy del resolver de fase 1. La ausencia de una asignación no es descanso ni falta; el horario predeterminado de estación está prohibido como fallback. La fecha laboral usa la fecha local de inicio del turno en el timezone contractual del Workplace.
- **Addendum M03/M04 V1.0, §§3–7:** autoriza `HABITUAL` y `TEMPORAL` por trabajador, Workplace, día e intervalo; prevalece el temporal durante su vigencia y luego se recupera el habitual. Exige cobertura canónica de membresía para todo el intervalo, conserva actor/historial y restringe la edición de M03 al alcance de estación vigente. No define cobertura laboral de un centro distinto al de adscripción.
- **Shift Scheduling Service & API Contract V1.0, §§1–6:** ya define asignaciones de turno por `id_personal`, `id_estacion`, weekday y vigencia; soporta multi-Workplace, resolución read-only por trabajador/Workplace/fecha y valida colisiones físicas. Su §3 aplica permisos y alcance de estación; §§4.2–4.3 definen escritura de asignación, lectura y resolución. Este contrato no define Rol de Comodines, la distinción entre Workplace habitual y de cobertura ni quién puede programar a través de localidades.
- **Shift Scheduling Authorization Addendum V1.0, §§2–5:** limita `checador.programacion.editar` al personal verificado dentro del alcance de estación resuelto en servidor. El Encargado está limitado a su estación activa; Dirección conserva lectura. No concede a un Encargado autoridad sobre otra localidad por el mero hecho de que haya una cobertura.

### 2.2 Código observado

- Portal3 conserva el flujo de Rol de Comodines en `RolComodinesController`, `RolComodinesService`, los modelos `RhRolComodines`/`RhComodinesDia`, la vista `role-comodines.php` y sus inicializadores JS. El modelo del rol guarda periodo, estación/centro de cobertura, estado, trabajador y día, pero no referencia plantilla/version de turno ni una asignación efectiva de Workplace versionada.
- La ruta de comodines tiene su propio conjunto de autorizaciones institucionales, pero la búsqueda actual de trabajadores se limita al roster de la estación autorizada. No representa una relación trabajador-centro de origen distinta del centro de cobertura.
- La lectura de Asistencia del día puede mostrar el dato de comodín como información, con una etiqueta que deja claro que la autorización no está acreditada. Esa lectura no crea un horario efectivo.
- El servicio de Portal3 mantiene cerrado el camino de escrituras directas para programación canónica mientras API V2 no disponga de un escritor autorizado de cobertura. No se identificó un endpoint API V2 de Rol de Comodines aprobado.
- El servicio Portal3 de asignaciones de Workplace escribe hechos versionados de adscripción; no constituye por sí mismo un escritor aprobado de cobertura laboral concurrente. La implementación API V2 de validación de membresía exige hoy que el intervalo solicitado quede cubierto de forma única por la relación canónica leída. Esa validación no equivale al contrato de una cobertura temporal superpuesta a una adscripción habitual.
- El resolver de turnos existente recibe explícitamente `(id_personal, id_estacion, fecha_local)` y prioriza `TEMPORAL` sobre `HABITUAL` **dentro del Workplace consultado**. No selecciona una cobertura por sí solo ni convierte una fila del rol en `ShiftAssignment`.

Lo observado en código describe implementación; no crea permisos ni aprobación contractual.

## 3. Modelo mínimo propuesto

### 3.1 Identidad y dos localidades

Una cobertura canónica debe referenciar:

| Dato | Semántica propuesta |
|---|---|
| `worker_id` | `op_rh_personal.id`; nunca `id_usuario`, nombre o número de lista. |
| `home_workplace_id` | Adscripción habitual acreditada por la historia versionada, sin reescribirla por cubrir otro centro. |
| `coverage_workplace_id` | Workplace canónico al que se cubrirá, distinto del hogar cuando aplique. Resolver y validar como `op_rh_localidades.id`; no equiparar IDs con `tb_estaciones.id` o `numlista`. |
| `shift_template_id` + versión | Plantilla M04 exacta y vigente para el Workplace de cobertura. No copiar ni crear un catálogo paralelo. |
| `effective_local_date_from/to` | Intervalo civil semiabierto `[desde, hasta)` en el calendario local contractual del Workplace de cobertura. |
| actor, motivo, recorded time | Actor administrativo validado desde JWT delegado, motivo requerido, `recorded_at_utc` y revisiones append-only. |
| idempotency/correlation | Clave por operación y huella de contenido; reintento idéntico no duplica y clave con otro contenido se rechaza. |

La fila de cobertura no cambia la adscripción permanente del trabajador. No se interpreta la estación física del lector como Workplace programado ni al revés.

### 3.2 Propietario y autorización de escritura

**Propietario canónico propuesto:** API V2. Portal3 autentica al usuario en su sesión y delega identidad mediante el JWT server-to-server existente; API V2 valida sujeto activo, permiso funcional y alcance de origen/destino. No hay SQL directo desde Portal3, navegador o un escritor Legacy.

**Actor autorizado: decisión pendiente.** La recomendación segura es una capacidad explícita de planificación de coberturas, concedida a un rol de RH/planificación autorizado para ambos Workplaces del intervalo. Un Encargado local no debe recibir autoridad sobre el Workplace de cobertura por ser responsable del Workplace de origen, ni el Encargado del destino debe poder cambiar la adscripción de origen. Hasta que el responsable defina el actor/permiso y su ámbito, API debe denegar la creación y modificación. No se asignan permisos en esta propuesta ni se copian grants existentes.

La autorización debe comprobar por separado: permiso para programar el trabajador en el origen, autoridad para planear en el destino, acceso al turno del destino, estado activo del trabajador, pertenencia base vigente y límites temporales. Los IDs que envía el cliente nunca acreditan ninguna de esas condiciones.

### 3.3 Escritura, cambios y conflictos

API V2 debería ofrecer una operación canónica versionada para crear, cambiar o cancelar la cobertura. La operación debe ser transaccional, idempotente, append-only y auditable; debe comprobar referencias canónicas, vigencia, permisos y conflictos antes de confirmar. Reintentos idénticos recuperan el mismo resultado. Un contenido distinto con la misma clave se rechaza. Cambiar turno, periodo o Workplace genera una nueva revisión o una transición auditable; no se actualiza silenciosamente el hecho histórico.

El contrato deberá decidir y validar al menos:

1. Si dos coberturas incompatibles del mismo trabajador pueden solaparse; por defecto, rechazar solapamientos para el mismo trabajador cuando las jornadas se intersecten, incluso si Workplace difiere.
2. Si las colisiones se calculan por intervalos UTC derivados del timezone de cada Workplace (recomendado), incluyendo cambio estacional y turnos nocturnos, y qué hacer si timezone o plantilla no se resuelve: rechazar/fallar `UNKNOWN`, nunca asumir.
3. Si una cobertura temporal de Workplace B suprime el horario esperado del Workplace A para esas fechas. Este comportamiento no está congelado en J1 §8. No generar dos jornadas esperadas ni transformar la cobertura en descanso.
4. Qué sucede con una cobertura cancelada, trabajador dado de baja, cambio de adscripción, plantilla desactivada o modificación posterior de una plantilla. Las referencias históricas deben conservar la versión exacta; no editar fechas previas para simular una cancelación.
5. Cuál es la relación entre periodo administrativo del Rol de Comodines y fechas efectivas de trabajo. La fuente del periodo debe convertirse a fechas locales explícitas y validadas, no inferirse de una fecha sin zona.

Una vez aceptada la cobertura, Portal3/API deben reutilizar el escritor existente de Shift Assignment `TEMPORAL` para la jornada/weekday del destino, siempre que se extienda su contrato para reconocer la cobertura como fundamento válido y el mismo acto transaccional mantenga la relación entre cobertura y asignación efectiva. No se habilita el escritor M03 actual como una vía que eluda membership o alcance.

### 3.4 Resolución efectiva, Journey, incidencias y marcaciones

- API V2 debe seguir siendo la única autoridad del horario efectivo. La resolución debe incorporar una cobertura válida mediante su relación versionada y la referencia exacta de `ShiftAssignment`/`ShiftTemplate`, o rechazar si hay falta de cobertura, solapamiento, conflicto o periodo no acreditado. No implementar un segundo evaluator en Portal3.
- Un addendum de J2 debe definir cómo consulta el resolver esta nueva fuente versionada V2, qué fuente gana frente al habitual y cómo responde cuando hay dos Workplaces candidatos. J2 §2.3 seguirá excluyendo la programación especial Legacy; J1 §8 no permite asumir la precedencia candidata que enumera.
- Journey permanece on-demand según J1 §9 y debe consumir la salida canónica del resolver con el Workplace y timezone laborales efectivos. No se materializa una jornada ni se duplica una fila semanal por guardar el rol. La materialización permanece fuera de esta propuesta.
- Las incidencias no se generan automáticamente por el registro de comodín. Una incidencia que afecte esa jornada debe enlazarse al Journey canónico del trabajador/fecha/Workplace y conservar su historial. La fuente del rol es contexto administrativo, no una resolución de incidencia ni un PunchEvent.
- PunchEvents siguen siendo hechos raw inmutables. La identidad del dispositivo se conserva por la identidad canónica `id_dispositivo`/fuente de evento. `id_estacion_resuelta` continúa siendo el Workplace canónico resuelto para la marcación según J1 §5; no se sustituye por `coverage_workplace_id`. La regla de política para una checada en otro centro está pendiente en Architecture Freeze §37 y no queda resuelta por autorizar una cobertura.
- No afirmar ausencia, falta o conflicto basándose en la fila del rol sola. Deben seguir aplicándose los gates independientes de identidad, timezone, integridad y completitud J1/J2.

## 4. Alternativas

| Criterio | A. Reutilizar M03 sin cambiar el contrato | B. Extender `WorkerWorkplaceAssignment` y reutilizar el resolver |
|---|---|---|
| Compatibilidad contractual | **Insuficiente.** M03 exige membership en el Workplace destino y autoridad del actor en su ámbito; J2 §2.3 excluye programación especial del resolver fase 1. | **Compatible como evolución del concepto ya congelado en Freeze §17**, pero requiere addendum contractual de API/J2 aprobado. No se modifica el texto del Freeze. |
| Portal3 | Convertir el rol en una llamada M03 actual no tiene un fundamento versionado para cobertura; riesgo de asociar de forma errónea el hogar y el destino. | Mantener el UI/entidad Rol; enviarla al escritor canónico API con identidad, destino, periodo, turno y clave de idempotencia. Leer readback canónico. Sin SQL de programación. |
| API V2 | No hay escritor de rol detectado; el resolver no consume `op_rh_comodines_dia`. | Añadir una operación versionada para cobertura y ampliar membership/effective schedule para consumirla. Reutilizar plantilla, assignment, validaciones y resolver de turnos existentes; no construir otro evaluator. |
| Esquema | No resuelve persistencia/linaje efectivo. | **Se requiere ampliación física aditiva** salvo que API demuestre una relación ya existente que diferencie hogar/cobertura, actor, vigencia y recorded time. La inspección observada no encontró esos campos en los datos/modelos actuales de Rol ni un esquema aprobado para cobertura; el diseño DDL queda pendiente. No se propone ni ejecuta migración aquí. |
| Riesgos | Falsa pertenencia, bypass de ACL, dos horarios esperados, pérdida de adscripción permanente, cambios retroactivos y errores de concurrencia. | Riesgo principal: definición contractual incompleta de actor, precedencia, intervalos entre timezones y estado cancelado. Se mitiga con fail-closed y aprobación antes de código. |
| Asistencia del día | Seguiría mostrando rol informativo sin turno acreditado; no convertiría la cobertura en horario. | Podrá mostrar cobertura y horario efectivo únicamente si el API devuelve relación/procedencia completa y resolución válida. No se infiere turno desde el rol. |
| Journey | No puede elegir legalmente el Workplace/horario de la jornada. | Consume horario y Workplace canónicos resueltos por API. Journey sigue on-demand; no se materializa por esta tarea. |
| Incidencias | No hay vínculo fiable a la jornada cubierta. | Incidencias siguen unidas al Journey de fecha/Workplace canónico resultante; el comodín por sí solo no crea ni resuelve incidencias. |
| Pruebas y regresión | Pruebas no podrían validar cobertura sin fixtures que simulen una pertenencia que producción no acredita. Alto riesgo de ocultar controles. | Pruebas de writer, concurrencia, idempotencia, ACL cruzada, cambios/cancelación, timezone, colisión destino-origen, Journey e incidencia. Mayor alcance de contrato, pero un solo resolver canónico. |

**Recomendación:** B. La opción A sólo sería aceptable si “comodín” fuese una mera etiqueta para una asignación existente dentro de la misma localidad y con membresía y permiso ya válidos. El caso solicitado de cobertura en otro Workplace no satisface esas condiciones.

## 5. Regresiones reportadas y calidad de evidencia

| Hallazgo | Clasificación actual | Evidencia/acción |
|---|---|---|
| Conteo esperado de 18 frente a 19 personas observadas en Autolavado | **Diferencia de alcance de roster no resuelta; no demuestra por sí sola una regresión de cobertura.** | La consulta `current_roster_only` acredita la fotografía actual, no pertenencia histórica para fechas anteriores. El conteo de prueba debe fijar fecha, fuente y estado activo; no cambiarse a 19 para forzar PASS ni excluir a alguien sin evidencia. Comparar con padrón RH y membresías históricas para resolverlo antes de escritura. |
| Regresión del directorio Checador | **No atribuida a Rol de Comodines con la evidencia disponible aquí.** | No se encontró una causalidad contractual ni una prueba de regresión reproducida en esta revisión focal. Registrar URL, actor, localidad, respuesta y artefacto de prueba al reproducir; no cambiar ACL por inferencia. |
| Cobertura histórica con padrón `current_roster_only` | **Limitación de evidencia conocida, no dato histórico.** | El padrón actual no prueba la pertenencia efectiva en una fecha pasada. Solo las versiones canónicas de Worker–Workplace y sus intervalos pueden hacerlo; faltante/ambigüedad da `UNKNOWN`/`DATA_INCOMPLETE`. No crear membresías retroactivas para hacer pasar la prueba. |
| Ausencia de turno efectivo de comodín | **GAP funcional y contractual real.** | Las filas actuales de Rol no contienen referencia de turno/version efectiva y el resolver no las lee. No declarar un turno o Journey válido a partir de ese registro. La solución requiere el camino B aprobado e implementado. |

## 6. Pruebas de aceptación requeridas después de aprobación

1. Verificar actor JWT, permiso funcional y alcance conjunto origen/destino; denegar Encargado que sólo tenga alcance local y parámetros de Workplace falsificados.
2. Confirmar identidad `op_rh_personal.id`, estado activo y adscripción habitual histórica; no aceptar `id_usuario` como identidad canónica.
3. Crear una cobertura con plantilla M04 exacta/versionada, fecha inicial/final local, motivo, actor, `recorded_at_utc` y clave de idempotencia. Reintento idéntico no crea copia; mismo token con payload distinto da conflicto.
4. Rechazar localidad inexistente, mapeo ambiguo, trabajador no adscrito, fecha inválida, plantilla no habilitada en destino, versión incorrecta o falta de zona horaria.
5. Validar intervalo semiabierto y primera/última fecha; timezone de Workplace y turno nocturno a través de DST; no timestamp fijo ni fechas retrospectivas inferidas.
6. Probar colisión con horario habitual en origen y asignaciones del destino. El resultado sigue bloqueado hasta aprobar supresión/prioridad; después, comprobar un único horario efectivo por fecha según regla aprobada.
7. Probar edición/cancelación como nuevas revisiones, concurrencia, error transaccional y readback. La adscripción permanente y versiones históricas permanecen iguales.
8. Resolver a través del endpoint canónico de horario y después leer Journey on-demand; verificar Workplace efectivo, versión de template, timezone, procedencia y neutralidad de `UNKNOWN`.
9. Verificar M05/Asistencia del día usa resolución API, muestra el contexto de cobertura y nunca modifica el dispositivo/estación de PunchEvent.
10. Verificar que incidencia se relaciona sólo con el Journey/Workplace canónico resultante; el comodín no crea una incidencia ni cambia una resolución.
11. Repetir con Dirección de solo lectura y confirmar que otros perfiles/localidades conservan grants actuales.
12. Comparar históricos previos al periodo de aprobación para garantizar que no se reatribuyen marcaciones, asignaciones, membresías ni jornadas.

Las pruebas de desarrollo deben usar trabajadores QA aislados y evidencia real de MariaDB; mocks/fixtures no sustituyen readback ni E2E. Ninguna prueba puede escribir turnos de trabajadores ordinarios sin autorización explícita.

## 7. Decisiones requeridas antes de implementación

La autoridad de Dirección y la precedencia temporal ya están aprobadas. El addendum detallado [CHECADOR_V2_COMODINES_WORKPLACE_COVERAGE_ADDENDUM_DRAFT_V1.0.md](CHECADOR_V2_COMODINES_WORKPLACE_COVERAGE_ADDENDUM_DRAFT_V1.0.md) define el writer, el patrón semanal, los permisos propuestos sin grants, el resolver y las reglas de cancelación/conflicto. Antes de implementar sigue pendiente la aprobación contractual cruzada de su SHA, la concesión separada de cualquier grant, y el cotejo de DDL/tipos con `information_schema`. Hasta entonces Rol de Comodines continúa siendo información administrativa, no fuente de horario efectivo.

## 8. Registro de alcance de esta propuesta

- Documentación agregada únicamente en Portal3; API V2 no se editó.
- Sin cambios a PHP, JavaScript, CSS, rutas, pruebas, ACL, esquema, migraciones, `bd_admongas`, producción, M03/M04, PIN temporal, Journey, incidencias o PunchEvents.
- No se concede ni se presume ninguna autorización de implementación.

## 9. Estado solicitado

```yaml
COMODINES_M03_CANONICAL_REUSE: NOT_POSSIBLE
COMODINES_EFFECTIVE_SHIFT_CONTRACT: NEW_REQUIRED
COMODINES_API_WRITER_REQUIRED: YES
COMODINES_SCHEMA_CHANGE_REQUIRED: YES
COMODINES_LOCALITY_AUTHORIZATION: DEFINED
COMODINES_JOURNEY_INTEGRATION: DEFINED
COMODINES_INCIDENCE_INTEGRATION: DEFINED
RECOMMENDED_SOLUTION: B
ARCHITECTURE_CHANGE_REQUIRED: NO
IMPLEMENTATION_AUTHORIZED: NO
FINAL_APPROVAL_REQUIRED: YES
```

`ARCHITECTURE_CHANGE_REQUIRED: NO` significa que Architecture Freeze §17 ya contempla conceptualmente coberturas; **no** significa que el contrato operativo esté vigente. Se requiere aprobación cruzada del addendum API/J1/J2 antes de implementar; el texto congelado no se modifica unilateralmente.
