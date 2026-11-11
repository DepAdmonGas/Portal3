# Checador V2 — Propuesta de contrato de lectura de Ajustes administrativos

**Estado:** `PROPUESTA CORREGIDA — PENDIENTE DE APROBACIÓN FINAL`
**Versión:** 1.0 — propuesta
**Alcance:** Portal3 y API V2, exclusivamente consulta de ajustes administrativos persistidos
**Entorno inspeccionado:** desarrollo local `bd_admongas`, 2026-11-06
**Implementación autorizada por este documento:** NO
**Cambios de datos, ACL o esquema realizados:** NO

Este documento propone una extensión compatible para listar y consultar el detalle de ajustes administrativos V2. No modifica ni reemplaza Architecture Freeze, J1/J2, el contrato P3-G vigente ni operaciones existentes. Requiere aprobación expresa del contrato y autorización técnica antes de implementar.

## 1. Estado actual verificado

### Portal3

- La tarjeta y pantalla existentes usan la ruta `GET /departamento-operativo/recursos-humanos/checador/ajustes`, registrada en `routes/web.php`; el controlador es `ChecadorController::adjustments()` y renderiza `workspace-adjustments.php`.
- El servicio actual `ChecadorDailyReadService::readAdministrativeEvaluation()` consulta `GET /api/v2/administrative-evaluations` para trabajador, localidad canónica y fecha laboral concretos.
- La pantalla actual presenta el resultado de evaluación y, cuando la respuesta contiene referencias enlazadas, los cinco campos disponibles de cada referencia. No es un listado general de ajustes ni permite navegar por su historial completo.
- El navegador no llama directamente a API V2. La lectura actual viaja desde el backend Portal3 mediante el mecanismo server-to-server vigente.

### API V2

- El contrato P3-G actual contempla `GET /api/v2/administrative-evaluations`, no una colección ni un detalle independiente de ajustes.
- El GET de evaluación requiere `worker_id`, `workplace_id` y `work_date`; autentica JWT delegado de Portal3, comprueba permisos y alcance de localidad, y devuelve las referencias enlazadas a esa evaluación.
- La cláusula vigente autoriza ese GET con `checador-v2-incidencias.leer = 1` **o** `biometricos.leer = 1`; esta condición solo aplica al recurso de evaluación existente, no prueba permiso para una colección general de ajustes.
- Cada `linked_adjustments[]` contiene únicamente `uuid_ajuste`, `uuid_ajuste_logico`, `adjustment_type`, `revision_number` y `adjustment_status`.
- Una evaluación inexistente se responde como `evaluation_exists: false`; no significa que no existan otros ajustes del trabajador o de la localidad.
- No existe en el contrato público actual una ruta para enumerar ajustes, consultar por referencia pública o recuperar su historia independiente.

## 2. Contratos y arquitectura aplicables

- **Architecture Freeze V1.0, §§1–3, §25 y §26:** mantiene la autoridad del Freeze y la separación hecho original/interpretación/ajuste/resultado validado; reconoce `AttendanceAdjustment`, requiere conservar el hecho original y su auditoría, y no autoriza por sí mismo implementación ni escrituras. Su estado `Implementation Authorized: NO` permanece intacto.
- **J1 canónico:** `docs/architecture/CHECADOR_V2_PORTAL3_JOURNEY_INCIDENTS_FREEZE_V1.0.md`. Mantiene la separación entre hecho raw, Journey e interpretación administrativa; no autoriza acceso SQL de Portal3 a datos propiedad de API.
- **J2 V1.0:** rige horario efectivo, fecha laboral local, timezone y completitud de fuentes. Esta propuesta no cambia el resolver ni asignaciones; usa la fecha local de la jornada vinculada como fecha afectada y no calcula horarios en Portal3.
- **Contrato Portal3 P3-G V1.0, §§2.1 y 3.2:** §2.1 fija el dominio canónico V2 y prohíbe que Portal3 construya un motor o almacén administrativo competidor; §3.2 prohíbe escrituras dobles hacia las tablas Legacy de asistencia. Estos apartados no crean por sí solos un endpoint de inventario ni detallan el transporte.
- **Contrato Portal3 P3-G V1.0, §§12.1–12.2:** fija expresamente el transporte `Portal3 backend → API`, prohíbe llamadas directas desde navegador y requiere JWT delegado, permiso funcional específico y alcance de localidad validado en servidor.
- **Contrato API P3-G Boundary V1.0, §§3.1–3.4 y 9.10:** define la frontera server-to-server, JWT delegado del usuario activo, permiso específico y scope autorizado; el §9.10 conserva el recurso `GET /api/v2/administrative-evaluations` y su semántica para una evaluación concreta. Ninguna de estas cláusulas autoriza SQL de Portal3 ni un listado general de ajustes.
- **Modelo físico V2.5 y addendum V1.1:** documentan los ajustes, su linaje lógico/versionado y los enlaces de procedencia evaluación–ajuste. Estos documentos de modelo no equivalen a un endpoint público ni a permiso de lectura para Portal3.

