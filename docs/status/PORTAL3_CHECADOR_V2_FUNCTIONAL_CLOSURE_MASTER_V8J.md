# Portal3 Checador V2 — cierre funcional y preparación V8J

**Fecha:** 2026-10-22
**Entorno revisado:** Portal3 local / Fedora
**Alcance:** consolidación V8A–V8I y verificaciones focalizadas; sin cambios de aplicación.

## Dictamen ejecutivo

La implementación local cubre consulta de marcaciones, consulta diaria V2, horario histórico/nocturno, Journey/P3-H, ciclo de incidencias y lectura comparativa informativa con nómina. Las pruebas locales/sintéticas no equivalen a una validación real con sesión, ACL y API en el subdominio.

No se encontró un defecto funcional nuevo en las verificaciones focalizadas de esta actividad. La validación visual autenticada no pudo ejecutarse porque la herramienta de navegador falló al inicializar. La verificación de sesión real está pendiente. La lectura SQL local del reporte raw tiene evidencia previa de solo lectura en `bd_admongas`; la lectura combinada Checador–Nómina no tiene prueba real autorizada.

**Preparación:** Portal3 puede prepararse para desplegarse al subdominio sólo de manera condicionada, manteniendo ocultas o explícitamente indisponibles las funciones cuyo contrato o entorno está pendiente y completando los gates de configuración, ACL, fuente de datos y UAT siguientes. Este documento no autoriza despliegue ni declara `READY_FOR_PRODUCTION`.

## Inventario funcional

Los estados describen el estado principal de cada capacidad. `CLOSED_LOCAL` significa implementación y regresión local aprobadas; no implica verificación en el entorno remoto.

| Funcionalidad | Estado | Evidencia / límite operativo |
|---|---|---|
| PIN temporal (V8A) | `BLOCKED_LECTOR` | Portal3 contiene el flujo supervisor y controles locales. El recorrido real depende de una solicitud originada por Lector, autenticación de dispositivo, consumo/validación del PIN y persistencia/API/ACL desplegadas. El contrato V1.0 define esos papeles; no se afirma integración real. |
| Horarios históricos (V8C) | `CLOSED_LOCAL` | Lectura independiente, vigencias históricas, paginación, errores y reconciliación con horario efectivo; test `checador_historical_schedule_validity_regression.php`: 27 PASS / 0 FAIL. La implementación local de API V8C-R1 todavía requiere comprobación cruzada de compatibilidad al integrar repositorios. |
| Turnos nocturnos / zona laboral (V8D) | `CLOSED_LOCAL` | La fecha laboral, timezone contractual y `end_day_offset` se presentan sin conversión inferida ni cálculo de jornada en Portal3. Evidencia P04C/P3-F documentada; la evaluación permanece bajo autoridad API. |
| Incidencias V2 y ciclo administrativo (V8E) | `READY_FOR_LOCAL_UAT` | Portal3 implementa lectura, alta bajo contrato, revisiones, resolución, autorización, rechazo, revocación y evaluación con controles de autorización/CSRF/idempotencia/readback. P3G-A/C/D tienen pruebas locales aprobadas. Smoke real sigue pendiente por ACL/identidad/entorno; no confundir fixtures con persistencia real. |
| Ajustes administrativos auditables (V8F) | `BLOCKED_API_CONTRACT` | La evaluación muestra los cinco campos de referencias de ajuste que expone el GET existente. No existe frontera pública Portal3 para detalle/historial completo ni comandos/ACL de ajustes. No se reutilizan transiciones de incidencia. |
| Completitud, cierre y reapertura de periodo (V8G) | `BLOCKED_API_CONTRACT` | El reporte raw y Level 2 no prueban completitud agregada de todos los trabajadores/fuentes del periodo. No hay padrón histórico contractual, contrato agregado, endpoint/ACL de cierre/reapertura ni historial de cierres. No se crean estados locales de periodo. |
| Comparación informativa Checador–Nómina (V8I) | `BLOCKED_ENVIRONMENT` | Servicio/vista read-only y 39 PASS / 0 FAIL / 0 SKIPPED. Requiere ACL de lectura en ambos módulos y localidad común. El guard actual permite únicamente `APP_ENV` dev/local, host local `fedora`, puerto 3306 y base `bd_admongas`; por diseño no funciona en un host de subdominio. No invoca `getNominaData()`/`sincronizarPersonal()` ni muestra importes. |
| Reportes de marcaciones y consulta avanzada | `READY_FOR_LOCAL_UAT` | Reporte raw SQL con filtro obligatorio de localidad, identidad canónica, rango UTC acotado, paginación y CSV con permiso independiente. Consulta diaria integra timezone, Level 1, Level 2, horarios, Journey/P3-F/P3-H e incidencias sin inferir faltas. La prueba local anterior ejecutó SELECT sobre `bd_admongas`; falta sesión/browser y validación del origen de datos del subdominio. |

