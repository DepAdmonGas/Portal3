-- CHECADOR_V2_MVP_ROLE_STATION_SCOPE_IMPLEMENTATION
-- PROPOSAL ONLY — NOT EXECUTED.
-- Target: local development database bd_admongas only.
-- Never run against production or preproduction.
--
-- Verified allowlist is in tb_estaciones ID space:
--   [1,2,3,4,5,6,7,8,14]
-- IDs 1-8 and 14 have estatus=0 and a unique numlista mapping to
-- op_rh_localidades. IDs 9, 10 and 12 have estatus=1 and are excluded.
-- estatus=0 is the active value documented by the existing security contract.
--
-- This script tracks only rows actually inserted/changed in a MEMORY
-- temporary table. Keep the SAME SQL session open after applying so the
-- rollback block can remove only those introduced rows and restore the prior
-- config value. If the session/temporary table is lost, do not run a broad
-- DELETE; inspect current state and prepare a separately reviewed rollback.

-- PRE-FLIGHT: stop unless the client is connected to the local development
-- MariaDB instance and all rows below still match the reviewed state.
SELECT DATABASE() AS selected_database, @@hostname AS database_server;

SELECT id, modulo_key, tipo, estaciones_soportadas, tipo_departamento,
       allow_all, activo
FROM tb_modulos_config
WHERE id = 39 AND modulo_key = 'checador';

SELECT e.id AS station_id, e.numlista, e.nombre, e.estatus,
       COUNT(DISTINCT l.id) AS locality_matches,
       MIN(l.id) AS locality_id, MIN(l.localidad) AS locality_name
FROM tb_estaciones AS e
LEFT JOIN op_rh_localidades AS l ON l.numlista = e.numlista
WHERE e.id IN (1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 12, 14)
GROUP BY e.id, e.numlista, e.nombre, e.estatus
ORDER BY e.id;

SELECT parent.id AS parent_id, parent.clave AS parent_key,
       parent.activo AS parent_active, child.id AS submodule_id,
       child.clave AS submodule_key, child.activo AS submodule_active
FROM modulos_dpto_operativo AS parent
JOIN modulos_sub_dpto_operativo AS child ON child.id_modulo = parent.id
WHERE parent.id = 2 AND parent.clave = 'recursos-humanos'
  AND child.id = 63 AND child.clave = 'checador';

SELECT id, id_sub_modulo, id_puesto
FROM modulos_sub_puestos_do
WHERE id_sub_modulo = 63 AND id_puesto IN (6, 13)
ORDER BY id_puesto, id;

-- APPLY BLOCK — manual execution requires a separate explicit approval.
-- The temporary ledger is per-connection and deliberately is not persistent.
CREATE TEMPORARY TABLE checador_role_scope_rollback (
    ledger_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    change_kind VARCHAR(32) NOT NULL,
    pivot_row_id BIGINT UNSIGNED NULL,
    config_id BIGINT UNSIGNED NULL,
    previous_value TEXT NULL
) ENGINE=MEMORY;

START TRANSACTION;

-- Capture the old value only if the reviewed baseline is still exact.
INSERT INTO checador_role_scope_rollback (change_kind, config_id, previous_value)
SELECT 'config_before', id, estaciones_soportadas
FROM tb_modulos_config
WHERE id = 39
  AND modulo_key = 'checador'
  AND tipo = 'stations_only'
  AND tipo_departamento = 'puestos'
  AND activo = 1
  AND allow_all = 0
  AND estaciones_soportadas = '[1]';

-- Grant only the Checador submodule membership to the two verified puestos.
-- No parent flags, other submodules, user ACLs, or write capabilities change.
INSERT INTO modulos_sub_puestos_do (id_sub_modulo, id_puesto)
SELECT 63, 13
FROM DUAL
WHERE EXISTS (
    SELECT 1 FROM modulos_dpto_operativo p
    JOIN modulos_sub_dpto_operativo s ON s.id_modulo = p.id
    WHERE p.id = 2 AND p.clave = 'recursos-humanos' AND p.activo = 1
      AND s.id = 63 AND s.clave = 'checador' AND s.activo = 1
)
AND EXISTS (
    SELECT 1 FROM tb_modulos_config
    WHERE id = 39 AND modulo_key = 'checador'
      AND tipo = 'stations_only' AND tipo_departamento = 'puestos'
      AND activo = 1 AND allow_all = 0 AND estaciones_soportadas = '[1]'
)
AND NOT EXISTS (
    SELECT 1 FROM modulos_sub_puestos_do
    WHERE id_sub_modulo = 63 AND id_puesto = 13
);
SET @checador_inserted_rows = ROW_COUNT();
SET @checador_inserted_id = IF(@checador_inserted_rows = 1, LAST_INSERT_ID(), NULL);
INSERT INTO checador_role_scope_rollback (change_kind, pivot_row_id)
SELECT 'inserted_membership', @checador_inserted_id
WHERE @checador_inserted_id IS NOT NULL;

