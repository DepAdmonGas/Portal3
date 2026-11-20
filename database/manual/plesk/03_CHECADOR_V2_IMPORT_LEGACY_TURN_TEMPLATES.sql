-- Importación controlada del catálogo legacy a Checador V2.
-- NO ejecutar hasta validar el preflight en bd_admongas_v2_preprod.
-- Este archivo no crea tablas, no modifica filas existentes y no crea asignaciones.
-- Requiere en la BD destino las tres tablas V2 ya instaladas y una fuente legacy
-- op_rh_localidades_horario con el inventario aprobado de 111 filas (93 válidas + 18 excluidas).
-- Fuente y destino son la misma base Plesk. DATABASE() se valida antes de cualquier INSERT.
--
-- La localidad destino se resuelve por id_estacion físico -> tb_estaciones.numlista
-- -> op_rh_localidades.id. No se equiparan IDs físicos y canónicos.
-- Las plantillas se agrupan por localidad e intervalo. Un turno nocturno es salida < entrada;
-- se representa como un segmento WORK con dia_offset_fin=1. No se infieren descansos.
-- Los horarios ya representados por un template GLOBAL o STATION con el mismo segmento
-- exacto se conservan y no se duplican. No se envía es_activo: se aplica el DEFAULT del
-- esquema existente, sin mapear un estado legacy inexistente. No se inventa linaje: la
-- fuente legacy no contiene una relación de origen entre plantillas globales y locales.

USE `bd_admongas_v2_preprod`;
SET SESSION group_concat_max_len = 65535;

-- Conteos de origen y V2 previos. Revisar también la salida IMPORT_READY.
SELECT DATABASE() INTO @M04_DATABASE_NAME;
SELECT COUNT(*) INTO @M04_SOURCE_TOTAL FROM `op_rh_localidades_horario`;
SELECT COUNT(*) INTO @M04_SOURCE_EXCLUDED
FROM `op_rh_localidades_horario`
WHERE UPPER(TRIM(`titulo`)) IN ('BAJA','VACACIONES','INCAPACIDAD','PERMISO');
SELECT COUNT(*) INTO @M04_SOURCE_ELIGIBLE
FROM `op_rh_localidades_horario`
WHERE UPPER(TRIM(`titulo`)) NOT IN ('BAJA','VACACIONES','INCAPACIDAD','PERMISO');
SELECT COUNT(*) INTO @M04_BAD_MAPPING
FROM `op_rh_localidades_horario` AS h
WHERE UPPER(TRIM(h.`titulo`)) NOT IN ('BAJA','VACACIONES','INCAPACIDAD','PERMISO')
  AND (SELECT COUNT(*)
       FROM `tb_estaciones` AS e
       JOIN `op_rh_localidades` AS l ON l.`numlista` = e.`numlista`
       WHERE e.`id` = h.`id_estacion` AND e.`estatus` = 0) <> 1;
SELECT COUNT(*) INTO @M04_INVALID_INTERVAL
FROM `op_rh_localidades_horario`
WHERE UPPER(TRIM(`titulo`)) NOT IN ('BAJA','VACACIONES','INCAPACIDAD','PERMISO')
  AND (`titulo` IS NULL OR TRIM(`titulo`) = '' OR `hora_entrada` = `hora_salida`);
SELECT COUNT(*) INTO @M04_TEMPLATES_BEFORE FROM `op_rh_v2_turnos_plantillas`;
SELECT COUNT(*) INTO @M04_SEGMENTS_BEFORE FROM `op_rh_v2_turnos_segmentos`;
SELECT COUNT(*) INTO @M04_LINEAGE_BEFORE FROM `op_rh_v2_shift_template_station_lineage`;
SELECT COUNT(*) INTO @M04_ACTIVE_DEFAULT_VALID
FROM `information_schema`.`COLUMNS`
WHERE `TABLE_SCHEMA` = DATABASE()
  AND `TABLE_NAME` = 'op_rh_v2_turnos_plantillas'
  AND `COLUMN_NAME` = 'es_activo'
  AND `COLUMN_DEFAULT` = '1';

