-- ═══════════════════════════════════════════════════════════════════
-- FASE 10 — ESTABILIZACIÓN CRÍTICA DEL MÓDULO DE PASEOS
-- Ejecutar en phpMyAdmin (local XAMPP y byethost) UNA sola vez.
--
-- 1. membresias.fecha_fin_paseos: de columna GENERADA a columna REAL.
--    Necesario para que la renovación EXTIENDA la vigencia desde el
--    vencimiento actual (antes la fecha fin siempre era inicio+30 días
--    y renovar era imposible sin pisar el periodo).
-- 2. pedidos_paseo: columnas para la cancelación administrativa
--    (el estado 'cancelado' existía en el enum pero nada podía usarlo).
-- ═══════════════════════════════════════════════════════════════════

-- ── 1. fecha_fin_paseos real ─────────────────────────────────────────
ALTER TABLE `membresias` DROP COLUMN `fecha_fin_paseos`;

ALTER TABLE `membresias`
  ADD COLUMN `fecha_fin_paseos` DATETIME DEFAULT NULL
  AFTER `fecha_inicio_hospedaje`;

-- Backfill: conservar la vigencia que tenían las membresías existentes
UPDATE `membresias`
SET `fecha_fin_paseos` = DATE_ADD(`fecha_inicio_paseos`, INTERVAL 30 DAY)
WHERE `fecha_inicio_paseos` IS NOT NULL;

-- ── 2. Cancelación de pedidos de paseo ───────────────────────────────
ALTER TABLE `pedidos_paseo`
  ADD COLUMN `motivo_cancelacion` VARCHAR(160) DEFAULT NULL AFTER `estado`,
  ADD COLUMN `cancelado_por` ENUM('admin','cliente','sistema') DEFAULT NULL AFTER `motivo_cancelacion`,
  ADD COLUMN `fecha_cancelacion` DATETIME DEFAULT NULL AFTER `cancelado_por`;
