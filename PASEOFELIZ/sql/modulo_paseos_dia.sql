-- ═══════════════════════════════════════════════════════════════════════
-- Paseo Feliz — Estado diario de paseos (Individual / Grupal)
--
-- Guarda, por cada día y pedido, el estado del paseo de esa mascota:
--   pendiente → recogido → en_paseo → entregado
--                        ↘ cancelado (con motivo obligatorio)
--
-- La usa el mapa del paseador (segmentos "Individual" y "Grupal"):
--   - model/obtener_paseos_hoy_paseador.php  (lee)
--   - model/marcar_paseo_dia.php             (escribe)
--
-- NOTA: los endpoints crean esta tabla automáticamente si no existe
-- (CREATE TABLE IF NOT EXISTS), así que este archivo es solo de
-- referencia/documentación. No hace falta ejecutarlo a mano.
-- ═══════════════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS `paseos_dia` (
  `id_paseo_dia` int(11) NOT NULL AUTO_INCREMENT,
  `fecha` date NOT NULL,
  `id_pedido` int(11) NOT NULL,
  `id_paseador` int(11) NOT NULL,
  `estado` enum('pendiente','recogido','en_paseo','entregado','cancelado') NOT NULL DEFAULT 'pendiente',
  `motivo_cancelacion` varchar(120) DEFAULT NULL,
  `hora_recogida` datetime DEFAULT NULL,
  `hora_cancelacion` datetime DEFAULT NULL,
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id_paseo_dia`),
  UNIQUE KEY `uq_dia_pedido` (`fecha`,`id_pedido`),
  KEY `idx_pd_paseador_fecha` (`id_paseador`,`fecha`),
  CONSTRAINT `fk_pd_pedido` FOREIGN KEY (`id_pedido`) REFERENCES `pedidos_paseo` (`id_pedido`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_pd_paseador` FOREIGN KEY (`id_paseador`) REFERENCES `paseadores` (`id_paseador`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
