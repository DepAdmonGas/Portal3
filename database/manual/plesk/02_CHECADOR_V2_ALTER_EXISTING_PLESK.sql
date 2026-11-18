-- Checador V2 / M03-M04 — Plesk schema diagnostics before preparing ALTERs.
-- Read-only file: contains SELECT and SHOW statements only; no ALTER is executed here.
-- Plesk was not queried from this task. Do not invent an ALTER plan until these results
-- have been reviewed against 01_CHECADOR_V2_CREATE_TABLES_PLESK.sql and Fedora SHOW CREATE.
-- Existing data is preserved; any later ALTER must be separately reviewed for duplicates,
-- orphan references, conversion loss, defaults and nullability before execution.
-- This file does not migrate rows from op_rh_localidades_horario.

SELECT DATABASE() AS current_database, VERSION() AS mariadb_version;

-- Presence, storage engine, collation and next AUTO_INCREMENT for target/dependency tables.
SELECT TABLE_NAME, ENGINE, TABLE_COLLATION, AUTO_INCREMENT
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME IN (
    'op_rh_v2_worker_workplace_coverage_versions',
    'op_rh_v2_turnos_plantillas',
    'op_rh_v2_turnos_segmentos',
    'op_rh_v2_turnos_asignaciones',
    'op_rh_v2_shift_template_station_lineage',
    'op_rh_v2_turnos_audit_log',
    'op_rh_v2_jornadas_programadas',
    'op_rh_v2_permission_grants',
    'tb_usuarios',
    'op_rh_personal_localidad_version',
    'tb_estaciones',
    'op_rh_localidades',
    'op_rh_personal'
  )
ORDER BY TABLE_NAME;

-- Columns, types, nullability, defaults, generated expressions and keys.
SELECT TABLE_NAME, ORDINAL_POSITION, COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE,
       COLUMN_DEFAULT, EXTRA, GENERATION_EXPRESSION, CHARACTER_SET_NAME, COLLATION_NAME
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME IN (
    'op_rh_v2_worker_workplace_coverage_versions',
    'op_rh_v2_turnos_plantillas',
    'op_rh_v2_turnos_segmentos',
    'op_rh_v2_turnos_asignaciones',
    'op_rh_v2_shift_template_station_lineage',
    'op_rh_v2_turnos_audit_log',
    'op_rh_v2_jornadas_programadas',
    'op_rh_v2_permission_grants'
  )
ORDER BY TABLE_NAME, ORDINAL_POSITION;

-- Referenced core key-column types, including the coverage-version FK target.
SELECT TABLE_NAME, COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_KEY, EXTRA
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND COLUMN_NAME = 'id'
  AND TABLE_NAME IN (
    'tb_usuarios', 'op_rh_personal_localidad_version', 'tb_estaciones',
    'op_rh_localidades', 'op_rh_personal', 'op_rh_v2_worker_workplace_coverage_versions'
  )
ORDER BY TABLE_NAME;

-- Index column order, uniqueness and prefix lengths.
SELECT TABLE_NAME, INDEX_NAME, NON_UNIQUE, SEQ_IN_INDEX, COLUMN_NAME,
       SUB_PART, COLLATION, INDEX_TYPE
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME IN (
    'op_rh_v2_worker_workplace_coverage_versions',
    'op_rh_v2_turnos_plantillas',
    'op_rh_v2_turnos_segmentos',
    'op_rh_v2_turnos_asignaciones',
    'op_rh_v2_shift_template_station_lineage',
    'op_rh_v2_turnos_audit_log',
    'op_rh_v2_jornadas_programadas',
    'op_rh_v2_permission_grants'
  )
ORDER BY TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX;

-- Foreign-key source/target columns and referential actions.
SELECT k.TABLE_NAME, k.CONSTRAINT_NAME, k.COLUMN_NAME,
       k.REFERENCED_TABLE_NAME, k.REFERENCED_COLUMN_NAME,
       r.UPDATE_RULE, r.DELETE_RULE
