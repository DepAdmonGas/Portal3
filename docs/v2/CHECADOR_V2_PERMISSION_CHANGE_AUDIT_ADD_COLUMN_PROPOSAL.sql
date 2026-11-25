-- PROPOSAL ONLY for the verified local database bd_admongas.
-- Do not execute until the owner separately approves this DDL.
-- Adds structured, non-secret before/after metadata for capability-grant changes.
-- Existing M11 actor, permission, entity, action and UTC timestamp columns remain authoritative.
-- The application writes a curated change record, never the raw submitted form payload.
-- MariaDB 10.11 / 11.8 support ADD COLUMN IF NOT EXISTS and JSON storage.
ALTER TABLE `bd_admongas`.`op_rh_v2_m11_configuration_audit`
  ADD COLUMN IF NOT EXISTS `permission_change_json` JSON NULL AFTER `action`;

-- Read-only postflight after an approved manual ALTER:
SELECT `COLUMN_NAME`, `DATA_TYPE`, `IS_NULLABLE`
FROM `information_schema`.`COLUMNS`
WHERE `TABLE_SCHEMA` = 'bd_admongas'
  AND `TABLE_NAME` = 'op_rh_v2_m11_configuration_audit'
  AND `COLUMN_NAME` = 'permission_change_json';
