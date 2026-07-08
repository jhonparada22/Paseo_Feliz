-- ═══════════════════════════════════════════════════════════════════════
-- Calificación por estrellas del paseo (cliente -> paseador)
-- Ejecutar UNA SOLA VEZ vía phpMyAdmin sobre `b17_42313426_paseofeliztest`.
--
-- La puntuación de paseadores.puntuacion deja de editarse a mano desde el
-- admin y pasa a ser el promedio automático de estas calificaciones
-- (recalculado en model/calificar_paseo.php cada vez que un cliente
-- califica un paseo entregado).
-- ═══════════════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS `calificaciones_paseo` (
  `id_calificacion` int(11) NOT NULL AUTO_INCREMENT,
  `id_pedido` int(11) NOT NULL,
  `id_ruta` int(11) NOT NULL,
  `id_paseador` int(11) NOT NULL,
  `id_usuario_cliente` int(11) NOT NULL,
  `estrellas` tinyint(1) NOT NULL,
  `comentario` varchar(255) DEFAULT NULL,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_calificacion`),
  UNIQUE KEY `uq_calificacion_pedido_ruta` (`id_pedido`, `id_ruta`),
  KEY `idx_calif_paseador` (`id_paseador`),
  KEY `idx_calif_cliente` (`id_usuario_cliente`),
  CONSTRAINT `fk_calif_pedido` FOREIGN KEY (`id_pedido`) REFERENCES `pedidos_paseo` (`id_pedido`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_calif_ruta` FOREIGN KEY (`id_ruta`) REFERENCES `rutas` (`id_ruta`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_calif_paseador` FOREIGN KEY (`id_paseador`) REFERENCES `paseadores` (`id_paseador`) ON DELETE CASCADE ON UPDATE RESTRICT,
  CONSTRAINT `fk_calif_cliente` FOREIGN KEY (`id_usuario_cliente`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Verificación
DESCRIBE calificaciones_paseo;