**Recuento (8 funciones):** 2 `CLOSED_LOCAL`; 2 `READY_FOR_LOCAL_UAT`; 0 `PARTIAL_PORTAL3`; 2 `BLOCKED_API_CONTRACT`; 1 `BLOCKED_LECTOR`; 1 `BLOCKED_ENVIRONMENT`; 0 `NOT_IMPLEMENTED`. V8B (AGENTS/Skills) es configuración de trabajo, no una función de usuario, y queda completada localmente.

## Evidencia focalizada

### Ejecutado en esta consolidación

Pruebas locales de comportamiento/fixtures; ninguna llamó a API real ni ejecutó escrituras de base:

| Prueba | Resultado |
|---|---:|
| `checador_functional_integration_v5a_regression.php` — contexto, identidad/localidad/fecha, filtro pre-paginación, aislamiento demo | 10 PASS / 0 FAIL / 0 SKIPPED |
| `checador_shared_station_selector_v6b1_regression.php` — selector y revalidación de alcance | 14 PASS / 0 FAIL / 0 SKIPPED |
| `checador_filter_modal_v6b2_regression.php` — modales, filtros, DataTables, CSV y contexto | 10 PASS / 0 FAIL / 0 SKIPPED |
| `checador_historical_schedule_validity_regression.php` — V8C, historial, vigencia y noche | 27 PASS / 0 FAIL / 0 SKIPPED |

**Total ejecutado aquí:** 61 PASS / 0 FAIL / 0 SKIPPED. No se ejecutó la suite completa ni se repitió V8I (39 PASS) ya validada en la actividad anterior.

### Evidencia previa conservada, no repetida

- V8H: `checador_mvp_punch_report_local_readonly_integration.php`, 27 PASS / 0 FAIL / 0 SKIPPED; el reporte hizo consultas SELECT locales sobre `bd_admongas`, con paginación y CSV. Es evidencia de desarrollo, no del subdominio.
- V8H: el reporte histórico documenta regresiones aprobadas para P04C (31), P04D (41), P3F (35), P3G-A (25) y P3G-D (47), además del reporte raw de solo lectura. Se preservan los resultados por prueba sin volver a agregarlos en V8J.
- P3H/P3G y suite de seguridad tienen cierres locales previos documentados en [`CHECADOR_V2_PORTAL3_STATUS.md`](CHECADOR_V2_PORTAL3_STATUS.md). No se repitieron.

### Integración real y visual

- **DB local:** evidencia previa de SELECT raw en la base de desarrollo. No se repitió ni se modificó la base en V8J.
- **Sesión real / ACL efectiva:** no verificadas en esta actividad. No se creó sesión, usuario, JWT ni grant.
- **API real:** no se ejecutaron llamadas remotas en V8J. Los PASS citados son pruebas locales o sintéticas salvo el SELECT de desarrollo expresamente indicado.
- **Navegador:** no verificado. La herramienta falló durante su inicialización; no se reintentó ni se afirma igualdad visual.
- **Vistas para UAT manual:** pantalla diaria; reporte raw y sus filtros/CSV; historial de horarios; Journey/P3-H y procedencia; incidencias e historial; formularios P3-G; comparación informativa con nómina (sólo en el entorno permitido). Revisar layout, topbar/sidebar, responsive 1366/1024/768/390 px, estados vacíos/errores, mensajes en español y contexto de estación/fecha/trabajador.

## Dependencias de API

No se proponen rutas, campos ni esquemas no aprobados. Las dependencias siguientes afectan sólo a las funciones indicadas; no impiden por sí solas la consulta raw cuando la fuente y ACL están disponibles.

