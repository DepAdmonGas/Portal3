-- =====================================================================
-- Submódulo: Fallas Terminales Punto de Venta
-- ---------------------------------------------------------------------
-- Los usuarios 635 y 650 ("Proveedor CONTROLUX") solo deben entrar a la
-- sección de Fallas, no al maestro de Terminales Punto de Venta. Por eso
-- se crea el submódulo aparte y a ellos se les traslada el permiso.
--
-- Es idempotente: se puede ejecutar varias veces sin duplicar nada.
-- =====================================================================

-- ---------------------------------------------------------------------
-- 1) Submódulo dentro del módulo Almacén (id_modulo = 4)
-- ---------------------------------------------------------------------
INSERT INTO modulos_sub_dpto_operativo (id_modulo, nombre, clave, ruta, icono, activo)
SELECT 4,
       'Fallas Terminales Punto de Venta',
       'terminales-punto-venta-fallas',
       '/departamento-operativo/almacen/terminales-punto-venta-fallas',
       '',
       1
WHERE NOT EXISTS (
    SELECT 1 FROM modulos_sub_dpto_operativo
    WHERE clave = 'terminales-punto-venta-fallas'
);

-- ---------------------------------------------------------------------
-- 2) Entrada del sidebar
--    orden 41: queda justo debajo de "Terminales Punto de Venta" (40).
--    etiqueta e icono vacíos a propósito: el servicio de menú cae al
--    nombre y al icono por defecto del submódulo.
-- ---------------------------------------------------------------------
INSERT INTO modulos_sidebar_do (id_sub_modulo, id_modulo_do, etiqueta, icono, orden, activo, obligatorio)
SELECT s.id, 4, '', '', 41, 1, 0
FROM modulos_sub_dpto_operativo s
WHERE s.clave = 'terminales-punto-venta-fallas'
  AND NOT EXISTS (
      SELECT 1 FROM modulos_sidebar_do sb WHERE sb.id_sub_modulo = s.id
  );

-- ---------------------------------------------------------------------
-- 3) Permiso del personal de ControlUx (635 y 650)
--    Se les quita el maestro de TPV y se les da el de Fallas.
--    Solo toca modulos_sub_usuarios_do (permiso directo por usuario);
--    modulos_sub_puestos_do no se modifica porque su puesto (13,
--    Dirección de operaciones) conserva el maestro de TPV.
-- ---------------------------------------------------------------------
INSERT INTO modulos_sub_usuarios_do (id_usuario, id_sub_modulo)
SELECT u.id, s.id
FROM tb_usuarios u
CROSS JOIN modulos_sub_dpto_operativo s
WHERE s.clave = 'terminales-punto-venta-fallas'
  AND u.id IN (635, 650)
  AND NOT EXISTS (
      SELECT 1 FROM modulos_sub_usuarios_do p
      WHERE p.id_usuario = u.id AND p.id_sub_modulo = s.id
  );

DELETE p
FROM modulos_sub_usuarios_do p
JOIN modulos_sub_dpto_operativo s ON s.id = p.id_sub_modulo
WHERE s.clave = 'terminales-punto-venta'
  AND p.id_usuario IN (635, 650);

-- =====================================================================
-- Verificación
-- ---------------------------------------------------------------------
-- SELECT s.id, s.id_modulo, s.clave, s.nombre, s.ruta
--   FROM modulos_sub_dpto_operativo s
--  WHERE s.clave = 'terminales-punto-venta-fallas';
--
-- SELECT p.id_usuario, s.clave
--   FROM modulos_sub_usuarios_do p
--   JOIN modulos_sub_dpto_operativo s ON s.id = p.id_sub_modulo
--  WHERE p.id_usuario IN (635, 650);
--
-- ---------------------------------------------------------------------
-- Rollback
-- ---------------------------------------------------------------------
-- INSERT INTO modulos_sub_usuarios_do (id_usuario, id_sub_modulo)
-- SELECT u.id, s.id
--   FROM tb_usuarios u
--   CROSS JOIN modulos_sub_dpto_operativo s
--  WHERE s.clave = 'terminales-punto-venta'
--    AND u.id IN (635, 650)
--    AND NOT EXISTS (SELECT 1 FROM modulos_sub_usuarios_do p
--                     WHERE p.id_usuario = u.id AND p.id_sub_modulo = s.id);
--
-- DELETE FROM modulos_sidebar_do
--  WHERE id_sub_modulo = (SELECT id FROM modulos_sub_dpto_operativo
--                          WHERE clave = 'terminales-punto-venta-fallas');
--
-- DELETE FROM modulos_sub_dpto_operativo
--  WHERE clave = 'terminales-punto-venta-fallas';
-- =====================================================================