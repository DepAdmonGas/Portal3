-- PARA REVISIÓN; NO EJECUTADO. Sólo bd_admongas tras verificación del operador.
-- Los IDs de estaciones provienen del catálogo físico configurado en Checador.
-- Inspeccionar antes: DATABASE(), módulos, configuración y permisos individuales.
SELECT DATABASE() AS base_efectiva;
SELECT id, clave, activo FROM modulos_dpto_operativo WHERE clave = 'acceso-temporal';
SELECT modulo_key, tipo, tipo_departamento, estaciones_soportadas, allow_all, activo
FROM tb_modulos_config WHERE modulo_key IN ('checador', 'acceso-temporal');
SELECT p.id, p.id_puesto, p.leer, p.editar
FROM modulos_puestos_do p JOIN modulos_dpto_operativo m ON m.id = p.id_modulo
WHERE m.clave = 'acceso-temporal' AND p.id_puesto IN (6,13);
SELECT u.id, u.id_usuario, u.leer, u.editar
FROM modulos_usuarios_do u JOIN modulos_dpto_operativo m ON m.id = u.id_modulo
WHERE m.clave = 'acceso-temporal';

-- Detener si base distinta, módulo ausente/inactivo, duplicados de grants,
-- configuración ambigua o estaciones sin mapeo unívoco; no alterar denegaciones.
START TRANSACTION;
SELECT id FROM modulos_dpto_operativo WHERE clave = 'acceso-temporal' AND activo = 1 FOR UPDATE;

-- estaciones_soportadas contiene IDs físicos de tb_estaciones, no localidades.
UPDATE tb_modulos_config target
JOIN tb_modulos_config checador ON checador.modulo_key = 'checador' AND checador.activo = 1
SET target.estaciones_soportadas = (
    SELECT JSON_ARRAYAGG(e.id ORDER BY e.id)
    FROM tb_estaciones e JOIN op_rh_localidades l ON l.numlista = e.numlista
    WHERE e.estatus = 0
      AND JSON_CONTAINS(checador.estaciones_soportadas, CAST(e.id AS CHAR), '$')
      AND (SELECT COUNT(*) FROM op_rh_localidades l2 WHERE l2.numlista = e.numlista) = 1
      AND (SELECT COUNT(*) FROM tb_estaciones e2 WHERE e2.numlista = e.numlista AND e2.estatus = 0) = 1
)
WHERE target.modulo_key = 'acceso-temporal' AND target.activo = 1
  AND target.tipo_departamento = 'localidades' AND target.allow_all = 0
  AND EXISTS (
      SELECT 1 FROM tb_estaciones e JOIN op_rh_localidades l ON l.numlista = e.numlista
      WHERE e.estatus = 0 AND JSON_CONTAINS(checador.estaciones_soportadas, CAST(e.id AS CHAR), '$')
        AND (SELECT COUNT(*) FROM op_rh_localidades l2 WHERE l2.numlista = e.numlista) = 1
        AND (SELECT COUNT(*) FROM tb_estaciones e2 WHERE e2.numlista = e.numlista AND e2.estatus = 0) = 1
  );

-- Insertar únicamente grants de puesto ausentes; no sustituir decisiones existentes.
INSERT INTO modulos_puestos_do (id_modulo, id_puesto, leer, crear, editar, eliminar, descargar)
SELECT m.id, requested.id_puesto, 1, 0, requested.editar, 0, 0
FROM modulos_dpto_operativo m
JOIN (SELECT 6 AS id_puesto, 1 AS editar UNION ALL SELECT 13, 0) requested
WHERE m.clave = 'acceso-temporal' AND m.activo = 1
  AND NOT EXISTS (SELECT 1 FROM modulos_puestos_do p WHERE p.id_modulo = m.id AND p.id_puesto = requested.id_puesto);

-- Comparar resultados y asignaciones individuales antes de decidir COMMIT.
SELECT modulo_key, estaciones_soportadas, tipo_departamento, allow_all, activo
FROM tb_modulos_config WHERE modulo_key = 'acceso-temporal';
SELECT p.id, p.id_puesto, p.leer, p.editar
FROM modulos_puestos_do p JOIN modulos_dpto_operativo m ON m.id = p.id_modulo
WHERE m.clave = 'acceso-temporal' AND p.id_puesto IN (6,13);
-- COMMIT o ROLLBACK corresponde al operador después de revisar los resultados.