### Mecanismo de lectura confirmado para este dominio

La lectura propuesta se ejecutará exclusivamente por **Portal3 backend → API V2 mediante JWT delegado**. Portal3 no consultará directamente las tablas SQL de ajustes y el navegador solo llamará a rutas same-origin de Portal3. Esto aplica a la nueva colección y al detalle de Ajustes administrativos; no altera decisiones de propiedad/lectura persistida de otros módulos.

Esta elección no se basa en una preferencia de implementación: Portal3 P3-G §§2.1 y 12.1–12.2 y API P3-G Boundary §§3.1–3.4 establecen fuente canónica y transporte; J1 preserva esa separación de fuente. Portal3 P3-G §3.2 se cita únicamente por la prohibición de dual-write Legacy, no como cláusula de transporte. J2 y Architecture Freeze no definen una alternativa SQL para esta colección ni se modifican por esta propuesta.

Las rutas de colección/detalle propuestas en §§6–7 siguen siendo nuevas capacidades contractuales. El precedente de transporte no equivale a aprobación de esas rutas ni a autorización de implementación.

## 3. Esquema y relaciones comprobadas

Se verificó por `information_schema` y consultas `SELECT` que la conexión utilizada fue `bd_admongas` local (`@@port = 3306`). No se ejecutaron escrituras.

### `op_rh_v2_ajustes_administrativos`

Columnas relevantes comprobadas:

| Columna | Tipo/condición observada | Uso propuesto |
|---|---|---|
| `id` | `BIGINT UNSIGNED`, PK | Orden interno estable; no exponer como identificador público |
| `uuid_ajuste` | `VARCHAR(64) NOT NULL`, único | Referencia pública de una revisión |
| `uuid_ajuste_logico` | `VARCHAR(64) NOT NULL` | Agrupa revisiones de un ajuste lógico |
| `revision_numero` | `INT UNSIGNED NOT NULL` | Número de revisión persistida |
| `estado_ajuste` | `VARCHAR(32) NOT NULL`, default `ACTIVE` | Estado persistido; no traducir a una conclusión distinta |
| `id_ajuste_anterior` | nullable, FK autorreferente | Linaje a revisión anterior |
| `id_jornada_programada` | obligatorio, FK a jornada | Fuente de trabajador, localidad y fecha afectada |
| `id_calculo_jornada` | obligatorio, FK a cálculo | Revisión P2 referenciada por el ajuste |
| `tipo_ajuste` | `VARCHAR(32) NOT NULL` | Tipo persistido |
| `hora_interpretada_entrada_utc`, `hora_interpretada_salida_utc` | nullable `DATETIME` | Valores interpretados, no PunchEvents |
| `motivo_ajuste` | `TEXT NOT NULL` | Motivo persistido |
| `creado_por_usuario_id`, `aprobado_por_usuario_id` | `INT NOT NULL` | IDs de actor persistidos |
| `creado_at_utc` | `DATETIME NOT NULL`, default `CURRENT_TIMESTAMP` | Instante persistido de creación, sujeto a la semántica UTC del escritor |

Índices relevantes observados: PK `id`, únicos `uuid_ajuste` y (`uuid_ajuste_logico`, `revision_numero`), y un índice por (`id_jornada_programada`, `id_calculo_jornada`). Las FK observadas cubren revisión anterior, jornada y cálculo. No se observó FK de los IDs de actor a `tb_usuarios`.

### Relaciones relacionadas

- `op_rh_v2_jornadas_programadas` contiene `id_personal`, `id_estacion`, `fecha_jornada_local`, `timezone_local` y `id`; tiene unicidad por trabajador/localidad/fecha.
- `op_rh_v2_calculos_jornada` contiene `id_jornada_programada`, `id_personal`, `id_estacion`, `fecha_jornada_local`, `revision_numero` y `id`; el ajuste referencia una revisión concreta mediante `id_calculo_jornada`.
- `op_rh_v2_evaluaciones_ajustes` relaciona una revisión de evaluación con un ajuste mediante una llave primaria compuesta y FKs. La relación es independiente de la existencia del ajuste.
- `op_rh_v2_evaluaciones_administrativas` conserva, entre otros campos, trabajador, localidad, fecha laboral, jornada, cálculo, revisión, estado y hora UTC de creación.

