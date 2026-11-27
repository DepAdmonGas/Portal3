# CHECADOR V2 — M01 ADDENDUM DE LECTURA SQL DE COBERTURAS PERSISTIDAS E INCIDENCIAS

**Versión:** 1.0
**Estado:** APROBADO POR EL PROPIETARIO PARA IMPLEMENTACIÓN LOCAL, conforme al prompt `M01_COVERAGE_AND_INCIDENCE_SQL_IMPLEMENTATION`.
**Ámbito:** exclusivamente M01 — Asistencia del día en Portal3
**Fecha de aprobación registrada:** 26/11/2026
**Bases y entornos:** sólo los perfiles ya aprobados por el addendum M01 vigente, sujetos a preflight independiente en cada runtime
**Freeze:** no modifica Architecture Freeze V1.0, J1, J2 ni contratos de API/Lector.

## 1. Aprobación y propósito

El propietario aprueba ampliar el alcance de lectura SQL de M01, sujeto a las condiciones de este addendum, para presentar junto con el horario y la jornada ya autorizados:

1. hechos persistidos de una cobertura de Comodines asociada inequívocamente a una asignación `TEMPORAL`; y
2. incidencias administrativas ya persistidas, mediante el lector SQL de M06 existente.

La autorización se limita a las lecturas descritas aquí. No se infiere de los addenda de M06 o Comodines: éstos conservan sus ámbitos propios.

Esta aprobación no autoriza escrituras, API HTTP, identidad SQL SERVICE, cambios de permisos/grants, cambios de esquema, materialización de jornadas ni despliegue. El código no activa la lectura en un runtime cuyo preflight de conexión, esquema y SELECT no haya pasado.

## 2. Conciliación arquitectónica

El modelo operativo permanece:

```text
Lector captura y transmite hechos
API conserva la ingesta y procesa/persiste J1, J2 y la resolución WORKER_EFFECTIVE
Portal3 consulta por SQL autorizado hechos y proyecciones que ya estén persistidos
```

Portal3 no ejecuta el resolvedor de API, no solicita `WORKER_EFFECTIVE` por HTTP, no emula J1/J2, no clasifica marcaciones ni recalcula jornadas. La lectura de las filas descritas aquí sólo autoriza una proyección de presentación de hechos administrativos explícitos; no la convierte en resultado del Motor.

Este addendum complementa exclusivamente el límite de lectura de `CHECADOR_V2_M01_SQL_DAILY_ATTENDANCE_READ_ADDENDUM_V1.0_PROPOSAL.md` y la lectura de horarios de `CHECADOR_V2_M01_ASSIGNED_SCHEDULE_SQL_ADDENDUM_20261126.md`. Para incidencias, autoriza a M01 a reutilizar en modo de lectura el contrato y lector ya aprobados exclusivamente para M06. No amplía el alcance de escritura de M06 ni los addenda M02 de Comparación con Nómina.

Architecture Freeze V1.0 y J1/J2 permanecen intactos. Si una regla de este addendum no puede conciliarse con el resultado persistido o con la fuente autorizada, M01 mostrará conflicto/no disponible y no elegirá una interpretación local.

## 3. Fuente canónica de Comodines

### 3.1 Fuentes ya persistidas

El modelo canónico descrito por `CHECADOR_V2_COMODINES_DIRECCION_ONLY_ADDENDUM_DRAFT_V1.2.md` consta de:

- `op_rh_v2_worker_workplace_coverage_versions`: revisiones de solicitud, autorización y cancelación, con UUID lógico, número de revisión, vínculo a revisión previa, trabajador, localidad habitual, versión de adscripción habitual, localidad destino, intervalo local semiabierto, zona horaria, patrón semanal, estado y actores/fechas.
- `op_rh_v2_turnos_asignaciones`: asignaciones `TEMPORAL` creadas al autorizar la cobertura. La revisión autorizada se vincula por `id_cobertura_version`; cada fila conserva trabajador, localidad destino (`id_estacion` según el contrato de escritura), plantilla, día de semana, vigencia y estado activo.
- `op_rh_v2_turnos_audit_log`: evidencia de acciones `WORKPLACE_COVERAGE`, incluida la versión de plantilla utilizada por la asignación y la referencia de ésta en el detalle de auditoría.
- `op_rh_v2_turnos_plantillas` y `op_rh_v2_turnos_segmentos`: plantilla y segmentos persistidos que M01 ya puede leer bajo el addendum de horarios, cuando se pueda confirmar la versión exacta usada.
- `op_rh_personal_localidad_version`: membresía canónica versionada, ya autorizada para las verificaciones de identidad/localidad de M01.
- `op_rh_localidades`, `tb_estaciones` y `op_rh_v2_localidades_zonas_horarias`: mapeo canónico/estación y zona histórica, sólo mediante las reglas M01 ya aprobadas.

