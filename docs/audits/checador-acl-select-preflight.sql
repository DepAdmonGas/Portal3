-- Portal3 Checador ACL preflight: SELECT statements only.
-- Run only after the database owner confirms this is the approved development database.
-- No IDs are assumed. The application models/tests document the expected columns;
-- statement 2 verifies the live schema before the remaining statements are used.

-- 1. Confirm the selected schema (compare with the locally resolved DB_DATABASE).
SELECT DATABASE() AS selected_database;

-- 2. Verify live columns for every table used below.
SELECT
    TABLE_NAME,
    COLUMN_NAME,
    ORDINAL_POSITION,
    COLUMN_TYPE,
    IS_NULLABLE
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME IN (
      'tb_puestos',
      'tb_usuarios',
      'modulos_dpto_operativo',
      'modulos_sub_dpto_operativo',
      'modulos_puestos_do',
      'modulos_usuarios_do',
      'modulos_sub_puestos_do',
      'modulos_sub_usuarios_do',
      'tb_modulos_config',
      'tb_multiestacion_puesto',
      'tb_multiestacion_usuario',
      'tb_estaciones',
      'op_rh_localidades'
  )
ORDER BY TABLE_NAME, ORDINAL_POSITION;

-- 3. Discover the actual IDs and labels for the requested puestos; no ID is presumed.
SELECT id, tipo_puesto, estatus
FROM tb_puestos
WHERE LOWER(tipo_puesto) LIKE '%direccion%'
   OR LOWER(tipo_puesto) LIKE '%operacion%'
   OR LOWER(tipo_puesto) LIKE '%encargado%'
ORDER BY tipo_puesto, id;

-- 4. Locate Recursos Humanos and its Checador child catalog entry.
SELECT
    parent.id AS parent_module_id,
    parent.clave AS parent_module_key,
    parent.nombre AS parent_module_name,
    parent.activo AS parent_active,
    child.id AS submodule_id,
    child.clave AS submodule_key,
    child.nombre AS submodule_name,
    child.ruta AS submodule_route,
    child.activo AS submodule_active
FROM modulos_dpto_operativo AS parent
LEFT JOIN modulos_sub_dpto_operativo AS child
       ON child.id_modulo = parent.id
      AND child.clave = 'checador'
WHERE parent.clave = 'recursos-humanos';

-- 5. Parent-module flags assigned by puesto.
SELECT
    module.id AS module_id,
    module.clave AS module_key,
    grant_row.id_puesto,
    puesto.tipo_puesto,
    grant_row.leer,
    grant_row.crear,
    grant_row.editar,
    grant_row.eliminar,
    grant_row.descargar
FROM modulos_puestos_do AS grant_row
JOIN modulos_dpto_operativo AS module ON module.id = grant_row.id_modulo
JOIN tb_puestos AS puesto ON puesto.id = grant_row.id_puesto
WHERE module.clave = 'recursos-humanos'
ORDER BY puesto.tipo_puesto, grant_row.id_puesto;

-- 6. Parent-module flags assigned directly to users in the requested puestos.
SELECT
    module.id AS module_id,
    module.clave AS module_key,
    user_row.id AS user_id,
    user_row.id_gas,
    user_row.id_puesto,
    puesto.tipo_puesto,
    grant_row.leer,
    grant_row.crear,
    grant_row.editar,
    grant_row.eliminar,
    grant_row.descargar
FROM modulos_usuarios_do AS grant_row
JOIN modulos_dpto_operativo AS module ON module.id = grant_row.id_modulo
JOIN tb_usuarios AS user_row ON user_row.id = grant_row.id_usuario
JOIN tb_puestos AS puesto ON puesto.id = user_row.id_puesto
WHERE module.clave = 'recursos-humanos'
  AND (
      LOWER(puesto.tipo_puesto) LIKE '%direccion%'
      OR LOWER(puesto.tipo_puesto) LIKE '%operacion%'
      OR LOWER(puesto.tipo_puesto) LIKE '%encargado%'
  )
ORDER BY puesto.tipo_puesto, user_row.id;

-- 7. Checador submodule assignment by puesto (membership grants access; no action flags exist here).
SELECT
    parent.clave AS parent_module_key,
    child.id AS submodule_id,
    child.clave AS submodule_key,
    assignment.id_puesto,
    puesto.tipo_puesto
FROM modulos_sub_puestos_do AS assignment
JOIN modulos_sub_dpto_operativo AS child ON child.id = assignment.id_sub_modulo
JOIN modulos_dpto_operativo AS parent ON parent.id = child.id_modulo
JOIN tb_puestos AS puesto ON puesto.id = assignment.id_puesto
WHERE parent.clave = 'recursos-humanos'
  AND child.clave = 'checador'
