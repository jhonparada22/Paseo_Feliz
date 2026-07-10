-- ═══════════════════════════════════════════════════════════════════════
-- Integración del módulo de precios dinámicos + adiestramiento + hospedaje
-- canino (trabajo de tu compañero) en `b17_42313426_paseofeliztest`.
--
-- Verificado línea por línea contra:
--   - El código PHP ya fusionado (controller/registrar_pago.php,
--     controller/guardar_precios.php, model/procesar_compra_paseos.php,
--     model/procesar_compra_adiestramiento.php,
--     model/procesar_compra_hospedaje.php, model/precios_helper.php,
--     model/obtener_precios.php, model/obtener_pedidos_paseos.php).
--   - Un dump fresco de la estructura REAL de esta base (10 de julio),
--     para no adivinar nombres de columnas que ya existen o ya cambiaron.
--
-- NO se toca ninguna tabla del módulo de rutas (rutas, ruta_paradas,
-- ruta_clientes, calificaciones_paseo, gps_paseadores, historial_gps,
-- notificaciones, estados_ruta, estados_parada) — confirmado que ningún
-- archivo de ese módulo usa pagos/membresias/pedidos_paseo.id_plan.
--
-- NO se crea `rutas_asignacion_chat` — verificado que ningún archivo PHP
-- del proyecto la referencia (experimento del compañero sin conectar).
--
-- Ejecutar UNA SOLA VEZ, de arriba hacia abajo, vía phpMyAdmin.
-- ═══════════════════════════════════════════════════════════════════════