SELECT COUNT(*) INTO @M04_SOURCE_GROUPS
FROM (
    SELECT l.`id`, h.`hora_entrada`, h.`hora_salida`
    FROM `op_rh_localidades_horario` AS h
    JOIN `tb_estaciones` AS e ON e.`id` = h.`id_estacion` AND e.`estatus` = 0
    JOIN `op_rh_localidades` AS l ON l.`numlista` = e.`numlista`
    WHERE UPPER(TRIM(h.`titulo`)) NOT IN ('BAJA','VACACIONES','INCAPACIDAD','PERMISO')
      AND h.`titulo` IS NOT NULL AND TRIM(h.`titulo`) <> '' AND h.`hora_entrada` <> h.`hora_salida`
    GROUP BY l.`id`, h.`hora_entrada`, h.`hora_salida`
) AS grouped_source;

-- Duplicados exactos del origen: se agrupan como una plantilla por localidad/intervalo.
-- Los nombres se validan contra el límite real varchar(128) antes de permitir escrituras.
SELECT COUNT(*) INTO @M04_LONG_NAMES
FROM (
    SELECT CONCAT(
        'Legacy — ',
        GROUP_CONCAT(DISTINCT TRIM(h.`titulo`) ORDER BY TRIM(h.`titulo`) SEPARATOR ' / '),
        ' — ', TIME_FORMAT(h.`hora_entrada`, '%H:%i'), '–', TIME_FORMAT(h.`hora_salida`, '%H:%i')
    ) AS generated_name
    FROM `op_rh_localidades_horario` AS h
    JOIN `tb_estaciones` AS e ON e.`id` = h.`id_estacion` AND e.`estatus` = 0
    JOIN `op_rh_localidades` AS l ON l.`numlista` = e.`numlista`
    WHERE UPPER(TRIM(h.`titulo`)) NOT IN ('BAJA','VACACIONES','INCAPACIDAD','PERMISO')
      AND h.`titulo` IS NOT NULL AND TRIM(h.`titulo`) <> '' AND h.`hora_entrada` <> h.`hora_salida`
    GROUP BY l.`id`, h.`hora_entrada`, h.`hora_salida`
) AS grouped_names
WHERE CHAR_LENGTH(grouped_names.generated_name) > 128;

-- Un key determinista preexistente sólo es aceptable si ya representa exactamente
-- esta plantilla y su segmento. Ante cualquier colisión no se inserta nada.
SELECT COUNT(*) INTO @M04_KEY_CONFLICTS
FROM (
    SELECT
        l.`id` AS locality_id,
        h.`hora_entrada`, h.`hora_salida`,
        CONCAT('LEGACY_L', l.`id`, '_', TIME_FORMAT(h.`hora_entrada`, '%H%i%s'), '_', TIME_FORMAT(h.`hora_salida`, '%H%i%s')) AS template_key,
        CONCAT(
            'Legacy — ',
            GROUP_CONCAT(DISTINCT TRIM(h.`titulo`) ORDER BY TRIM(h.`titulo`) SEPARATOR ' / '),
            ' — ', TIME_FORMAT(h.`hora_entrada`, '%H:%i'), '–', TIME_FORMAT(h.`hora_salida`, '%H:%i')
        ) AS generated_name
    FROM `op_rh_localidades_horario` AS h
    JOIN `tb_estaciones` AS e ON e.`id` = h.`id_estacion` AND e.`estatus` = 0
    JOIN `op_rh_localidades` AS l ON l.`numlista` = e.`numlista`
    WHERE UPPER(TRIM(h.`titulo`)) NOT IN ('BAJA','VACACIONES','INCAPACIDAD','PERMISO')
      AND h.`titulo` IS NOT NULL AND TRIM(h.`titulo`) <> '' AND h.`hora_entrada` <> h.`hora_salida`
    GROUP BY l.`id`, h.`hora_entrada`, h.`hora_salida`
) AS grouped
JOIN `op_rh_v2_turnos_plantillas` AS existing
  ON existing.`scope` = 'STATION'
 AND existing.`id_estacion` = grouped.`locality_id`
 AND existing.`clave` = grouped.`template_key`
WHERE NOT (
    existing.`version` = 1
    AND existing.`nombre` = grouped.`generated_name`
    AND existing.`tipo_turno` = IF(grouped.`hora_salida` < grouped.`hora_entrada`, 'NOCTURNO', 'REGULAR')
    AND (SELECT COUNT(*) FROM `op_rh_v2_turnos_segmentos` AS s WHERE s.`id_plantilla` = existing.`id`) = 1
    AND EXISTS (
        SELECT 1 FROM `op_rh_v2_turnos_segmentos` AS s
        WHERE s.`id_plantilla` = existing.`id` AND s.`ordinal` = 1 AND s.`tipo_segmento` = 'WORK'
          AND s.`hora_inicio_local` = grouped.`hora_entrada`
          AND s.`hora_fin_local` = grouped.`hora_salida`
          AND s.`dia_offset_inicio` = 0
          AND s.`dia_offset_fin` = IF(grouped.`hora_salida` < grouped.`hora_entrada`, 1, 0)
    )
);

