-- ═══════════════════════════════════════════════════════════════════
-- FASE 15 — HORA EXACTA DEL PASEO
-- Ejecutar en phpMyAdmin UNA sola vez, DESPUÉS de la fase 11.
--
-- El cliente ya no elige una franja de 3 horas ("8:00 a.m. – 11:00 a.m.")
-- sino la HORA exacta a la que quiere su paseo (ej. 7:00 a.m.). Con la
-- duración contratada queda definido el intervalo real (7:00 – 8:00),
-- el admin asigna sin choques de horario y la ruta del paseador se
-- ordena por hora de recogida.
--
-- franja_horaria se conserva como ETIQUETA legible derivada
-- ("7:00 a.m. – 8:00 a.m.") para no romper ninguna pantalla existente.
-- ═══════════════════════════════════════════════════════════════════

-- ── 1. Columna nueva: hora exacta contratada ─────────────────────────
ALTER TABLE `pedidos_paseo`
  ADD COLUMN IF NOT EXISTS `hora_paseo` TIME DEFAULT NULL
    COMMENT 'hora exacta de inicio del paseo elegida por el cliente'
    AFTER `franja_horaria`;

-- ── 2. Backfill: los pedidos viejos toman el INICIO de su franja ─────
-- "8:00 a.m. – 11:00 a.m." -> 08:00:00 (mismo criterio que
-- horaInicioDeFranja() en model/helpers.php).
UPDATE `pedidos_paseo`
SET `hora_paseo` = STR_TO_DATE(
      TRIM(REPLACE(UPPER(SUBSTRING_INDEX(`franja_horaria`, '–', 1)), '.', '')),
      '%l:%i %p')
WHERE `hora_paseo` IS NULL
  AND `franja_horaria` IS NOT NULL AND `franja_horaria` <> '';

-- ── 3. Regenerar la etiqueta con el intervalo REAL (hora + duración) ─
UPDATE `pedidos_paseo`
SET `franja_horaria` = CONCAT(
      DATE_FORMAT(`hora_paseo`, '%l:%i'),
      IF(HOUR(`hora_paseo`) < 12, ' a.m.', ' p.m.'),
      ' – ',
      DATE_FORMAT(ADDTIME(`hora_paseo`, SEC_TO_TIME(`duracion_min` * 60)), '%l:%i'),
      IF(HOUR(ADDTIME(`hora_paseo`, SEC_TO_TIME(`duracion_min` * 60))) < 12, ' a.m.', ' p.m.'))
WHERE `hora_paseo` IS NOT NULL;

-- ── 4. Propagar a las instancias futuras ya materializadas ───────────
UPDATE `paseos_programados` pp
JOIN `pedidos_paseo` p ON p.`id_pedido` = pp.`id_pedido`
SET pp.`hora_objetivo`  = p.`hora_paseo`,
    pp.`franja_horaria` = p.`franja_horaria`,
    pp.`duracion_min`   = p.`duracion_min`
WHERE pp.`fecha` >= CURDATE()
  AND pp.`estado` IN ('programado', 'asignado')
  AND p.`hora_paseo` IS NOT NULL;