El esquema y la existencia de las tablas de cobertura, asignación y auditoría constan en el preflight documental local del addendum Comodines V1.2. Ese antecedente no sustituye la comprobación de conexión, columnas, relaciones y SELECT efectivo del proceso Portal3 en cada runtime antes de habilitar esta lectura.

### 3.2 Lectura SQL M01 autorizada

La consulta será `SELECT` parametrizado por la conexión institucional `default`, dentro del lector M01 y con filtros derivados del contexto servidor: trabajador ya incluido en la consulta autorizada, localidad canónica seleccionada y fecha laboral local. Ningún identificador recibido del navegador amplía identidad o alcance.

La proyección sólo podrá presentarse como **cobertura autorizada persistida para la fecha** si se cumplen conjuntamente estas comprobaciones:

1. El `coverage_uuid` tiene una cadena de revisiones íntegra y una sola revisión vigente: los `supersedes_id` concuerdan, no hay ciclos, saltos o varias cabezas y el estado de la cabeza es exactamente `AUTHORIZED`. No se escogerá la fila por `MAX(id)` si la cadena no confirma cuál es la revisión vigente.
2. La revisión corresponde al trabajador de la fila M01; la localidad habitual y la localidad destino se verifican como localidades canónicas, y la localidad efectiva seleccionada está dentro del alcance M01 del usuario. La localidad habitual se valida como procedencia y membresía; no se expone su información fuera del alcance autorizado. Una estación física no se compara por igualdad numérica con un `workplace_id` canónico.
3. La fecha consultada satisface el intervalo semiabierto `[fecha_desde_local, fecha_hasta_local_exclusiva)` en la fecha laboral y zona horaria persistidas/aprobadas. Un estado `CANCELLED`, `REJECTED`, `REQUESTED`, desconocido o supersedido no constituye cobertura efectiva.
4. La `id_home_membership_version` existe, pertenece al trabajador y localidad habitual de la revisión y demuestra la membresía fuente para el intervalo requerido por el contrato Comodines. Una adscripción actual, una estación del trabajador o el nombre de la localidad no sustituyen esa versión.
5. Existe una asignación de día `TEMPORAL`, activa y vigente para el trabajador, localidad destino y día consultado, cuyo `id_cobertura_version` referencia exactamente el ID de la revisión `AUTHORIZED` vigente. Una asignación temporal sin ese vínculo nunca se interpreta como cobertura Comodines.
6. La asignación y su auditoría `WORKPLACE_COVERAGE` concuerdan en trabajador, localidad, revisión, día, intervalo, plantilla y versión. Si `id_plantilla` no es nulo, la versión de plantilla debe resolverse desde la evidencia persistida de cobertura y sus segmentos mediante los lectores M03/M04 ya autorizados. Si el vínculo de versión no es único, no coincide o falta, el horario de cobertura es incompleto y M01 no lo sustituye con la versión actual de la plantilla.
7. El patrón semanal autoriza el día como `WORK` o `DAY_OFF` y concuerda con la asignación vinculada. Una fila ausente, duplicada, inactiva, solapada o contradictoria no se repara ni se desempata en Portal3.

Cuando todas las pruebas confirman una sola asignación explícita, M01 podrá presentar el horario persistido como **cobertura autorizada persistida**. Esta etiqueta no afirma que Portal3 ejecutó ni reprodujo `WORKER_EFFECTIVE`. El API conserva la autoridad para resolver el contexto efectivo usado por el Motor.

### 3.3 Conflictos y precedencia de presentación

