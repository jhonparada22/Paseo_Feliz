-- ═══════════════════════════════════════════════════════════════════════
-- Paseo Feliz — Cronograma semanal de paseadores
--
-- El admin asigna pedidos pagados (pedidos_paseo 'listo_para_asignar')
-- a cada paseador por día de la semana. El dashboard del paseador
-- (index_paseador.php) lee este cronograma y "Empezar paseos" genera
-- la ruta del día automáticamente.
--
-- Ejecutar este bloque completo en la pestaña SQL de phpMyAdmin de
-- b17_42313426_paseofeliztest (requiere que ya exista pedidos_paseo,
-- del archivo modulo_pedidos_paseos.sql).
-- ═══════════════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS `cronograma_paseos` (
  `id_cronograma` int(11) NOT NULL AUTO_INCREMENT,
  `id_paseador` int(11) NOT NULL,
  `id_pedido` int(11) NOT NULL,
  `dia_semana` tinyint(1) NOT NULL COMMENT '1=lunes ... 7=domingo',
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_cronograma`),
  UNIQUE KEY `uq_pedido_dia` (`id_pedido`,`dia_semana`),
  KEY `idx_crono_paseador_dia` (`id_paseador`,`dia_semana`),
  CONSTRAINT `fk_crono_paseador` FOREIGN KEY (`id_paseador`) REFERENCES `paseadores` (`id_paseador`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_crono_pedido` FOREIGN KEY (`id_pedido`) REFERENCES `pedidos_paseo` (`id_pedido`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
