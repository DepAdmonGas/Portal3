-- Portal3: registra una sola finalización del tutorial de bienvenida por usuario.
-- Revisar que id_usuario coincida con el tipo de tb_usuarios.id en el entorno destino.

CREATE TABLE IF NOT EXISTS `tb_tutorial_usuario` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `id_usuario` INT UNSIGNED NOT NULL,
    `completado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_tb_tutorial_usuario_usuario` (`id_usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
