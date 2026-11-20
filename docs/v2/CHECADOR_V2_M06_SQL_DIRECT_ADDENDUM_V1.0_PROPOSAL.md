# CHECADOR V2 — M06 SQL DIRECT V1.0 — ADDENDUM APROBADO

**Aprobación del propietario:** 2026-11-20, limitada a M06 y a la conciliación de §2. **Ámbito:** Portal3, consulta, creación inicial e historial de incidencias administrativas M06. **Freeze:** no modifica `CHECADOR_V2_ARCHITECTURE_FREEZE_V1.0.md`. **Persistencia canónica:** maestro y revisiones V2 ya existentes; no crea un motor de incidencias. **La aprobación no habilita escrituras hasta comprobar el corte efectivo del escritor anterior y la protección del historial.**

## 1. Propósito y alcance

Esta propuesta resuelve únicamente el conflicto de transporte para M06. Portal3 usaría la conexión institucional MariaDB `default` para listar incidencias, consultar su historial y registrar un maestro con su revisión inicial. El navegador sólo se comunicaría con Portal3.

Quedan fuera: modificar marcaciones, producir o autorizar automáticamente una `FALTA`, aplicar incidencias a jornadas, evaluar jornadas, alterar ajustes, nómina, importar filas legacy, o cambiar M01/M03/M04/M11. La aprobación de `FALTA` seguirá siendo una acción separada y exclusiva de Dirección conforme a su autorización vigente; crear una incidencia no concede esa aprobación ni altera ese flujo.

## 2. Conciliación limitada de P3-G

La aprobación de esta propuesta sustituiría **sólo para M06** las cláusulas siguientes del contrato `CHECADOR_V2_PORTAL3_P3G_INCIDENCE_ADMINISTRATIVE_CONTRACT_V1.0.md`:

1. **§12.1, “Portal3 Backend → API Only”:** para `READ APPLICABLE INCIDENCES`, `CREATE INCIDENT` inicial e historial, Portal3 leería/escribiría directamente el modelo canónico SQL descrito aquí. No habría llamadas Portal3→API ni fallback HTTP para esas tres operaciones. Las prohibiciones de acceso API desde navegador y de credenciales de dispositivo permanecen.
2. **§12.3, capacidades futuras:** esas tres capacidades se asignan al backend Portal3 para M06. Las capacidades de revisión, autorización, rechazo, revocación, aplicación y evaluación no se transfieren por esta propuesta.
3. **§16, estado de implementación:** sólo el paquete M06 pasaría a “autorizable tras cumplir las condiciones de corte y preflight” cuando esta propuesta sea aprobada; el estado de los demás paquetes P3-G no cambia.
4. **§2.4, V2.5 canonical foundation:** las tablas y el modelo V2.5 siguen siendo la única fuente canónica. Portal3 sería el único escritor administrativo autorizado para la creación inicial M06; no implementaría un motor ni una proyección paralela. API/Motor conservaría lectura y cálculo downstream.

No se altera la semántica de §3 (inmutabilidad de `PunchEvent`), §4 (identidad y alcance), §6 (categorías), §7 (conflictos), §8 (historial append-only), §9 (evaluación), §10 (sin efecto de nómina) ni §11 (capacidades ACL separadas), salvo las precisiones operativas de este addendum. En caso de contradicción, esta propuesta tendría precedencia sólo dentro del alcance M06 y sólo después de aprobación expresa.

## 3. Modelo y operaciones M06

Se reutilizan exclusivamente:

- `op_rh_v2_incidencias_maestro`: identidad UUID, trabajador, ámbito, categoría, fechas y estado vigente.
- `op_rh_v2_incidencias_revisiones`: secuencia de revisión, estado, motivo, referencia de evidencia, actor creador/aprobador y fecha UTC.
- La relación maestro→revisiones para el detalle/historial completo.

### Evidencia física local

