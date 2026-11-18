-- Checador V2 / M03-M04 — CREATE TABLE definitions for review and Plesk preprod.
-- Source: SHOW CREATE TABLE from Fedora bd_admongas (MariaDB).
-- Target expected by operator: bd_admongas_v2_preprod.
-- This file has NOT been executed. It contains no schedule-row migration or seed data.
--
-- Dependency order:
--   0. Existing core tables listed below must already exist with compatible key types.
--   1. op_rh_v2_permission_grants (M03/M04 API authorization; no permission rows inserted).
--   2. op_rh_v2_worker_workplace_coverage_versions (referenced by assignments).
--   3. op_rh_v2_turnos_plantillas.
--   4. op_rh_v2_turnos_segmentos and op_rh_v2_shift_template_station_lineage.
--   5. op_rh_v2_turnos_asignaciones.
--   6. op_rh_v2_jornadas_programadas (materialized V2 shifts; FKs to assignments/templates).
--   7. op_rh_v2_turnos_audit_log (M03/M04 audit sink; Fedora definition has no FKs).
--
-- Required pre-existing core dependencies (not created or altered here):
--   tb_usuarios(id INT signed)
--   op_rh_personal_localidad_version(id INT UNSIGNED)
--   tb_estaciones(id INT signed)
--   op_rh_localidades(id INT signed)
--   op_rh_personal(id INT signed)
-- Verify them with 02_CHECADOR_V2_ALTER_EXISTING_PLESK.sql before applying this file.
-- CREATE TABLE IF NOT EXISTS does not reconcile an already-existing table's structure.
-- The source AUTO_INCREMENT counters are intentionally not copied; no Fedora rows are copied.
-- Active permission-grant records are data and are deliberately not inserted by this file.
-- The shift-assignment snapshot header/member tables are not included: Portal3 M03/M04
-- currently uses ordinary page-based assignment reads and does not request snapshot mode.