La lectura propuesta debe verificar que los IDs de jornada y cálculo guardados en cada ajuste concuerden y que trabajador, localidad y fecha del cálculo correspondan a la jornada relacionada. Una discrepancia no se debe ocultar como si la consulta estuviera completa.

### Datos actuales de desarrollo

La consulta agregada de solo lectura devolvió: **0 ajustes, 0 vínculos evaluación–ajuste, 0 ajustes sin vínculo, 0 motivos vacíos y 0 filas con actor nulo**. El total cero permite probar el estado vacío del backend de datos, pero no valida un detalle real, trazabilidad de un ajuste existente ni presentación de filas. La capacidad del esquema para que exista un ajuste sin vínculo de evaluación es `YES`: la fila de ajuste no exige una fila en `op_rh_v2_evaluaciones_ajustes`. No se observó un caso real porque ambas tablas están vacías.

Los campos de actor son obligatorios en el esquema, pero no tienen FK al usuario; con cero ajustes no puede validarse una identidad concreta ni su nombre visible. Por ello la respuesta deberá preservar los IDs y solo aportar nombre si el API puede resolver una cuenta única y autorizada; nunca inferir el responsable.

## 4. Limitación del contrato vigente

La respuesta de evaluación representa únicamente una evaluación concreta y sus referencias asociadas. No contiene motivo, instante/fecha de alta del ajuste, actor, horarios interpretados, referencias de jornada/cálculo para cada ajuste, relaciones completas de evaluación, ni todas las revisiones del ajuste lógico. Tampoco incluye ajustes que no estén enlazados a la evaluación solicitada.

Por tanto, el GET actual no puede sustentar los filtros ni el inventario general pedido. Reutilizarlo en un bucle por trabajadores/fechas no crea un contrato de colección, multiplica llamadas, omite filas sin evaluación y no prueba completitud global. La única forma compatible propuesta es una proyección nueva, propiedad de API V2.

## 5. Alcance exacto propuesto

### Incluido

- GET de colección de revisiones de ajuste, acotado por localidad canónica y periodo de fecha laboral.
- Filtros opcionales por trabajador, tipo y estado persistidos.
- GET de detalle por `uuid_ajuste` con su linaje completo y referencias disponibles.
- Paginación acotada, lectura parametrizada, autorización y aislamiento en API V2.
- Consumo del backend Portal3 a través de JWT delegado y presentación read-only.

### Excluido

- Crear, modificar, aprobar, rechazar, cancelar o eliminar ajustes.
- Ejecutar o recalcular evaluaciones, Journey, P1/P2 o nómina.
- Cambiar PunchEvents, jornadas, incidencias, ajustes, ACL, esquema, M03/M04 o configuración de producción.
- Leer tablas API directamente desde Portal3 o desde el navegador.

## 6. Contrato propuesto de listado

**Ruta propuesta (no implementada):** `GET /api/v2/administrative-adjustments`

### Parámetros

| Parámetro | Requerido | Regla propuesta |
|---|---:|---|
| `workplace_id` | Sí | Entero positivo; `op_rh_localidades.id` canónico y dentro del alcance server-side del actor |
| `date_from` | Sí | Fecha civil local `YYYY-MM-DD`; inicio inclusivo de `fecha_jornada_local` |
| `date_to` | Sí | Fecha civil local `YYYY-MM-DD`; fin inclusivo; no anterior a `date_from` |
| `worker_id` | No | Entero positivo, identidad canónica `op_rh_personal.id`; debe concordar con localidad/fecha en datos V2 |
| `adjustment_type` | No | Filtro exacto sobre `tipo_ajuste`; no convierte una incidencia en ajuste |
| `status` | No | Filtro exacto sobre `estado_ajuste` |
| `page` | No | Entero desde 1; default 1 |
| `per_page` | No | Entero 1–100; default 25 |

El periodo consulta la fecha **afectada** (`jornadas_programadas.fecha_jornada_local`), no `creado_at_utc` ni las horas interpretadas UTC. `creado_at_utc` se devuelve como dato de trazabilidad. Un futuro filtro por fecha de creación tendría que nombrarse y definirse por separado.

Para mantener bounded queries, se propone un periodo máximo de **31 fechas locales inclusivas**. El límite requiere aprobación y medición con distribución real de datos; excederlo devuelve `422 DATE_RANGE_TOO_LARGE`. No se proponen consultas sin periodo, ni elevación automática de límites.

### Fuente, orden y consistencia