La consulta de solo lectura a `bd_admongas` confirmó InnoDB para maestro, revisiones y aplicaciones. El maestro tiene UUID único e índice por trabajador/fechas; revisiones tienen unicidad por maestro/número y FK al maestro; aplicaciones tienen unicidad por jornada/revisión y FK a revisión y jornada. Al preflight inicial las tablas V2.5 consultadas estaban vacías. El propietario autorizó condicionalmente el alcance canónico y ya se verificó en esta base `id_localidad INT(11) NULL` con FK `fk_v2_incid_maestro_localidad` a `op_rh_localidades(id)`; la tabla maestra permaneció con cero filas. `id_estacion` se conserva como restricción física opcional y no sustituye `id_localidad`.

El puente `tb_estaciones.numlista`→`op_rh_localidades.numlista` tiene una coincidencia por fila en la lectura actual, pero no tiene índices únicos ni FK en esos campos y puede cambiar. Además, el checkout API examinado asigna el `workplace_id` recibido a `id_estacion`; por eso no se usa ese puente para el alcance histórico M06. Las nuevas filas Portal3 deben escribir `id_localidad` directamente después de verificar el permiso y la adscripción canónica.

El principal de `default` observado en desarrollo (`dev@localhost`) posee privilegios amplios, incluidos UPDATE/DELETE y grant option. Esto acredita capacidad, no grants mínimos ni protección SQL append-only, y no acredita otro entorno.

La consulta de la página M06 se independiza del snapshot de asistencia: obtiene el padrón de trabajadores por el servicio SQL canónico existente y la lista/historial desde maestro y revisiones. Los filtros de localidad, trabajador y periodo se vuelven a validar en servidor. Un error SQL se muestra como indisponibilidad; no se presenta como lista vacía.

La creación inserta el maestro y la revisión inicial dentro de **una transacción InnoDB**. No exige jornada ni marcación procesada, no inserta aplicaciones ni evaluaciones y no cambia datos legacy. Ambas filas deben confirmarse mediante SELECT antes de reportar éxito; después del commit se hace una lectura fresca de maestro y revisión. Si la confirmación posterior falla, se informa resultado no confirmado y el reintento conserva la misma clave idempotente.

## 4. Garantías técnicas propuestas

### Identidad, localidad y autorización

- `id_personal` se resuelve exclusivamente como `op_rh_personal.id`; nombre, estación del navegador o `id_asistencia` no identifican al trabajador.
- La localidad solicitada se obtiene del selector/session context y se vuelve a autorizar en cada request. El trabajador debe tener adscripción canónica vigente para **todo el periodo**; fecha o localidad ambiguas rechazan la operación.
- `id_localidad` del maestro es el alcance canónico y debe referenciar una fila existente de `op_rh_localidades`. `id_estacion` sólo conserva una restricción física cuando corresponda; nunca se llena copiando `id_localidad` por coincidencia numérica.
- El actor se obtiene de la sesión Portal3 validada, nunca del formulario. Para CREATE se exigen sesión activa, permiso dedicado `checador-v2-incidencias` (`crear` y `leer`) y alcance. Dirección (`id_puesto=13`) queda explícitamente en consulta; Encargado (`id_puesto=6`) sólo crea dentro del alcance autorizado y con ACL efectiva.
- Crear evidencia no equivale a aprobarla. La autorización de `FALTA` sigue separada, requiere acción explícita y actor Dirección. Esta propuesta no agrega códigos ni permisos para FALTA.

### Auditoría e historial

La revisión inicial debe guardar actor Portal3, instante UTC, motivo y folio/referencia disponible; maestro UUID y número de revisión correlacionan el historial. Las revisiones existentes nunca se actualizan ni eliminan. M06 no resuelve, autoriza, rechaza ni revoca incidencias.

### Estado inicial

El checkout API local examinado inicializa tanto `estado_actual` del maestro como `estado_revision` de la revisión 1 en `PENDING_APPROVAL`; la ruta de autorización cambia el estado después de una acción separada. En el DDL ambos campos son `VARCHAR(32)` sin ENUM/CHECK, por lo que la base no restringe los literales; el comentario DDL sólo enumera `ACTIVE/CANCELLED/SUPERSEDED`. El propietario aprobó para M06 conservar `PENDING_APPROVAL` al crear, sin efecto administrativo hasta una autorización separada. No se cambia el estado de incidencias existentes.