| Prioridad | Capacidad | Contrato actual / faltante | Responsable y permisos | Esquema posible | Efecto de despliegue |
|---|---|---|---|---|---|
| P1 por confirmar | Ajustes auditables | Existe dominio físico y el GET de evaluaciones entrega referencias enlazadas; falta API pública de detalle/historial y ciclo de comandos/revisión/readback. | API + responsable funcional. Definir permisos independientes por acción; no reutilizar ACL de incidencias. | El modelo físico ya contempla lineage; API debe confirmar si basta o si necesita migración. | Sólo bloquea la operación completa de ajustes; conservar presentación parcial/read-only. |
| P1 | Completitud agregada de periodo | Level 2 es evidencia de ventana, no agregación del periodo. Falta contrato/versionado que cubra fuentes, paginación y trabajadores. | API + responsable del dominio. Definir permiso de lectura agregado. | Puede requerir referencia/versionado de evaluación; pendiente de diseño API. | Bloquea declarar periodo completo; no bloquea reporte de eventos observados. |
| Condicional | Cierre/reapertura de periodo | No existe contrato, transiciones, endpoint ni historial. Primero confirmar que el dueño requiere esta función. | API + dueño de asistencia/nómina. Permisos separados de cierre/reapertura por definir. | Posible estado/revisión append-only; no asumir. | Sólo bloquea el cierre formal; los controles no deben aparecer mientras no exista contrato. |
| P1 | Padrón histórico autoritativo | Falta fuente contractual para enumerar trabajadores/localidades/vigencias de un periodo, incluidos quienes no tienen PunchEvents. | API/dominio RH y custodio de datos. ACL/scope histórico por definir. | Puede requerir historia efectiva/versionada si el origen actual no basta; no asumir. | Bloquea conclusiones de ausencia/completitud, no la lectura de eventos observados. |
| Gate de integración | PIN temporal | Contrato `TEMPORARY_ACCESS_API_CONTRACT.md` define request, autorización supervisora, validación única, idempotencia y asociación al evento. Falta verificar versión desplegada/runtime/ACL; no inventar contrato nuevo. | API: endpoints y aislamiento Device Auth; permisos supervisor Portal3 según contrato. | El contrato documenta el modelo; cambios sólo si API owner demuestra discrepancia. | Deshabilitar/indicar no disponible el recorrido real hasta validar API y Lector. |
| Gate de compatibilidad | V8C–V8I | V8C-R1 debe compararse con el contrato vigente al integrar API. En V8I no se ejecuta Journey: la evaluación existente es POST y no se trató como lectura inocua. | API owner valida resolver y define una lectura canónica si se necesita consultar resultados sin evaluar. | No se solicita nuevo esquema sin contrato. | No bloquea pantallas de horario/consulta ya probadas; sí limita la comparación completa por periodo/Journey. |

## Dependencias de Lector

Se conserva el contrato de acceso temporal y no se reabre trabajo ajeno a este cierre:

1. Lector debe originar la solicitud temporal mediante Device Auth, con `request_uuid` idempotente.
2. Debe consumir/validar el PIN en línea con `validation_uuid`, rechazar offline y respetar single-use/TTL/lockout.
3. Debe asociar la captura resultante al método y referencias contractuales; nunca enviar el PIN en claro como dato del PunchEvent.
4. La captura biométrica normal y su envío de PunchEvents al API es el origen que consume Portal3; V8J no modifica ni reimplementa esa integración.
5. La integridad criptográfica y procedencia son autoridad de API/Lector. Portal3 conserva referencias y presenta integridad como no verificada por Portal3; no copia secretos HMAC.

La dependencia PIN es `BLOCKED_LECTOR` hasta que exista evidencia de solicitud, validación y captura real en un entorno de prueba autorizado. Esto no prueba una falla nueva en Portal3.

## Trabajo Portal3 antes de habilitar en subdominio

No hay defecto local P0/P1 reproducido en los cuatro checks V8J; **no se corrigió código**. Antes de habilitar Checador en el destino:

1. Confirmar el origen autoritativo de `op_rh_v2_marcaciones_crudas` y provisionar una conexión privada de solo lectura con privilegios mínimos. El éxito local con `bd_admongas` no demuestra que el esquema/datos del subdominio sean el API runtime correcto.
2. Configurar en PHP-FPM del destino, por canal privado, la URL API del ambiente y `CHECADOR_API_JWT_SECRET`; no reutilizar `JWT_SECRET` global, exponer token, o dejar habilitado demo. Comprobar TLS y que host/base no correspondan accidentalmente a producción cuando el objetivo sea preproducción.
3. Verificar, mediante preflight de sólo lectura, que el módulo, la ACL específica y el scope canónico están configurados para los usuarios de prueba. No ampliar grants ni inferir igualdad de IDs estación/localidad.
4. Mantener la comparación con nómina oculta/denegada fuera de Fedora hasta que tenga configuración ambiental read-only aprobada. El guard `APP_ENV` + `fedora` + `bd_admongas` es local deliberado; no quitarlo como atajo.
5. Mantener sin acciones de escritura el ciclo de ajustes y de cierre de periodo. El flujo PIN real debe quedar inhabilitado o claramente pendiente si API/Lector no superaron su gate.
6. Revisar como release unitaria las modificaciones preexistentes del worktree, configurar el destino y ejecutar los checks focalizados indicados. V8J no creó commit ni alteró esos cambios.

Estas verificaciones son gates para habilitar las funciones; si la política del despliegue permite presentarlas deshabilitadas y claramente marcadas, no bloquean alojar las demás páginas del portal. No desplegar antes de autorización independiente.

## Orden mínimo de cierre

1. Manual UAT local autenticado de navegación, selector, raw report/CSV, historial de horarios, turno nocturno, Journey/P3-H e incidencias; verificar permisos sin modificar ACL.
2. Validar con responsable de infraestructura la fuente SQL del destino y su usuario estrictamente de lectura; comparar base, host y ambiente en runtime sin revelar secretos.
3. Ejecutar smoke GET autenticado contra API de preproducción con una identidad y localidad legítimas; observar errores como errores, no como lista vacía.
4. Decidir y aplicar gates visibles para PIN, ajustes, cierre de periodo y comparación con nómina. La comparación requiere diseño ambiental, no ampliar el guard actual.
5. Revisar/cerrar cambios locales y pasar por el proceso de release/approval. El despliegue al subdominio requiere autorización posterior.

## Postdespliegue (sólo tras autorización)

- Login real, permisos y scope con al menos un usuario multisitio y un encargado; denegar localidad no autorizada, incluida CSV.
- Verificar `GET` de timezone, Level 1/Level 2, horario histórico y lecturas P3-G en preproducción cuando ACL de prueba exista.
- Verificar el reporte raw contra el origen confirmado, conservando timezone/UTC/integridad tal como los proveen sus fuentes.
- Revisar logs/redacción de errores, recursos estáticos, navegación y responsive.
- Las mutaciones P3-G/PIN requieren un entorno aislado, registros de prueba desechables y autorización específica; no ejecutarlas como parte de un smoke de lectura.

## Criterios de release al subdominio

- El código publicado corresponde a un cambio revisado y reproducible; no incluir secretos ni cambios ajenos sin revisión.
- PHP-FPM usa variables del ambiente correcto, TLS validado, JWT Checador independiente y demo deshabilitado.
- La fuente de lectura raw está identificada como autoritativa, aislada, accesible sólo de forma privada y read-only; de lo contrario el reporte se mantiene deshabilitado.
- ACL y scope de usuario se verifican en servidor para UI, endpoints y CSV.
- Las funciones pendientes de API/Lector/ambiente se muestran deshabilitadas o con limitación explícita, sin botones engañosos ni inferencias de negocio.
- UAT autenticada y visual se documenta como tal; fixtures no se reportan como verificación real.
- Aprobación expresa de despliegue permanece pendiente. Este checklist no es una orden de deploy ni un `READY_FOR_PRODUCTION`.

## Alcance de V8J y preservación

V8J creó únicamente este documento. No modificó aplicación, pruebas, SQL, base de datos, ACL, API, Lector, Plesk, configuración de despliegue ni Architecture Freeze. No se creó commit ni se hizo push/deploy. Los cambios preexistentes del worktree se conservaron sin stage/restore/reset.

**`ARCHITECTURE_FREEZE_UNCHANGED: YES`**
**`DATABASE_MODIFIED: NO`**
**`APPLICATION_CODE_MODIFIED: NO`**
