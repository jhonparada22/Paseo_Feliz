-- ═══════════════════════════════════════════════════════════════════════
-- Paseo Feliz — Módulo de pedidos y pagos de mensualidad de Paseos
--
-- Crea las 3 tablas del flujo de compra (wizard de 4 pasos del cliente):
--   1. planes_paseos  → catálogo de planes/precios (editable por admin)
--   2. pedidos_paseo  → el pedido de mensualidad con mascota + ubicación
--   3. pagos          → el registro del pago asociado al pedido
--
-- Ejecutar POR BLOQUES en la pestaña SQL de phpMyAdmin de
-- b17_42313426_paseofeliztest (igual que completar_estructura.sql),
-- en el orden en que aparecen.
-- ═══════════════════════════════════════════════════════════════════════


-- ── BLOQUE 1: catálogo de planes ──────────────────────────────────────

CREATE TABLE IF NOT EXISTS `planes_paseos` (
  `id_plan` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(60) NOT NULL,
  `paseos_mes` int(11) NOT NULL,
  `precio_paseo` decimal(10,2) NOT NULL,
  `descuento_pct` tinyint(3) NOT NULL DEFAULT 0 COMMENT 'Porcentaje de descuento del plan',
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id_plan`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `planes_paseos` (`nombre`, `paseos_mes`, `precio_paseo`, `descuento_pct`, `activo`) VALUES
('4 paseos al mes',  4,  18000.00, 0, 1),
('8 paseos al mes',  8,  18000.00, 3, 1),
('12 paseos al mes', 12, 18000.00, 5, 1);


-- ── BLOQUE 2: pedidos de mensualidad ──────────────────────────────────

CREATE TABLE IF NOT EXISTS `pedidos_paseo` (
  `id_pedido` int(11) NOT NULL AUTO_INCREMENT,
  `id_usuario` int(11) NOT NULL,
  `id_mascota` int(11) NOT NULL,
  `id_plan` int(11) NOT NULL,
  `modalidad` enum('individual','grupal') NOT NULL DEFAULT 'grupal',
  `duracion_min` int(11) NOT NULL DEFAULT 60,
  `dias_preferidos` varchar(60) DEFAULT NULL COMMENT 'CSV: lun,mie,vie',
  `franja_horaria` varchar(40) DEFAULT NULL,
  `fecha_inicio` date NOT NULL,
  `comportamiento` varchar(30) DEFAULT NULL COMMENT 'sociable|timido|reactivo|no_sociable',
  `observaciones` text DEFAULT NULL,
  `direccion` varchar(255) NOT NULL,
  `barrio` varchar(100) DEFAULT NULL,
  `referencia` varchar(255) DEFAULT NULL,
  `instrucciones` varchar(255) DEFAULT NULL,
  `lat` decimal(10,7) NOT NULL,
  `lng` decimal(10,7) NOT NULL,
  `ubicacion_validada` tinyint(1) NOT NULL DEFAULT 0,
  `subtotal` decimal(10,2) NOT NULL DEFAULT 0.00,
  `descuento` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total` decimal(10,2) NOT NULL DEFAULT 0.00,
  `metodo_pago` enum('tarjeta','pse','nequi') DEFAULT NULL,
  `estado` enum('pendiente_pago','pago_fallido','pagado','listo_para_asignar','en_validacion','cancelado') NOT NULL DEFAULT 'pendiente_pago',
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id_pedido`),
  KEY `idx_pedido_usuario` (`id_usuario`),
  KEY `idx_pedido_estado` (`estado`),
  KEY `fk_pedido_mascota` (`id_mascota`),
  KEY `fk_pedido_plan` (`id_plan`),
  CONSTRAINT `fk_pedido_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_pedido_mascota` FOREIGN KEY (`id_mascota`) REFERENCES `mascota_usuario` (`id_mascota`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_pedido_plan` FOREIGN KEY (`id_plan`) REFERENCES `planes_paseos` (`id_plan`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- ── BLOQUE 3: pagos ───────────────────────────────────────────────────
-- Nunca se guarda el número completo de la tarjeta ni el CVV:
-- solo titular y últimos 4 dígitos.

CREATE TABLE IF NOT EXISTS `pagos` (
  `id_pago` int(11) NOT NULL AUTO_INCREMENT,
  `id_pedido` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `metodo` enum('tarjeta','pse','nequi') NOT NULL,
  `monto` decimal(10,2) NOT NULL,
  `estado` enum('aprobado','rechazado','pendiente') NOT NULL DEFAULT 'pendiente',
  `referencia` varchar(40) DEFAULT NULL COMMENT 'Referencia de transacción (simulada mientras no haya pasarela)',
  `titular` varchar(100) DEFAULT NULL,
  `ultimos4` char(4) DEFAULT NULL,
  `cuotas` tinyint(3) DEFAULT NULL,
  `banco` varchar(60) DEFAULT NULL COMMENT 'Solo PSE',
  `tipo_persona` enum('natural','juridica') DEFAULT NULL COMMENT 'Solo PSE',
  `documento` varchar(20) DEFAULT NULL COMMENT 'Solo PSE',
  `email_confirmacion` varchar(100) DEFAULT NULL COMMENT 'Solo PSE',
  `fact_usar_perfil` tinyint(1) NOT NULL DEFAULT 1,
  `fact_pais` varchar(60) DEFAULT NULL,
  `fact_ciudad` varchar(60) DEFAULT NULL,
  `fact_departamento` varchar(60) DEFAULT NULL,
  `fact_direccion` varchar(255) DEFAULT NULL,
  `fact_complemento` varchar(100) DEFAULT NULL,
  `fact_codigo_postal` varchar(12) DEFAULT NULL,
  `fecha_pago` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_pago`),
  KEY `idx_pago_pedido` (`id_pedido`),
  KEY `idx_pago_usuario` (`id_usuario`),
  CONSTRAINT `fk_pago_pedido` FOREIGN KEY (`id_pedido`) REFERENCES `pedidos_paseo` (`id_pedido`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_pago_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