La aplicación puede imponer el invariante de no ejecutar UPDATE/DELETE, pero la base local observada no lo refuerza para `default`: el principal tiene esos privilegios y el contrato de auditoría no está respaldado por protecciones append-only completas. Por tanto, la inmutabilidad sólo sería una garantía de aplicación mientras no se resuelva la protección SQL; no se presentará como garantía de base de datos.

### Idempotencia y concurrencia

La tabla ofrece `UNIQUE(uuid_incidencia)` y `UNIQUE(id_incidencia_maestro, revision_numero)`, pero no una columna de idempotencia. Propuesta sin DDL adicional: el formulario genera una clave aleatoria una vez y conserva esa misma clave en cada reintento. Portal3 calcula `uuid_incidencia = UUIDv5(namespace, idempotency_key)` con namespace fijo `98d64f66-bf8b-5d7b-b4d8-49d6f82a73f0` (UUIDv5 de DNS para `portal3.admongas.com.mx/checador-v2/m06/incidence-idempotency/v1`). La clave no es una autorización.

Un UUID único no basta: ante una colisión/replay, Portal3 debe leer maestro y revisión 1 y comparar exactamente actor, trabajador, `id_localidad`, categoría, alcance, fechas, motivo y referencia de evidencia. Sólo si todos coinciden devuelve el resultado ya creado; misma clave con contenido distinto devuelve conflicto y no crea revisión nueva. La comparación usa contenido normalizado y UTF-8 determinista; no se agrega metadata oculta al motivo. El índice único arbitra solicitudes simultáneas con la misma clave. La garantía requiere que todos los escritores del dominio estén cortados y que la clave se conserve al reintentar.

La transacción valida trabajador/localidad, comprueba conflictos según las reglas aprobadas, inserta maestro y revisión, y lee ambas filas. La protección de concurrencia sólo se considera efectiva después del corte del escritor anterior: no puede haber otro proceso insertando o revisando el mismo dominio sin participar en el protocolo. No se inventa una regla de precedencia para incidencias superpuestas.

### Plazo configurado

La fuente única es `ChecadorAttendancePolicyStoreService::readForEffectiveDate($workplaceId, $date)`, que consulta las versiones persistidas M11 por `workplace_id` y fecha efectiva y devuelve `incidence_deadline_days` y `timezone_id`; no se crea otra configuración ni se lee una política legacy. Si no hay política versionada o no se resuelve zona horaria, el alta que dependa del plazo se rechaza sin valor por defecto. El propietario aprobó para M06 el cómputo inclusivo descrito abajo.

La implementación API local usa `PunchIncidenceDeadlineService` para la categoría técnica `PUNCH_ATTENDANCE`: cutoff inclusivo al final del día local, `fecha_chequeo + días`; no aplica ese cálculo al alta genérica de periodo. `PUNCH_ATTENDANCE` no forma parte de los seis códigos P3-G que este M06 expone. Para un periodo M06, usar `fecha_fin_local` como día de referencia y aceptar hasta las 23:59:59.999999 locales de `fecha_fin_local + incidence_deadline_days`. Esta regla queda aprobada para M06; no autoriza el código `PUNCH_ATTENDANCE` ni altas fuera de las seis categorías canónicas.

## 5. Categorías

Se mantienen únicamente los códigos conceptuales de P3-G §6; no se introduce `FALTA`, “salida no marcada” ni `JUSTIFICATION` como código nuevo.

