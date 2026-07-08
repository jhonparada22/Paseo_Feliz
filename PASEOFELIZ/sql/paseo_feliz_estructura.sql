-- ═══════════════════════════════════════════════════════════════════════
-- Paseo Feliz — Estructura de referencia (SOLO estructura, sin datos)
--
-- Extraído del export local (127.0.0.1) que compartiste, tomando
-- únicamente las tablas de la base `paseo_feliz` que usa el código real
-- del proyecto (model/*.php). Se ignoró por completo:
--   - `paseo_perros`  → esquema viejo, no lo usa ningún archivo actual.
--   - `phpmyadmin`    → tablas internas de phpMyAdmin, no son de la app.
--
-- Este archivo NO trae los datos (usuarios, mascotas, rutas de prueba)
-- porque tu base real en byethost ya tiene sus propios datos de
-- producción; solo sirve para comparar/crear la ESTRUCTURA.
--
-- Cómo usarlo:
--   1. Entra al phpMyAdmin de byethost, en tu base
--      `b17_41964877_registro_paseofeliz`.
--   2. Compara qué tablas de la lista de abajo YA existen.
--   3. Si falta alguna tabla completa, selecciona su bloque
--      "CREATE TABLE ... ALTER TABLE" y ejecútalo con la pestaña SQL.
--   4. Si una tabla ya existe pero le faltan columnas nuevas del módulo
--      de mapas (ej. `paseadores.puntuacion`, `paseadores.hora_inicio`),
--      dímelo y te paso solo el `ALTER TABLE ADD COLUMN` puntual.
--
-- Tablas que usa el módulo de Mapas (admin/paseador/cliente):
--   rutas, ruta_paradas, ruta_clientes, gps_paseadores, historial_gps,
--   notificaciones, estados_ruta, estados_parada, paseadores
-- ═══════════════════════════════════════════════════════════════════════

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";
SET NAMES utf8mb4;

-- ── admin ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `admin` (
  `id_admin` int(11) NOT NULL AUTO_INCREMENT,
  `id_usuario` int(11) NOT NULL,
  `correo` varchar(100) NOT NULL,
  PRIMARY KEY (`id_admin`),
  KEY `id_usuario` (`id_usuario`),
  CONSTRAINT `admin_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- ── usuarios ──────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `usuarios` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `sexo` varchar(20) NOT NULL,
  `password` varchar(255) NOT NULL,
  `fecha_registro` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- ── info_usuario ──────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `info_usuario` (
  `id_info` int(11) NOT NULL AUTO_INCREMENT,
  `id_usuario` int(11) NOT NULL,
  `biografia` text DEFAULT NULL,
  `cumpleanos` date DEFAULT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `direccion` varchar(255) DEFAULT NULL,
  `avatar_url` varchar(255) DEFAULT '../assets/default/avatar.png',
  `banner_url` varchar(255) DEFAULT '../assets/default/banner.png',
  `profesion` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id_info`),
  KEY `fk_usuario_idx` (`id_usuario`),
  CONSTRAINT `fk_info_usuarios` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- ── mascota_usuario ───────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `mascota_usuario` (
  `id_mascota` int(11) NOT NULL AUTO_INCREMENT,
  `id_usuario` int(11) NOT NULL,
  `nombre_mascota` varchar(100) NOT NULL,
  `avatar_mascota` varchar(255) DEFAULT '../assets/default/dog.png',
  `biografia_canina` text DEFAULT NULL,
  `enfermedades_discapacidades` text DEFAULT NULL,
  PRIMARY KEY (`id_mascota`),
  KEY `fk_mascota_idx` (`id_usuario`),
  CONSTRAINT `fk_mascota_usuarios` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- ── paseadores ────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `paseadores` (
  `id_paseador` int(11) NOT NULL AUTO_INCREMENT,
  `id_usuario` int(11) NOT NULL,
  `correo` varchar(100) NOT NULL,
  `puntuacion` decimal(3,1) NOT NULL DEFAULT 0.0 COMMENT 'Ingresada manualmente por admin',
  `hora_inicio` time DEFAULT NULL COMMENT 'Inicio del horario de trabajo',
  `hora_fin` time DEFAULT NULL COMMENT 'Fin del horario de trabajo',
  `zona_trabajo` varchar(255) DEFAULT NULL COMMENT 'Zona/área de trabajo',
  `paseos_mes` int(11) NOT NULL DEFAULT 0 COMMENT 'Se reinicia cada 30 días',
  `paseos_totales` int(11) NOT NULL DEFAULT 0 COMMENT 'Acumulado histórico',
  `fecha_reset_mes` date DEFAULT NULL COMMENT 'Fecha del último reinicio de paseos_mes',
  PRIMARY KEY (`id_paseador`),
  KEY `fk_paseadores_usuario` (`id_usuario`),
  CONSTRAINT `fk_paseadores_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- ── estados_ruta (tabla de referencia — SÍ necesita estos datos) ──────
CREATE TABLE IF NOT EXISTS `estados_ruta` (
  `id_estado` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(30) NOT NULL,
  PRIMARY KEY (`id_estado`),
  UNIQUE KEY `nombre` (`nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT IGNORE INTO `estados_ruta` (`id_estado`, `nombre`) VALUES
(1, 'pendiente'),
(2, 'en_curso'),
(3, 'pausada'),
(4, 'finalizada'),
(5, 'cancelada');

-- ── estados_parada (tabla de referencia — SÍ necesita estos datos) ────
CREATE TABLE IF NOT EXISTS `estados_parada` (
  `id_estado` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(30) NOT NULL,
  PRIMARY KEY (`id_estado`),
  UNIQUE KEY `nombre` (`nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT IGNORE INTO `estados_parada` (`id_estado`, `nombre`) VALUES
(1, 'pendiente'),
(2, 'llegada'),
(3, 'completada'),
(4, 'omitida');

-- ── rutas ─────────────────────────────────────────────────────────────
-- `activa_key` + su UNIQUE KEY garantizan que solo pueda existir 1 ruta
-- activa (id_estado 1/2/3) por (paseador, fecha_paseo) — ver
-- sql/migraciones/2026_07_fase1_consolidar_rutas.sql para el porqué.
CREATE TABLE IF NOT EXISTS `rutas` (
  `id_ruta` int(11) NOT NULL AUTO_INCREMENT,
  `id_admin_creador` int(11) NOT NULL,
  `id_paseador` int(11) NOT NULL,
  `id_estado` int(11) NOT NULL DEFAULT 1,
  `fecha_paseo` date NOT NULL,
  `hora_inicio` time NOT NULL,
  `distancia_estimada_km` decimal(6,2) DEFAULT 0.00,
  `duracion_estimada_min` int(11) DEFAULT 0,
  `fecha_inicio_real` datetime DEFAULT NULL,
  `fecha_fin_real` datetime DEFAULT NULL,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `activa_key` varchar(30) GENERATED ALWAYS AS (
    CASE WHEN `id_estado` in (1,2,3) THEN CONCAT(`id_paseador`,'-',`fecha_paseo`) ELSE NULL END
  ) STORED,
  PRIMARY KEY (`id_ruta`),
  UNIQUE KEY `uq_ruta_activa` (`activa_key`),
  KEY `idx_ruta_paseador` (`id_paseador`),
  KEY `idx_ruta_fecha` (`fecha_paseo`),
  KEY `fk_ruta_admin` (`id_admin_creador`),
  KEY `fk_ruta_estado` (`id_estado`),
  CONSTRAINT `fk_ruta_admin` FOREIGN KEY (`id_admin_creador`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_ruta_estado` FOREIGN KEY (`id_estado`) REFERENCES `estados_ruta` (`id_estado`) ON UPDATE RESTRICT,
  CONSTRAINT `fk_ruta_paseador` FOREIGN KEY (`id_paseador`) REFERENCES `paseadores` (`id_paseador`) ON DELETE CASCADE ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ── ruta_paradas ──────────────────────────────────────────────────────
-- `id_pedido` referencia el pedido de origen (cronograma o asignación
-- manual) y sirve como agrupador de paseo grupal. `hora_estimada` es la
-- ETA calculada al generar/reordenar la ruta. `hora_recogida`/`hora_entrega`/
-- `hora_cancelacion` son las confirmaciones manuales del paseador (distintas
-- de `hora_llegada`/`hora_completado`, que las sigue usando la detección
-- automática por GPS).
CREATE TABLE IF NOT EXISTS `ruta_paradas` (
  `id_parada` int(11) NOT NULL AUTO_INCREMENT,
  `id_ruta` int(11) NOT NULL,
  `orden` tinyint(2) NOT NULL,
  `etiqueta` varchar(2) NOT NULL,
  `hora_estimada` time DEFAULT NULL,
  `tipo` enum('recogida','paseo','entrega') NOT NULL DEFAULT 'paseo',
  `direccion` varchar(255) NOT NULL,
  `lat` decimal(10,7) NOT NULL,
  `lng` decimal(10,7) NOT NULL,
  `id_usuario_cliente` int(11) DEFAULT NULL,
  `id_mascota` int(11) DEFAULT NULL,
  `id_pedido` int(11) DEFAULT NULL,
  `id_estado` int(11) NOT NULL DEFAULT 1,
  `hora_llegada` datetime DEFAULT NULL,
  `hora_completado` datetime DEFAULT NULL,
  `hora_recogida` datetime DEFAULT NULL,
  `hora_entrega` datetime DEFAULT NULL,
  `hora_cancelacion` datetime DEFAULT NULL,
  `motivo_cancelacion` varchar(120) DEFAULT NULL,
  PRIMARY KEY (`id_parada`),
  KEY `idx_parada_ruta` (`id_ruta`),
  KEY `idx_parada_pedido` (`id_pedido`),
  KEY `fk_parada_cliente` (`id_usuario_cliente`),
  KEY `fk_parada_mascota` (`id_mascota`),
  KEY `fk_parada_estado` (`id_estado`),
  CONSTRAINT `fk_parada_cliente` FOREIGN KEY (`id_usuario_cliente`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_parada_estado` FOREIGN KEY (`id_estado`) REFERENCES `estados_parada` (`id_estado`) ON UPDATE CASCADE,
  CONSTRAINT `fk_parada_mascota` FOREIGN KEY (`id_mascota`) REFERENCES `mascota_usuario` (`id_mascota`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_parada_ruta` FOREIGN KEY (`id_ruta`) REFERENCES `rutas` (`id_ruta`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ── ruta_clientes ─────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `ruta_clientes` (
  `id_ruta_cliente` int(11) NOT NULL AUTO_INCREMENT,
  `id_ruta` int(11) NOT NULL,
  `id_usuario_cliente` int(11) NOT NULL,
  `id_mascota` int(11) NOT NULL,
  PRIMARY KEY (`id_ruta_cliente`),
  UNIQUE KEY `uq_ruta_mascota` (`id_ruta`,`id_mascota`),
  KEY `fk_rc_cliente` (`id_usuario_cliente`),
  KEY `fk_rc_mascota` (`id_mascota`),
  CONSTRAINT `fk_rc_cliente` FOREIGN KEY (`id_usuario_cliente`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_rc_mascota` FOREIGN KEY (`id_mascota`) REFERENCES `mascota_usuario` (`id_mascota`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_rc_ruta` FOREIGN KEY (`id_ruta`) REFERENCES `rutas` (`id_ruta`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ── gps_paseadores (posición actual, 1 fila por paseador) ─────────────
CREATE TABLE IF NOT EXISTS `gps_paseadores` (
  `id_paseador` int(11) NOT NULL,
  `lat` decimal(10,7) NOT NULL,
  `lng` decimal(10,7) NOT NULL,
  `velocidad` decimal(6,2) DEFAULT 0.00,
  `precision_m` decimal(6,2) DEFAULT 0.00,
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id_paseador`),
  CONSTRAINT `fk_gps_paseador` FOREIGN KEY (`id_paseador`) REFERENCES `paseadores` (`id_paseador`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ── historial_gps (histórico de posiciones) ───────────────────────────
CREATE TABLE IF NOT EXISTS `historial_gps` (
  `id_historial` int(11) NOT NULL AUTO_INCREMENT,
  `id_paseador` int(11) NOT NULL,
  `id_ruta` int(11) DEFAULT NULL,
  `lat` decimal(10,7) NOT NULL,
  `lng` decimal(10,7) NOT NULL,
  `velocidad` decimal(6,2) DEFAULT 0.00,
  `precision_m` decimal(6,2) DEFAULT 0.00,
  `fecha_hora` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_historial`),
  KEY `idx_hist_paseador_fecha` (`id_paseador`,`fecha_hora`),
  KEY `idx_hist_ruta` (`id_ruta`),
  CONSTRAINT `fk_hist_paseador` FOREIGN KEY (`id_paseador`) REFERENCES `paseadores` (`id_paseador`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_hist_ruta` FOREIGN KEY (`id_ruta`) REFERENCES `rutas` (`id_ruta`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ── notificaciones ────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `notificaciones` (
  `id_notificacion` int(11) NOT NULL AUTO_INCREMENT,
  `id_usuario_destino` int(11) NOT NULL,
  `id_ruta` int(11) DEFAULT NULL,
  `tipo` enum('proximidad_recogida','proximidad_entrega','llegada_parada','sistema') NOT NULL,
  `mensaje` varchar(255) NOT NULL,
  `leida` tinyint(1) NOT NULL DEFAULT 0,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_notificacion`),
  KEY `idx_notif_usuario` (`id_usuario_destino`,`leida`),
  KEY `fk_notif_ruta` (`id_ruta`),
  CONSTRAINT `fk_notif_ruta` FOREIGN KEY (`id_ruta`) REFERENCES `rutas` (`id_ruta`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_notif_usuario` FOREIGN KEY (`id_usuario_destino`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ── calificaciones_paseo (cliente califica 1-5 estrellas al paseador) ─
-- paseadores.puntuacion ya NO se edita a mano: se recalcula como el
-- promedio de estas calificaciones (ver model/calificar_paseo.php).
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

-- ── (resto de tablas del proyecto, no relacionadas con mapas) ─────────
-- codigos_verificacion, conversaciones, escribiendo, mensajes,
-- membresias, adopcion: no se incluyen aquí porque no las toca el
-- módulo de mapas: si tu byethost ya corre el resto de la app
-- (chat, adopción, registro), seguramente ya las tiene. Avísame si
-- necesitas también esas.