CREATE TABLE IF NOT EXISTS `op_rh_v2_permission_grants` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `permission_key` varchar(80) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `principal_type` enum('POSITION','USER') NOT NULL,
  `principal_id` int(10) unsigned NOT NULL,
  `granted` tinyint(1) NOT NULL DEFAULT 1,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `reason` varchar(255) NOT NULL,
  `source_task` varchar(96) NOT NULL,
  `updated_by_user_id` int(10) unsigned DEFAULT NULL,
  `created_at_utc` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at_utc` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_v2_permission_principal` (`permission_key`,`principal_type`,`principal_id`),
  KEY `idx_v2_permission_active` (`permission_key`,`active`,`granted`),
  CONSTRAINT `chk_v2_permission_principal_positive` CHECK (`principal_id` > 0),
  CONSTRAINT `chk_v2_permission_boolean_values` CHECK (`granted` in (0,1) and `active` in (0,1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `op_rh_v2_worker_workplace_coverage_versions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `coverage_uuid` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `revision_no` int(10) unsigned NOT NULL,
  `supersedes_id` bigint(20) unsigned DEFAULT NULL,
  `id_personal` int(11) NOT NULL,
  `id_home_workplace` int(11) NOT NULL,
  `id_home_membership_version` int(10) unsigned NOT NULL,
  `id_coverage_workplace` int(11) NOT NULL,
  `id_home_physical_station` int(11) DEFAULT NULL,
  `id_coverage_physical_station` int(11) DEFAULT NULL,
  `fecha_desde_local` date NOT NULL,
  `fecha_hasta_local_exclusiva` date NOT NULL,
  `timezone_iana` varchar(64) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
  `proposed_schedule_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`proposed_schedule_json`)),
  `status` enum('REQUESTED','AUTHORIZED','REJECTED','CANCELLED') NOT NULL,
  `requested_by_usuario_id` int(11) NOT NULL,
  `requested_at_utc` datetime(6) NOT NULL,
  `approved_by_usuario_id` int(11) DEFAULT NULL,
  `approved_at_utc` datetime(6) DEFAULT NULL,
  `transition_actor_usuario_id` int(11) NOT NULL,
  `transition_action` enum('REQUEST','AUTHORIZE','REJECT','CANCEL','REVISE') NOT NULL,
  `motivo_solicitud` varchar(500) NOT NULL,
  `motivo_transicion` varchar(500) DEFAULT NULL,
  `idempotency_key_hash` char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `payload_fingerprint` char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `recorded_at_utc` datetime(6) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_coverage_uuid_revision` (`coverage_uuid`,`revision_no`),
  UNIQUE KEY `uk_coverage_idempotency` (`idempotency_key_hash`),
  KEY `idx_coverage_worker_window` (`id_personal`,`status`,`fecha_desde_local`,`fecha_hasta_local_exclusiva`),
  KEY `idx_coverage_home_target` (`id_home_workplace`,`id_coverage_workplace`,`status`),
  KEY `idx_coverage_target_scope` (`id_coverage_workplace`,`status`,`fecha_desde_local`,`fecha_hasta_local_exclusiva`),
  KEY `idx_coverage_supersedes` (`supersedes_id`),
  KEY `idx_coverage_home_membership` (`id_home_membership_version`),
  KEY `idx_coverage_home_physical_station` (`id_home_physical_station`),
  KEY `idx_coverage_target_physical_station` (`id_coverage_physical_station`),
  KEY `idx_coverage_requested_by` (`requested_by_usuario_id`),
  KEY `idx_coverage_approved_by` (`approved_by_usuario_id`),
  KEY `idx_coverage_transition_actor` (`transition_actor_usuario_id`),
  KEY `idx_coverage_recorded` (`recorded_at_utc`),
  CONSTRAINT `fk_coverage_approve_actor` FOREIGN KEY (`approved_by_usuario_id`) REFERENCES `tb_usuarios` (`id`),
  CONSTRAINT `fk_coverage_home_membership` FOREIGN KEY (`id_home_membership_version`) REFERENCES `op_rh_personal_localidad_version` (`id`),
  CONSTRAINT `fk_coverage_home_station` FOREIGN KEY (`id_home_physical_station`) REFERENCES `tb_estaciones` (`id`),
  CONSTRAINT `fk_coverage_home_workplace` FOREIGN KEY (`id_home_workplace`) REFERENCES `op_rh_localidades` (`id`),
  CONSTRAINT `fk_coverage_request_actor` FOREIGN KEY (`requested_by_usuario_id`) REFERENCES `tb_usuarios` (`id`),
  CONSTRAINT `fk_coverage_supersedes` FOREIGN KEY (`supersedes_id`) REFERENCES `op_rh_v2_worker_workplace_coverage_versions` (`id`),
  CONSTRAINT `fk_coverage_target_station` FOREIGN KEY (`id_coverage_physical_station`) REFERENCES `tb_estaciones` (`id`),
  CONSTRAINT `fk_coverage_target_workplace` FOREIGN KEY (`id_coverage_workplace`) REFERENCES `op_rh_localidades` (`id`),
  CONSTRAINT `fk_coverage_transition_actor` FOREIGN KEY (`transition_actor_usuario_id`) REFERENCES `tb_usuarios` (`id`),
  CONSTRAINT `fk_coverage_worker` FOREIGN KEY (`id_personal`) REFERENCES `op_rh_personal` (`id`),
  CONSTRAINT `chk_coverage_valid_range` CHECK (`fecha_hasta_local_exclusiva` > `fecha_desde_local`),
  CONSTRAINT `chk_coverage_distinct_workplaces` CHECK (`id_home_workplace` <> `id_coverage_workplace`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `op_rh_v2_turnos_plantillas` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `clave` varchar(64) NOT NULL COMMENT 'Technical identifier (e.g., TURNO_DIA_06_14, TURNO_NOCHE_22_06)',
  `version` int(10) unsigned NOT NULL DEFAULT 1 COMMENT 'Version number for immutable template revisioning',
  `nombre` varchar(128) NOT NULL COMMENT 'Human-readable description (e.g., Turno Matutino Gasolinera)',
  `tipo_turno` varchar(32) NOT NULL DEFAULT 'REGULAR' COMMENT 'Shift metadata (REGULAR, NOCTURNO, MIXTO, ROTATIVO)',
  `scope` enum('GLOBAL','STATION') NOT NULL DEFAULT 'GLOBAL' COMMENT 'Scope of template: GLOBAL or STATION',
  `id_estacion` int(11) DEFAULT NULL COMMENT 'Canonical station ID for STATION scope; NULL for GLOBAL',
  `scope_id` int(11) GENERATED ALWAYS AS (coalesce(`id_estacion`,0)) STORED,
  `es_activo` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1 = Active, 0 = Disabled',
  `creado_por_usuario_id` int(11) DEFAULT NULL COMMENT 'Actor derived from validated Portal3 JWT user_id',
  `creado_at` datetime NOT NULL DEFAULT current_timestamp(),
  `actualizado_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_v2_turnos_plantillas_scope_clave_version` (`scope_id`,`clave`,`version`),
  KEY `idx_v2_turnos_plantillas_activo` (`es_activo`),
  KEY `idx_v2_turnos_plantillas_scope_estacion` (`scope`,`id_estacion`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `op_rh_v2_turnos_segmentos` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_plantilla` int(10) unsigned NOT NULL COMMENT 'FK to op_rh_v2_turnos_plantillas',
  `ordinal` tinyint(3) unsigned NOT NULL COMMENT 'Segment position sequence (1, 2, 3...)',
  `tipo_segmento` varchar(32) NOT NULL DEFAULT 'WORK' COMMENT 'Segment classification (WORK, BREAK, etc.)',
  `hora_inicio_local` time NOT NULL COMMENT 'Local start time (HH:MM:SS)',
  `hora_fin_local` time NOT NULL COMMENT 'Local end time (HH:MM:SS)',
  `dia_offset_inicio` tinyint(4) NOT NULL DEFAULT 0 COMMENT '0 = Same calendar day, 1 = Next day',
  `dia_offset_fin` tinyint(4) NOT NULL DEFAULT 0 COMMENT '0 = Same day, 1 = Next day (Cross-midnight)',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_v2_segmento_plantilla_ordinal` (`id_plantilla`,`ordinal`),
  KEY `idx_v2_segmento_plantilla` (`id_plantilla`),
  CONSTRAINT `fk_v2_segmentos_plantilla` FOREIGN KEY (`id_plantilla`) REFERENCES `op_rh_v2_turnos_plantillas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `op_rh_v2_shift_template_station_lineage` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `id_estacion` int(11) NOT NULL,
  `source_global_clave` varchar(100) NOT NULL,
  `source_global_version` int(10) unsigned NOT NULL,
  `source_global_template_id` int(10) unsigned NOT NULL,
  `local_clave` varchar(100) NOT NULL,
  `local_template_id` int(10) unsigned NOT NULL,
  `created_by_user_id` int(11) NOT NULL,
  `created_at_utc` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_v2_shift_lineage_station_source` (`id_estacion`,`source_global_clave`),
  UNIQUE KEY `uq_v2_shift_lineage_station_local` (`id_estacion`,`local_clave`),
  UNIQUE KEY `uq_v2_shift_lineage_local_template` (`local_template_id`),
  KEY `idx_v2_shift_lineage_source_version` (`source_global_template_id`,`source_global_version`),
  CONSTRAINT `fk_v2_shift_lineage_local_template` FOREIGN KEY (`local_template_id`) REFERENCES `op_rh_v2_turnos_plantillas` (`id`),
  CONSTRAINT `fk_v2_shift_lineage_source_template` FOREIGN KEY (`source_global_template_id`) REFERENCES `op_rh_v2_turnos_plantillas` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `op_rh_v2_turnos_asignaciones` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `id_personal` int(11) NOT NULL COMMENT 'Worker ID in op_rh_personal',
  `id_estacion` int(11) NOT NULL COMMENT 'Workplace / Station ID in op_rh_localidades',
  `id_plantilla` int(10) unsigned DEFAULT NULL COMMENT 'NULL = explicit day off; otherwise FK to op_rh_v2_turnos_plantillas',
  `tipo_asignacion` enum('HABITUAL','TEMPORAL') NOT NULL DEFAULT 'HABITUAL' COMMENT 'Assignment kind: HABITUAL or TEMPORAL',
  `dia_semana` enum('LUNES','MARTES','MIERCOLES','JUEVES','VIERNES','SABADO','DOMINGO') NOT NULL COMMENT 'ISO Weekday assignment',
  `valido_desde` date NOT NULL COMMENT 'Effective start date inclusive',
  `valido_hasta` date DEFAULT NULL COMMENT 'Effective end date exclusive (NULL = Indefinite)',
  `es_activo` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1 = Active assignment, 0 = Revoked',
  `creado_at` datetime NOT NULL DEFAULT current_timestamp(),
  `registrado_at_utc` datetime NOT NULL DEFAULT current_timestamp() COMMENT 'Audit timestamp for as-recorded reconstruction',
  `creado_por_usuario_id` int(11) DEFAULT NULL COMMENT 'Actor user ID from verified Portal3 identity',
  `actualizado_por_usuario_id` int(11) DEFAULT NULL COMMENT 'Actor updating or closing assignment',
  `motivo` varchar(255) DEFAULT NULL COMMENT 'Optional reason for assignment or temporary exception',
  `id_cobertura_version` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_v2_asig_busqueda_historica` (`id_personal`,`id_estacion`,`dia_semana`,`valido_desde`,`valido_hasta`),
  KEY `idx_v2_asig_plantilla` (`id_plantilla`),
  KEY `idx_v2_asig_tipo` (`tipo_asignacion`),
  KEY `idx_v2_asig_personal_tipo` (`id_personal`,`tipo_asignacion`,`es_activo`),
  KEY `idx_shift_assignment_coverage` (`id_cobertura_version`),
  CONSTRAINT `fk_shift_assignment_coverage` FOREIGN KEY (`id_cobertura_version`) REFERENCES `op_rh_v2_worker_workplace_coverage_versions` (`id`),
  CONSTRAINT `fk_v2_asig_plantilla` FOREIGN KEY (`id_plantilla`) REFERENCES `op_rh_v2_turnos_plantillas` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `op_rh_v2_jornadas_programadas` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `id_personal` int(11) NOT NULL COMMENT 'Worker ID in op_rh_personal',
  `id_estacion` int(11) NOT NULL COMMENT 'Workplace / Station ID in op_rh_localidades',
  `fecha_jornada_local` date NOT NULL COMMENT 'Local calendar workday date',
  `id_asignacion_origen` bigint(20) unsigned DEFAULT NULL COMMENT 'Provenance FK to op_rh_v2_turnos_asignaciones',
  `id_plantilla_origen` int(10) unsigned DEFAULT NULL COMMENT 'Provenance FK to op_rh_v2_turnos_plantillas',
  `tipo_jornada` enum('SCHEDULED','DAY_OFF','UNSCHEDULED') NOT NULL DEFAULT 'SCHEDULED',
  `scheduled_start_utc` datetime DEFAULT NULL COMMENT 'Exact UTC start timestamp of shift',
  `scheduled_end_utc` datetime DEFAULT NULL COMMENT 'Exact UTC end timestamp of shift',
  `timezone_local` varchar(64) NOT NULL DEFAULT 'America/Mexico_City' COMMENT 'Canonical Workplace timezone used for UTC conversion',
  `payload_segmentos_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Snapshot of shift segments for immutable audit' CHECK (json_valid(`payload_segmentos_json`)),
  `creado_at_utc` datetime NOT NULL DEFAULT current_timestamp(),
  `actualizado_at_utc` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_v2_jornada_personal_estacion_fecha` (`id_personal`,`id_estacion`,`fecha_jornada_local`),
  KEY `idx_v2_jornada_estacion_fecha` (`id_estacion`,`fecha_jornada_local`),
  KEY `idx_v2_jornada_intervalo_utc` (`scheduled_start_utc`,`scheduled_end_utc`),
  KEY `fk_v2_jornada_asignacion` (`id_asignacion_origen`),
  KEY `fk_v2_jornada_plantilla` (`id_plantilla_origen`),
  CONSTRAINT `fk_v2_jornada_asignacion` FOREIGN KEY (`id_asignacion_origen`) REFERENCES `op_rh_v2_turnos_asignaciones` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_v2_jornada_plantilla` FOREIGN KEY (`id_plantilla_origen`) REFERENCES `op_rh_v2_turnos_plantillas` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `op_rh_v2_turnos_audit_log` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `entity_type` enum('TEMPLATE','ASSIGNMENT','WORKPLACE_COVERAGE') NOT NULL,
  `entity_id` bigint(20) unsigned NOT NULL,
  `operacion` varchar(32) NOT NULL COMMENT 'CREATE, VERSION, CLOSE, REPLACE, CORRECTION',
  `id_usuario_actor` int(11) NOT NULL COMMENT 'Actor user ID derived strictly from JWT',
  `id_estacion` int(11) NOT NULL COMMENT 'Canonical station',
  `id_personal` int(11) DEFAULT NULL COMMENT 'Worker ID if assignment',
  `tipo_asignacion` enum('HABITUAL','TEMPORAL') DEFAULT NULL,
  `valido_desde` date DEFAULT NULL,
  `valido_hasta` date DEFAULT NULL,
  `id_plantilla` int(10) unsigned DEFAULT NULL,
  `version_plantilla` int(10) unsigned DEFAULT NULL,
  `detalle_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`detalle_json`)),
  `recorded_at_utc` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_v2_turnos_audit_entity` (`entity_type`,`entity_id`),
  KEY `idx_v2_turnos_audit_worker` (`id_personal`,`recorded_at_utc`),
  KEY `idx_v2_turnos_audit_station` (`id_estacion`,`recorded_at_utc`),
  KEY `idx_v2_turnos_audit_actor` (`id_usuario_actor`,`recorded_at_utc`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
