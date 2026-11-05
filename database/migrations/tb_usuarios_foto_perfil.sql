-- Portal3: foto de perfil personalizada para usuarios.
-- Se puede ejecutar aunque la columna ya exista.

SET @portal3_foto_sql = IF(
    (SELECT COUNT(*)
     FROM information_schema.columns
     WHERE table_schema = DATABASE()
       AND table_name = 'tb_usuarios'
       AND column_name = 'foto') = 0,
    'ALTER TABLE `tb_usuarios` ADD COLUMN `foto` VARCHAR(255) NULL',
    'SELECT 1'
);
PREPARE portal3_foto_stmt FROM @portal3_foto_sql;
EXECUTE portal3_foto_stmt;
DEALLOCATE PREPARE portal3_foto_stmt;
