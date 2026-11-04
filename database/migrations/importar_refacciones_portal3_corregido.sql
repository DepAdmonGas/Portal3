-- Portal3 | Esquema consolidado vacío del módulo Refacciones.
-- Requiere la base Portal3 ya instalada, en particular op_pedido_materiales
-- y las tablas centrales usadas por Portal3.
-- Crea las tablas del módulo vacías y adapta las columnas de la versión anterior.
-- No incluye INSERT, UPDATE, DELETE ni datos de catálogo.

CREATE TABLE IF NOT EXISTS op_refacciones_catalogo (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    sku VARCHAR(60) NOT NULL,
    tipo_articulo ENUM('refaccion', 'uniforme', 'calcomania') NOT NULL DEFAULT 'refaccion',
    nombre VARCHAR(250) NOT NULL,
    descripcion TEXT NULL,
    medida VARCHAR(100) NULL,
    modelo VARCHAR(150) NULL,
    marca VARCHAR(150) NULL,
    categoria VARCHAR(120) NULL,
    unidad_medida VARCHAR(40) NOT NULL DEFAULT 'Pieza',
    stock_minimo DOUBLE NOT NULL DEFAULT 0,
    costo_unitario DOUBLE NOT NULL DEFAULT 0,
    imagen VARCHAR(255) NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    creado_por BIGINT UNSIGNED NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_op_refacciones_catalogo_sku (sku),
    KEY ix_op_refacciones_catalogo_tipo_activo (tipo_articulo, activo),
    KEY ix_op_refacciones_catalogo_nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS op_refacciones_existencias (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_refaccion BIGINT UNSIGNED NOT NULL,
    id_estacion INT UNSIGNED NOT NULL COMMENT 'ID de localidad usado por el módulo para estaciones o departamentos',
    existencia DOUBLE NOT NULL DEFAULT 0,
    costo_promedio DOUBLE NOT NULL DEFAULT 0 COMMENT 'Costo promedio ponderado de la refacción en esta ubicación',
    stock_minimo DOUBLE NOT NULL DEFAULT 0,
    actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_op_refacciones_existencia_ubicacion (id_refaccion, id_estacion),
    KEY ix_op_refacciones_existencia_ubicacion (id_estacion),
    CONSTRAINT fk_op_refacciones_existencia_catalogo
        FOREIGN KEY (id_refaccion) REFERENCES op_refacciones_catalogo (id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS op_refacciones_movimientos (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    folio VARCHAR(40) NOT NULL,
    tipo VARCHAR(20) NOT NULL COMMENT 'entrada, salida, traspaso, ajuste, prestamo, devolucion',
    fecha DATETIME NULL,
    id_estacion_origen INT UNSIGNED NULL,
    id_estacion_destino INT UNSIGNED NULL,
    id_usuario BIGINT UNSIGNED NOT NULL,
    proveedor VARCHAR(180) NULL,
    referencia VARCHAR(120) NULL,
    motivo VARCHAR(250) NULL,
    notas TEXT NULL,
    estado VARCHAR(20) NOT NULL DEFAULT 'confirmado' COMMENT 'confirmado, cancelado',
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_op_refacciones_movimientos_folio (folio),
    KEY ix_op_refacciones_movimientos_fecha_tipo (fecha, tipo),
    KEY ix_op_refacciones_movimientos_origen_fecha (id_estacion_origen, fecha),
    KEY ix_op_refacciones_movimientos_destino_fecha (id_estacion_destino, fecha),
    KEY ix_op_refacciones_movimientos_usuario_fecha (id_usuario, fecha)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS op_refacciones_movimientos_detalle (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_movimiento BIGINT UNSIGNED NOT NULL,
    id_refaccion BIGINT UNSIGNED NOT NULL,
    cantidad DOUBLE NOT NULL,
    costo_unitario DOUBLE NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    KEY ix_op_refacciones_mov_detalle_movimiento (id_movimiento),
    KEY ix_op_refacciones_mov_detalle_refaccion (id_refaccion),
    CONSTRAINT fk_op_refacciones_mov_detalle_movimiento
        FOREIGN KEY (id_movimiento) REFERENCES op_refacciones_movimientos (id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_op_refacciones_mov_detalle_catalogo
        FOREIGN KEY (id_refaccion) REFERENCES op_refacciones_catalogo (id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS op_refacciones_movimientos_detalle_costos (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_movimiento_detalle BIGINT UNSIGNED NOT NULL,
    costo_unitario DOUBLE NOT NULL DEFAULT 0,
    cantidad DOUBLE NOT NULL DEFAULT 0,
    registrado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY ix_op_refacciones_mov_det_costos_detalle (id_movimiento_detalle),
    CONSTRAINT fk_op_refacciones_mov_det_costos_detalle
        FOREIGN KEY (id_movimiento_detalle) REFERENCES op_refacciones_movimientos_detalle (id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS op_refacciones_prestamos (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    folio VARCHAR(40) NOT NULL,
    id_estacion_origen INT UNSIGNED NOT NULL,
    id_estacion_destino INT UNSIGNED NOT NULL,
    fecha_salida DATETIME NOT NULL,
    fecha_retorno_programada DATE NULL,
    fecha_retorno DATETIME NULL,
    id_usuario BIGINT UNSIGNED NOT NULL,
    estado VARCHAR(20) NOT NULL DEFAULT 'prestado' COMMENT 'prestado, parcial, devuelto, cancelado',
    notas TEXT NULL,
    id_movimiento_salida BIGINT UNSIGNED NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_op_refacciones_prestamos_folio (folio),
    KEY ix_op_refacciones_prestamos_origen_estado (id_estacion_origen, estado),
    KEY ix_op_refacciones_prestamos_destino_estado (id_estacion_destino, estado),
    CONSTRAINT fk_op_refacciones_prestamos_movimiento
        FOREIGN KEY (id_movimiento_salida) REFERENCES op_refacciones_movimientos (id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS op_refacciones_prestamos_detalle (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_prestamo BIGINT UNSIGNED NOT NULL,
    id_refaccion BIGINT UNSIGNED NOT NULL,
    cantidad DOUBLE NOT NULL,
    cantidad_devuelta DOUBLE NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    KEY ix_op_refacciones_prestamos_detalle_prestamo (id_prestamo),
    CONSTRAINT fk_op_refacciones_prestamos_detalle_prestamo
        FOREIGN KEY (id_prestamo) REFERENCES op_refacciones_prestamos (id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_op_refacciones_prestamos_detalle_catalogo
        FOREIGN KEY (id_refaccion) REFERENCES op_refacciones_catalogo (id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS op_refacciones_uniformes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_refaccion BIGINT UNSIGNED NOT NULL,
    talla VARCHAR(40) NULL,
    color VARCHAR(80) NULL,
    notas VARCHAR(500) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_op_refacciones_uniformes_refaccion (id_refaccion),
    CONSTRAINT fk_op_refacciones_uniformes_catalogo
        FOREIGN KEY (id_refaccion) REFERENCES op_refacciones_catalogo (id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS op_refacciones_calcomanias (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_refaccion BIGINT UNSIGNED NOT NULL,
    dimension VARCHAR(100) NULL,
    colores_pantone VARCHAR(250) NULL,
    acabado VARCHAR(80) NULL,
    reproduccion VARCHAR(100) NULL,
    notas TEXT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_op_refacciones_calcomanias_refaccion (id_refaccion),
    CONSTRAINT fk_op_refacciones_calcomanias_catalogo
        FOREIGN KEY (id_refaccion) REFERENCES op_refacciones_catalogo (id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS op_refacciones_acuses (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_movimiento BIGINT UNSIGNED NOT NULL,
    nombre_entrega VARCHAR(160) NOT NULL,
    nombre_recibe VARCHAR(160) NOT NULL,
    firma_entrega VARCHAR(255) NULL,
    firma_recibe VARCHAR(255) NULL,
    archivo_pdf VARCHAR(255) NULL,
    firmado_en DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_op_refacciones_acuses_movimiento (id_movimiento),
    CONSTRAINT fk_op_refacciones_acuses_movimiento
        FOREIGN KEY (id_movimiento) REFERENCES op_refacciones_movimientos (id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS op_refacciones_calendario (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    titulo VARCHAR(180) NOT NULL,
    tipo_evento VARCHAR(40) NOT NULL COMMENT 'compra, mantenimiento_externo, prueba, calibracion, otro',
    categoria VARCHAR(120) NULL,
    proveedor_externo VARCHAR(180) NULL,
    fecha DATE NOT NULL,
    hora TIME NULL,
    id_estacion INT UNSIGNED NULL,
    estado VARCHAR(20) NOT NULL DEFAULT 'pendiente',
    correo_responsable VARCHAR(180) NULL,
    notas TEXT NULL,
    id_usuario BIGINT UNSIGNED NOT NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY ix_op_refacciones_calendario_fecha (fecha),
    KEY ix_op_refacciones_calendario_tipo_fecha (tipo_evento, fecha),
    KEY ix_op_refacciones_calendario_estacion_fecha (id_estacion, fecha)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS op_refacciones_calendario_ubicaciones (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_evento BIGINT UNSIGNED NOT NULL,
    id_estacion INT UNSIGNED NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_op_refacciones_calendario_ubicacion (id_evento, id_estacion),
    KEY ix_op_refacciones_calendario_ubicaciones_estacion (id_estacion, id_evento),
    CONSTRAINT fk_op_refacciones_calendario_ubicaciones_evento
        FOREIGN KEY (id_evento) REFERENCES op_refacciones_calendario (id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS op_refacciones_categorias (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    nombre VARCHAR(120) NOT NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_op_refacciones_categorias_nombre (nombre),
    KEY ix_op_refacciones_categorias_activo (activo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS op_refacciones_salidas (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_movimiento BIGINT UNSIGNED NOT NULL,
    estado_salida VARCHAR(30) NOT NULL DEFAULT 'Prestado',
    fecha_retorno DATE NULL DEFAULT NULL,
    id_proveedor_retorno BIGINT UNSIGNED NULL,
    proveedor_retorno VARCHAR(180) NULL,
    referencia_retorno VARCHAR(120) NULL,
    notas_retorno TEXT NULL,
    estatus TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0 pendiente de firma de elaboración, 1 pendiente de recepción, 2 recepción firmada',
    firma_elabora VARCHAR(255) NULL,
    nombre_elabora VARCHAR(160) NULL,
    fecha_firma_elabora DATETIME NULL,
    stock_origen_aplicado TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Indica si el stock ya fue descontado del origen al finalizar la salida',
    firma_recibe VARCHAR(255) NULL,
    id_encargado_recibe BIGINT UNSIGNED NULL,
    nombre_recibe VARCHAR(160) NULL,
    fecha_firma_recibe DATETIME NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_op_refacciones_salidas_movimiento (id_movimiento),
    KEY ix_op_refacciones_salidas_estatus_creado (estatus, creado_en),
    CONSTRAINT fk_op_refacciones_salidas_movimiento
        FOREIGN KEY (id_movimiento) REFERENCES op_refacciones_movimientos (id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS op_refacciones_calendario_recordatorios (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_evento BIGINT UNSIGNED NOT NULL,
    id_usuario BIGINT UNSIGNED NOT NULL,
    canal VARCHAR(20) NOT NULL COMMENT 'correo o telegram',
    fecha_envio DATE NOT NULL,
    estado VARCHAR(20) NOT NULL DEFAULT 'procesando' COMMENT 'procesando, enviado o fallido',
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    procesado_en DATETIME NULL DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_op_refacciones_recordatorio_dia (id_evento, id_usuario, canal, fecha_envio),
    KEY ix_op_refacciones_recordatorio_fecha (fecha_envio, estado),
    KEY ix_op_refacciones_recordatorio_usuario (id_usuario, fecha_envio)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS op_refacciones_solicitudes_calcomanias (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_ubicacion INT UNSIGNED NOT NULL,
    id_usuario INT UNSIGNED NOT NULL,
    nombre_personal VARCHAR(180) NOT NULL,
    puesto VARCHAR(120) NULL,
    fecha_hora DATETIME NOT NULL,
    observaciones TEXT NULL,
    estado TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0 Pendiente, 1 En proceso, 2 Finalizado',
    firma_encargado LONGTEXT NULL,
    firmado_encargado_en DATETIME NULL,
    firma_autorizacion LONGTEXT NULL,
    id_usuario_autorizacion INT UNSIGNED NULL,
    firmado_autorizacion_en DATETIME NULL,
    creado_en DATETIME NOT NULL,
    actualizado_en DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_op_refacciones_solicitudes_calcomanias_ubicacion_fecha (id_ubicacion, fecha_hora),
    KEY idx_op_refacciones_solicitudes_calcomanias_estado (estado),
    KEY idx_op_refacciones_solicitudes_calcomanias_usuario (id_usuario)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS op_refacciones_solicitudes_calcomanias_detalle (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_solicitud BIGINT UNSIGNED NOT NULL,
    id_calcomania BIGINT UNSIGNED NOT NULL,
    sku_calcomania VARCHAR(80) NOT NULL,
    nombre_calcomania VARCHAR(180) NOT NULL,
    dimension VARCHAR(100) NULL,
    acabado VARCHAR(80) NULL,
    reproduccion VARCHAR(100) NULL,
    cantidad INT UNSIGNED NOT NULL,
    PRIMARY KEY (id),
    KEY idx_op_refacciones_solicitudes_calcomanias_detalle_solicitud (id_solicitud),
    KEY idx_op_refacciones_solicitudes_calcomanias_detalle_calcomania (id_calcomania),
    CONSTRAINT fk_op_refacciones_solicitudes_calcomanias_detalle_solicitud
        FOREIGN KEY (id_solicitud) REFERENCES op_refacciones_solicitudes_calcomanias (id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_op_refacciones_solicitudes_calcomanias_detalle_catalogo
        FOREIGN KEY (id_calcomania) REFERENCES op_refacciones_catalogo (id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS op_refacciones_solicitudes_calcomanias_token (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_solicitud BIGINT UNSIGNED NOT NULL,
    id_usuario INT UNSIGNED NOT NULL,
    fecha_creacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    token CHAR(6) NOT NULL,
    PRIMARY KEY (id),
    KEY ix_op_refacciones_solicitudes_calcomanias_token_solicitud (id_solicitud),
    KEY ix_op_refacciones_solicitudes_calcomanias_token_usuario (id_usuario),
    CONSTRAINT fk_op_refacciones_solicitudes_calcomanias_token_solicitud
        FOREIGN KEY (id_solicitud) REFERENCES op_refacciones_solicitudes_calcomanias (id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS op_refacciones_precios (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_refaccion BIGINT UNSIGNED NOT NULL,
    costo_anterior DOUBLE NULL,
    costo_nuevo DOUBLE NOT NULL,
    id_usuario BIGINT UNSIGNED NULL,
    registrado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY ix_op_refacciones_precios_refaccion_fecha (id_refaccion, registrado_en),
    CONSTRAINT fk_op_refacciones_precios_catalogo
        FOREIGN KEY (id_refaccion) REFERENCES op_refacciones_catalogo (id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS op_refacciones_existencias_lotes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_refaccion BIGINT UNSIGNED NOT NULL,
    id_estacion INT UNSIGNED NOT NULL,
    costo_unitario DOUBLE NOT NULL DEFAULT 0,
    existencia DOUBLE NOT NULL DEFAULT 0,
    registrado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY ix_op_refacciones_lotes_ubicacion (id_estacion, id_refaccion, existencia),
    CONSTRAINT fk_op_refacciones_lotes_catalogo
        FOREIGN KEY (id_refaccion) REFERENCES op_refacciones_catalogo (id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS op_pedido_materiales_refacciones (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_pedido INT NOT NULL,
    id_refaccion BIGINT UNSIGNED NOT NULL,
    cantidad DOUBLE NOT NULL,
    id_movimiento_salida BIGINT UNSIGNED NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_op_pedido_materiales_refaccion_pedido (id_pedido),
    UNIQUE KEY uq_op_pedido_materiales_refaccion_movimiento (id_movimiento_salida),
    CONSTRAINT fk_op_pedido_materiales_refacciones_pedido
        FOREIGN KEY (id_pedido) REFERENCES op_pedido_materiales (id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_op_pedido_materiales_refacciones_catalogo
        FOREIGN KEY (id_refaccion) REFERENCES op_refacciones_catalogo (id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_op_pedido_materiales_refacciones_movimiento
        FOREIGN KEY (id_movimiento_salida) REFERENCES op_refacciones_movimientos (id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Modificación a una tabla de Portal3 preexistente. La columna se agrega solo
-- si todavía no existe, para que esta parte pueda importarse más de una vez.
SET @refacciones_ddl = IF(
    EXISTS(
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'op_pedido_materiales'
          AND COLUMN_NAME = 'usa_refacciones_catalogo'
    ),
    'SELECT 1',
    'ALTER TABLE op_pedido_materiales ADD COLUMN usa_refacciones_catalogo TINYINT(1) NOT NULL DEFAULT 0 COMMENT ''Las órdenes nuevas toman refacciones de op_refacciones_catalogo'''
);
PREPARE refacciones_stmt FROM @refacciones_ddl;
EXECUTE refacciones_stmt;
DEALLOCATE PREPARE refacciones_stmt;

-- Ajustes compatibles con instalaciones que ya tenían una versión anterior.
-- Las tablas nuevas quedaron declaradas arriba con su estructura final.

SET @refacciones_ddl = IF(
    EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'op_refacciones_catalogo' AND COLUMN_NAME = 'stock_minimo'),
    'SELECT 1',
    'ALTER TABLE op_refacciones_catalogo ADD COLUMN stock_minimo DOUBLE NOT NULL DEFAULT 0 AFTER unidad_medida'
);
PREPARE refacciones_stmt FROM @refacciones_ddl;
EXECUTE refacciones_stmt;
DEALLOCATE PREPARE refacciones_stmt;

SET @refacciones_ddl = IF(
    EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'op_refacciones_catalogo' AND COLUMN_NAME = 'nombre_completo'),
    'ALTER TABLE op_refacciones_catalogo DROP COLUMN nombre_completo',
    'SELECT 1'
);
PREPARE refacciones_stmt FROM @refacciones_ddl;
EXECUTE refacciones_stmt;
DEALLOCATE PREPARE refacciones_stmt;

SET @refacciones_ddl = IF(
    EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'op_refacciones_existencias' AND COLUMN_NAME = 'costo_promedio'),
    'SELECT 1',
    'ALTER TABLE op_refacciones_existencias ADD COLUMN costo_promedio DOUBLE NOT NULL DEFAULT 0 AFTER existencia'
);
PREPARE refacciones_stmt FROM @refacciones_ddl;
EXECUTE refacciones_stmt;
DEALLOCATE PREPARE refacciones_stmt;

SET @refacciones_ddl = IF(
    EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'op_refacciones_calendario' AND COLUMN_NAME = 'categoria'),
    'SELECT 1',
    'ALTER TABLE op_refacciones_calendario ADD COLUMN categoria VARCHAR(120) NULL AFTER tipo_evento'
);
PREPARE refacciones_stmt FROM @refacciones_ddl;
EXECUTE refacciones_stmt;
DEALLOCATE PREPARE refacciones_stmt;

SET @refacciones_ddl = IF(
    EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'op_refacciones_calendario' AND COLUMN_NAME = 'proveedor_externo'),
    'SELECT 1',
    'ALTER TABLE op_refacciones_calendario ADD COLUMN proveedor_externo VARCHAR(180) NULL AFTER categoria'
);
PREPARE refacciones_stmt FROM @refacciones_ddl;
EXECUTE refacciones_stmt;
DEALLOCATE PREPARE refacciones_stmt;

SET @refacciones_ddl = IF(
    EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'op_refacciones_salidas' AND COLUMN_NAME = 'estado')
    AND NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'op_refacciones_salidas' AND COLUMN_NAME = 'estatus'),
    'ALTER TABLE op_refacciones_salidas CHANGE COLUMN estado estatus TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT ''0 pendiente de firma de elaboración, 1 pendiente de recepción, 2 recepción firmada''',
    'SELECT 1'
);
PREPARE refacciones_stmt FROM @refacciones_ddl;
EXECUTE refacciones_stmt;
DEALLOCATE PREPARE refacciones_stmt;

SET @refacciones_ddl = IF(
    EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'op_refacciones_salidas' AND INDEX_NAME = 'ix_op_refacciones_salidas_estado_creado')
    AND NOT EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'op_refacciones_salidas' AND INDEX_NAME = 'ix_op_refacciones_salidas_estatus_creado'),
    'ALTER TABLE op_refacciones_salidas DROP INDEX ix_op_refacciones_salidas_estado_creado, ADD KEY ix_op_refacciones_salidas_estatus_creado (estatus, creado_en)',
    'SELECT 1'
);
PREPARE refacciones_stmt FROM @refacciones_ddl;
EXECUTE refacciones_stmt;
DEALLOCATE PREPARE refacciones_stmt;

SET @refacciones_ddl = IF(
    EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'op_refacciones_salidas' AND COLUMN_NAME = 'id_proveedor_retorno'),
    'SELECT 1',
    'ALTER TABLE op_refacciones_salidas ADD COLUMN id_proveedor_retorno BIGINT UNSIGNED NULL AFTER fecha_retorno'
);
PREPARE refacciones_stmt FROM @refacciones_ddl;
EXECUTE refacciones_stmt;
DEALLOCATE PREPARE refacciones_stmt;

SET @refacciones_ddl = IF(
    EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'op_refacciones_salidas' AND COLUMN_NAME = 'proveedor_retorno'),
    'SELECT 1',
    'ALTER TABLE op_refacciones_salidas ADD COLUMN proveedor_retorno VARCHAR(180) NULL AFTER id_proveedor_retorno'
);
PREPARE refacciones_stmt FROM @refacciones_ddl;
EXECUTE refacciones_stmt;
DEALLOCATE PREPARE refacciones_stmt;

SET @refacciones_ddl = IF(
    EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'op_refacciones_salidas' AND COLUMN_NAME = 'referencia_retorno'),
    'SELECT 1',
    'ALTER TABLE op_refacciones_salidas ADD COLUMN referencia_retorno VARCHAR(120) NULL AFTER proveedor_retorno'
);
PREPARE refacciones_stmt FROM @refacciones_ddl;
EXECUTE refacciones_stmt;
DEALLOCATE PREPARE refacciones_stmt;

SET @refacciones_ddl = IF(
    EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'op_refacciones_salidas' AND COLUMN_NAME = 'notas_retorno'),
    'SELECT 1',
    'ALTER TABLE op_refacciones_salidas ADD COLUMN notas_retorno TEXT NULL AFTER referencia_retorno'
);
PREPARE refacciones_stmt FROM @refacciones_ddl;
EXECUTE refacciones_stmt;
DEALLOCATE PREPARE refacciones_stmt;

SET @refacciones_ddl = IF(
    EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'op_refacciones_salidas' AND COLUMN_NAME = 'stock_origen_aplicado'),
    'SELECT 1',
    'ALTER TABLE op_refacciones_salidas ADD COLUMN stock_origen_aplicado TINYINT(1) NOT NULL DEFAULT 0 COMMENT ''Indica si el stock ya fue descontado del origen al finalizar la salida'' AFTER fecha_firma_elabora'
);
PREPARE refacciones_stmt FROM @refacciones_ddl;
EXECUTE refacciones_stmt;
DEALLOCATE PREPARE refacciones_stmt;

-- Normaliza los tipos numéricos finales del módulo a DOUBLE.
ALTER TABLE op_refacciones_catalogo
    MODIFY COLUMN stock_minimo DOUBLE NOT NULL DEFAULT 0,
    MODIFY COLUMN costo_unitario DOUBLE NOT NULL DEFAULT 0;

ALTER TABLE op_refacciones_existencias
    MODIFY COLUMN existencia DOUBLE NOT NULL DEFAULT 0,
    MODIFY COLUMN costo_promedio DOUBLE NOT NULL DEFAULT 0 COMMENT 'Costo promedio ponderado de la refacción en esta ubicación',
    MODIFY COLUMN stock_minimo DOUBLE NOT NULL DEFAULT 0;

ALTER TABLE op_refacciones_movimientos
    MODIFY COLUMN fecha DATETIME NULL;

ALTER TABLE op_refacciones_movimientos_detalle
    MODIFY COLUMN cantidad DOUBLE NOT NULL,
    MODIFY COLUMN costo_unitario DOUBLE NOT NULL DEFAULT 0;

ALTER TABLE op_refacciones_prestamos_detalle
    MODIFY COLUMN cantidad DOUBLE NOT NULL,
    MODIFY COLUMN cantidad_devuelta DOUBLE NOT NULL DEFAULT 0;

ALTER TABLE op_refacciones_precios
    MODIFY COLUMN costo_anterior DOUBLE NULL,
    MODIFY COLUMN costo_nuevo DOUBLE NOT NULL;

ALTER TABLE op_pedido_materiales_refacciones
    MODIFY COLUMN cantidad DOUBLE NOT NULL;

ALTER TABLE op_refacciones_salidas
    MODIFY COLUMN stock_origen_aplicado TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Indica si el stock ya fue descontado del origen al finalizar la salida';