- API consulta solo las tablas canónicas y relaciones verificadas; SQL parametrizado.
- La fuente de localidad/fecha/trabajador es la jornada programada y debe validarse frente a la revisión de cálculo referenciada por el ajuste. Si las referencias contradicen, la solicitud falla con `500 DATA_INTEGRITY_CONFLICT`, registra diagnóstico solo en observabilidad protegida y no devuelve una colección aparentemente completa.
- Orden total determinista propuesto: `op_rh_v2_ajustes_administrativos.id ASC` como clave interna de desempate. La API no lo expone; el cliente usa `uuid_ajuste`.
- `page`/`per_page` son paginación offset. Cada respuesta incluye `as_of_utc` y `total_records`; cada página es una lectura viva, no una transacción snapshot que se prolonga entre peticiones. Si una revisión se agrega o su estado cambia mientras el usuario pagina, una petición posterior puede reflejar el estado más reciente. La interfaz no debe afirmar que varias páginas son una foto global a un único instante.
- Si se exige consistencia point-in-time entre páginas, este contrato necesita una decisión adicional sobre almacenamiento snapshot compartido/API. No se presume que una sesión PHP, caché local o las tablas de snapshot de PunchEvents sirvan para ello. No se autoriza crear una tabla ni reutilizar otro dominio de snapshots en esta propuesta.

### Respuesta propuesta

```json
{
  "status": "success",
  "data": {
    "workplace_id": 9,
    "query": {
      "date_from": "2026-10-01",
      "date_to": "2026-10-31",
      "worker_id": null,
      "adjustment_type": null,
      "status": null
    },
    "as_of_utc": "2026-11-06T18:00:00Z",
    "total_records": 0,
    "page": 1,
    "per_page": 25,
    "total_pages": 0,
    "adjustments": []
  }
}
```

Cada elemento contendría únicamente campos sustentados: `uuid_ajuste`, `uuid_ajuste_logico`, `revision_number`, `status`, `adjustment_type`, `worker_id`, `workplace_id`, `work_date_local`, `workplace_timezone` (de la jornada), `motive`, `interpreted_entry_at_utc`, `interpreted_exit_at_utc`, `created_at_utc`, `created_by_user_id`, `approved_by_user_id` y `linked_evaluation_count` (solo si se consulta la relación completa). Campos temporales nulos permanecen `null`; no se convierten en cero, jornada o marca. Ninguna cifra de nómina ni PunchEvent se devuelve.

`adjustments: []` con HTTP 200 significa que la consulta autorizada fue correcta y no encontró filas para esos filtros. Error o autorización denegada nunca se representa como lista vacía.

## 7. Contrato propuesto de detalle e historia

**Ruta propuesta (no implementada):** `GET /api/v2/administrative-adjustments/{uuid_ajuste}`

- `uuid_ajuste` identifica una revisión pública concreta; no se aceptan IDs SQL.
- API verifica autorización y localidad de la jornada vinculada antes de devolver cualquier dato. UUID inexistente y UUID fuera de alcance responden igual con `404 ADJUSTMENT_NOT_FOUND` para evitar enumeración.
- Incluye datos de la revisión seleccionada, su jornada/cálculo verificados, actores persistidos y la cadena ordenada de revisiones que comparte `uuid_ajuste_logico`, recuperada por el propietario API.
- Cada revisión preserva `uuid_ajuste`, número, estado, motivo, horas UTC, `creado_at_utc`, creador, aprobador y referencia previa cuando exista. Las referencias internas se exponen solo como UUID público o referencia de revisión necesaria; no se exponen PK SQL como claves operativas.
- Incluye `linked_evaluations[]` solamente como relaciones reales de `op_rh_v2_evaluaciones_ajustes`, con referencias de evaluación y revisión/fecha/estado disponibles en la fila relacionada. `[]` significa que la consulta de relaciones tuvo éxito y no se encontraron vínculos para el ajuste; no crea evaluación ni afirma un estado de asistencia.
- No se devuelven evidencia/documentos no presentes en el esquema, valores anteriores/posteriores que no estén almacenados, estado de aprobación inferido, responsable con nombre adivinado ni historial reconstruido desde incidencias.

## 8. Autenticación, permisos y alcance

### Autenticación y transporte

- Solo backend Portal3 puede llamar a API V2.
- Se conserva JWT de usuario delegado validado por API (emisor/tipo/expiración/firma y usuario activo); actor deriva de la identidad validada, no de parámetros del cliente.
- El navegador usa una ruta Portal3 autenticada y CSRF/session de Portal3 cuando corresponda; no recibe JWT, secreto API ni URL API.
- Se prohíbe autenticación técnica del Lector.

### Permiso propuesto