INSERT INTO modulos_sub_puestos_do (id_sub_modulo, id_puesto)
SELECT 63, 6
FROM DUAL
WHERE EXISTS (
    SELECT 1 FROM modulos_dpto_operativo p
    JOIN modulos_sub_dpto_operativo s ON s.id_modulo = p.id
    WHERE p.id = 2 AND p.clave = 'recursos-humanos' AND p.activo = 1
      AND s.id = 63 AND s.clave = 'checador' AND s.activo = 1
)
AND EXISTS (
    SELECT 1 FROM tb_modulos_config
    WHERE id = 39 AND modulo_key = 'checador'
      AND tipo = 'stations_only' AND tipo_departamento = 'puestos'
      AND activo = 1 AND allow_all = 0 AND estaciones_soportadas = '[1]'
)
AND NOT EXISTS (
    SELECT 1 FROM modulos_sub_puestos_do
    WHERE id_sub_modulo = 63 AND id_puesto = 6
);
SET @checador_inserted_rows = ROW_COUNT();
SET @checador_inserted_id = IF(@checador_inserted_rows = 1, LAST_INSERT_ID(), NULL);
INSERT INTO checador_role_scope_rollback (change_kind, pivot_row_id)
SELECT 'inserted_membership', @checador_inserted_id
WHERE @checador_inserted_id IS NOT NULL;

-- Expand the module's explicit supported catalog only to verified
-- active/mapped IDs. Dirección receives this Checador-only list; Encargado
-- remains bound to the current id_gas in ChecadorAccessService. This does not
-- modify the global multi-station allowlists used by other modules.
UPDATE tb_modulos_config
SET estaciones_soportadas = '[1,2,3,4,5,6,7,8,14]'
WHERE id = 39
  AND modulo_key = 'checador'
  AND tipo = 'stations_only'
  AND tipo_departamento = 'puestos'
  AND activo = 1
  AND allow_all = 0
  AND estaciones_soportadas = '[1]';
SET @checador_config_changed = ROW_COUNT();
UPDATE checador_role_scope_rollback
SET change_kind = 'changed_config'
WHERE change_kind = 'config_before'
  AND @checador_config_changed = 1;

COMMIT;

-- READBACK after an approved execution.
SELECT id, id_sub_modulo, id_puesto
FROM modulos_sub_puestos_do
WHERE id_sub_modulo = 63 AND id_puesto IN (6, 13)
ORDER BY id_puesto, id;

SELECT id, modulo_key, estaciones_soportadas, tipo, tipo_departamento,
       allow_all, activo
FROM tb_modulos_config
WHERE id = 39 AND modulo_key = 'checador';

SELECT ledger_id, change_kind, pivot_row_id, config_id, previous_value
FROM checador_role_scope_rollback
ORDER BY ledger_id;

-- ROLLBACK BLOCK — run only if a rollback is separately approved, in the same
-- still-open SQL session (the temporary ledger identifies introduced rows).
--
-- START TRANSACTION;
-- DELETE target
-- FROM modulos_sub_puestos_do AS target
-- JOIN checador_role_scope_rollback AS ledger
--   ON ledger.pivot_row_id = target.id
--  AND ledger.change_kind = 'inserted_membership';
-- UPDATE tb_modulos_config AS config
-- JOIN checador_role_scope_rollback AS ledger
--   ON ledger.config_id = config.id
--  AND ledger.change_kind = 'changed_config'
-- SET config.estaciones_soportadas = ledger.previous_value
-- WHERE config.modulo_key = 'checador'
--   AND config.estaciones_soportadas = '[1,2,3,4,5,6,7,8,14]';
-- COMMIT;
--
-- SELECT id_sub_modulo, id_puesto FROM modulos_sub_puestos_do
-- WHERE id_sub_modulo = 63 AND id_puesto IN (6, 13);
-- SELECT id, estaciones_soportadas FROM tb_modulos_config
-- WHERE id = 39 AND modulo_key = 'checador';
-- DROP TEMPORARY TABLE checador_role_scope_rollback;