SET @M04_IMPORT_READY := IF(
    @M04_DATABASE_NAME = 'bd_admongas_v2_preprod'
    AND @M04_SOURCE_TOTAL = 111
    AND @M04_SOURCE_EXCLUDED = 18
    AND @M04_SOURCE_ELIGIBLE = 93
    AND @M04_BAD_MAPPING = 0
    AND @M04_INVALID_INTERVAL = 0
    AND @M04_LONG_NAMES = 0,
    1, 0
);
SET @M04_IMPORT_READY := IF(@M04_KEY_CONFLICTS = 0, @M04_IMPORT_READY, 0);
SET @M04_IMPORT_READY := IF(@M04_ACTIVE_DEFAULT_VALID = 1, @M04_IMPORT_READY, 0);

SELECT
    @M04_DATABASE_NAME AS selected_database,
    @M04_SOURCE_TOTAL AS source_rows,
    @M04_SOURCE_EXCLUDED AS excluded_rows,
    @M04_SOURCE_ELIGIBLE AS eligible_rows,
    @M04_BAD_MAPPING AS unmapped_or_ambiguous_rows,
    @M04_INVALID_INTERVAL AS invalid_rows,
    @M04_LONG_NAMES AS generated_names_over_128,
    @M04_KEY_CONFLICTS AS deterministic_key_conflicts,
    @M04_ACTIVE_DEFAULT_VALID AS expected_es_activo_default,
    @M04_TEMPLATES_BEFORE AS templates_before,
    @M04_SEGMENTS_BEFORE AS segments_before,
    @M04_LINEAGE_BEFORE AS lineage_before,
    @M04_SOURCE_GROUPS AS unique_locality_interval_groups,
    @M04_IMPORT_READY AS IMPORT_READY;

-- IMPORT_READY debe ser 1. Con cualquier otro resultado las sentencias siguientes
-- no insertan. No continuar manualmente si el preflight no coincide con 111/18/93.
START TRANSACTION;

INSERT INTO `op_rh_v2_turnos_plantillas`
    (`clave`, `version`, `nombre`, `tipo_turno`, `scope`, `id_estacion`)
SELECT
    grouped.`template_key`, 1, grouped.`generated_name`,
    IF(grouped.`hora_salida` < grouped.`hora_entrada`, 'NOCTURNO', 'REGULAR'),
    'STATION', grouped.`locality_id`
FROM (
    SELECT
        l.`id` AS locality_id,
        h.`hora_entrada`, h.`hora_salida`,
        CONCAT('LEGACY_L', l.`id`, '_', REPLACE(TIME_FORMAT(h.`hora_entrada`, '%H%i%s'), ':', ''), '_', REPLACE(TIME_FORMAT(h.`hora_salida`, '%H%i%s'), ':', '')) AS template_key,
        CONCAT(
            'Legacy — ',
            GROUP_CONCAT(DISTINCT TRIM(h.`titulo`) ORDER BY TRIM(h.`titulo`) SEPARATOR ' / '),
            ' — ', TIME_FORMAT(h.`hora_entrada`, '%H:%i'), '–', TIME_FORMAT(h.`hora_salida`, '%H:%i')
        ) AS generated_name
    FROM `op_rh_localidades_horario` AS h
    JOIN `tb_estaciones` AS e ON e.`id` = h.`id_estacion` AND e.`estatus` = 0
    JOIN `op_rh_localidades` AS l ON l.`numlista` = e.`numlista`
    WHERE @M04_IMPORT_READY = 1
      AND UPPER(TRIM(h.`titulo`)) NOT IN ('BAJA','VACACIONES','INCAPACIDAD','PERMISO')
      AND h.`titulo` IS NOT NULL AND TRIM(h.`titulo`) <> '' AND h.`hora_entrada` <> h.`hora_salida`
    GROUP BY l.`id`, h.`hora_entrada`, h.`hora_salida`
) AS grouped
WHERE NOT EXISTS (
    SELECT 1
    FROM `op_rh_v2_turnos_plantillas` AS existing
    JOIN `op_rh_v2_turnos_segmentos` AS segment ON segment.`id_plantilla` = existing.`id`
    WHERE (existing.`scope` = 'GLOBAL'
           OR (existing.`scope` = 'STATION' AND existing.`id_estacion` = grouped.`locality_id`))
      AND segment.`ordinal` = 1 AND segment.`tipo_segmento` = 'WORK'
      AND segment.`hora_inicio_local` = grouped.`hora_entrada`
      AND segment.`hora_fin_local` = grouped.`hora_salida`
      AND segment.`dia_offset_inicio` = 0
      AND segment.`dia_offset_fin` = IF(grouped.`hora_salida` < grouped.`hora_entrada`, 1, 0)
      AND (SELECT COUNT(*) FROM `op_rh_v2_turnos_segmentos` AS all_segments
           WHERE all_segments.`id_plantilla` = existing.`id`) = 1
)
AND NOT EXISTS (
    SELECT 1 FROM `op_rh_v2_turnos_plantillas` AS key_collision
    WHERE key_collision.`scope` = 'STATION'
      AND key_collision.`id_estacion` = grouped.`locality_id`
      AND key_collision.`clave` = grouped.`template_key`
);