Se propone una capacidad independiente para el módulo exacto `checador-v2-ajustes`, acción `leer` (representada como `checador-v2-ajustes.leer` en la matriz funcional y como módulo `checador-v2-ajustes` + bandera `leer` en el ACL de API). No se concede ni configura en esta tarea. No se autoriza por equivalencia con `checador-v2-incidencias.leer`, `biometricos.leer`, lectura de jornadas o permisos genéricos de RH.

La concesión se resolverá conforme al ACL vigente y únicamente para puestos/perfiles expresamente autorizados mediante el procedimiento de gobierno. No se copiarán permisos de puestos a usuarios, no se cambiarán filas de otros módulos y no se concederá acceso efectivo en esta tarea. La intención de producto propuesta es permitir a Encargados la consulta dentro de sus localidades autorizadas y a Dirección la consulta dentro de su allowlist vigente; la asignación efectiva de cada grant sigue pendiente de aprobación y verificación de la configuración ACL actual. Este documento no afirma que un perfil ya tenga ese grant.

### Precedencia de permisos por módulo

Para la nueva capacidad de Ajustes se propone que la decisión funcional se calcule en el namespace del **mismo módulo solicitado**, antes de validar alcance:

1. Resolver el módulo `checador-v2-ajustes` y consultar una posible regla directa del usuario para ese módulo y acción.
2. Si existe esa regla directa para el mismo módulo, su valor efectivo gobierna. Una denegación directa (`leer = 0`, incluida una regla directa existente con lectura desactivada) no puede reemplazarse por herencia del puesto.
3. Solo si no existe regla directa para ese mismo módulo, resolver el grant heredado del puesto autenticado para ese módulo y acción.
4. Las reglas directas del usuario pertenecientes a **otros módulos** no intervienen en esta decisión y no suprimen la herencia de Ajustes.
5. Si no existe autorización aplicable en ninguna de esas dos fuentes, denegar.
6. Después de permitir el permiso funcional, intersectar el workplace pedido con el alcance institucional vigente, validado por API en cada solicitud. El permiso funcional nunca amplía el alcance.

No se usa una unión de grants, no se permite que un grant de otro módulo autorice Ajustes y no se reemplaza una denegación directa del mismo módulo. La decisión no cambia la precedencia de otros módulos ni las operaciones existentes.

**Hallazgo de compatibilidad de implementación (no es una autorización para modificar código):** el API `Portal3JwtAuthHelper::authorizeModuleByKey()` actualmente decide si hay cualquier fila en `modulos_usuarios_do` para el usuario y, si existe, consulta solo la fila directa del módulo pedido; solo consulta `modulos_puestos_do` cuando no hay filas directas en ningún módulo (`modelo/v2/Portal3JwtAuthHelper.php`, método `authorizeModuleByKey()`). Así, una fila directa ajena puede suprimir la herencia de Ajustes. El servicio ACL local de Portal3 usa una búsqueda directa por `permission_key` y luego el grant de puesto para esa misma clave (`app/Services/ChecadorV2PermissionService.php`, `hasPermission()` y `effectiveGrantDecision()`); no demuestra que el helper API tenga la misma semántica. Antes de implementar deben comprobarse el DDL/índices reales, la representación de una denegación y la identidad del puesto en el entorno. La solución contractual mínima es aplicar esta regla en un evaluador de Ajustes aislado o en una abstracción cuya modificación no cambie decisiones de módulos existentes, y probar compatibilidad. No se autoriza corregir globalmente `authorizeModuleByKey()` en esta tarea.

### Matriz normativa de autorización propuesta

| Regla directa del usuario para Ajustes | Grant de puesto para Ajustes | Regla directa en otro módulo | Permiso funcional | Resultado antes de scope |
|---|---|---|---|---|
| No existe | Sí (`leer=1`) | No existe | Sí | Permitir; después validar localidad |
| No existe | Sí (`leer=1`) | Existe con cualquier valor | Sí | Permitir por herencia de Ajustes; la regla ajena no suprime herencia |
| Existe (`leer=1`) | Sí o no | Cualquiera | Sí | Permitir por regla directa de Ajustes; después validar localidad |
| Existe (`leer=0`) | Sí (`leer=1`) | Cualquiera | No | Denegar; denegación directa del mismo módulo prevalece |
| No existe | No existe | Existe con `leer=1` | No | Denegar; otro módulo no concede Ajustes |
| No existe | No existe | No existe | No | Denegar |

Para Dirección se requiere además un grant efectivo aprobado para Ajustes y cada localidad debe estar dentro de la allowlist vigente. Tener permiso de lectura para `administrative-evaluations` no concede por sí solo este nuevo recurso.

### Alcance

