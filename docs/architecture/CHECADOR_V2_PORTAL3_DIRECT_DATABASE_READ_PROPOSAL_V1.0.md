# CHECADOR V2 — Portal3 Direct Database Read Proposal V1.0

**STATUS: DRAFT_NOT_APPROVED**  
**PROJECT:** CHECADOR V2 — ADMON GAS  
**TASK:** `CHECADOR_V2_PORTAL3_DIRECT_DATABASE_READ_CONTRACT_V1`  
**SCOPE:** Architecture proposal only. This document is not a runtime contract and does not authorize implementation.

## 1. Objetivo

Proponer cómo Portal3 podría leer información administrativa persistida de Checador V2 directamente desde una fuente de base de datos autorizada, sin reconstruir cálculos ni reinterpretar marcaciones. La aprobación debe preceder cualquier cambio a los contratos vigentes, al esquema o al código.

La arquitectura deseada se expresa como:

```text
Lector → API V2 → base de datos → Portal3
```

API V2 conserva la recepción, validación, integridad, idempotencia, continuidad, resolución histórica y generación de resultados. Portal3 autentica a su usuario, aplica su autorización institucional y consulta únicamente resultados publicados para lectura.

## 2. Arquitectura actual

- Portal3 configura Eloquent en `app/Core/Database.php` mediante variables de entorno. API tiene su propia configuración; no se verificó que ambos procesos apunten a la misma base o instancia.
- `ChecadorDailyReadService` usa solicitudes HTTPS autenticadas a API para eventos, snapshots, Level 2, horarios, jornadas, incidencias y evaluaciones administrativas.
- `ChecadorAttendanceRosterService` usa modelos Portal3 para el padrón operativo y el resolvedor de asignaciones institucionales.
- `ChecadorRawPunchReadService` contiene SELECT de desarrollo sobre `op_rh_v2_marcaciones_crudas`; su propia documentación deshabilita uso productivo hasta disponer de una conexión separada de sólo lectura.
- Las migraciones del repositorio API documentan tablas V2 para hechos originales, snapshots, continuidad, horarios, jornadas, pareos, cálculos e incidencias. No se consultó un servidor de base de datos; el esquema activo no está verificado.
- P3-H V1.1 conserva una frontera de presentación basada en el payload contractual de API y prohíbe que Portal3 rehaga pareos, cálculos, incidencias o zona horaria. P3-G define el dominio V2 de incidencias como canónico y prescribe una frontera autenticada Portal3 backend → API para operaciones administrativas.

## 3. Arquitectura propuesta

Se recomienda **Opción B: proyección de lectura dedicada**, propiedad del componente/API que genera los resultados V2. API materializa o publica una proyección versionada en una fuente de datos autorizada; Portal3 la consulta con Eloquent mediante una conexión dedicada de sólo lectura.

La proyección no será un motor alternativo. Debe exponer resultados ya calculados, sus referencias de procedencia, estado de integridad y completitud, y una versión de contrato. El acceso de lectura de Portal3 no modificará eventos, evaluaciones ni estados. Las operaciones transaccionales, cambios administrativos y validación de PIN continúan por API.

```text
Lector ──ingesta──> API V2 ──> hechos y resultados V2
                                  │
                       proyección versionada, propiedad de API
                                  │ conexión SQL read-only
                                  ▼
                         servicio de lectura Portal3
                                  │
               autorización institucional y alcance de estación
                                  ▼
                               interfaz
```

Esta propuesta asume una fuente SQL de lectura accesible para Portal3, pero no asume que exista hoy. Se debe decidir si la proyección se publica en la misma base con permisos aislados o en una réplica/almacén de lectura. La elección depende de infraestructura, latencia aceptable y garantías de consistencia; no se copiarán credenciales ni se reutilizará automáticamente la conexión actual.

## 4. Comparación de alternativas