-- Segmento único WORK por cada plantilla que esta importación insertó.
-- Si una plantilla con la clave determinista ya existía, no se cambia ni se completa.
INSERT INTO `op_rh_v2_turnos_segmentos`
    (`id_plantilla`, `ordinal`, `tipo_segmento`, `hora_inicio_local`, `hora_fin_local`, `dia_offset_inicio`, `dia_offset_fin`)
SELECT
    target.`id`, 1, 'WORK', grouped.`hora_entrada`, grouped.`hora_salida`, 0,
    IF(grouped.`hora_salida` < grouped.`hora_entrada`, 1, 0)
FROM (
    SELECT
        l.`id` AS locality_id,
        h.`hora_entrada`, h.`hora_salida`,
        CONCAT('LEGACY_L', l.`id`, '_', REPLACE(TIME_FORMAT(h.`hora_entrada`, '%H%i%s'), ':', ''), '_', REPLACE(TIME_FORMAT(h.`hora_salida`, '%H%i%s'), ':', '')) AS template_key,
        CONCAT(
            'Legacy — ',
            GROUP_CONCAT(DISTINCT TRIM(h.`titulo`) ORDER BY TRIM(h.`titulo`) SEPARATOR ' / '),
            ' — ', TIME_FORMAT(h.`hora_entrada`, '%H:%i'), '–', TIME_FORMAT(h.`hora_salida`, '%H:%i')
        ) AS generated_name
    FROM `op_rh_localidades_horario` AS h
    JOIN `tb_estaciones` AS e ON e.`id` = h.`id_estacion` AND e.`estatus` = 0
    JOIN `op_rh_localidades` AS l ON l.`numlista` = e.`numlista`
    WHERE @M04_IMPORT_READY = 1
      AND UPPER(TRIM(h.`titulo`)) NOT IN ('BAJA','VACACIONES','INCAPACIDAD','PERMISO')
      AND h.`titulo` IS NOT NULL AND TRIM(h.`titulo`) <> '' AND h.`hora_entrada` <> h.`hora_salida`
    GROUP BY l.`id`, h.`hora_entrada`, h.`hora_salida`
) AS grouped
JOIN `op_rh_v2_turnos_plantillas` AS target
  ON target.`scope` = 'STATION'
 AND target.`id_estacion` = grouped.`locality_id`
 AND target.`clave` = grouped.`template_key`
 AND target.`version` = 1
 AND target.`nombre` = grouped.`generated_name`
 AND target.`tipo_turno` = IF(grouped.`hora_salida` < grouped.`hora_entrada`, 'NOCTURNO', 'REGULAR')
WHERE @M04_IMPORT_READY = 1
  AND NOT EXISTS (
      SELECT 1 FROM `op_rh_v2_turnos_segmentos` AS existing_segment
      WHERE existing_segment.`id_plantilla` = target.`id`
  );

