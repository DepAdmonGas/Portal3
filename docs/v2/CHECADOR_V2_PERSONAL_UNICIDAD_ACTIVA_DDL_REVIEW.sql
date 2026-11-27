-- PROPUESTA PARA REVISIÓN; NO EJECUTAR EN PREPRODUCCIÓN SIN AUTORIZACIÓN.
-- Antes de aplicar: verificar base destino, esquema, escritores y duplicados.
-- Cualquier fila devuelta bloquea el ALTER hasta resolverla institucionalmente.
SELECT id_estacion, no_colaborador, COUNT(*) AS total
FROM op_rh_personal
WHERE estado = 1 AND no_colaborador > 0
GROUP BY id_estacion, no_colaborador
HAVING COUNT(*) > 1;

-- Esta restricción protege también ante escritores que no toman el bloqueo
-- de tb_estaciones. Los trabajadores inactivos no participan en la clave.
ALTER TABLE op_rh_personal
  ADD COLUMN no_colaborador_activo INT
    GENERATED ALWAYS AS (IF(estado = 1 AND no_colaborador > 0, no_colaborador, NULL)) STORED,
  ADD UNIQUE KEY uk_personal_estacion_colaborador_activo (id_estacion, no_colaborador_activo);