FROM information_schema.KEY_COLUMN_USAGE AS k
LEFT JOIN information_schema.REFERENTIAL_CONSTRAINTS AS r
  ON r.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA
 AND r.TABLE_NAME = k.TABLE_NAME
 AND r.CONSTRAINT_NAME = k.CONSTRAINT_NAME
WHERE k.CONSTRAINT_SCHEMA = DATABASE()
  AND k.REFERENCED_TABLE_NAME IS NOT NULL
  AND k.TABLE_NAME IN (
    'op_rh_v2_worker_workplace_coverage_versions',
    'op_rh_v2_turnos_plantillas',
    'op_rh_v2_turnos_segmentos',
    'op_rh_v2_turnos_asignaciones',
    'op_rh_v2_shift_template_station_lineage',
    'op_rh_v2_turnos_audit_log',
    'op_rh_v2_jornadas_programadas',
    'op_rh_v2_permission_grants'
  )
ORDER BY k.TABLE_NAME, k.CONSTRAINT_NAME, k.ORDINAL_POSITION;

-- CHECK constraints (where exposed by this MariaDB version).
SELECT tc.TABLE_NAME, tc.CONSTRAINT_NAME, cc.CHECK_CLAUSE
FROM information_schema.TABLE_CONSTRAINTS AS tc
LEFT JOIN information_schema.CHECK_CONSTRAINTS AS cc
  ON cc.CONSTRAINT_SCHEMA = tc.CONSTRAINT_SCHEMA
 AND cc.CONSTRAINT_NAME = tc.CONSTRAINT_NAME
WHERE tc.CONSTRAINT_SCHEMA = DATABASE()
  AND tc.CONSTRAINT_TYPE = 'CHECK'
  AND tc.TABLE_NAME IN (
    'op_rh_v2_worker_workplace_coverage_versions',
    'op_rh_v2_turnos_plantillas',
    'op_rh_v2_turnos_segmentos',
    'op_rh_v2_turnos_asignaciones',
    'op_rh_v2_shift_template_station_lineage',
    'op_rh_v2_turnos_audit_log',
    'op_rh_v2_jornadas_programadas',
    'op_rh_v2_permission_grants'
  )
ORDER BY tc.TABLE_NAME, tc.CONSTRAINT_NAME;

-- For each table reported as present above, run its SHOW CREATE separately, e.g.:
-- SHOW CREATE TABLE `op_rh_v2_worker_workplace_coverage_versions`;
-- SHOW CREATE TABLE `op_rh_v2_turnos_plantillas`;
-- SHOW CREATE TABLE `op_rh_v2_turnos_segmentos`;
-- SHOW CREATE TABLE `op_rh_v2_turnos_asignaciones`;
-- SHOW CREATE TABLE `op_rh_v2_shift_template_station_lineage`;
-- SHOW CREATE TABLE `op_rh_v2_jornadas_programadas`;
-- SHOW CREATE TABLE `op_rh_v2_turnos_audit_log`;
-- SHOW CREATE TABLE `op_rh_v2_permission_grants`;
-- SHOW CREATE TABLE `tb_usuarios`;
-- SHOW CREATE TABLE `op_rh_personal_localidad_version`;
-- SHOW CREATE TABLE `tb_estaciones`;
-- SHOW CREATE TABLE `op_rh_localidades`;
-- SHOW CREATE TABLE `op_rh_personal`;