- Una jornada materializada con su proyección y horario histórico persistidos conserva prioridad en M01; la cobertura se puede mostrar como procedencia complementaria, pero no reemplaza ni reconstruye el horario histórico.
- Si no existe jornada materializada, una única asignación de cobertura `AUTHORIZED`, vigente, correctamente vinculada y consistente puede proporcionar el horario asignado que M01 presenta, conforme al contrato de Comodines. No inicia materialización ni evaluación.
- Si no hay cobertura canónica autorizada para esa fecha, M01 continúa usando la programación habitual aprobada por el addendum M03/M04.
- Dos coberturas candidatas, conflicto entre asignación habitual/temporal, múltiples asignaciones, cancelación no reflejada, cadena de revisión rota, membresía no acreditada o evidencia de plantilla discrepante producen un estado explícito de conflicto/información no concluyente. M01 no elige una de ellas ni cae silenciosamente a una asignación temporal sin vínculo.
- Un cero de filas sólo significa que no se encontró una cobertura persistida bajo la consulta autorizada. No confirma ausencia de trabajador, ausencia de horario, falta o ausencia física.

El lector administrativo actual `RolComodinesPortalWriterService::listVisible()` consulta revisiones visibles para la pantalla de Comodines, pero su listado por `MAX(id)` no demuestra por sí solo la cadena vigente, la asignación activa para una fecha ni la versión exacta de plantilla. No se considera suficiente, sin una consulta M01 que compruebe los vínculos anteriores. M01 no llamará a `ChecadorRolComodinesService::getCanonicalScheduledWorkersForDate()` como sustituto de la cobertura canónica.

No se reutiliza `checador_journey_service`, el principal SERVICE `journey-local`, credenciales/identidad de API ni el resolvedor API. Tampoco se crea una copia de ese servicio.

## 4. Incidencias: reutilización de lectura M06

### 4.1 Reutilización autorizada

El servicio existente `ChecadorAdministrativeIncidenceSqlService` expone `listForWorkplace(Usuario, workplaceId, filters, page, perPage, sortBy, sortDirection)`. Consulta por conexión `default`, parametriza filtros, exige la autorización de lectura M06, pagina resultados, presenta el maestro y adjunta revisiones persistidas. Su consulta usa:

- `op_rh_v2_incidencias_maestro`: UUID, trabajador, `id_localidad`, estación opcional, tipo, alcance, fechas locales de inicio/fin, estado/revisión vigentes y fecha de creación;
- `op_rh_v2_incidencias_revisiones`: número/estado de revisión, motivo, referencia de evidencia, actor creador/aprobador y fecha; y
- `op_rh_personal`: identidad de presentación ya permitida por el lector.

La lectura M01 reutiliza `listForWorkplace()` con la localidad del contexto autorizado, `worker_id` de una fila que M01 ya puede mostrar y filtro de fecha que usa la semántica inclusiva M06 de traslape (`fecha_inicio_local <= fecha_consultada <= fecha_fin_local`). No se ejecuta una consulta duplicada ni se llama a `detail()`; el detalle completo requeriría una aprobación independiente.

El permiso de lectura M01 no reemplaza al M06: en cada llamada deben pasar tanto la ACL M01 y la jurisdicción canónica del usuario como el `authorizeIncidenceRead()` interno del lector M06. La denegación M06, pérdida de sesión, rechazo de localidad o error SQL no se convierte en resultado vacío. No se modifica la política de Dirección/Encargado ni se concede permiso alguno.

La respuesta que se presente se limita a los campos necesarios para M01: tipo, periodo local, estado vigente, número de revisión y referencia UUID ya escapada. No se muestran motivos, evidencia, actores ni historial completo de M06 en la tabla principal M01 mediante esta autorización.

### 4.2 Resultado y relación con Jornada

M01 distinguirá estos resultados por trabajador y fecha:

- **Incidencias existentes:** una o más filas devueltas por el lector M06 que contienen la fecha consultada en su periodo.
- **Cero incidencias confirmadas:** el lector devuelve éxito, consulta completa bajo sus límites/paginación y cero filas para ese trabajador/localidad/fecha.
- **Información de incidencias no disponible:** fallo ACL, SQL, preflight, paginación incompleta, límite excedido o error de lectura. Se conserva el mensaje de no disponible y nunca se presenta `0` como conteo.