- `workplace_id` es siempre `op_rh_localidades.id`; la localidad solicitada funciona como filtro, nunca como autoridad.
- API revalida en cada llamada permiso y alcance de localidades derivado de JWT/ACL actual.
- Encargado solo obtiene su localidad autorizada; Dirección, únicamente las localidades autorizadas que el mecanismo actual resuelva. No se infiere acceso global.
- `worker_id`, si viene, se resuelve como `op_rh_personal.id` y debe aparecer ligado en los datos V2 a la localidad solicitada. No se usa nombre, `numlista`, cuenta Portal3, alias ni igualdad numérica de catálogos como fallback.
- Consultas por UUID de ajuste aplican la misma verificación en la API antes de cargar y serializar el detalle.
- Si una jornada, cálculo o localidad no puede resolverse inequívocamente, se falla de forma cerrada y no se filtra la fila como si no existiera.
- La comprobación del permiso y la de localidad son decisiones independientes: primero debe existir permiso funcional efectivo para el módulo Ajustes; después la localidad canónica solicitada se valida contra el alcance API del actor. Un `workplace_id` suministrado por el cliente jamás amplía dicho alcance.

## 9. Errores propuestos

| HTTP | Código | Condición |
|---:|---|---|
| 400 | `INVALID_PAYLOAD` | Parámetros mal formados, página/tamaño no entero o fechas inválidas |
| 401 | `UNAUTHORIZED` | JWT ausente, inválido, vencido o tipo de autenticación no permitido |
| 403 | `FUNCTION_PERMISSION_DENIED` | Falta permiso explícito de lectura de ajustes |
| 403 | `STATION_SCOPE_DENIED` | `workplace_id` no pertenece al alcance autorizado |
| 404 | `ADJUSTMENT_NOT_FOUND` | UUID no existe o no está en alcance |
| 404 | `WORKER_NOT_FOUND_OR_OUT_OF_SCOPE` | El trabajador no está resoluble/autorizado para la localidad/consulta, sin revelar existencia fuera de ámbito |
| 422 | `INVALID_DATE_RANGE` | `date_to` anterior a `date_from` |
| 422 | `DATE_RANGE_TOO_LARGE` | Rango excede el máximo contractual propuesto |
| 422 | `INVALID_FILTER` | Tipo/estado no admitido por el contrato de filtros |
| 500 | `DATA_INTEGRITY_CONFLICT` | Jornada, cálculo, trabajador, localidad o fecha se contradicen |
| 500 | `SERVER_ERROR` | Fallo interno sanitizado; sin SQL, traza ni datos secretos |
| 503 | `SOURCE_UNAVAILABLE` | Fuente canónica no disponible |

## 10. Compatibilidad

- El GET actual `GET /api/v2/administrative-evaluations` y su respuesta permanecen byte-semantically sin cambio para consumidores actuales. La referencia enlazada sigue significando únicamente el conjunto asociado a esa evaluación específica.
- El contrato nuevo es aditivo e independiente; no altera evaluación, incidencia, cálculo, vigencias, estado ni escrituras.
- Las páginas antiguas de Portal3 no requieren cambiar para continuar consultando evaluaciones existentes; la pantalla Ajustes incorporaría la nueva colección únicamente después de aprobar el contrato y habilitar permiso efectivo.
- No se añadirán aliases de parámetros `station_id` ni se reinterpretarán IDs físicos como `workplace_id` en las rutas nuevas: se requiere `workplace_id` canónico.
- No se necesita cambio de esquema para devolver columnas/relaciones que ya existen. No se propone migración ni grants en SQL. La exposición API y la concesión del nuevo permiso sí son cambios contractuales/técnicos que requieren autorización independiente.

## 11. Consumo Portal3 permitido

Después de aprobar:

1. La ruta de página ya existente sigue sirviendo el workspace Ajustes autenticado.
2. El servicio de lectura backend envía filtros validados a API V2 usando JWT delegado y valida estrictamente eco de filtros, esquema de respuesta, paginación y tipos. No pasa a SQL directo.
3. La carga de DataTables será server-side, pues la colección está paginada por API. La ruta AJAX de mismo origen valida sesión, ACL/alcance, filtros y CSRF cuando aplique antes de llamar al servicio; el navegador nunca recibe la dirección ni credencial API.
4. Select2 de colaboradores usa la fuente de personal ya autorizada de Portal3, con `op_rh_personal.id` como clave de correspondencia y nombre completo como etiqueta. Si el catálogo no puede demostrar identidad/localidad, no ofrece trabajador para filtrar.
5. El detalle se carga solo al seleccionar una referencia y queda read-only. Motivo y nombres se escapan; horas UTC se presentan mediante formateador Portal3 y zona laboral aplicable sin alterar los valores API.
6. Respuestas vacías, denegación, fuente no disponible y datos incompletos se muestran como estados distintos. Fallos nunca se transforman en “sin ajustes”.