| Código V2 existente | Etiqueta propuesta en M06 | Límite de uso |
|---|---|---|
| `VACATION` | Vacaciones | El alta M06 inicia en `PENDING_APPROVAL`; no copia formatos legacy ni autoriza ausencia. Sólo una autorización posterior separada puede hacerla aplicable; conflictos conservan `REVIEW_REQUIRED`. |
| `PERMISSION` | Permiso | Mismo límite: no copiar permisos legacy ni asumir aprobación; no altera marcaciones. |
| `DISABILITY` | Incapacidad | Entrada V2 manual; automatización desde la tabla legacy permanece bloqueada. Sin efecto antes de aprobación separada. |
| `AUTHORIZED_LATENESS` | Justificación de retardo | Categoría conceptual reconocida, pero fuente/autoridad no congelada: sólo evidencia pendiente/review; no cambia el punch ni produce efecto final. |
| `AUTHORIZED_EARLY_DEPARTURE` | Justificación de salida anticipada | Igual límite; no se crea `FALTA` ni se cambia una marcación. |
| `OFFICIAL_MISSION` | Misión oficial | Sin fuente autoritativa congelada; sólo evidencia pendiente/review, sin efecto final. |

Los seis códigos son los únicos tipos canónicos definidos por P3-G §6. `INCAPACITY`, `JUSTIFICATION`, `FALTA`, “salida no marcada” y `PUNCH_ATTENDANCE` no se agregan como categorías M06. Las etiquetas de presentación no cambian el código persistido. `FALTA` es un resultado/decisión de evaluación, no un tipo de incidencia; su autorización explícita sigue siendo exclusiva de Dirección y fuera de la creación M06.

Las fuentes y autoridad de `AUTHORIZED_LATENESS`, `AUTHORIZED_EARLY_DEPARTURE` y `OFFICIAL_MISSION` siguen pendientes en P3-G §6; su registro sólo puede ser evidencia que requiere revisión, nunca una autorización final. El propietario aprobó exponer las seis categorías canónicas como altas pendientes, con los límites y efectos nulos descritos en la tabla; esta aprobación no asigna efectos finales a las tres categorías sin fuente autoritativa.

## 6. Corte de escritor único y activación

Antes de habilitar el servicio SQL:

1. Aprobar este addendum y resolver los puntos pendientes de §8.
2. Verificar el esquema y grants en cada base destino, incluyendo tablas, claves, engine y permiso de SELECT/INSERT requerido. El estado local no acredita Plesk.
3. En el checkout API local inspeccionado (`portal-api-v.1.0.1`, rama `codex/m04-station-template-write-cutover-20261119`, HEAD `46827dd5741d21a4481e8dcc0ceae52b41d522b2`), `index.php` enruta actualmente POST de creación y revisión, además de resolve/authorize/reject/revoke, hacia `AdministrativeIncidenceV2Controller`; `AdministrativeEvaluationService` inserta maestro/revisión y en la revisión posterior también actualiza el maestro. El checkout tiene cambios M04 pendientes y no demuestra remoto ni runtime desplegado. El corte propuesto debe cerrar exclusivamente estos seis POST: `/api/v2/incidences`, `/api/v2/incidences/{uuid}/revisions`, y `{resolve,authorize,reject,revoke}`. Mantener los GET, `/api/v2/administrative-evaluations/evaluate`, ingesta de marcaciones y rutas ajenas. No aplicar un gate general de API.
4. Antes de abrir SQL Portal3, comprobar que cada POST de esa lista rechaza sin mutación y que los conteos de maestro/revisiones no cambian. El rechazo exacto y pruebas de API requieren autorización/cambio separado; no se infieren por el código local. No se activan simultáneamente dos escritores.
5. Sólo entonces habilitar el writer Portal3 en el entorno autorizado, ejecutar pruebas aisladas y confirmar transacción, rollback, replay, conflicto de clave, auditoría e identidad/localidad por readback.

El diseño M06 sólo necesita SELECT en las tablas de identidad/adscripción/política requeridas y SELECT/INSERT en maestro/revisiones. No necesita UPDATE/DELETE para M06. Los grants amplios observados en desarrollo no se consideran mínimos ni acreditan los de otro entorno; como `default` es compartida, revocar esos privilegios puede afectar módulos ajenos. La protección estricta de historial no puede asegurarse mediante grants sin resolver previamente ese impacto.

## 7. Flujo de interfaz mínimo a preparar