-- ───────────────────────────────────────────────────────────────────────
-- PASO 1 — Tablas completamente nuevas
-- ───────────────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `precios_servicios` (
  `tipo_membresia` enum('paseos','adiestramiento','hospedaje') NOT NULL,
  `precio_unidad` decimal(10,2) NOT NULL,
  `unidad_label` varchar(20) NOT NULL DEFAULT 'día' COMMENT 'día, sesión, noche...',
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`tipo_membresia`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Semilla obligatoria: controller/guardar_precios.php NUNCA escribe
-- unidad_label (solo actualiza precio_unidad al editar desde el admin),
-- así que sin esta fila inicial el wizard de compra fallaría con
-- "no tiene un precio configurado todavía" en los 3 servicios.
INSERT IGNORE INTO `precios_servicios` (`tipo_membresia`, `precio_unidad`, `unidad_label`) VALUES
('paseos', 18000.00, 'día'),
('adiestramiento', 22000.00, 'sesión'),
('hospedaje', 28000.00, 'noche');

CREATE TABLE IF NOT EXISTS `descuentos_servicios` (
  `id_descuento` int(11) NOT NULL AUTO_INCREMENT,
  `tipo_membresia` enum('paseos','adiestramiento','hospedaje') NOT NULL,
  `cantidad_minima` int(11) NOT NULL COMMENT 'A partir de esta cantidad aplica el % de descuento',
  `descuento_pct` tinyint(3) NOT NULL,
  PRIMARY KEY (`id_descuento`),
  KEY `idx_tipo` (`tipo_membresia`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
-- Sin semilla: se configura desde el botón "Precios" del admin cuando quieras.

CREATE TABLE IF NOT EXISTS `pedidos_adiestramiento` (
  `id_pedido` int(11) NOT NULL AUTO_INCREMENT,
  `id_usuario` int(11) NOT NULL,
  `id_mascota` int(11) NOT NULL,
  `cantidad_sesiones` int(11) NOT NULL,
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
  KEY `idx_pedido_adi_usuario` (`id_usuario`),
  KEY `idx_pedido_adi_estado` (`estado`),
  KEY `fk_pedido_adi_mascota` (`id_mascota`),
  CONSTRAINT `fk_pedido_adi_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_pedido_adi_mascota` FOREIGN KEY (`id_mascota`) REFERENCES `mascota_usuario` (`id_mascota`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `pedidos_hospedaje` (
  `id_pedido` int(11) NOT NULL AUTO_INCREMENT,
  `id_usuario` int(11) NOT NULL,
  `id_mascota` int(11) NOT NULL,
  `fecha_entrada` date NOT NULL,
  `fecha_salida` date NOT NULL,
  `cantidad_noches` int(11) NOT NULL,
  `comportamiento` varchar(30) DEFAULT NULL,
  `observaciones` text DEFAULT NULL,
  `direccion` varchar(255) NOT NULL COMMENT 'Dirección de recogida/entrega de la mascota',
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
  KEY `idx_pedido_hosp_usuario` (`id_usuario`),
  KEY `idx_pedido_hosp_estado` (`estado`),
  KEY `fk_pedido_hosp_mascota` (`id_mascota`),
  CONSTRAINT `fk_pedido_hosp_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_pedido_hosp_mascota` FOREIGN KEY (`id_mascota`) REFERENCES `mascota_usuario` (`id_mascota`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- ───────────────────────────────────────────────────────────────────────
-- PASO 2 — pedidos_paseo: cantidad_paseos reemplaza a id_plan
-- ───────────────────────────────────────────────────────────────────────

ALTER TABLE `pedidos_paseo`
  ADD COLUMN `cantidad_paseos` int(11) NOT NULL DEFAULT 0 AFTER `id_mascota`;

-- Backfill: los pedidos existentes solo tenían id_plan; se rellena
-- cantidad_paseos con el paseos_mes de su plan para no perder el dato.
UPDATE `pedidos_paseo` p
JOIN `planes_paseos` pl ON pl.id_plan = p.id_plan
SET p.cantidad_paseos = pl.paseos_mes
WHERE p.cantidad_paseos = 0;

-- id_plan ya no lo llena el código nuevo — queda NULL en pedidos nuevos
-- (ver model/procesar_compra_paseos.php, comentario en la línea del INSERT).
ALTER TABLE `pedidos_paseo`
  MODIFY `id_plan` int(11) DEFAULT NULL;


-- ───────────────────────────────────────────────────────────────────────
-- PASO 3 — membresias: por mascota, no solo por usuario
-- ───────────────────────────────────────────────────────────────────────

ALTER TABLE `membresias`
  ADD COLUMN `id_mascota` int(11) DEFAULT NULL AFTER `id_usuario`;

-- El código (registrar_pago.php y los 3 procesar_compra_*.php) hace
-- upsert de membresías esperando 1 fila por (usuario, mascota), no solo
-- por usuario. Sin este cambio, un cliente con 2+ mascotas pisaría la
-- membresía de una con la de otra (justo el bug que ya viste con
-- rogelio/salchicha en el dashboard).
--
-- Orden importante: primero se CREA el índice nuevo y LUEGO se borra el
-- viejo (no al revés) porque `uq_usuario` sostiene hoy la FK
-- fk_memb_usuario (id_usuario -> usuarios.id); si se borra primero, MySQL
-- rechaza el DROP con el error 1553 "needed in a foreign key constraint".
-- El índice compuesto (id_usuario, id_mascota) también cubre esa FK por
-- el prefijo izquierdo, así que al momento del DROP ya hay reemplazo.
ALTER TABLE `membresias` ADD UNIQUE KEY `uq_usuario_mascota` (`id_usuario`, `id_mascota`);
ALTER TABLE `membresias` DROP INDEX `uq_usuario`;

ALTER TABLE `membresias`
  ADD KEY `fk_membresia_mascota` (`id_mascota`),
  ADD CONSTRAINT `fk_membresia_mascota` FOREIGN KEY (`id_mascota`) REFERENCES `mascota_usuario` (`id_mascota`) ON DELETE SET NULL ON UPDATE CASCADE;

-- Nota: las membresías existentes (de pruebas) quedan con id_mascota=NULL
-- porque el esquema viejo nunca capturó a qué mascota pertenecían — no
-- hay forma confiable de inferirlo retroactivamente. Coincide con cómo
-- quedaron también en la base de tu compañero.


-- ───────────────────────────────────────────────────────────────────────
-- PASO 4 — pagos: vincular a mascota/adiestramiento/hospedaje +
-- renombrar estado -> estado_pago
-- ───────────────────────────────────────────────────────────────────────
-- OJO: las columnas de facturación (fact_pais, fact_ciudad, fact_direccion,
-- etc.) YA EXISTEN en esta base (confirmado en el dump del 10 de julio)
-- — NO se tocan ni se duplican aquí.

ALTER TABLE `pagos`
  ADD COLUMN `id_mascota` int(11) DEFAULT NULL AFTER `id_usuario`,
  ADD COLUMN `id_pedido_adiestramiento` int(11) DEFAULT NULL AFTER `id_pedido`,
  ADD COLUMN `id_pedido_hospedaje` int(11) DEFAULT NULL AFTER `id_pedido_adiestramiento`;

-- Rename: todo el código ya fusionado (los 3 procesar_compra_*.php y
-- registrar_pago.php) escribe/lee `estado_pago`, no `estado`. Se conserva
-- el DEFAULT 'pendiente' que ya tenía en esta base (no el 'aprobado' que
-- usa tu compañero en la suya) para no cambiar más de lo necesario.
ALTER TABLE `pagos`
  CHANGE COLUMN `estado` `estado_pago` enum('aprobado','rechazado','pendiente') NOT NULL DEFAULT 'pendiente';

ALTER TABLE `pagos`
  ADD KEY `fk_pagos_mascota` (`id_mascota`),
  ADD KEY `fk_pago_pedido_adi` (`id_pedido_adiestramiento`),
  ADD KEY `fk_pago_pedido_hosp` (`id_pedido_hospedaje`),
  ADD CONSTRAINT `fk_pagos_mascota` FOREIGN KEY (`id_mascota`) REFERENCES `mascota_usuario` (`id_mascota`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_pago_pedido_adi` FOREIGN KEY (`id_pedido_adiestramiento`) REFERENCES `pedidos_adiestramiento` (`id_pedido`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_pago_pedido_hosp` FOREIGN KEY (`id_pedido_hospedaje`) REFERENCES `pedidos_hospedaje` (`id_pedido`) ON DELETE SET NULL ON UPDATE CASCADE;


-- ───────────────────────────────────────────────────────────────────────
-- Verificación final
-- ───────────────────────────────────────────────────────────────────────
DESCRIBE precios_servicios;
DESCRIBE descuentos_servicios;
DESCRIBE pedidos_adiestramiento;
DESCRIBE pedidos_hospedaje;
DESCRIBE pedidos_paseo;
DESCRIBE membresias;
DESCRIBE pagos;
SELECT * FROM precios_servicios;