### Cambios mínimos previstos tras aprobación

- **API V2:** agregar handler GET de lista/detalle, validación del nuevo permiso y alcance, consultas parametrizadas/serialización, registrar rutas en el router existente y pruebas focalizadas. No tocar `AdministrativeEvaluationService` ni escritores.
- **API V2 — autorización:** verificar el esquema ACL real y aplicar la precedencia por módulo de §8 únicamente al nuevo lector de Ajustes, idealmente en un evaluador dedicado. La alternativa de cambiar `Portal3JwtAuthHelper::authorizeModuleByKey()` de forma general requiere análisis y pruebas de compatibilidad adicionales; no se asume autorizada por esta propuesta.
- **Portal3:** extender `ChecadorDailyReadService` o añadir un servicio read-only aislado para la colección/detalle; integrar filtros con `ChecadorController::adjustments()` y `workspace-adjustments.php`; añadir transporte same-origin/DataTables server-side y detalle read-only, con pruebas focalizadas. Sin llamadas API desde JS.
- **No modificar:** Architecture Freeze, J1/J2, M02–M06, PIN temporal, ACL efectivo, tablas, migraciones ni operaciones de escritura.

## 12. Pruebas de aceptación propuestas

### API V2

- JWT ausente/vencido/inválido; permiso ausente; scope válido/no válido; puesto Encargado limitado; Dirección solo a lista autorizada.
- Precedencia del permiso de Ajustes: herencia de puesto sin regla directa; regla directa en otro módulo sin supresión de herencia; denegación directa del mismo módulo prevalece; ausencia de grants deniega. Verificar que la corrección de Ajustes no habilite ninguna capacidad previamente denegada en otro módulo.
- Compatibilidad de perfiles existentes: un usuario con una fila directa en `acceso-temporal`, incidencias u otro módulo debe conservar exactamente las decisiones efectivas previas en esos módulos; PIN temporal, sus perfiles y su alcance no cambian. No ejecutar una prueba que modifique grants reales: cubrirla con ACL aislada/fakes autorizados y después validar el diff de decisiones.
- Filtros individualmente y combinados; fecha local inclusiva; límite de periodo; parámetros mal formados; consultas parametrizadas.
- Paginación 0/1/múltiples páginas, orden repetible, tamaño máximo; respuesta `total_records`/vacío; cambios concurrentes claramente tratados conforme a consistencia aprobada.
- Worker/localidad contradictorios; IDs inexistentes; UUID de ajuste dentro/fuera de localidad; detalle sin vínculos de evaluación; varias revisiones; estado/motivo/actor persistidos.
- Datos de jornada/cálculo discordantes fail-closed; diferencia entre cero resultados, 403, 404, 500 y 503.
- Comprobación de no mutación de tablas: todo endpoint nuevo solo GET, sin DML/DDL, evaluación, Journey ni cambios de asistencia.

### Portal3 y navegador

- Chrome con perfiles Encargado y Dirección y localidades en alcance/fuera de alcance; ruta existente, filtros Select2, DataTables server-side, detalle y manejo de estados.
- Confirmar que no aparece acción de escritura ni llamada API directa desde navegador.
- Verificar nombres mediante identidad canónica, aislamiento de localidades y búsqueda/periodo/tipo/estado combinados.
- Verificar que la ACL de PIN temporal y los demás módulos conserva sus valores y decisiones, y que no aparece ningún acceso recién habilitado fuera de Ajustes.
- Responsive a 375, 768, 1024 y 1440 px; accesibilidad, consola y navegación repetida.
- Hasta que existan ajustes reales accesibles en desarrollo, probar detalle solo con fixtures aislados en API; no declarar readback real ni E2E de filas canónicas. La base de desarrollo observada está vacía para ajustes.

## 13. Riesgos y decisiones requeridas