No requiere nueva arquitectura de frontend ni endpoints adicionales. La implementación posterior conservaría las rutas GET `/recursos-humanos/checador/incidencias` y POST `/recursos-humanos/checador/incidencias/crear` existentes, pero separaría M06 de `renderDashboardWorkspace('incidences')` y del snapshot.

- `ChecadorController::incidences()`: validar localidad y filtros servidor-side; cargar padrón desde el servicio canónico SQL, lista/historial desde el nuevo adaptador SQL y estado de permisos/errores/paginación.
- Nuevo `ChecadorAdministrativeIncidenceSqlService`: sólo SELECT y, después de aprobar, transacción maestro+revisión, idempotencia y readback. No será motor de evaluación.
- `ChecadorController::createIncidence()`: conservar CSRF/ruta, validar ACL, identidad, localidad, tipo, fechas, evidencia y plazo; llamar al servicio SQL sólo tras aprobación y corte.
- `ChecadorAccessService`: mantener el ACL dedicado; hacer explícito que Dirección sólo consulta y Encargado requiere `id_puesto=6`, `crear`+`leer` efectivos y alcance por localidad en cada solicitud.
- `workspace-incidences.php`: filtro/botón Buscar y modal de filtros; botón/modal Nueva incidencia independiente de resultados; tabla con trabajador, tipo, periodo, estado y acciones; modal de detalle/historial. El selector usa trabajadores autorizados del padrón, sin exigir viaje, `id_asistencia` ni snapshot.
- Reutilizar layout, modales y controles Bootstrap actuales. Sin CSS global ni JavaScript paralelo. Añadir regresiones focalizadas para scope, tabla vacía/error SQL, reintentos, estado pendiente y ausencia de llamadas HTTP en el flujo M06.

No se propone modificar `ChecadorDailyReadService` compartido con M01; M06 tendrá su ruta/controlador y servicio delimitados. La implementación visual y la consulta SQL también esperan conciliación de transporte aprobada, para no contradecir P3-G antes de tiempo.

## 8. Recuperación del flujo histórico

El checkout histórico `6fa6cee8beea7daffb9f595a41e816d99010af4a` muestra que el flujo previo se iniciaba desde una asistencia concreta: `BiometricosController::agregarIncidencia()` recibía `id_asistencia`, `id_incidencia`, comentario, fechas y, cuando aplicaba, documento; `ControlDocumentosPersonalService` escribía en las tablas legacy. Esa implementación **no se reutiliza**: exigía una asistencia y escribía una fuente expresamente no canónica.

Se recuperaría sólo la interacción útil: seleccionar trabajador, elegir categoría, periodo y motivo/evidencia; confirmar; y consultar detalle/historial. En M06 el selector provendría del padrón SQL autorizado y no exigiría `id_asistencia`, viaje ni snapshot. No se copiarán su JavaScript, carga de archivos ni escrituras legacy.

## 9. Condiciones pendientes antes de abrir escrituras

La aprobación de esta conciliación no prueba el corte operacional. Antes de habilitar POST SQL:

1. Publicar esta misma adenda en Portal3 y API mediante cambios revisables. No modificar el Architecture Freeze ni reescribir P3-G/J1/J2 congelados.
2. Desactivar sólo los seis POST de incidencias enumerados en §6 y comprobar cada rechazo sin mutación; preservar GET, evaluación, ingesta y rutas ajenas.
3. Confirmar el runtime API que atiende el entorno; el checkout local activo no acredita remoto ni runtime.
4. Confirmar que maestro/revisiones son append-only para el principal escritor. La lectura local de `bd_admongas` verificó que no hay triggers en esas tablas; los grants de `default` incluyen UPDATE/DELETE. Con esta evidencia no se puede afirmar inmutabilidad SQL fuerte.
5. Verificar DDL, InnoDB, FK `id_localidad`, índices y grants en cada entorno objetivo. La inspección local no acredita otros entornos.

Hasta cumplir esas condiciones, M06 puede consultar SQL y mostrar la interfaz; el guardado real permanece cerrado. Esta adenda no autoriza DDL, cambios de grants ni despliegues.
