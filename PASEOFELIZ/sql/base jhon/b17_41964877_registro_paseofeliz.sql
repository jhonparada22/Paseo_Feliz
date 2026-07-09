-- phpMyAdmin SQL Dump
-- version 4.9.0.1
-- https://www.phpmyadmin.net/
--
-- Servidor: sql300.byethost17.com
-- Tiempo de generación: 09-07-2026 a las 10:52:46
-- Versión del servidor: 11.4.12-MariaDB
-- Versión de PHP: 7.2.22

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `b17_41964877_registro_paseofeliz`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `admin`
--

CREATE TABLE `admin` (
  `id_admin` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `correo` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Volcado de datos para la tabla `admin`
--

INSERT INTO `admin` (`id_admin`, `id_usuario`, `correo`) VALUES
(6, 9, 'max1@gmail.com');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `adopcion`
--

CREATE TABLE `adopcion` (
  `id_adopcion` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `img_adop` varchar(255) NOT NULL,
  `edad` varchar(50) NOT NULL,
  `tamano` varchar(50) NOT NULL,
  `raza` varchar(100) NOT NULL,
  `color` varchar(100) NOT NULL,
  `descripcion` text NOT NULL,
  `requisitos` text NOT NULL,
  `fecha_reg` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `adopcion`
--

INSERT INTO `adopcion` (`id_adopcion`, `nombre`, `img_adop`, `edad`, `tamano`, `raza`, `color`, `descripcion`, `requisitos`, `fecha_reg`) VALUES
(9, 'Manuel', 'Manuel_1782302129.jpg', '1 Año', 'Pequeño', 'Caramelo', 'Marron Claro', 'te descrimina con la mirada', 'tener plata', '2026-06-24 11:55:29'),
(10, 'El Hueson', 'El_Hueson_1782302251.jpg', '2 Años', 'Pequeño', 'Bromas', 'Tricolor', 'es el hueson que pas quieres', 'aguantar bromas', '2026-06-24 11:57:31'),
(11, 'Licenciado Fernandez', 'Licenciado_Fernandez_1782302332.jpg', '5 años', 'Mediano', 'Licenciado', 'Marron Claro', 'esta licenciado', 'tener problemas legales', '2026-06-24 11:58:52'),
(12, 'Chill De Cojones', 'Chill_De_Cojones_1782302563.jpg', '7 Años', 'Grande', 'Chill De Cojones', 'Marron', 'Chill De Cojones', 'estar Chill De Cojones', '2026-06-24 12:02:43'),
(13, 'Coca Cola', 'Coca_Cola_1782302617.jpg', '3 años', 'Pequeño', 'Gaseosa', 'Negro', 'coca cola espuma', 'ser hater de pessi', '2026-06-24 12:03:38'),
(14, 'Programador Del Sena', 'Programador_Del_Sena_1782302697.jpg', '16 Años', 'Grande', 'Sena', 'Marron Claro', 'se graduo como programacion del sena', 'firmar un contrato de aprendizaje', '2026-06-24 12:04:57'),
(16, 'Princesa', 'Princesa_1782480955.png', '4 Años', 'Grande', 'Terror', 'Blanco y Negro', 'no muerde pero te mira el alma', 'ser testigo de jeova', '2026-06-26 13:35:55');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `codigos_verificacion`
--

CREATE TABLE `codigos_verificacion` (
  `id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `codigo` varchar(10) NOT NULL,
  `tipo` enum('registro','recuperacion') NOT NULL DEFAULT 'registro',
  `expiracion` datetime NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Volcado de datos para la tabla `codigos_verificacion`
--

INSERT INTO `codigos_verificacion` (`id`, `email`, `codigo`, `tipo`, `expiracion`) VALUES
(10, 'max1@gmail.com', '33739', 'recuperacion', '2026-06-23 19:29:59'),
(11, 'max3@gmail.com', '66652', 'registro', '2026-06-24 07:52:33'),
(9, 'militian22@hotmail.com', '33094', 'registro', '2026-06-22 10:18:12');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `conversaciones`
--

CREATE TABLE `conversaciones` (
  `id_conversacion` int(11) NOT NULL,
  `id_usuario_1` int(11) NOT NULL,
  `id_usuario_2` int(11) NOT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `fecha_creacion` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `conversaciones`
--

INSERT INTO `conversaciones` (`id_conversacion`, `id_usuario_1`, `id_usuario_2`, `activo`, `fecha_creacion`) VALUES
(17, 9, 11, 0, '2026-07-01 19:16:58'),
(18, 8, 11, 1, '2026-07-02 11:49:14');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cronograma_paseos`
--

CREATE TABLE `cronograma_paseos` (
  `id_cronograma` int(11) NOT NULL,
  `id_paseador` int(11) NOT NULL,
  `id_pedido` int(11) NOT NULL,
  `dia_semana` tinyint(1) NOT NULL COMMENT '1=lunes ... 7=domingo',
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `descuentos_servicios`
--

CREATE TABLE `descuentos_servicios` (
  `id_descuento` int(11) NOT NULL,
  `tipo_membresia` enum('paseos','adiestramiento','hospedaje') NOT NULL,
  `cantidad_minima` int(11) NOT NULL COMMENT 'A partir de esta cantidad aplica el % de descuento',
  `descuento_pct` tinyint(3) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `escribiendo`
--

CREATE TABLE `escribiendo` (
  `id_conversacion` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `estados_parada`
--

CREATE TABLE `estados_parada` (
  `id_estado` int(11) NOT NULL,
  `nombre` varchar(30) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `estados_parada`
--

INSERT INTO `estados_parada` (`id_estado`, `nombre`) VALUES
(3, 'completada'),
(2, 'llegada'),
(4, 'omitida'),
(1, 'pendiente');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `estados_ruta`
--

CREATE TABLE `estados_ruta` (
  `id_estado` int(11) NOT NULL,
  `nombre` varchar(30) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `estados_ruta`
--

INSERT INTO `estados_ruta` (`id_estado`, `nombre`) VALUES
(5, 'cancelada'),
(2, 'en_curso'),
(4, 'finalizada'),
(3, 'pausada'),
(1, 'pendiente');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `gps_paseadores`
--

CREATE TABLE `gps_paseadores` (
  `id_paseador` int(11) NOT NULL,
  `lat` decimal(10,7) NOT NULL,
  `lng` decimal(10,7) NOT NULL,
  `velocidad` decimal(6,2) DEFAULT 0.00,
  `precision_m` decimal(6,2) DEFAULT 0.00,
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `historial_gps`
--

CREATE TABLE `historial_gps` (
  `id_historial` int(11) NOT NULL,
  `id_paseador` int(11) NOT NULL,
  `id_ruta` int(11) DEFAULT NULL,
  `lat` decimal(10,7) NOT NULL,
  `lng` decimal(10,7) NOT NULL,
  `velocidad` decimal(6,2) DEFAULT 0.00,
  `precision_m` decimal(6,2) DEFAULT 0.00,
  `fecha_hora` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `info_usuario`
--

CREATE TABLE `info_usuario` (
  `id_info` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `biografia` text DEFAULT NULL,
  `cumpleanos` date DEFAULT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `direccion` varchar(255) DEFAULT NULL,
  `avatar_url` varchar(255) DEFAULT '../assets/default/avatar.png',
  `banner_url` varchar(255) DEFAULT '../assets/default/banner.png',
  `profesion` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Volcado de datos para la tabla `info_usuario`
--

INSERT INTO `info_usuario` (`id_info`, `id_usuario`, `biografia`, `cumpleanos`, `telefono`, `direccion`, `avatar_url`, `banner_url`, `profesion`) VALUES
(2, 8, 'no lo se, quien soy?', '2006-11-29', '3224199155', 'cucuta', '../assets/uploads/avatar_user_8_1780405268.jpg', '../assets/uploads/banner_user_8_1780405268.gif', 'desempleado'),
(3, 11, NULL, NULL, NULL, NULL, '../assets/uploads/avatar_user_11_1783044327.png', '../assets/uploads/banner_user_11_1782905948.png', NULL),
(6, 13, 'amo a mi moto', '1939-12-01', '3224199155', 'el salado', '../assets/uploads/avatar_user_13_1781615910.jpeg', '../assets/uploads/banner_user_13_1781615910.png', 'estudiante de analisis y desarrollo de software'),
(7, 9, '', NULL, NULL, '', 'assets/uploads/avatar_user_9_1783044238.png', 'assets/uploads/banner_user_9_1782905693.png', '');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `mascota_usuario`
--

CREATE TABLE `mascota_usuario` (
  `id_mascota` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `nombre_mascota` varchar(100) NOT NULL,
  `avatar_mascota` varchar(255) DEFAULT '../assets/default/dog.png',
  `biografia_canina` text DEFAULT NULL,
  `enfermedades_discapacidades` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Volcado de datos para la tabla `mascota_usuario`
--

INSERT INTO `mascota_usuario` (`id_mascota`, `id_usuario`, `nombre_mascota`, `avatar_mascota`, `biografia_canina`, `enfermedades_discapacidades`) VALUES
(1, 9, 'Max', 'assets/uploads/avatar_pet_9_1782477592.jpg', '', ''),
(4, 13, 'el brayan', '../assets/uploads/avatar_pet_13_1781615910.png', 'es un gato pero que se identifica como un perro', 'ninguna solo que los paseadores no se descuiden porque sino les roba el celular'),
(5, 13, 'gato aleman', '../assets/uploads/avatar_pet_13_1781616063.png', 'es un gatito que nose como se identifica , pero habla en aleman y cada vez que ve algo relacionado con alemania maulla en aleman y habla raro levantando la pata hacia arriba. me lo regalaron', ''),
(6, 8, 'maximo comun multiplo', '../assets/default/dog.png', 'es un perro, Gracias', 'respirar'),
(7, 9, 'Pikachu', 'assets/uploads/avatar_pet_9_1782905789.jpg', '', ''),
(8, 11, 'Goku', '../assets/uploads/avatar_pet_11_1783195305.png', '', ''),
(10, 11, 'Vegetta', '../assets/uploads/avatar_pet_11_1783195399.png', '', '');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `membresias`
--

CREATE TABLE `membresias` (
  `id_membresia` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `id_mascota` int(11) DEFAULT NULL,
  `paseos` tinyint(1) NOT NULL DEFAULT 0,
  `adiestramiento` tinyint(1) NOT NULL DEFAULT 0,
  `hospedaje` tinyint(1) NOT NULL DEFAULT 0,
  `id_pago_paseos` int(11) DEFAULT NULL,
  `id_pago_adiestramiento` int(11) DEFAULT NULL,
  `id_pago_hospedaje` int(11) DEFAULT NULL,
  `fecha_inicio_paseos` datetime DEFAULT NULL,
  `fecha_inicio_adiestramiento` datetime DEFAULT NULL,
  `fecha_inicio_hospedaje` datetime DEFAULT NULL,
  `fecha_fin_paseos` datetime GENERATED ALWAYS AS (`fecha_inicio_paseos` + interval 30 day) VIRTUAL,
  `fecha_fin_adiestramiento` datetime GENERATED ALWAYS AS (`fecha_inicio_adiestramiento` + interval 30 day) VIRTUAL,
  `fecha_fin_hospedaje` datetime GENERATED ALWAYS AS (`fecha_inicio_hospedaje` + interval 30 day) VIRTUAL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

--
-- Volcado de datos para la tabla `membresias`
--

INSERT INTO `membresias` (`id_membresia`, `id_usuario`, `id_mascota`, `paseos`, `adiestramiento`, `hospedaje`, `id_pago_paseos`, `id_pago_adiestramiento`, `id_pago_hospedaje`, `fecha_inicio_paseos`, `fecha_inicio_adiestramiento`, `fecha_inicio_hospedaje`) VALUES
(161, 11, NULL, 0, 0, 0, NULL, NULL, NULL, NULL, NULL, NULL),
(162, 9, NULL, 0, 0, 0, NULL, NULL, NULL, NULL, NULL, NULL),
(163, 9, NULL, 0, 0, 0, NULL, NULL, NULL, NULL, NULL, NULL),
(164, 11, NULL, 0, 0, 0, NULL, NULL, NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `mensajes`
--

CREATE TABLE `mensajes` (
  `id_mensaje` int(11) NOT NULL,
  `id_conversacion` int(11) NOT NULL,
  `id_emisor` int(11) NOT NULL,
  `mensaje` text DEFAULT NULL,
  `ruta_imagen` varchar(255) DEFAULT NULL,
  `fecha_envio` timestamp NULL DEFAULT current_timestamp(),
  `leido` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `mensajes`
--

INSERT INTO `mensajes` (`id_mensaje`, `id_conversacion`, `id_emisor`, `mensaje`, `ruta_imagen`, `fecha_envio`, `leido`) VALUES
(90, 17, 9, 'mae', NULL, '2026-07-01 19:17:03', 1),
(91, 17, 11, 'a', NULL, '2026-07-01 19:21:57', 1),
(92, 18, 11, 'asasasas', NULL, '2026-07-02 11:49:25', 1),
(93, 18, 8, 'asasa', NULL, '2026-07-02 11:49:50', 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `notificaciones`
--

CREATE TABLE `notificaciones` (
  `id_notificacion` int(11) NOT NULL,
  `id_usuario_destino` int(11) NOT NULL,
  `id_ruta` int(11) DEFAULT NULL,
  `tipo` enum('proximidad_recogida','proximidad_entrega','llegada_parada','sistema') NOT NULL,
  `mensaje` varchar(255) NOT NULL,
  `leida` tinyint(1) NOT NULL DEFAULT 0,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `pagos`
--

CREATE TABLE `pagos` (
  `id_pago` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `id_mascota` int(11) DEFAULT NULL,
  `id_pedido` int(11) DEFAULT NULL,
  `id_pedido_adiestramiento` int(11) DEFAULT NULL,
  `id_pedido_hospedaje` int(11) DEFAULT NULL,
  `tipo_membresia` enum('paseos','adiestramiento','hospedaje') NOT NULL,
  `monto` decimal(10,2) NOT NULL,
  `fecha_pago` datetime NOT NULL DEFAULT current_timestamp(),
  `metodo_pago` varchar(50) NOT NULL DEFAULT 'manual',
  `metodo` enum('tarjeta','pse','nequi') DEFAULT NULL,
  `estado_pago` enum('aprobado','rechazado','pendiente') NOT NULL DEFAULT 'aprobado',
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
  `fact_codigo_postal` varchar(12) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `paseadores`
--

CREATE TABLE `paseadores` (
  `id_paseador` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `correo` varchar(100) NOT NULL,
  `puntuacion` decimal(3,1) NOT NULL DEFAULT 0.0 COMMENT 'Ingresada manualmente por admin',
  `hora_inicio` time DEFAULT NULL COMMENT 'Inicio del horario de trabajo',
  `hora_fin` time DEFAULT NULL COMMENT 'Fin del horario de trabajo',
  `zona_trabajo` varchar(255) DEFAULT NULL COMMENT 'Zona/área de trabajo',
  `paseos_mes` int(11) NOT NULL DEFAULT 0 COMMENT 'Se reinicia cada 30 días',
  `paseos_totales` int(11) NOT NULL DEFAULT 0 COMMENT 'Acumulado histórico',
  `fecha_reset_mes` date DEFAULT NULL COMMENT 'Fecha del último reinicio de paseos_mes'
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Volcado de datos para la tabla `paseadores`
--

INSERT INTO `paseadores` (`id_paseador`, `id_usuario`, `correo`, `puntuacion`, `hora_inicio`, `hora_fin`, `zona_trabajo`, `paseos_mes`, `paseos_totales`, `fecha_reset_mes`) VALUES
(3, 12, 'juan@gmail.com', '4.0', NULL, NULL, NULL, 0, 0, '2026-06-25'),
(10, 13, 'm@gmail.com', '0.0', NULL, NULL, NULL, 0, 0, '2026-06-30');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `pedidos_adiestramiento`
--

CREATE TABLE `pedidos_adiestramiento` (
  `id_pedido` int(11) NOT NULL,
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
  `metodo_pago` enum('tarjeta','pse','nequi','manual') DEFAULT NULL,
  `estado` enum('pendiente_pago','pago_fallido','pagado','listo_para_asignar','en_validacion','cancelado') NOT NULL DEFAULT 'pendiente_pago',
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `pedidos_hospedaje`
--

CREATE TABLE `pedidos_hospedaje` (
  `id_pedido` int(11) NOT NULL,
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
  `metodo_pago` enum('tarjeta','pse','nequi','manual') DEFAULT NULL,
  `estado` enum('pendiente_pago','pago_fallido','pagado','listo_para_asignar','en_validacion','cancelado') NOT NULL DEFAULT 'pendiente_pago',
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `pedidos_paseo`
--

CREATE TABLE `pedidos_paseo` (
  `id_pedido` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `id_mascota` int(11) NOT NULL,
  `id_plan` int(11) DEFAULT NULL,
  `cantidad_paseos` int(11) NOT NULL DEFAULT 1,
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
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `planes_paseos`
--

CREATE TABLE `planes_paseos` (
  `id_plan` int(11) NOT NULL,
  `nombre` varchar(60) NOT NULL,
  `paseos_mes` int(11) NOT NULL,
  `precio_paseo` decimal(10,2) NOT NULL,
  `descuento_pct` tinyint(3) NOT NULL DEFAULT 0 COMMENT 'Porcentaje de descuento del plan',
  `activo` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `planes_paseos`
--

INSERT INTO `planes_paseos` (`id_plan`, `nombre`, `paseos_mes`, `precio_paseo`, `descuento_pct`, `activo`) VALUES
(1, '4 paseos al mes', 4, '18000.00', 0, 1),
(2, '8 paseos al mes', 8, '18000.00', 3, 1),
(3, '12 paseos al mes', 12, '18000.00', 5, 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `precios_servicios`
--

CREATE TABLE `precios_servicios` (
  `tipo_membresia` enum('paseos','adiestramiento','hospedaje') NOT NULL,
  `precio_unidad` decimal(10,2) NOT NULL,
  `unidad_label` varchar(20) NOT NULL DEFAULT 'día' COMMENT 'día, sesión, noche...',
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `precios_servicios`
--

INSERT INTO `precios_servicios` (`tipo_membresia`, `precio_unidad`, `unidad_label`, `fecha_actualizacion`) VALUES
('paseos', '18000.00', 'día', '2026-07-09 14:28:50'),
('adiestramiento', '22000.00', 'sesión', '2026-07-09 02:05:27'),
('hospedaje', '28000.00', 'noche', '2026-07-09 02:05:27');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `rutas`
--

CREATE TABLE `rutas` (
  `id_ruta` int(11) NOT NULL,
  `id_admin_creador` int(11) NOT NULL,
  `id_paseador` int(11) NOT NULL,
  `id_estado` int(11) NOT NULL DEFAULT 1,
  `fecha_paseo` date NOT NULL,
  `hora_inicio` time NOT NULL,
  `distancia_estimada_km` decimal(6,2) DEFAULT 0.00,
  `duracion_estimada_min` int(11) DEFAULT 0,
  `fecha_inicio_real` datetime DEFAULT NULL,
  `fecha_fin_real` datetime DEFAULT NULL,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `rutas_asignacion_chat`
--

CREATE TABLE `rutas_asignacion_chat` (
  `id_ruta` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `id_paseador` int(11) NOT NULL,
  `ruta_nombre` varchar(150) NOT NULL,
  `fecha_asignacion` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `ruta_clientes`
--

CREATE TABLE `ruta_clientes` (
  `id_ruta_cliente` int(11) NOT NULL,
  `id_ruta` int(11) NOT NULL,
  `id_usuario_cliente` int(11) NOT NULL,
  `id_mascota` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `ruta_paradas`
--

CREATE TABLE `ruta_paradas` (
  `id_parada` int(11) NOT NULL,
  `id_ruta` int(11) NOT NULL,
  `orden` tinyint(2) NOT NULL,
  `etiqueta` varchar(2) NOT NULL,
  `tipo` enum('recogida','paseo','entrega') NOT NULL DEFAULT 'paseo',
  `direccion` varchar(255) NOT NULL,
  `lat` decimal(10,7) NOT NULL,
  `lng` decimal(10,7) NOT NULL,
  `id_usuario_cliente` int(11) DEFAULT NULL,
  `id_mascota` int(11) DEFAULT NULL,
  `id_estado` int(11) NOT NULL DEFAULT 1,
  `hora_llegada` datetime DEFAULT NULL,
  `hora_completado` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL,
  `nombre` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `sexo` varchar(20) NOT NULL,
  `password` varchar(255) NOT NULL,
  `fecha_registro` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`id`, `nombre`, `email`, `sexo`, `password`, `fecha_registro`) VALUES
(2, 'Jonatán Limas', 'jonatanlimasg2020@gmail.com', 'masculino', '$2y$10$u4TPyVxL/jgteSFh7gmaW.tlhQ3HOyfdG9sHTLyIEEU7Lr4jPMYR2', '2026-05-24 20:46:11'),
(8, 'Maikol Holguin', 'maikolholguin74@gmail.com', 'masculino', '$2y$10$RQ7o3CGlQiPDgkWSvvl/Q.Aq2M1sAGrLr6KOLiCzOd.Qao0JIvqeS', '2026-05-25 11:49:46'),
(9, 'max1@gmail.com', 'max1@gmail.com', 'masculino', '$2y$10$MQ/UTGuNU37TwGESi9tzRuxbr8a.S.4.OdI1ryhHruQF30XcmJu2.', '2026-05-25 12:49:06'),
(10, 'Maikol Holguin', 'maikolholguin75@gmail.com', 'masculino', '$2y$10$kW1Rb/l5bRvMadOI5ceDxuJJfBv/cUAdRWqbw8KOY8kJhmsSQygM.', '2026-05-28 13:19:57'),
(11, 'max2@gmail.com', 'max2@gmail.com', 'masculino', '$2y$10$zrB0eEpm.tO1thkmhfpva.hropS9.ezlDeuMuvpZEFCeuzReGdcKe', '2026-06-01 11:26:39'),
(12, 'juan', 'juan@gmail.com', 'masculino', '$2y$10$HjKeiS/rG61CzVRqpFAYsevGIfmy6MaYbrQCYaqFm2QevjP0rPug6', '2026-06-03 11:53:45'),
(13, 'maikol wilfrido', 'm@gmail.com', 'masculino', '$2y$10$A7lpfCnqgm9JOFF4jLtWl.mEvTH8XIkOrCqYFN7g3h6l7jWGBywq6', '2026-06-03 12:03:34'),
(16, 'paseador001', 'paseador001@gmail.com', 'masculino', '$2y$10$wHqTRIoMR5HBpJWo6MSZquOLkNKvNhaZ/RUzdYZOND0qFYLkA.wdm', '2026-06-12 11:48:33'),
(17, 'paseador001', 'paseador123@gmail.com', 'masculino', '$2y$10$RsH6uFTaIUqwMUo3UNHQOOd7Tn2YuNo85MWyjcBtBBCyyNK6CSGpa', '2026-06-12 11:50:10'),
(20, 'maikolwilfredo', 'maikolwilfredo74@gmail.com', 'masculino', '$2y$10$VQ.4m7hmZEVGjARmdbyM7O6zIrlV2SQnxtTg4HMe/7K4pGVzBYmkG', '2026-06-15 21:13:22');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `admin`
--
ALTER TABLE `admin`
  ADD PRIMARY KEY (`id_admin`),
  ADD KEY `id_usuario` (`id_usuario`);

--
-- Indices de la tabla `adopcion`
--
ALTER TABLE `adopcion`
  ADD PRIMARY KEY (`id_adopcion`);

--
-- Indices de la tabla `codigos_verificacion`
--
ALTER TABLE `codigos_verificacion`
  ADD PRIMARY KEY (`id`),
  ADD KEY `email` (`email`);

--
-- Indices de la tabla `conversaciones`
--
ALTER TABLE `conversaciones`
  ADD PRIMARY KEY (`id_conversacion`),
  ADD UNIQUE KEY `intercambio_unico` (`id_usuario_1`,`id_usuario_2`),
  ADD KEY `id_usuario_2` (`id_usuario_2`);

--
-- Indices de la tabla `cronograma_paseos`
--
ALTER TABLE `cronograma_paseos`
  ADD PRIMARY KEY (`id_cronograma`),
  ADD UNIQUE KEY `uq_pedido_dia` (`id_pedido`,`dia_semana`),
  ADD KEY `idx_crono_paseador_dia` (`id_paseador`,`dia_semana`);

--
-- Indices de la tabla `descuentos_servicios`
--
ALTER TABLE `descuentos_servicios`
  ADD PRIMARY KEY (`id_descuento`),
  ADD KEY `idx_tipo` (`tipo_membresia`);

--
-- Indices de la tabla `escribiendo`
--
ALTER TABLE `escribiendo`
  ADD PRIMARY KEY (`id_conversacion`,`id_usuario`);

--
-- Indices de la tabla `estados_parada`
--
ALTER TABLE `estados_parada`
  ADD PRIMARY KEY (`id_estado`),
  ADD UNIQUE KEY `nombre` (`nombre`);

--
-- Indices de la tabla `estados_ruta`
--
ALTER TABLE `estados_ruta`
  ADD PRIMARY KEY (`id_estado`),
  ADD UNIQUE KEY `nombre` (`nombre`);

--
-- Indices de la tabla `gps_paseadores`
--
ALTER TABLE `gps_paseadores`
  ADD PRIMARY KEY (`id_paseador`);

--
-- Indices de la tabla `historial_gps`
--
ALTER TABLE `historial_gps`
  ADD PRIMARY KEY (`id_historial`),
  ADD KEY `idx_hist_paseador_fecha` (`id_paseador`,`fecha_hora`),
  ADD KEY `idx_hist_ruta` (`id_ruta`);

--
-- Indices de la tabla `info_usuario`
--
ALTER TABLE `info_usuario`
  ADD PRIMARY KEY (`id_info`),
  ADD KEY `fk_usuario_idx` (`id_usuario`);

--
-- Indices de la tabla `mascota_usuario`
--
ALTER TABLE `mascota_usuario`
  ADD PRIMARY KEY (`id_mascota`),
  ADD KEY `fk_mascota_idx` (`id_usuario`);

--
-- Indices de la tabla `membresias`
--
ALTER TABLE `membresias`
  ADD PRIMARY KEY (`id_membresia`),
  ADD UNIQUE KEY `uq_usuario_mascota` (`id_usuario`,`id_mascota`),
  ADD KEY `fk_membresia_pago_paseos` (`id_pago_paseos`),
  ADD KEY `fk_membresia_pago_adiestramiento` (`id_pago_adiestramiento`),
  ADD KEY `fk_membresia_pago_hospedaje` (`id_pago_hospedaje`),
  ADD KEY `fk_membresia_mascota` (`id_mascota`);

--
-- Indices de la tabla `mensajes`
--
ALTER TABLE `mensajes`
  ADD PRIMARY KEY (`id_mensaje`),
  ADD KEY `id_conversacion` (`id_conversacion`),
  ADD KEY `id_emisor` (`id_emisor`);

--
-- Indices de la tabla `notificaciones`
--
ALTER TABLE `notificaciones`
  ADD PRIMARY KEY (`id_notificacion`),
  ADD KEY `idx_notif_usuario` (`id_usuario_destino`,`leida`),
  ADD KEY `fk_notif_ruta` (`id_ruta`);

--
-- Indices de la tabla `pagos`
--
ALTER TABLE `pagos`
  ADD PRIMARY KEY (`id_pago`),
  ADD KEY `fk_pagos_usuario` (`id_usuario`),
  ADD KEY `idx_pagos_fecha` (`DESC`),
  ADD KEY `fk_pagos_mascota` (`id_mascota`),
  ADD KEY `idx_pago_pedido` (`id_pedido`),
  ADD KEY `fk_pago_pedido_adi` (`id_pedido_adiestramiento`),
  ADD KEY `fk_pago_pedido_hosp` (`id_pedido_hospedaje`);

--
-- Indices de la tabla `paseadores`
--
ALTER TABLE `paseadores`
  ADD PRIMARY KEY (`id_paseador`),
  ADD KEY `fk_paseadores_usuario` (`id_usuario`);

--
-- Indices de la tabla `pedidos_adiestramiento`
--
ALTER TABLE `pedidos_adiestramiento`
  ADD PRIMARY KEY (`id_pedido`),
  ADD KEY `idx_pedido_adi_usuario` (`id_usuario`),
  ADD KEY `idx_pedido_adi_estado` (`estado`),
  ADD KEY `fk_pedido_adi_mascota` (`id_mascota`);

--
-- Indices de la tabla `pedidos_hospedaje`
--
ALTER TABLE `pedidos_hospedaje`
  ADD PRIMARY KEY (`id_pedido`),
  ADD KEY `idx_pedido_hosp_usuario` (`id_usuario`),
  ADD KEY `idx_pedido_hosp_estado` (`estado`),
  ADD KEY `fk_pedido_hosp_mascota` (`id_mascota`);

--
-- Indices de la tabla `pedidos_paseo`
--
ALTER TABLE `pedidos_paseo`
  ADD PRIMARY KEY (`id_pedido`),
  ADD KEY `idx_pedido_usuario` (`id_usuario`),
  ADD KEY `idx_pedido_estado` (`estado`),
  ADD KEY `fk_pedido_mascota` (`id_mascota`),
  ADD KEY `fk_pedido_plan` (`id_plan`);

--
-- Indices de la tabla `planes_paseos`
--
ALTER TABLE `planes_paseos`
  ADD PRIMARY KEY (`id_plan`);

--
-- Indices de la tabla `precios_servicios`
--
ALTER TABLE `precios_servicios`
  ADD PRIMARY KEY (`tipo_membresia`);

--
-- Indices de la tabla `rutas`
--
ALTER TABLE `rutas`
  ADD PRIMARY KEY (`id_ruta`),
  ADD KEY `idx_ruta_paseador` (`id_paseador`),
  ADD KEY `idx_ruta_fecha` (`fecha_paseo`),
  ADD KEY `fk_ruta_admin` (`id_admin_creador`),
  ADD KEY `fk_ruta_estado` (`id_estado`);

--
-- Indices de la tabla `rutas_asignacion_chat`
--
ALTER TABLE `rutas_asignacion_chat`
  ADD PRIMARY KEY (`id_ruta`),
  ADD KEY `id_usuario` (`id_usuario`),
  ADD KEY `id_paseador` (`id_paseador`);

--
-- Indices de la tabla `ruta_clientes`
--
ALTER TABLE `ruta_clientes`
  ADD PRIMARY KEY (`id_ruta_cliente`),
  ADD UNIQUE KEY `uq_ruta_mascota` (`id_ruta`,`id_mascota`),
  ADD KEY `fk_rc_cliente` (`id_usuario_cliente`),
  ADD KEY `fk_rc_mascota` (`id_mascota`);

--
-- Indices de la tabla `ruta_paradas`
--
ALTER TABLE `ruta_paradas`
  ADD PRIMARY KEY (`id_parada`),
  ADD KEY `idx_parada_ruta` (`id_ruta`),
  ADD KEY `fk_parada_cliente` (`id_usuario_cliente`),
  ADD KEY `fk_parada_mascota` (`id_mascota`),
  ADD KEY `fk_parada_estado` (`id_estado`);

--
-- Indices de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `admin`
--
ALTER TABLE `admin`
  MODIFY `id_admin` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de la tabla `adopcion`
--
ALTER TABLE `adopcion`
  MODIFY `id_adopcion` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT de la tabla `codigos_verificacion`
--
ALTER TABLE `codigos_verificacion`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT de la tabla `conversaciones`
--
ALTER TABLE `conversaciones`
  MODIFY `id_conversacion` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT de la tabla `cronograma_paseos`
--
ALTER TABLE `cronograma_paseos`
  MODIFY `id_cronograma` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `descuentos_servicios`
--
ALTER TABLE `descuentos_servicios`
  MODIFY `id_descuento` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `estados_parada`
--
ALTER TABLE `estados_parada`
  MODIFY `id_estado` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `estados_ruta`
--
ALTER TABLE `estados_ruta`
  MODIFY `id_estado` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `historial_gps`
--
ALTER TABLE `historial_gps`
  MODIFY `id_historial` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `info_usuario`
--
ALTER TABLE `info_usuario`
  MODIFY `id_info` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT de la tabla `mascota_usuario`
--
ALTER TABLE `mascota_usuario`
  MODIFY `id_mascota` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT de la tabla `membresias`
--
ALTER TABLE `membresias`
  MODIFY `id_membresia` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=165;

--
-- AUTO_INCREMENT de la tabla `mensajes`
--
ALTER TABLE `mensajes`
  MODIFY `id_mensaje` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=94;

--
-- AUTO_INCREMENT de la tabla `notificaciones`
--
ALTER TABLE `notificaciones`
  MODIFY `id_notificacion` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `pagos`
--
ALTER TABLE `pagos`
  MODIFY `id_pago` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT de la tabla `paseadores`
--
ALTER TABLE `paseadores`
  MODIFY `id_paseador` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT de la tabla `pedidos_adiestramiento`
--
ALTER TABLE `pedidos_adiestramiento`
  MODIFY `id_pedido` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `pedidos_hospedaje`
--
ALTER TABLE `pedidos_hospedaje`
  MODIFY `id_pedido` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `pedidos_paseo`
--
ALTER TABLE `pedidos_paseo`
  MODIFY `id_pedido` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `planes_paseos`
--
ALTER TABLE `planes_paseos`
  MODIFY `id_plan` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `rutas`
--
ALTER TABLE `rutas`
  MODIFY `id_ruta` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `rutas_asignacion_chat`
--
ALTER TABLE `rutas_asignacion_chat`
  MODIFY `id_ruta` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `ruta_clientes`
--
ALTER TABLE `ruta_clientes`
  MODIFY `id_ruta_cliente` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `ruta_paradas`
--
ALTER TABLE `ruta_paradas`
  MODIFY `id_parada` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `admin`
--
ALTER TABLE `admin`
  ADD CONSTRAINT `admin_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `conversaciones`
--
ALTER TABLE `conversaciones`
  ADD CONSTRAINT `conversaciones_ibfk_1` FOREIGN KEY (`id_usuario_1`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `conversaciones_ibfk_2` FOREIGN KEY (`id_usuario_2`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `cronograma_paseos`
--
ALTER TABLE `cronograma_paseos`
  ADD CONSTRAINT `fk_crono_paseador` FOREIGN KEY (`id_paseador`) REFERENCES `paseadores` (`id_paseador`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_crono_pedido` FOREIGN KEY (`id_pedido`) REFERENCES `pedidos_paseo` (`id_pedido`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `gps_paseadores`
--
ALTER TABLE `gps_paseadores`
  ADD CONSTRAINT `fk_gps_paseador` FOREIGN KEY (`id_paseador`) REFERENCES `paseadores` (`id_paseador`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `historial_gps`
--
ALTER TABLE `historial_gps`
  ADD CONSTRAINT `fk_hist_paseador` FOREIGN KEY (`id_paseador`) REFERENCES `paseadores` (`id_paseador`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_hist_ruta` FOREIGN KEY (`id_ruta`) REFERENCES `rutas` (`id_ruta`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Filtros para la tabla `info_usuario`
--
ALTER TABLE `info_usuario`
  ADD CONSTRAINT `fk_info_usuarios` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `mascota_usuario`
--
ALTER TABLE `mascota_usuario`
  ADD CONSTRAINT `fk_mascota_usuarios` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `membresias`
--
ALTER TABLE `membresias`
  ADD CONSTRAINT `fk_memb_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_membresia_mascota` FOREIGN KEY (`id_mascota`) REFERENCES `mascota_usuario` (`id_mascota`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_membresia_pago_adiestramiento` FOREIGN KEY (`id_pago_adiestramiento`) REFERENCES `pagos` (`id_pago`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_membresia_pago_hospedaje` FOREIGN KEY (`id_pago_hospedaje`) REFERENCES `pagos` (`id_pago`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_membresia_pago_paseos` FOREIGN KEY (`id_pago_paseos`) REFERENCES `pagos` (`id_pago`) ON DELETE SET NULL;

--
-- Filtros para la tabla `mensajes`
--
ALTER TABLE `mensajes`
  ADD CONSTRAINT `mensajes_ibfk_1` FOREIGN KEY (`id_conversacion`) REFERENCES `conversaciones` (`id_conversacion`) ON DELETE CASCADE,
  ADD CONSTRAINT `mensajes_ibfk_2` FOREIGN KEY (`id_emisor`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `notificaciones`
--
ALTER TABLE `notificaciones`
  ADD CONSTRAINT `fk_notif_ruta` FOREIGN KEY (`id_ruta`) REFERENCES `rutas` (`id_ruta`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_notif_usuario` FOREIGN KEY (`id_usuario_destino`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `pagos`
--
ALTER TABLE `pagos`
  ADD CONSTRAINT `fk_pago_pedido` FOREIGN KEY (`id_pedido`) REFERENCES `pedidos_paseo` (`id_pedido`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_pago_pedido_adi` FOREIGN KEY (`id_pedido_adiestramiento`) REFERENCES `pedidos_adiestramiento` (`id_pedido`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_pago_pedido_hosp` FOREIGN KEY (`id_pedido_hospedaje`) REFERENCES `pedidos_hospedaje` (`id_pedido`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_pagos_mascota` FOREIGN KEY (`id_mascota`) REFERENCES `mascota_usuario` (`id_mascota`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_pagos_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `paseadores`
--
ALTER TABLE `paseadores`
  ADD CONSTRAINT `fk_paseadores_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `pedidos_adiestramiento`
--
ALTER TABLE `pedidos_adiestramiento`
  ADD CONSTRAINT `fk_pedido_adi_mascota` FOREIGN KEY (`id_mascota`) REFERENCES `mascota_usuario` (`id_mascota`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_pedido_adi_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `pedidos_hospedaje`
--
ALTER TABLE `pedidos_hospedaje`
  ADD CONSTRAINT `fk_pedido_hosp_mascota` FOREIGN KEY (`id_mascota`) REFERENCES `mascota_usuario` (`id_mascota`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_pedido_hosp_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `pedidos_paseo`
--
ALTER TABLE `pedidos_paseo`
  ADD CONSTRAINT `fk_pedido_mascota` FOREIGN KEY (`id_mascota`) REFERENCES `mascota_usuario` (`id_mascota`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_pedido_plan` FOREIGN KEY (`id_plan`) REFERENCES `planes_paseos` (`id_plan`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_pedido_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `rutas`
--
ALTER TABLE `rutas`
  ADD CONSTRAINT `fk_ruta_admin` FOREIGN KEY (`id_admin_creador`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ruta_estado` FOREIGN KEY (`id_estado`) REFERENCES `estados_ruta` (`id_estado`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ruta_paseador` FOREIGN KEY (`id_paseador`) REFERENCES `paseadores` (`id_paseador`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `rutas_asignacion_chat`
--
ALTER TABLE `rutas_asignacion_chat`
  ADD CONSTRAINT `fk_rutas_paseador` FOREIGN KEY (`id_paseador`) REFERENCES `paseadores` (`id_paseador`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_rutas_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `ruta_clientes`
--
ALTER TABLE `ruta_clientes`
  ADD CONSTRAINT `fk_rc_cliente` FOREIGN KEY (`id_usuario_cliente`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_rc_mascota` FOREIGN KEY (`id_mascota`) REFERENCES `mascota_usuario` (`id_mascota`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_rc_ruta` FOREIGN KEY (`id_ruta`) REFERENCES `rutas` (`id_ruta`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `ruta_paradas`
--
ALTER TABLE `ruta_paradas`
  ADD CONSTRAINT `fk_parada_cliente` FOREIGN KEY (`id_usuario_cliente`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_parada_estado` FOREIGN KEY (`id_estado`) REFERENCES `estados_parada` (`id_estado`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_parada_mascota` FOREIGN KEY (`id_mascota`) REFERENCES `mascota_usuario` (`id_mascota`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_parada_ruta` FOREIGN KEY (`id_ruta`) REFERENCES `rutas` (`id_ruta`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