| Criterio | Opción A: SELECT directo a tablas operativas | Opción B: proyección dedicada versionada |
|---|---|---|
| Complejidad inicial | Menor en Portal3, mayor acoplamiento a tablas y joins internos | Requiere contrato y productor de proyección; Portal3 consume un esquema estable |
| Seguridad | Permisos deben cubrir tablas sensibles; riesgo de leer datos de otro ámbito por joins o filtros omitidos | Permiso limitado a la proyección y alcance consultable; superficie de exposición menor |
| Rendimiento | Consultas complejas pueden competir con escrituras/operación API | Índices y forma de consulta diseñados para lectura administrativa; puede aislar carga |
| Consistencia | Riesgo de combinar filas de revisiones o momentos distintos | La proyección puede publicar revisión/versión y snapshot de forma atómica o identificable |
| Trazabilidad | Portal3 debe conocer IDs internos y reconstruir relaciones para explicar el resultado | La proyección incluye referencias de fuente, revisión y estado del cálculo sin transferir autoridad |
| Dependencias | Acoplamiento fuerte a migraciones y estructura interna de API | Dependencia explícita de un contrato de proyección versionado y su productor |
| Cambios de esquema | Probables cambios coordinados cada vez que cambien tablas internas | Cambios aditivos/versionados con periodo de compatibilidad definido |
| Mantenimiento | Alto riesgo de duplicar lógica, alcance o reglas en consultas | Menor duplicación; mayor responsabilidad de mantener y monitorear el productor |

**Recomendación:** Opción B. Opción A consulta persistencia interna que no está diseñada como interfaz de lectura Portal3 y puede requerir reconstruir relaciones, estados y semánticas. La proyección versionada mantiene los cálculos en su propietario y permite el objetivo de evitar HTTP para lecturas ordinarias con un límite de acceso más claro. La proyección y su disponibilidad todavía requieren diseño y aprobación; no se afirma que ya exista.

## 5. Fuentes de datos candidatas

Las tablas siguientes son las que aparecen en migraciones/contratos consultados. Son candidatas de origen, no un permiso para que Portal3 las consulte directamente. El contenido real y la cobertura del esquema desplegado no se verificaron.

