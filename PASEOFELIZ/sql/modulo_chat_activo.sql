-- ═══════════════════════════════════════════════════════════════
-- modulo_chat_activo.sql
-- El nuevo chat_controller.php (activar/desactivar conversaciones,
-- solo el admin puede reactivar) necesita esta columna. Es aditiva:
-- no afecta ninguna conversación existente (todas quedan "activo=1").
-- Ejecutar este bloque completo en phpMyAdmin.
-- ═══════════════════════════════════════════════════════════════

ALTER TABLE `conversaciones`
    ADD COLUMN `activo` TINYINT(1) NOT NULL DEFAULT 1 AFTER `id_usuario_2`;