1. **Aprobación y asignación del nuevo permiso:** confirmar que se acepta el módulo/permiso independiente `checador-v2-ajustes` + `leer`, y qué puestos/perfiles podrán recibirlo. La ACL actual por perfil no se verificó mediante lectura de grants reales en esta tarea y no hay autorización para cambiarla; la concesión debe preservar filas y herencias de todos los demás módulos.
2. **Compatibilidad del autorizador API:** el helper observado usa existencia de cualquier permiso directo como selector excluyente de fuente. La implementación futura debe resolver direct-vs-position por módulo solicitado, preservando la denegación directa del mismo módulo, sin cambiar decisiones de otros módulos. Confirmar esquema real y casos de filas/denegaciones antes de codificar.
3. **Semántica de paginación:** propuesta page/offset determinista con lectura viva por respuesta y `as_of_utc`; no representa un snapshot temporal coherente entre varias páginas. Si se exige un inventario point-in-time, se debe aprobar y diseñar almacenamiento de snapshot compartido. No reusar snapshots de PunchEvents sin autorización.
4. **Fecha del periodo:** se define como `fecha_jornada_local` afectada. Si RH necesita filtrar por fecha de creación UTC, hace falta un filtro nombrado aparte; no se confunden ambos tiempos.
5. **Trazabilidad del actor:** IDs creador/aprobador están persistidos y obligatorios, pero no tienen FK en esquema. El nombre solo se puede publicar tras resolución única en la fuente de cuentas; el dataset actual vacío no permite confirmar esa resolución con una fila real.
6. **Muestras reales ausentes:** 0 ajustes significan que pruebas de contenido, estado y trazabilidad con datos canónicos deben esperar a que exista un ajuste legítimo; no se deben crear datos para esta tarea.
7. **Rendimiento:** el rango propuesto de 31 días aprovecha el índice localidad-fecha de jornadas y la FK de ajustes por jornada/cálculo; debe medirse con datos representativos antes de fijar el límite. No aumentar a ciegas.
8. **Autorización de implementación:** el contrato P3-G existente es `FROZEN_OWNER_APPROVED`, pero no cubre estas rutas nuevas. Este documento no lo modifica ni equivale a aprobación. API y Portal3 deben registrar aprobación cruzada y alcance técnico antes de publicar la extensión.

## 14. Regresiones reportadas P3GA / P3GD

Los contadores recibidos para revisión fueron `P3GA 24 PASS / 1 FAIL` y `P3GD 43 PASS / 4 FAIL`. No se reejecutaron los runners en esta tarea documental; algunos runners API son DB-backed/destructivos. La clasificación se basa en las aserciones locales y el código actual inspeccionados.

| Prueba | Clasificación | Evidencia y efecto |
|---|---|---|
| P3GA, único fallo (aserción no-mutación de fuente) | Expectativa estática de alcance demasiado amplio; no demuestra mutación de lectura | La aserción escanea el archivo completo y compartido `ChecadorController.php`/servicio por palabras DML, incluyendo otros handlers. No es una medición de estado DB antes/después ni está limitada al método de Ajustes. Sin evidencia de runtime no se declara defecto funcional; mantener guard de no escritura en el nuevo slice. |
| P3GPD32 | Expectativa estática de alcance demasiado amplio | Escanea el controlador entero por la cadena `raw PunchEvent`; existe un comentario de un método hermano de lectura raw, fuera del recorrido de evaluación. No prueba mezcla de fuentes en el handler de evaluación. |
| P3GPD35 | Brecha visual/proyección real respecto a la información esperada; aserción demasiado literal en su forma | El servicio conserva `policy_rule_version` nullable y la vista muestra identificador/fecha de cálculo bajo otra etiqueta, pero no renderiza la referencia nullable con la expresión textual esperada ni llama “Referencia de cálculo” como exige la prueba. La distinción de semántica nula no debe inventarse; tras aprobación, la UI puede etiquetar los datos existentes sin cambiar cálculo. |
| P3GPD39 | Expectativa de texto/capitalización obsoleta | La vista dice “nunca se activa automáticamente” en minúsculas; la prueba busca “Nunca se activa automáticamente” de forma sensible a mayúsculas. El significado y acción siguen siendo explícitos. |
| P3GPD46 | Brecha real de presentación | La vista no contiene la frase contractual de que el GET actual no entrega historia completa. El estado actual muestra referencias enlazadas, pero no comunica suficientemente ese límite. Debe corregirse en el slice de presentación aprobado; no implica que exista el API de detalle. |

Ninguna de estas pruebas autoriza a cambiar contrato, ACL o implementación en esta etapa. No se editaron pruebas.

## 15. Resumen de autorización

| Área | Estado de esta propuesta |
|---|---|
| Listado y detalle de ajustes | Propuestos; no implementados |
| API V2 GET nuevos | Requieren aprobación contractual/técnica |
| Permiso `checador-v2-ajustes.leer` | Propuesto; sin grant efectivo |
| Portal3 DataTables/Select2/detalle | Adaptaciones futuras después de aprobación |
| API/Portal3 escrituras de ajustes | Fuera de alcance y no autorizadas |
| Base de datos/desarrollo/producción | Sin cambios |
| Arquitectura Freeze, J1/J2 y contrato P3-G | Sin cambios |
| Aprobación final | Pendiente; requerida antes de implementar |