| Información | Fuente autoritativa / tablas candidatas | Naturaleza y propietario | Restricción de lectura |
|---|---|---|---|
| Asistencia del día / reporte de marcaciones | Hechos `op_rh_v2_marcaciones_crudas`; dispositivos `op_rh_v2_dispositivos`; identidad histórica `op_rh_v2_dispositivo_identidad_personal`; membresía `op_rh_v2_read_snapshots` + `op_rh_v2_read_snapshot_members` | La marcación recibida es un hecho original e inmutable, persistido por API. Snapshot es una membresía estable para recuperación completa Level 1 | Mostrar hecho recibido separado de validación/evaluación. No leer un rango como completo si falta membresía/snapshot o su estado de completitud |
| Integridad del evento | Metadatos/sello del evento crudo, `PunchEventIntegrityService`, credenciales/clave administradas por API | API sella/verifica HMAC-SHA256 y aplica idempotencia | Portal3 conserva estado y referencia; no recalcula ni modifica sellos ni presume verificación local |
| Continuidad | Tablas de generaciones, telemetría y evaluaciones de ventana Level 2 (nombres/cobertura exactos a confirmar contra contrato y despliegue) | Metadatos de continuidad generados por API/Lector según contrato | Presentar `VERIFIED`, `INCOMPLETE`, `UNKNOWN` y datos ausentes como estados distintos; no inferir cero marcaciones por ausencia de filas |
| Horarios y turnos históricos | `op_rh_v2_turnos_plantillas`, `op_rh_v2_turnos_segmentos`, `op_rh_v2_turnos_asignaciones`, `op_rh_v2_jornadas_programadas` | Horarios con vigencia e instancias programadas persistidas por componente V2; la asignación efectiva también está expuesta por API | La asociación válida depende de trabajador, workplace, fecha y vigencia. No reconstruir horario sólo con plantilla ni tratar un contrato candidato como runtime verificado |
| Pareo de marcaciones | `op_rh_v2_pareos_jornada`, `op_rh_v2_pareos_segmentos`, `op_rh_v2_pareos_marcaciones_asociadas` | Resultado P1 derivado y persistido por API; roles como `ENTRY`, `EXIT`, `SURPLUS`, `UNMATCHED` | Portal3 presenta rol/ordinal autoritativos. No volver a parear ni truncar eventos; mantener posiciones neutrales `Entrada 1`, `Salida 1`, `Entrada 2`, `Salida 2` |
| Jornada | `op_rh_v2_calculos_jornada`, `op_rh_v2_calculos_segmentos`, asociadas con `op_rh_v2_jornadas_programadas` y pareos | Resultado P2 derivado y versionado por API | Mostrar resultado sólo con estado disponible y referencias consistentes; Portal3 no calcula minutos, faltas o zona horaria |
| Incidencias | `op_rh_v2_incidencias_maestro`, `op_rh_v2_incidencias_revisiones`, `op_rh_v2_incidencias_aplicaciones` | Evidencia/estado administrativo canónico V2, administrado por API | Preservar revisiones y estado; no sustituir por tablas legacy ni derivar aplicabilidad en Portal3 |
| Evaluación administrativa | `op_rh_v2_evaluaciones_administrativas`, `op_rh_v2_evaluaciones_incidencias`, `op_rh_v2_evaluaciones_ajustes`; ajustes administrativos y revisiones | Resultado derivado con linaje de jornada, incidencias y ajustes, generado por API | No tratar evaluación faltante como `FALTA`; mostrar no disponible/incompleta hasta verificar prerequisitos y la revisión vigente |
| PIN temporal | Solicitud temporal y validación administrada por API, incluida `op_rh_v2_solicitudes_acceso_temporal` | Operación de seguridad/transaccional | Siempre validar por API. No consultar PIN o hashes desde Portal3 para decidir aceptación |

Las tablas de pareos/cálculos y la salida contractual Journey existen como conceptos y persistencias documentadas. **No se identificó una proyección compacta/versionada de lectura Portal3** que una todos los estados anteriores con una política consistente de revisión y autorización. Esa es una dependencia faltante de la Opción B.

### Identidad y estación

- La identidad canónica de persona es `op_rh_personal.id`; la referencia externa de dispositivo puede resolverse históricamente a esa persona según dispositivo y vigencia. El nombre y número de empleado son etiquetas, no claves de vinculación.
- Persistencia V2 registra estación/dispositivo y estación resuelta. El ámbito Checador denomina `workplace_id` a `op_rh_localidades.id`, según P3-G.
- No se confirmó una relación autoritativa completa estación física → dispositivo → localidad/workplace ni su historial de vigencia. No vincular por igualdad numérica. API/propietario de identidad debe publicar el mapeo estable y sus cambios o confirmar una relación ya gobernada.
- Portal3 debe obtener los workplaces autorizados para el usuario mediante `ChecadorAccessService`; la proyección debe permitir filtrar por `workplace_id` y verificar que persona/evento corresponden al alcance solicitado. Toda consulta debe incluir ámbito en el servidor, no aceptar un ID de estación enviado por navegador como autorización.

## 6. Contrato de conexión y seguridad propuesto