El modelo maestro/revisión y los campos actualmente seleccionados por el lector M06 no contienen ni devuelven una clave explícita de jornada. Por lo tanto, M01 puede mostrar “incidencias relacionadas” por trabajador y traslape de fecha laboral, pero no asociar una incidencia a una jornada, segmento o entrada/salida. Esa asociación sólo se mostrará cuando exista un vínculo persistido explícito, esté incluido en el contrato M06 correspondiente y sea devuelto por un lector autorizado. No se inferirá a partir de coincidencia de fechas o tipo.

Las incidencias no llenan posiciones P1, no alteran P2, no generan faltas ni cambian el estado de jornada. Esta autorización no concede ninguna operación de escritura.

## 5. ACL, identidad, localidad y filtros

Para cada request M01 el controlador revalida en el servidor:

1. sesión activa y autorización general de M01;
2. localidad canónica autorizada y mapeo único a estación física sólo donde esa fuente lo requiere;
3. identidad del trabajador procedente del padrón/adscripción histórica o de la marcación ya autorizada que generó la fila M01;
4. fecha laboral validada y, cuando la fuente depende de límites temporales, zona IANA histórica y ventana correspondiente;
5. ACL específica M06 al llamar el lector de incidencias; y
6. verificación de las localidades habitual y efectiva de Comodines. El resultado sólo se presenta en la localidad efectiva seleccionada y autorizada; los datos de origen sólo se presentan si el usuario también tiene alcance sobre esa localidad.

`worker_id`, localidad, cobertura UUID y otros valores del navegador son filtros, nunca autorización. Un trabajador fuera del alcance M01 o de la adscripción aplicable queda fuera del resultado. Si la identidad/membresía, mapeo o zona está ausente o es ambigua, no se atribuye la fuente a una persona ni se afirma que la consulta esté vacía.

## 6. Acceso SQL y entornos

Las nuevas consultas son únicamente `SELECT` parametrizados por `default` y quedan aisladas en el lector M01. No se amplía globalmente `ChecadorRawPunchReadService`, M02 ni los lectores de otros módulos. No se usa `SELECT *`.

Se conservan exactamente los perfiles y límites ambientales del addendum M01 actual: Fedora `bd_admongas` y preproducción `bd_admongas_v2_preprod` bajo su preflight aprobado. Esta autorización se implementa y valida localmente en Fedora; no constituye por sí misma una autorización de despliegue o activación en otro runtime. Cada runtime debe verificar por su propia conexión efectiva `DATABASE()`, principal de PHP-FPM, tablas, columnas, relaciones, índices y SELECT requeridos antes de habilitar la lectura. No se crean cuentas, grants, tablas o migraciones. La ausencia de cualquier condición cierra sólo el overlay afectado y presenta “no disponible”.

La presencia de tablas/cero filas reportada en un preflight anterior no sustituye la verificación de los grants de SELECT efectivos para las nuevas columnas/tablas y el runtime que ejecutará el código.

## 7. Exclusiones

Este addendum no autoriza:

- HTTP Portal3 → API, JWT, credenciales API, `WORKER_EFFECTIVE` por endpoint o uso de SERVICE;
- ejecución de reglas/resolvedores API, J1, J2, P1/P2, materialización o evaluación;
- clasificación de marcaciones, cálculo de horas, retardos, faltas, descuentos o resultados de nómina;
- escrituras, DDL, cambios a datos, permisos, grants, estructura o contratos congelados;
- convertir cualquier fila `TEMPORAL` en cobertura sin `id_cobertura_version` válido y revisión autorizada;
- elegir entre coberturas o asignaciones conflictivas;
- inferir un vínculo incidencia–jornada;
- exposición a otra localidad, identidad no confirmada o historial de datos fuera del periodo/propósito de M01.

## 8. Implementación local realizada conforme a la aprobación

La implementación local queda limitada a:

| Archivo/componente | Implementación local |
|---|---|
| `app/Services/ChecadorDailyAttendanceSqlReadService.php` | Leer por `default` y SELECT parametrizado la cadena de cobertura, asignación temporal vinculada, pertenencia histórica, plantilla/segmentos y auditoría. Sólo presenta como autorizada una relación completa y única; los demás casos quedan neutrales o no disponibles. |
| `app/Services/ChecadorWeeklyScheduleSqlReadService.php` / `ChecadorWeeklyScheduleService::matrix()` | Reutilizar sólo para hidratación y proyección de la plantilla/segmentos exactos ya acreditados. No modificar el selector habitual ni hacer fallback a otra versión. Si la API contractual no queda trazable en el esquema, la hidratación falla de forma visible. |
| `app/Services/ChecadorAdministrativeIncidenceSqlService.php` | Reutilizar `listForWorkplace()` sin cambiar su consulta, ACL ni operaciones. |
| `app/Services/ChecadorM01IncidenceReadService.php` | Adaptar el lector M06 a M01, revalidar sesión/alcance M01 y conservar la ACL M06, verificar paginación y exponer sólo campos autorizados. |
| `app/Controllers/ChecadorController.php::readDailySqlAttendance()` | Enriquecer filas dentro del alcance; conservar precedencia de jornada persistida, cobertura vinculada y programación habitual; separar cero/error por fuente sin bloquear la lectura de marcaciones. |
| `app/Views/departamento-operativo/2-recursos-humanos/checador/daily-attendance.php` | Presentar cobertura sólo con evidencia única; mostrar incidencias, cero confirmado o error/no disponible. No asociar incidencias con Jornada sin vínculo persistido. Se conserva la vista, sin CSS/JS global. |

No se agregaron tablas, modelos de persistencia ni dependencias externas. La lectura queda sujeta al preflight independiente de cada runtime descrito en §6.

## 9. Pruebas requeridas antes de activar

### Coberturas

- revisión vigente única y cadena `supersedes_id` íntegra;
- `AUTHORIZED` dentro del intervalo semiabierto y días de inicio/fin;
- `REQUESTED`, `REJECTED`, `CANCELLED` y autorizaciones supersedidas no presentadas como vigentes;
- asignación TEMPORAL vinculada al ID exacto de revisión y mismo worker/destino/día/vigencia;
- asignación temporal no vinculada excluida;
- patrón `WORK` y `DAY_OFF`, siete asignaciones y versión de plantilla/auditoría coincidente;
- origen/destino dentro del alcance, mapeo canónico correcto y membresía histórica/versionada válida;
- ausencia de cobertura diferenciada de conflicto, vínculo roto, revisión doble, membresía dudosa y SQL no disponible;
- ausencia de preferencia artificial entre dos candidatos o entre asignaciones contradictorias;
- jornada P1/P2 persistida conserva precedencia y jamás se reconstruye;
- SELECT-only, ningún resolvedor/API/SERVICE y ningún dato de otra localidad.

### Incidencias

- `listForWorkplace()` reutilizado sin cambiar su ACL, con trabajador, localidad y traslape de fecha;
- Encargado/Dirección conforme a sus permisos M01 y M06 efectivos; denegación individual y localidad ajena denegadas;
- un resultado, cero confirmado, M06 denegado, error SQL y paginación incompleta producen estados distintos;
- periodo inclusivo M06 en inicio/fin y trabajador que no pertenece a la fila M01 no se expone;
- sin relación explícita de jornada, no se muestra asociación a Jornada/segmento;
- M01 conserva marcaciones, horario, P1/P2, tabla, filtros y permisos;
- ninguna escritura M01/M06, HTTP, JWT o ejecución de Motor.

Las pruebas con fixtures aislados no acreditan el esquema/grants ni resultados reales. Antes de habilitar por entorno se requiere preflight SELECT-only con la conexión real `default`; después, una validación local/manual con sesión autorizada y datos existentes, sin insertar datos de prueba.

## 10. Aprobación y activación por runtime

El propietario aprobó este alcance para implementación local el 26/11/2026. El código no acredita por sí mismo disponibilidad de MariaDB, esquema compatible ni grants efectivos. Antes de habilitar la lectura en un runtime se ejecuta el preflight SELECT-only de §6. Si falta evidencia o la consulta falla, M01 mantiene separados error/no disponible, cero incidencias confirmadas, conflicto de cobertura y ausencia de cobertura. Portal3 no infiere resultados ni activa escrituras.
