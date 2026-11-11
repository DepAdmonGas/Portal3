-- Checador V2 Rol de Comodines V1.2 — prepared security SQL only.
-- DO NOT EXECUTE until the owner separately authorizes account/grant/view changes.
-- The password is intentionally a placeholder; generate and store it only in
-- the local secret mechanism used by Portal3. Never commit or paste it here.

-- Read-only preflight. Run first and stop unless the database, account and
-- view states match the expected development state.
SELECT DATABASE() AS selected_database, VERSION() AS mariadb_version;
SELECT User, Host FROM mysql.user
WHERE User = 'portal3_comodines' AND Host = 'localhost';
SELECT TABLE_NAME, TABLE_TYPE
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = 'bd_admongas'
  AND TABLE_NAME IN (
    'op_rh_v2_comodines_assignments_v1',
    'op_rh_v2_comodines_audit_v1',
    'op_rh_v2_turnos_asignaciones',
    'op_rh_v2_turnos_audit_log',
    'op_rh_v2_worker_workplace_coverage_versions'
  )
ORDER BY TABLE_NAME;

-- EXECUTION BLOCK — not executed by Codex. Require a separate explicit approval.
-- This CREATE USER deliberately errors if the account already exists; do not
-- replace it with an account alteration without reviewing its current grants.
-- Replace the placeholder locally with a generated secret before execution.
-- CREATE USER 'portal3_comodines'@'localhost'
--   IDENTIFIED BY '<GENERATE_AND_STORE_LOCAL_SECRET>';

-- Two updatable, definer-security views constrain writes to coverage-linked
-- TEMPORAL assignments and WORKPLACE_COVERAGE audit rows. The writer account
-- receives no INSERT/UPDATE privilege on either shared base table.
-- CREATE ALGORITHM=MERGE SQL SECURITY DEFINER VIEW bd_admongas.op_rh_v2_comodines_assignments_v1 AS
-- SELECT id,id_personal,id_estacion,id_plantilla,tipo_asignacion,dia_semana,
--        valido_desde,valido_hasta,es_activo,creado_at,registrado_at_utc,
--        creado_por_usuario_id,actualizado_por_usuario_id,motivo,id_cobertura_version
-- FROM bd_admongas.op_rh_v2_turnos_asignaciones
-- WHERE tipo_asignacion='TEMPORAL' AND id_cobertura_version IS NOT NULL
-- WITH CASCADED CHECK OPTION;
--
-- CREATE ALGORITHM=MERGE SQL SECURITY DEFINER VIEW bd_admongas.op_rh_v2_comodines_audit_v1 AS
-- SELECT id,entity_type,entity_id,operacion,id_usuario_actor,id_estacion,id_personal,
--        tipo_asignacion,valido_desde,valido_hasta,id_plantilla,version_plantilla,
--        detalle_json,recorded_at_utc
-- FROM bd_admongas.op_rh_v2_turnos_audit_log
-- WHERE entity_type='WORKPLACE_COVERAGE'
-- WITH CASCADED CHECK OPTION;

-- Exact read columns used by the Portal3 writer/resolver preflight.
-- GRANT SELECT (id,estado) ON bd_admongas.op_rh_personal TO 'portal3_comodines'@'localhost';
-- GRANT SELECT (id,worker_id,workplace_id,valid_from_utc,valid_to_utc,recorded_at_utc)
--   ON bd_admongas.op_rh_personal_localidad_version TO 'portal3_comodines'@'localhost';
-- GRANT SELECT (id) ON bd_admongas.op_rh_localidades TO 'portal3_comodines'@'localhost';
-- GRANT SELECT (id_localidad,timezone_iana,valido_desde,valido_hasta)
--   ON bd_admongas.op_rh_v2_localidades_zonas_horarias TO 'portal3_comodines'@'localhost';
-- GRANT SELECT (id,clave,version,nombre,tipo_turno,scope,id_estacion,es_activo)
--   ON bd_admongas.op_rh_v2_turnos_plantillas TO 'portal3_comodines'@'localhost';
-- GRANT SELECT (id_plantilla,ordinal,tipo_segmento,hora_inicio_local,hora_fin_local,dia_offset_inicio,dia_offset_fin)
--   ON bd_admongas.op_rh_v2_turnos_segmentos TO 'portal3_comodines'@'localhost';
-- GRANT SELECT (id,id_personal,fecha_jornada_local)
--   ON bd_admongas.op_rh_v2_jornadas_programadas TO 'portal3_comodines'@'localhost';
-- GRANT SELECT (id,id_personal,id_estacion,id_plantilla,tipo_asignacion,dia_semana,valido_desde,valido_hasta,
--               es_activo,creado_por_usuario_id,actualizado_por_usuario_id,motivo,id_cobertura_version)
--   ON bd_admongas.op_rh_v2_turnos_asignaciones TO 'portal3_comodines'@'localhost';

