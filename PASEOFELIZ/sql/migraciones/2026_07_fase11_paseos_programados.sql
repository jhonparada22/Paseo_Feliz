-- ═══════════════════════════════════════════════════════════════════
-- FASE 11 — MODELO OPERATIVO: PASEOS PROGRAMADOS + EVENTOS
-- Ejecutar en phpMyAdmin UNA sola vez, DESPUÉS de la fase 10.
--
-- Introduce la entidad que faltaba entre la compra y la ejecución:
--   paseos_programados = "el paseo de ESTA mascota, ESTE día concreto".
-- Hasta ahora el sistema saltaba de la plantilla semanal (cronograma_paseos)
-- a la ruta del día materializada por el paseador, sin ninguna instancia
-- intermedia: no se podía contar el mes, ver el futuro, reprogramar ni
-- detectar días perdidos.
--
--   eventos_paseo   = log append-only de todo lo que le pasa a un paseo
--                     (auditoría; nada se sobreescribe).
--   control_procesos = throttle del generador de instancias (byethost no
--                     tiene cron: se genera de forma perezosa desde los
--                     endpoints, como desactivarMembresiasVencidas).
-- ═══════════════════════════════════════════════════════════════════

-- ── 1. Instancias de paseo con fecha concreta ────────────────────────
CREATE TABLE IF NOT EXISTS `paseos_programados` (
  `id_paseo` INT(11) NOT NULL AUTO_INCREMENT,
  `id_pedido` INT(11) NOT NULL,
  `id_mascota` INT(11) NOT NULL,
  `id_usuario_cliente` INT(11) NOT NULL,
  `fecha` DATE NOT NULL,
  `franja_horaria` VARCHAR(40) DEFAULT NULL,
  `hora_objetivo` TIME DEFAULT NULL,
  `duracion_min` SMALLINT NOT NULL DEFAULT 60,
  `modalidad` ENUM('individual','grupal') NOT NULL DEFAULT 'grupal',
  `id_paseador` INT(11) DEFAULT NULL,
  `id_ruta` INT(11) DEFAULT NULL,
  `estado` ENUM('programado','asignado','en_ruta','recogido','completado',
                'cancelado','no_ejecutado','reprogramado') NOT NULL DEFAULT 'programado',
  `origen` ENUM('cronograma','manual','reposicion') NOT NULL DEFAULT 'cronograma',
  `id_paseo_origen` INT(11) DEFAULT NULL COMMENT 'si es reposición/reprogramación de otro paseo',
  `motivo_cancelacion` VARCHAR(160) DEFAULT NULL,
  `cancelado_por` ENUM('cliente','paseador','admin','sistema') DEFAULT NULL,
  `creado_en` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `actualizado_en` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_paseo`),
  UNIQUE KEY `uq_paseo_pedido_fecha` (`id_pedido`, `fecha`),
  KEY `idx_fecha_paseador` (`fecha`, `id_paseador`),
  KEY `idx_pedido_estado` (`id_pedido`, `estado`),
  KEY `idx_ruta` (`id_ruta`),
  CONSTRAINT `fk_pp_pedido` FOREIGN KEY (`id_pedido`)
    REFERENCES `pedidos_paseo` (`id_pedido`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ── 2. Log de eventos (append-only, nunca se borra ni se edita) ──────
CREATE TABLE IF NOT EXISTS `eventos_paseo` (
  `id_evento` INT(11) NOT NULL AUTO_INCREMENT,
  `id_paseo` INT(11) NOT NULL,
  `tipo` VARCHAR(30) NOT NULL COMMENT 'programado|asignado|en_ruta|recogido|entregado|cancelado|no_ejecutado|deshecho',
  `detalle` VARCHAR(255) DEFAULT NULL,
  `actor` ENUM('cliente','paseador','admin','sistema') NOT NULL DEFAULT 'sistema',
  `creado_en` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_evento`),
  KEY `idx_ev_paseo` (`id_paseo`),
  CONSTRAINT `fk_ev_paseo` FOREIGN KEY (`id_paseo`)
    REFERENCES `paseos_programados` (`id_paseo`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ── 3. Control de procesos perezosos (sin cron) ──────────────────────
CREATE TABLE IF NOT EXISTS `control_procesos` (
  `proceso` VARCHAR(40) NOT NULL,
  `ultima_ejecucion` DATETIME DEFAULT NULL,
  PRIMARY KEY (`proceso`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ── 4. Vínculo parada -> instancia ───────────────────────────────────
ALTER TABLE `ruta_paradas`
  ADD COLUMN `id_paseo` INT(11) DEFAULT NULL AFTER `id_pedido`,
  ADD KEY `idx_rp_paseo` (`id_paseo`);

-- ── 5. Backfill: reconstruir instancias del histórico de ejecución ───
-- Estado derivado de los timestamps reales de las paradas. Si un pedido
-- tuvo dos rutas el mismo día (datos de prueba), gana la primera (UNIQUE).
INSERT IGNORE INTO `paseos_programados`
  (`id_pedido`, `id_mascota`, `id_usuario_cliente`, `fecha`, `franja_horaria`,
   `duracion_min`, `modalidad`, `id_paseador`, `id_ruta`, `estado`, `origen`)
SELECT rp.id_pedido, p.id_mascota, p.id_usuario, r.fecha_paseo, p.franja_horaria,
       p.duracion_min, p.modalidad, r.id_paseador, r.id_ruta,
       CASE
         WHEN MAX(rp.hora_entrega) IS NOT NULL THEN 'completado'
         WHEN MAX(rp.hora_cancelacion) IS NOT NULL THEN 'cancelado'
         WHEN r.fecha_paseo < CURDATE() THEN 'no_ejecutado'
         WHEN MAX(rp.hora_recogida) IS NOT NULL THEN 'recogido'
         ELSE 'en_ruta'
       END,
       'cronograma'
FROM `ruta_paradas` rp
JOIN `rutas` r ON r.id_ruta = rp.id_ruta
JOIN `pedidos_paseo` p ON p.id_pedido = rp.id_pedido
WHERE rp.id_pedido IS NOT NULL
GROUP BY rp.id_pedido, r.id_ruta, r.fecha_paseo, p.id_mascota, p.id_usuario,
         p.franja_horaria, p.duracion_min, p.modalidad, r.id_paseador;

-- ── 6. Vincular las paradas históricas con su instancia ──────────────
UPDATE `ruta_paradas` rp
JOIN `rutas` r ON r.id_ruta = rp.id_ruta
JOIN `paseos_programados` pp ON pp.id_pedido = rp.id_pedido AND pp.fecha = r.fecha_paseo
SET rp.id_paseo = pp.id_paseo
WHERE rp.id_pedido IS NOT NULL;
