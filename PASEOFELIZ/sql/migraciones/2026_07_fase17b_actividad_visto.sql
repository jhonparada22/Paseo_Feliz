-- ═══════════════════════════════════════════════════════════════════
-- FASE 17b — "Visto" en el Centro de Actividad
-- Ejecutar en phpMyAdmin UNA sola vez, DESPUÉS de la fase 17.
--
-- Permite al admin marcar un reporte como visto para que desaparezca del
-- feed sin borrarlo (queda el registro histórico, solo se oculta). El
-- feed excluye visto = 1.
-- ═══════════════════════════════════════════════════════════════════

ALTER TABLE `actividad_sistema`
  ADD COLUMN IF NOT EXISTS `visto` TINYINT(1) NOT NULL DEFAULT 0
    COMMENT 'admin lo marcó como visto: se oculta del feed' AFTER `resuelto`,
  ADD KEY IF NOT EXISTS `idx_visto` (`visto`);