-- El linaje no se genera: el catálogo legacy no demuestra derivación de una plantilla global.
-- Se verifica que ninguna fila existente haya sido removida y se muestran los conteos finales.
SELECT COUNT(*) INTO @M04_TEMPLATES_AFTER FROM `op_rh_v2_turnos_plantillas`;
SELECT COUNT(*) INTO @M04_SEGMENTS_AFTER FROM `op_rh_v2_turnos_segmentos`;
SELECT COUNT(*) INTO @M04_LINEAGE_AFTER FROM `op_rh_v2_shift_template_station_lineage`;
SELECT COUNT(*) INTO @M04_GROUPS_READBACK
FROM (
    SELECT l.`id` AS locality_id, h.`hora_entrada`, h.`hora_salida`
    FROM `op_rh_localidades_horario` AS h
    JOIN `tb_estaciones` AS e ON e.`id` = h.`id_estacion` AND e.`estatus` = 0
    JOIN `op_rh_localidades` AS l ON l.`numlista` = e.`numlista`
    WHERE @M04_IMPORT_READY = 1
      AND UPPER(TRIM(h.`titulo`)) NOT IN ('BAJA','VACACIONES','INCAPACIDAD','PERMISO')
      AND h.`titulo` IS NOT NULL AND TRIM(h.`titulo`) <> '' AND h.`hora_entrada` <> h.`hora_salida`
    GROUP BY l.`id`, h.`hora_entrada`, h.`hora_salida`
) AS source_groups
WHERE EXISTS (
    SELECT 1
    FROM `op_rh_v2_turnos_plantillas` AS t
    JOIN `op_rh_v2_turnos_segmentos` AS s ON s.`id_plantilla` = t.`id`
    WHERE (t.`scope` = 'GLOBAL'
           OR (t.`scope` = 'STATION' AND t.`id_estacion` = source_groups.`locality_id`))
      AND s.`ordinal` = 1 AND s.`tipo_segmento` = 'WORK'
      AND s.`hora_inicio_local` = source_groups.`hora_entrada`
      AND s.`hora_fin_local` = source_groups.`hora_salida`
      AND s.`dia_offset_inicio` = 0
      AND s.`dia_offset_fin` = IF(source_groups.`hora_salida` < source_groups.`hora_entrada`, 1, 0)
      AND (SELECT COUNT(*) FROM `op_rh_v2_turnos_segmentos` AS all_s
           WHERE all_s.`id_plantilla` = t.`id`) = 1
);
SET @M04_FINAL_READY := IF(
    @M04_IMPORT_READY = 1
    AND @M04_GROUPS_READBACK = @M04_SOURCE_GROUPS
    AND (@M04_TEMPLATES_AFTER - @M04_TEMPLATES_BEFORE) = (@M04_SEGMENTS_AFTER - @M04_SEGMENTS_BEFORE)
    AND @M04_LINEAGE_AFTER = @M04_LINEAGE_BEFORE,
    1, 0
);
SELECT
    @M04_IMPORT_READY AS IMPORT_READY,
    @M04_FINAL_READY AS FINAL_VALIDATION_READY,
    @M04_TEMPLATES_BEFORE AS templates_before,
    @M04_TEMPLATES_AFTER AS templates_after,
    @M04_TEMPLATES_AFTER - @M04_TEMPLATES_BEFORE AS templates_added,
    @M04_SEGMENTS_BEFORE AS segments_before,
    @M04_SEGMENTS_AFTER AS segments_after,
    @M04_SEGMENTS_AFTER - @M04_SEGMENTS_BEFORE AS segments_added,
    @M04_LINEAGE_BEFORE AS lineage_before,
    @M04_LINEAGE_AFTER AS lineage_after,
    @M04_SOURCE_GROUPS AS eligible_locality_interval_groups,
    @M04_GROUPS_READBACK AS exact_schedule_groups_read_back,
    (SELECT COUNT(*) FROM `op_rh_v2_turnos_plantillas` WHERE `scope` = 'STATION' AND `id_estacion` = 1) AS interlomas_station_templates,
    (SELECT COUNT(*) FROM `op_rh_v2_turnos_plantillas` WHERE `scope` = 'STATION' AND `id_estacion` = 2) AS palo_solo_station_templates,
    (SELECT COUNT(*) FROM `op_rh_v2_turnos_plantillas` WHERE `scope` = 'STATION' AND `id_estacion` = 9) AS autolavado_station_templates;

-- Esta transacción queda abierta intencionalmente para revisión interactiva.
-- Ejecutar COMMIT sólo si IMPORT_READY=1 y FINAL_VALIDATION_READY=1, y después
-- confirmar la lectura M04 de la misma localidad. En cualquier otro caso: ROLLBACK.
-- COMMIT;
-- ROLLBACK;