-- Coverage revisions are append-only; SELECT supports list/detail/history and
-- INSERT records REQUESTED/AUTHORIZED/CANCELLED revisions. No UPDATE/DELETE.
-- GRANT SELECT ON bd_admongas.op_rh_v2_worker_workplace_coverage_versions TO 'portal3_comodines'@'localhost';
-- GRANT INSERT (coverage_uuid,revision_no,supersedes_id,id_personal,id_home_workplace,
--   id_home_membership_version,id_coverage_workplace,id_home_physical_station,
--   id_coverage_physical_station,fecha_desde_local,fecha_hasta_local_exclusiva,
--   timezone_iana,proposed_schedule_json,status,requested_by_usuario_id,requested_at_utc,
--   approved_by_usuario_id,approved_at_utc,transition_actor_usuario_id,transition_action,
--   motivo_solicitud,motivo_transicion,idempotency_key_hash,payload_fingerprint,recorded_at_utc)
--   ON bd_admongas.op_rh_v2_worker_workplace_coverage_versions TO 'portal3_comodines'@'localhost';

-- Assignment writes only through the filtered view. SELECT on the view is limited
-- to columns used by UPDATE predicates/expressions; UPDATE is limited to cancellation fields.
-- GRANT INSERT (id_personal,id_estacion,id_plantilla,tipo_asignacion,dia_semana,valido_desde,
--   valido_hasta,es_activo,creado_at,registrado_at_utc,creado_por_usuario_id,motivo,id_cobertura_version)
--   ON bd_admongas.op_rh_v2_comodines_assignments_v1 TO 'portal3_comodines'@'localhost';
-- GRANT SELECT (id_cobertura_version,es_activo,motivo)
--   ON bd_admongas.op_rh_v2_comodines_assignments_v1 TO 'portal3_comodines'@'localhost';
-- GRANT UPDATE (es_activo,valido_hasta,actualizado_por_usuario_id,motivo)
--   ON bd_admongas.op_rh_v2_comodines_assignments_v1 TO 'portal3_comodines'@'localhost';
-- GRANT SHOW VIEW ON bd_admongas.op_rh_v2_comodines_assignments_v1 TO 'portal3_comodines'@'localhost';

-- Audit writes and reads only through the filtered view; no base audit-table grants.
-- GRANT INSERT (entity_type,entity_id,operacion,id_usuario_actor,id_estacion,id_personal,
--   tipo_asignacion,valido_desde,valido_hasta,id_plantilla,version_plantilla,detalle_json,recorded_at_utc)
--   ON bd_admongas.op_rh_v2_comodines_audit_v1 TO 'portal3_comodines'@'localhost';
-- GRANT SELECT (id,entity_type,entity_id,operacion,id_usuario_actor,id_estacion,id_personal,
--   valido_desde,valido_hasta,id_plantilla,version_plantilla,detalle_json,recorded_at_utc)
--   ON bd_admongas.op_rh_v2_comodines_audit_v1 TO 'portal3_comodines'@'localhost';
-- GRANT SHOW VIEW ON bd_admongas.op_rh_v2_comodines_audit_v1 TO 'portal3_comodines'@'localhost';

-- No GRANT OPTION, global/database-wide privilege, DELETE, schema privilege,
-- direct assignment/audit DML, API credentials or user/ACL table access is allowed.
-- Validate with SHOW GRANTS FOR 'portal3_comodines'@'localhost' after an approved
-- provisioning operation; the application repeats a strict profile check before writes.