1. Configurar una conexión Eloquent separada por entorno para lectura Checador V2. No reutilizar ni asumir la conexión operativa actual. Desplegarla sólo cuando infraestructura confirme host/base y segmentación autorizados.
2. Usar una identidad SQL propia de Portal3 con `SELECT` sólo sobre las vistas/tablas de proyección aprobadas. Sin `INSERT`, `UPDATE`, `DELETE`, DDL, privilegios de cuenta, acceso a secretos HMAC ni permisos amplios sobre tablas API.
3. Guardar secretos en el mecanismo de configuración aprobado para cada entorno. No incluirlos en código, logs, excepciones ni salida de diagnóstico. Aplicar TLS y rotación según plataforma.
4. Ejecutar consultas parametrizadas por `workplace_id`, fecha/rango y, cuando aplique, `worker_id`. Limitar ventana, paginación, orden permitido y tamaño de respuesta. El servicio vuelve a validar IDs y ámbito.
5. Autenticar al usuario en Portal3 y resolver ACL/ámbito antes de consultar. La base de datos impone mínimo privilegio, pero no sustituye la autorización institucional de Portal3.
6. Prohibir acceso SQL desde navegador. Ninguna respuesta de error debe cambiar a otra fuente menos autoritativa ni fabricar marcaciones.
7. Clasificar fallos sin filtrar SQL ni credenciales: `UNAUTHORIZED`, `SCOPE_DENIED`, `SOURCE_UNAVAILABLE`, `DATA_INCOMPLETE`, `PROJECTION_VERSION_UNSUPPORTED` y `NO_RECORDS` como estados separados.

La conexión común o identidad física de base entre API y Portal3 debe confirmarse por responsables de infraestructura. El contrato no requiere que Portal3 tenga permisos sobre la base de escritura primaria si se provee réplica/proyección autorizada.

## 7. Integridad, trazabilidad y consistencia

La proyección debe preservar como mínimo `event_uuid`, identidad canónica resuelta, estación/workplace gobernado, tiempo de ocurrencia UTC y referencia de zona/fecha local aplicable; estado de procesamiento e integridad API; snapshot/su referencia de auditoría y estado Level 1; estado Level 2 y su ventana; IDs públicos de jornada/eventos y revisión/versión de pareo, cálculo e incidencia/evaluación. El contrato de proyección debe definir campos finales, no este borrador.

Distinciones obligatorias:

1. **Marcación recibida:** hecho crudo inmutable, único por regla de idempotencia API.
2. **Marcación validada:** estado de ingestión/integridad emitido por API; no es sinónimo de evaluada.
3. **Marcación evaluada:** evento clasificado por una revisión de pareo P1, asociado a rol y segmento.
4. **Jornada calculada:** resultado P2 asociado a horario, snapshot y revisión identificables.
5. **Incidencia administrativa:** registro y revisión de P3-G, separado del evento físico y de la jornada.

Cada fila/proyección debe permitir identificar versión de contrato y referencia/revisión de origen; la publicación debe evitar mezclar generaciones. Una vista de asistencia sólo puede presentarse como completa cuando los estados de snapshot y continuidad satisfacen los criterios contractuales, y cuando existe una revisión de evaluación aplicable. En caso contrario debe mostrar incompleto/no disponible y conservar los datos parciales como evidencia, no como conclusión.

El snapshot Level 1 representa pertenencia exacta a una vista MVCC consistente y el consumidor debe recuperar la membresía completa. La proyección debe retener snapshot/audit ref y cobertura consultada. Level 2 es la fuente de afirmación de completitud de entrega/ventana; ausencia de eventos sólo significa cero eventos si API confirma cobertura completa. Cambios de horario, pareo, evaluación o incidencia deben conservar histórico append-only/revisionado y permitir distinguir revisión actual de anteriores.

## 8. Flujo futuro de `/departamento-operativo/recursos-humanos/checador/asistencia`

1. Portal3 autentica al usuario y obtiene permisos institucionales.
2. Valida selección de estación/workplace dentro de su alcance; para fecha, valida formato y límites de rango.
3. Obtiene zona horaria y fecha laboral autoritativas según la fuente aprobada; no deduce la zona por estación numérica.
4. El servicio Portal3 consulta la proyección autorizada con filtros parametrizados de workplace, fecha y filtros opcionales validados.
5. Lee la versión/revisión y comprueba estado de fuente, snapshot Level 1, continuidad Level 2 y disponibilidad de jornada/evaluación. No ejecuta el cálculo.
6. Adapta sólo la representación a la interfaz. Las cuatro posiciones son etiquetas neutrales; lista completa de marcaciones y extras debe seguir disponible.
7. Renderiza uno de estos resultados:

| Estado | Comportamiento |
|---|---|
| Registros completos | Presentar eventos y resultados publicados con sus estados/referencias |
| Consulta válida sin registros | Mostrar vacío sólo si la cobertura/snapshot y continuidad autorizan concluir que no hubo eventos |
| Información incompleta | Mostrar evidencia recuperada y etiqueta explícita de incompletitud; omitir conclusiones definitivas |
| Error de conexión/fuente | Estado de servicio no disponible, conservar filtros/contexto; cero registros no es fallback |
| Acceso no autorizado | Rechazar antes de consulta o filtrar en servidor con alcance autorizado; no revelar existencia de otras estaciones |
| Evaluación no disponible | Mostrar marcación/horario disponibles con estado “evaluación no disponible”; nunca inferir falta o ausencia |

El flujo no usa datos demo ni registros ficticios. El manejo de PIN continúa siendo una operación del API, fuera de esta consulta.

## 9. Contratos afectados y propuesta de enmienda

Esta propuesta **no modifica** Architecture Freeze ni contratos originales. Requiere aprobar un paquete de enmiendas antes de implementar.

| Regla vigente | Conflicto | Cambio propuesto para aprobación | Justificación e impacto |
|---|---|---|---|
| Architecture Freeze V1.0: la autoridad de captura, ingestión y persistencia está en Lector/API; no hay autorización implícita para implementar ni cambiar tablas/contratos | No define una interfaz SQL de lectura administrativa para Portal3 | Añadir una enmienda versionada que autorice expresamente el consumo Portal3 de una proyección de lectura aprobada, sin otorgar acceso general a tablas internas ni cambiar propiedad de hechos/cálculos | Aclara que la lectura no transfiere autoridad. API define/publica proyección; Portal3 consume; Lector no cambia. Requiere aprobación arquitectónica explícita y revisión del Freeze sin editar el original |
| P3-H V1.1 exige usar payload API y prohíbe autoridad/cálculos locales | Para la lectura deseada, Portal3 recibe esos campos desde DB | Mantener semántica y payload como esquema de la proyección; añadir la proyección DB como canal alternativo autorizado para esos mismos resultados/versiones | Afecta API (productor), Portal3 (lector/adaptador), no Lector. Riesgo de divergencia entre payload HTTP y proyección; definir fuente única de serialización |
| Level 1 exige snapshot inmutable, recuperación completa y alcance autorizado | SELECT ad hoc no ofrece por sí solo miembro completo ni vista estable | La proyección debe contener snapshot ref, miembro/estado de completitud, ventana y revisión; publicar lectura atómica/identificable y un criterio para confirmar que el conjunto se recuperó completo | Preserva integridad del corte. Requiere prueba contractual de paginación/consistencia y política de expiración/retención |
| Level 2 atribuye a la fuente de continuidad los estados de integridad de ventana | Portal3 no puede inferir continuidad leyendo conteos crudos | Incluir estado agregado de ventana, dimensiones y referencia de evaluación Level 2 emitidos por el propietario | Evita falso “sin marcaciones”. No recalcular continuidad en Portal3 |
| Scheduling/Journey deja horarios y cálculos bajo propiedad V2; contratos de Portal3 prohíben recálculo | Tabla directa puede exigir reconstruir la regla temporal y seleccionar revisión | Incluir horario efectivo/histórico, jornada, pareo, cálculo, clasificación y revisiones ya resueltos | Mantiene responsabilidad API. Confirmar que cualquier servicio candidato está implementado y que la salida es persistida antes de declarar disponibilidad |
| P3-G identifica API V2 como dominio canónico de incidencias y exige frontera Portal3 backend → API para operaciones P3-G | Lectura de incidencias/evaluaciones desde proyección SQL se apartaría de la frontera de transporte | Solicitar modificación explícita sólo para lectura ordinaria de proyección; conservar toda escritura, ciclo de vida, evaluación solicitada y PIN por API | No se cambia propietario ni ACL; requiere especificar segregación entre lectura y mutación, linaje y revocación de acceso |

