-- Los registros existentes conservan NULL hasta que se actualice su ficha.
SET @refacciones_norma_ddl = IF(
    EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'op_refacciones_calcomanias' AND COLUMN_NAME = 'norma'),
    'SELECT 1',
    'ALTER TABLE op_refacciones_calcomanias ADD COLUMN norma VARCHAR(250) NULL AFTER reproduccion'
);
PREPARE refacciones_norma_stmt FROM @refacciones_norma_ddl;
EXECUTE refacciones_norma_stmt;
DEALLOCATE PREPARE refacciones_norma_stmt;
