# CHECADOR V2 — PORTAL3 AJUSTES ADMINISTRATIVOS SQL READ ADDENDUM V1.0

**Estado:** APROBADO POR EL PROPIETARIO — ALCANCE DE CONSULTA SOLAMENTE
**Fecha de aprobación registrada:** 2026-11-20
**Repositorio consumidor:** Portal3
**Entorno inspeccionado:** Fedora / MariaDB `bd_admongas`
**Escrituras, evaluación y autorización de FALTA:** NO AUTORIZADAS

## 1. Decisión y límites

La instrucción expresa del propietario del 2026-11-20 autoriza preparar e implementar una proyección de consulta SQL de Ajustes administrativos, una vez verificado el esquema y el alcance. Este addendum registra esa excepción estrecha y no representa autorización para crear, modificar, cancelar, evaluar ni aprobar ajustes, autorizar FALTAs o modificar resultados de Jornada.

Portal3 consulta las filas persistidas mediante la conexión institucional `default`, exclusivamente con operaciones `SELECT` parametrizadas. API V2 conserva la propiedad de evaluación, creación y persistencia de ajustes y evaluaciones. Lector conserva la captura de marcaciones. Portal3 no llama API V2 por HTTP para esta pantalla, no calcula jornadas y no altera datos. El navegador se comunica sólo con Portal3.

Este addendum no reescribe ni cambia `CHECADOR_V2_ARCHITECTURE_FREEZE_V1.0.md`, P3-G ni P3-H. Constituye la excepción puntual autorizada para M07/Ajustes de consulta. No amplía C01, M06 ni ningún permiso de escritura.

## 2. Conciliación limitada de contratos

- **P3-G §12.1** define el límite de lectura administrativo de Portal3 a través de la frontera API existente. Para esta vista, únicamente el transporte de consulta del catálogo/detalle/historial de ajustes se sustituye por la lectura SQL de las proyecciones persistidas de §3. Las operaciones de evaluación y escritura siguen fuera de Portal3.
- **P3-H §3 y §12** describen referencias de ajuste y su exposición a través de la proyección de evaluación existente; esa respuesta no contiene por sí misma el registro íntegro, el linaje ni el historial completo de Ajustes. Este addendum autoriza consultar esos datos persistidos mediante las relaciones físicas verificadas en §4.
- El Freeze y ambos contratos congelados permanecen inalterados. Esta excepción sólo cubre el lector de Ajustes aquí descrito.

## 3. Esquema físico comprobado

Se ejecutaron `SHOW COLUMNS`, `SHOW CREATE TABLE` y conteos de sólo lectura en `bd_admongas`. Las tres tablas administrativas existen, son InnoDB y al momento de la consulta contenían cero registros.

| Tabla | Columnas verificadas y uso permitido |
|---|---|
| `op_rh_v2_ajustes_administrativos` | `id`, `uuid_ajuste`, `uuid_ajuste_logico`, `revision_numero`, `estado_ajuste`, `id_ajuste_anterior`, `id_jornada_programada`, `id_calculo_jornada`, `tipo_ajuste`, `hora_interpretada_entrada_utc`, `hora_interpretada_salida_utc`, `motivo_ajuste`, `creado_por_usuario_id`, `aprobado_por_usuario_id`, `creado_at_utc`. Identidad lógica: `uuid_ajuste_logico`; identidad de revisión: `uuid_ajuste` y `revision_numero`.
| `op_rh_v2_evaluaciones_administrativas` | `id`, `id_jornada_programada`, `id_calculo_jornada`, `id_personal`, `id_estacion`, `fecha_jornada_local`, `revision_numero`, `version_algoritmo_admin`, `version_politica`, `estado_evaluacion`, `estado_revision_humana`, `fingerprint_ejecucion`, `creado_at_utc`.
| `op_rh_v2_evaluaciones_ajustes` | `id_evaluacion_administrativa`, `id_ajuste_administrativo`, `creado_at_utc`; clave primaria compuesta por los dos IDs.

El maestro de ajustes tiene índice único por `uuid_ajuste` y por `(uuid_ajuste_logico, revision_numero)`, y el comentario del DDL enumera `ACTIVE`, `SUPERSEDED` y `CANCELLED`. Sus FKs declaradas son `id_ajuste_anterior → ajustes.id`, `id_jornada_programada → jornadas.id` e `id_calculo_jornada → calculos.id`. La evaluación tiene FKs a Jornada y cálculo, índice único por Jornada/revisión y fingerprint. La tabla de relaciones tiene PK compuesta `(id_evaluacion_administrativa, id_ajuste_administrativo)` y FKs a evaluación y ajuste. No se observó columna de trabajador, localidad o fecha laboral en el maestro de ajustes; no se inventa ninguna.

## 4. Relaciones y alcance canónico

Tablas auxiliares permitidas sólo para validar identidad, etiqueta, fecha/alcance y membresía histórica:

- `op_rh_v2_jornadas_programadas`: unión `ajustes.id_jornada_programada = jornadas.id`; proporciona `id_personal`, `id_estacion`, `fecha_jornada_local` y `timezone_local` persistidos.
- `op_rh_v2_calculos_jornada`: unión `ajustes.id_calculo_jornada = calculos.id`; exigir coincidencia de `id_jornada_programada`, `id_personal`, `id_estacion` y `fecha_jornada_local` entre cálculo y Jornada.
- `op_rh_v2_evaluaciones_ajustes` → `op_rh_v2_evaluaciones_administrativas`: exponer una evaluación vinculada sólo si Jornada, cálculo, trabajador, localidad y fecha de la evaluación coinciden exactamente con los IDs asociados al ajuste. Un vínculo discrepante no se presenta como válido.
- `op_rh_personal`: unión por `jornadas.id_personal = personal.id`, exclusivamente para nombre y número de colaborador.
- `op_rh_localidades`: unión por `jornadas.id_estacion = localidades.id`, exclusivamente para nombre canónico de localidad.
- `op_rh_personal_localidad_version`: resolver adscripción histórica bitemporal del trabajador para el inicio de su fecha laboral local, convirtiendo medianoche con `jornadas.timezone_local` a UTC y usando una sola hora `recorded_as_of_utc` por consulta.
- `op_rh_v2_localidades_zonas_horarias`: sólo la consulta del padrón autorizado reutiliza el resolvedor existente de zona horaria para determinar la fecha local de referencia y filtrar opciones de trabajador; no sustituye `jornadas.timezone_local` al validar una fila de ajuste.

La ACL de Portal3 se vuelve a evaluar en el servidor para el usuario activo y la localidad canónica seleccionada. La consulta siempre restringe SQL por `jornadas.id_estacion = :localidad_autorizada`; la membresía histórica debe resolver exactamente una adscripción a esa misma localidad. No se usa la localidad actual del padrón como sustituto de adscripción histórica. Un departamento sin relación canónica verificada no se remapea a una estación.

## 5. Revisiones, detalle e historial

Las revisiones se agrupan por `uuid_ajuste_logico`, ordenadas por `revision_numero`. La revisión actual sólo se presenta como confirmada si la secuencia inicia en 1, es consecutiva, el primer `id_ajuste_anterior` es nulo, cada revisión posterior referencia exactamente el ID anterior, las revisiones anteriores tienen un estado terminal admitido por el comentario DDL (`SUPERSEDED` o `CANCELLED`) y la última revisión tiene estado `ACTIVE` o `CANCELLED`. Cadena incompleta, bifurcada, estados desconocidos, identidad o vínculos incompatibles se excluyen como datos no verificables; no se elige una revisión por conveniencia.

El detalle muestra únicamente campos persistidos: UUID de revisión y lógico, tipo, estado, número, jornada/cálculo asociados, horas interpretadas cuando existan, motivo, actor creador/aprobador y timestamp UTC. La aprobación ya persistida se presenta como dato histórico; la pantalla no ejecuta aprobación alguna. El historial muestra todas las revisiones recuperables de la cadena, en modo sólo lectura. Las evaluaciones relacionadas se muestran sólo tras validar todas las claves descritas en §4.

Los filtros admitidos son localidad autorizada, rango de fechas laborales locales de hasta 31 días, trabajador canónico opcional, tipo y estado existentes. La paginación es limitada. El catálogo de trabajadores se obtiene del padrón autorizado existente y no de las filas de ajustes. Fechas y estados se presentan tal como están persistidos; Portal3 no infiere faltas ni resultados.

## 6. Estados, errores y privacidad

Una consulta SQL exitosa sin filas es un estado vacío institucional. Error SQL, esquema no disponible, ACL denegada, filtro inválido o relación histórica no verificable son estados distintos; nunca se muestran como cero ajustes. UUIDs y referencias se escapan al renderizar. No se exponen fingerprints, credenciales ni datos de marcaciones.

El lector no ejecuta evaluaciones ni muestra controles para crear, editar, cancelar, evaluar, aprobar o autorizar FALTA. Los permisos ya existentes y endpoints de evaluación/autorización permanecen sin cambios; este addendum no los concede ni los invoca.

## 7. Evidencia y aprobación

La inspección en `bd_admongas` verificó las columnas y relaciones enumeradas en §3–4. Los conteos observados fueron cero en las tres tablas administrativas. Por ello, el estado vacío y las consultas reales pueden comprobarse en esta base, pero los ejemplos con ajustes positivos, múltiples revisiones y evaluaciones relacionadas se validan exclusivamente con fixtures aislados; esos fixtures no se cargan en rutas operativas ni se presentan como datos reales.

**Aprobación del propietario (2026-11-20):** lectura SQL de Ajustes administrativos en Portal3, sujeta a los límites de este addendum. **Escrituras/evaluaciones/aprobaciones: no autorizadas.** No se aprueban cambios de esquema, grants, API, Lector, Plesk ni producción.
