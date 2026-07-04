-- Estado en línea / desconectado del chat.
-- Cada petición al chat (listar_chats, cargar_mensajes, etc.) refresca esta
-- marca de tiempo; un usuario se considera "en línea" si tiene actividad en
-- los últimos 45 segundos (ver controller/chat_controller.php).
ALTER TABLE usuarios ADD COLUMN ultima_actividad DATETIME NULL DEFAULT NULL;