-- Data preflight for future foreign keys. Uncomment each block only after confirming
-- every referenced table exists. A nonzero result blocks adding that FK until reviewed.
-- SELECT 'segments_template_fk_orphans' AS check_name, COUNT(*) AS problem_rows
-- FROM op_rh_v2_turnos_segmentos AS s
-- LEFT JOIN op_rh_v2_turnos_plantillas AS t ON t.id = s.id_plantilla
-- WHERE t.id IS NULL;
--
-- SELECT 'assignments_template_fk_orphans' AS check_name, COUNT(*) AS problem_rows
-- FROM op_rh_v2_turnos_asignaciones AS a
-- LEFT JOIN op_rh_v2_turnos_plantillas AS t ON t.id = a.id_plantilla
-- WHERE a.id_plantilla IS NOT NULL AND t.id IS NULL;
--
-- SELECT 'assignments_coverage_fk_orphans' AS check_name, COUNT(*) AS problem_rows
-- FROM op_rh_v2_turnos_asignaciones AS a
-- LEFT JOIN op_rh_v2_worker_workplace_coverage_versions AS c ON c.id = a.id_cobertura_version
-- WHERE a.id_cobertura_version IS NOT NULL AND c.id IS NULL;
--
-- SELECT 'lineage_local_template_fk_orphans' AS check_name, COUNT(*) AS problem_rows
-- FROM op_rh_v2_shift_template_station_lineage AS l
-- LEFT JOIN op_rh_v2_turnos_plantillas AS t ON t.id = l.local_template_id
-- WHERE t.id IS NULL;
--
-- SELECT 'lineage_source_template_fk_orphans' AS check_name, COUNT(*) AS problem_rows
-- FROM op_rh_v2_shift_template_station_lineage AS l
-- LEFT JOIN op_rh_v2_turnos_plantillas AS t ON t.id = l.source_global_template_id
-- WHERE t.id IS NULL;
--
-- SELECT 'scheduled_shift_assignment_fk_orphans' AS check_name, COUNT(*) AS problem_rows
-- FROM op_rh_v2_jornadas_programadas AS j
-- LEFT JOIN op_rh_v2_turnos_asignaciones AS a ON a.id = j.id_asignacion_origen
-- WHERE j.id_asignacion_origen IS NOT NULL AND a.id IS NULL;
--
-- SELECT 'scheduled_shift_template_fk_orphans' AS check_name, COUNT(*) AS problem_rows
-- FROM op_rh_v2_jornadas_programadas AS j
-- LEFT JOIN op_rh_v2_turnos_plantillas AS t ON t.id = j.id_plantilla_origen
-- WHERE j.id_plantilla_origen IS NOT NULL AND t.id IS NULL;

-- Duplicate preflight for target unique indexes before any future ALTER ADD UNIQUE.
-- Duplicate preflight for target unique indexes. Uncomment only when each table exists.
-- SELECT COALESCE(id_estacion, 0) AS station_scope_key, clave, version, COUNT(*) AS duplicate_rows
-- FROM op_rh_v2_turnos_plantillas
-- GROUP BY COALESCE(id_estacion, 0), clave, version
-- HAVING COUNT(*) > 1;
--
-- SELECT id_plantilla, ordinal, COUNT(*) AS duplicate_rows
-- FROM op_rh_v2_turnos_segmentos
-- GROUP BY id_plantilla, ordinal
-- HAVING COUNT(*) > 1;
--
-- SELECT id_estacion, source_global_clave, COUNT(*) AS duplicate_rows
-- FROM op_rh_v2_shift_template_station_lineage
-- GROUP BY id_estacion, source_global_clave
-- HAVING COUNT(*) > 1;
--
-- SELECT id_estacion, local_clave, COUNT(*) AS duplicate_rows
-- FROM op_rh_v2_shift_template_station_lineage
-- GROUP BY id_estacion, local_clave
-- HAVING COUNT(*) > 1;
--
-- SELECT local_template_id, COUNT(*) AS duplicate_rows
-- FROM op_rh_v2_shift_template_station_lineage
-- GROUP BY local_template_id
-- HAVING COUNT(*) > 1;
--
-- SELECT permission_key, principal_type, principal_id, COUNT(*) AS duplicate_rows
-- FROM op_rh_v2_permission_grants
-- GROUP BY permission_key, principal_type, principal_id
-- HAVING COUNT(*) > 1;
--
-- SELECT id_personal, id_estacion, fecha_jornada_local, COUNT(*) AS duplicate_rows
-- FROM op_rh_v2_jornadas_programadas
-- GROUP BY id_personal, id_estacion, fecha_jornada_local
-- HAVING COUNT(*) > 1;

-- ALTER section intentionally empty until the Plesk diagnostics above are reviewed.
-- CREATE TABLE IF NOT EXISTS is sufficient only for missing tables. It does not repair
-- tables that already exist with differing columns, indexes, generated expressions or FKs.
-- Do not apply automatic type conversions, DROP operations, FK disabling, or guessed defaults.