### Texto propuesto para la nueva regla (borrador)

> Portal3 podrá leer, exclusivamente desde el backend y mediante una identidad de base de datos de sólo lectura, la proyección Checador V2 versionada y aprobada por el propietario API. La proyección será derivada de las fuentes V2 autoritativas, preservará identificadores públicos, revisiones, integridad, snapshot, continuidad y estados de evaluación, y no concederá a Portal3 autoridad para escribir, validar, parear, calcular, resolver incidencias ni validar PIN. La autorización institucional y el alcance por workplace se verificarán en Portal3 para cada consulta. La ausencia o incompletitud de fuente nunca se interpretará como ausencia de marcaciones o falta.

Este texto requiere revisión y aprobación del responsable y de propietarios API/seguridad/datos. No es regla vigente.

## 10. Inventario futuro de archivos

| Archivo | Tratamiento futuro propuesto | Motivo |
|---|---|---|
| `app/Core/Database.php` | Adaptar sólo si la plataforma aprueba una conexión Eloquent separada por entorno | No reutilizar credenciales/conexión operativa |
| `app/Services/ChecadorDailyReadService.php` | Adaptar su responsabilidad de lecturas ordinarias para consumir servicio de proyección; conservar API para operaciones y cualquier lectura aún contractual | No eliminar transportes sin enmienda aprobada |
| `app/Services/ChecadorAttendanceRosterService.php` | Reutilizar padrón actual para fecha actual; definir fuente histórica autoritativa antes de ampliar | No asumir que roster presente equivale a roster histórico |
| `app/Services/ChecadorRawPunchReadService.php` | No promover directamente; reutilizar sólo validaciones/lectura si la fuente y contrato se aprueban; sustituir su conexión de desarrollo por la conexión dedicada | Estado actual es explícitamente desarrollo |
| `app/Services/ChecadorAccessService.php` | Reutilizar autorización institucional y workplace; adaptar sólo al mapeo formal estación/workplace aprobado | Debe seguir siendo validación de servidor |
| `app/Services/WorkerWorkplaceAssignmentResolver.php` | Reutilizar para asignaciones Portal3 que cubra; no usar como conversor de estación física sin contrato | Fuente no demuestra mapeo global de dispositivos |
| `app/Controllers/ChecadorController.php` | Adaptar orquestación/estados de la pantalla sólo después de aprobar contrato y servicio | Mantener presentación sin autoridad de cálculo |
| Configuración de despliegue por entorno | Crear entradas de conexión y secreto de cuenta read-only aprobadas | Archivo específico depende del mecanismo de configuración autorizado; no tocar `.env` en esta propuesta |

No se proponen modelos ni servicios nuevos en esta fase. El esquema candidato de proyección y su productor son decisiones pendientes, no nombres de tablas definidos.

## 11. Riesgos y mitigaciones