ORDER BY puesto.tipo_puesto, assignment.id_puesto;

-- 8. Checador submodule assignment directly to users in the requested puestos.
SELECT
    parent.clave AS parent_module_key,
    child.id AS submodule_id,
    child.clave AS submodule_key,
    user_row.id AS user_id,
    user_row.id_gas,
    user_row.id_puesto,
    puesto.tipo_puesto
FROM modulos_sub_usuarios_do AS assignment
JOIN modulos_sub_dpto_operativo AS child ON child.id = assignment.id_sub_modulo
JOIN modulos_dpto_operativo AS parent ON parent.id = child.id_modulo
JOIN tb_usuarios AS user_row ON user_row.id = assignment.id_usuario
JOIN tb_puestos AS puesto ON puesto.id = user_row.id_puesto
WHERE parent.clave = 'recursos-humanos'
  AND child.clave = 'checador'
  AND (
      LOWER(puesto.tipo_puesto) LIKE '%direccion%'
      OR LOWER(puesto.tipo_puesto) LIKE '%operacion%'
      OR LOWER(puesto.tipo_puesto) LIKE '%encargado%'
  )
ORDER BY puesto.tipo_puesto, user_row.id;

-- 9. Checador's module-level station configuration (context/supported scope, not an ACL grant).
SELECT
    id,
    modulo_key,
    tipo,
    estaciones_soportadas,
    departamentos_soportados,
    tipo_departamento,
    allow_all,
    placeholder,
    activo
FROM tb_modulos_config
WHERE modulo_key = 'checador';

-- 10. Multi-station scope attached to the requested puestos.
SELECT
    scope.id,
    scope.id_puesto,
    puesto.tipo_puesto,
    scope.estaciones,
    scope.departamentos_puestos,
    scope.departamentos_localidades,
    scope.activo
FROM tb_multiestacion_puesto AS scope
JOIN tb_puestos AS puesto ON puesto.id = scope.id_puesto
WHERE LOWER(puesto.tipo_puesto) LIKE '%direccion%'
   OR LOWER(puesto.tipo_puesto) LIKE '%operacion%'
   OR LOWER(puesto.tipo_puesto) LIKE '%encargado%'
ORDER BY puesto.tipo_puesto, scope.id;

-- 11. Multi-station scope attached to users in the requested puestos.
SELECT
    scope.id,
    scope.id_usuario AS user_id,
    user_row.id_gas,
    user_row.id_puesto,
    puesto.tipo_puesto,
    scope.estaciones,
    scope.departamentos_puestos,
    scope.departamentos_localidades,
    scope.activo
FROM tb_multiestacion_usuario AS scope
JOIN tb_usuarios AS user_row ON user_row.id = scope.id_usuario
JOIN tb_puestos AS puesto ON puesto.id = user_row.id_puesto
WHERE LOWER(puesto.tipo_puesto) LIKE '%direccion%'
   OR LOWER(puesto.tipo_puesto) LIKE '%operacion%'
   OR LOWER(puesto.tipo_puesto) LIKE '%encargado%'
ORDER BY puesto.tipo_puesto, scope.id_usuario, scope.id;

-- 12. All direct parent-module grants for users in the requested puestos.
-- Existing service semantics: any direct user grant in any module makes user grants
-- take precedence globally; they are not merged with puesto flags.
SELECT
    user_row.id AS user_id,
    user_row.id_gas,
    user_row.id_puesto,
    puesto.tipo_puesto,
    module.id AS module_id,
    module.clave AS module_key,
    grant_row.leer,
    grant_row.crear,
    grant_row.editar,
    grant_row.eliminar,
    grant_row.descargar
FROM modulos_usuarios_do AS grant_row
JOIN tb_usuarios AS user_row ON user_row.id = grant_row.id_usuario
JOIN tb_puestos AS puesto ON puesto.id = user_row.id_puesto
LEFT JOIN modulos_dpto_operativo AS module ON module.id = grant_row.id_modulo
WHERE LOWER(puesto.tipo_puesto) LIKE '%direccion%'
   OR LOWER(puesto.tipo_puesto) LIKE '%operacion%'
   OR LOWER(puesto.tipo_puesto) LIKE '%encargado%'
ORDER BY puesto.tipo_puesto, user_row.id, grant_row.id_modulo;

-- 13. Station/locality catalog mapping used to interpret configured station IDs.
SELECT id, numlista, nombre
FROM tb_estaciones
ORDER BY numlista, id;

SELECT id, numlista, localidad
FROM op_rh_localidades
ORDER BY numlista, id;
