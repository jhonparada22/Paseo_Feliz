-- ═══════════════════════════════════════════════════════════════
-- modulo_pagos_admin.sql
-- Reconcilia `pagos` y `membresias` para que el panel de pagos del
-- admin (registrar_pago.php / pagos_admin.php) pueda registrar pagos
-- MANUALES de cualquier membresía (paseos, adiestramiento, hospedaje)
-- sin pedido asociado, SIN afectar el wizard de compra de Paseos que
-- ya funciona (pedidos_paseo + pagos con id_pedido).
--
-- Todo es aditivo / relaja restricciones — no borra ni renombra nada:
--   - pagos.id_pedido pasa de NOT NULL a NULL (el wizard siempre lo
--     sigue llenando; los pagos manuales del admin lo dejan vacío).
--   - pagos.metodo pasa de NOT NULL a NULL (el wizard sigue llenándolo
--     con 'tarjeta'/'pse'/'nequi'; los pagos manuales usan la nueva
--     columna metodo_pago en su lugar).
--   - se agregan pagos.tipo_membresia y pagos.metodo_pago (nuevas,
--     solo las usan los pagos manuales del admin).
--   - se agregan membresias.id_pago_paseos/adiestramiento/hospedaje
--     (nuevas, para saber qué pago activó cada membresía).
--
-- NO se toca fecha_fin_paseos/adiestramiento/hospedaje: siguen siendo
-- columnas calculadas automáticamente (fecha_inicio + 30 días) tal
-- como las usa hoy todo el sistema de paseos.
--
-- Ejecutar este bloque completo en phpMyAdmin.
-- ═══════════════════════════════════════════════════════════════

ALTER TABLE `pagos`
    MODIFY `id_pedido` INT(11) NULL,
    MODIFY `metodo` ENUM('tarjeta','pse','nequi') NULL,
    ADD COLUMN `tipo_membresia` ENUM('paseos','adiestramiento','hospedaje') NULL DEFAULT NULL AFTER `id_usuario`,
    ADD COLUMN `metodo_pago` VARCHAR(50) NULL DEFAULT 'manual' AFTER `metodo`;

ALTER TABLE `membresias`
    ADD COLUMN `id_pago_paseos` INT(11) NULL DEFAULT NULL AFTER `hospedaje`,
    ADD COLUMN `id_pago_adiestramiento` INT(11) NULL DEFAULT NULL AFTER `id_pago_paseos`,
    ADD COLUMN `id_pago_hospedaje` INT(11) NULL DEFAULT NULL AFTER `id_pago_adiestramiento`,
    ADD CONSTRAINT `fk_membresia_pago_paseos` FOREIGN KEY (`id_pago_paseos`) REFERENCES `pagos` (`id_pago`) ON DELETE SET NULL,
    ADD CONSTRAINT `fk_membresia_pago_adiestramiento` FOREIGN KEY (`id_pago_adiestramiento`) REFERENCES `pagos` (`id_pago`) ON DELETE SET NULL,
    ADD CONSTRAINT `fk_membresia_pago_hospedaje` FOREIGN KEY (`id_pago_hospedaje`) REFERENCES `pagos` (`id_pago`) ON DELETE SET NULL;
