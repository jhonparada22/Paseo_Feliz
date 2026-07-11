-- ═══════════════════════════════════════════════════════════════════
-- FASE 13 — EVIDENCIAS DEL PASEO (FOTOS)
-- Ejecutar en phpMyAdmin UNA sola vez, DESPUÉS de la fase 11.
--
-- El landing promete "Fotos del paseo" desde siempre, pero no existía
-- ninguna pieza que lo soportara. El paseador sube fotos desde el mapa
-- (recogida / durante el paseo / entrega) y el cliente las ve en su
-- dashboard en tiempo real.
-- ═══════════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS `evidencias_paseo` (
  `id_evidencia` INT(11) NOT NULL AUTO_INCREMENT,
  `id_paseo` INT(11) NOT NULL COMMENT 'instancia en paseos_programados',
  `id_pedido` INT(11) NOT NULL,
  `tipo` ENUM('recogida','paseo','entrega') NOT NULL DEFAULT 'paseo',
  `url` VARCHAR(255) NOT NULL COMMENT 'ruta relativa del archivo subido',
  `nota` VARCHAR(255) DEFAULT NULL,
  `creado_en` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_evidencia`),
  KEY `idx_evid_paseo` (`id_paseo`),
  KEY `idx_evid_pedido` (`id_pedido`),
  CONSTRAINT `fk_evid_paseo` FOREIGN KEY (`id_paseo`)
    REFERENCES `paseos_programados` (`id_paseo`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