- **Lecturas inconsistentes o duplicadas:** usar snapshot/revisión y publicación atómica identificable; rechazar páginas incompletas.
- **Filtración entre estaciones:** autorización Portal3 en cada solicitud, filtros parametrizados obligatorios, permisos de BD limitados y prueba de denegación cruzada al implementar.
- **Acoplamiento a esquema interno:** Portal3 consume esquema público versionado, no tablas internas de escritura.
- **Estado atrasado en réplica:** exponer hora/revisión de actualización y antigüedad; definir SLA y cuándo se muestra stale/no disponible.
- **Desacuerdo HTTP/SQL:** un serializador/fuente semántica de API debe producir ambas representaciones o definir precedencia y reconciliación.
- **Confundir falta de filas con falta de asistencia:** sólo evaluación completa autorizada puede generar estado administrativo; estados incompletos y sin evaluación siguen visibles.
- **Mapeo de workplace incorrecto:** falta relación autoritativa estación física-dispositivo-localidad; bloquear lecturas hasta definirla.
- **Permisos excesivos:** credencial dedicada con SELECT sólo en proyección; auditoría y rotación; nunca exponerla al navegador.
- **Migración prematura:** no crear tablas hasta confirmar si las persistencias actuales pueden alimentar contrato versionado y si una vista/replica satisface consistencia y auditoría.

## 12. Decisiones pendientes

1. Aprobar Opción B y autorizar formalmente una enmienda al Freeze y a P3-H/P3-G/Level 1/Level 2/scheduling-Journey.
2. Nombrar propietarios que aprueban el contrato de proyección (API, Portal3, datos/DB, seguridad y responsable de Checador).
3. Confirmar si se leerá desde DB primaria, réplica o almacén separado y demostrar conectividad/segmentación sin compartir secretos.
4. Definir esquema versionado, clave estable, revisiones, estados, cardinalidad, paginación, retención y política de consistencia.
5. Probar/documentar el mapeo vigente e histórico estación física → dispositivo → `op_rh_localidades.id` (`workplace_id`) y su relación con personal.
6. Confirmar qué cálculos/evaluaciones están persistidos y disponibles en runtime, frente a sólo documentados o candidatos.
7. Determinar el límite de frescura aceptable para asistencia ordinaria y reportes históricos.
8. Decidir si lecturas de incidencias/evaluaciones se incluyen en proyección o permanecen en API; mantener mutaciones en API en ambos casos.
9. Definir respuesta de proyección inexistente, vencida, parcial, obsoleta o con versión incompatible.

## 13. Recomendación técnica

Aprobar para diseño detallado la **Opción B: proyección de lectura dedicada, versionada y propiedad de API V2**, servida a Portal3 desde una conexión Eloquent read-only por entorno. Mantener los contratos semánticos y la propiedad de cálculos; cambiar sólo el canal de lectura ordinaria una vez aprobadas las enmiendas. Las tablas operativas pueden seguir siendo la fuente autoritativa detrás de la proyección, pero Portal3 no debe enlazarlas directamente ni replicar decisiones de evaluación.

La aprobación debe quedar condicionada al contrato de mapeo workplace, evidencia de completitud snapshot/Level 2, linaje de revisiones, SLA de frescura, cuenta SQL restringida y compatibilidad entre las representaciones HTTP y SQL. Hasta entonces continúan vigentes los endpoints y restricciones actuales; este documento no autoriza código, DDL, acceso de producción ni cambio de responsabilidades.

## Referencias consultadas

- `docs/architecture/CHECADOR_V2_ARCHITECTURE_FREEZE_V1.0.md`
- `docs/v2/CHECADOR_V2_PORTAL3_P3H_PRESENTATION_PROJECTION_CONTRACT_V1.1.md`
- `docs/v2/CHECADOR_V2_PORTAL3_P3G_INCIDENCE_ADMINISTRATIVE_CONTRACT_V1.0.md`
- `docs/v2/PUNCH_EVENTS_SNAPSHOT_READ_API_CONTRACT_V2.4_LEVEL1.md` (API)
- `docs/v2/CHECADOR_V2_P3H_EVENT_CLASSIFICATION_PUBLIC_PAYLOAD_CONTRACT_V1.1.md` (API)
- API migrations `2026_09_28_000001` raw punches, `2026_10_05_000001` snapshots, `2026_10_06_000001` scheduling, and `2026_10_07_000001`–`000004` journey and administrative incidence/provenance.

**Approval state:** Pending architectural approval. Original Freeze and contracts remain unchanged.
