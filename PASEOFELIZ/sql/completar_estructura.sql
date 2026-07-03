-- ═══════════════════════════════════════════════════════════════════════
-- Paseo Feliz — Completar estructura faltante en b17_42313426_paseofeliztest
--
-- El dump que exportaste (b17_42313426_paseofeliztest.sql, generado hoy
-- 01-07-2026 18:14:39 desde sql113.byethost17.com) se corta justo después
-- de los índices de `mascota_usuario`. Le faltan por completo:
--   1. PRIMARY KEY / índices de 8 tablas: usuarios, paseadores, membresias,
--      mensajes, notificaciones, rutas, ruta_clientes, ruta_paradas.
--   2. AUTO_INCREMENT en TODAS las tablas (ni siquiera las 11 tablas que
--      sí tienen PRIMARY KEY lo tienen configurado).
--   3. Las llaves foráneas (FOREIGN KEY) — no hay ninguna en toda la base.
--
-- Esto probablemente pasó porque el script SQL completo se cortó a la
-- mitad al importarlo (los hostings gratuitos limitan el tiempo/tamaño
-- de ejecución en phpMyAdmin). Ejecuta este archivo POR BLOQUES
-- (cada -- BLOQUE N--) en la pestaña SQL de phpMyAdmin de
-- b17_42313426_paseofeliztest, uno a la vez, para evitar que se vuelva
-- a cortar.
-- ═══════════════════════════════════════════════════════════════════════


-- ── BLOQUE 1: limpiar filas duplicadas en `membresias` ────────────────
-- El login (controller/login.php) hace "INSERT IGNORE INTO membresias"
-- en cada inicio de sesión. Sin PRIMARY KEY/UNIQUE, "IGNORE" no tenía
-- nada que ignorar, así que se crearon 6 filas basura con id_membresia=0
-- (4 para el usuario 8, 2 para el usuario 10). Las borramos y las
-- recreamos limpias más abajo.

DELETE FROM `membresias` WHERE `id_membresia` = 0;


-- ── BLOQUE 2: PRIMARY KEY / índices de las 8 tablas que no los tienen ──

ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

ALTER TABLE `paseadores`
  ADD PRIMARY KEY (`id_paseador`),
  ADD KEY `fk_paseadores_usuario` (`id_usuario`);

ALTER TABLE `membresias`
  ADD PRIMARY KEY (`id_membresia`),
  ADD UNIQUE KEY `uq_usuario` (`id_usuario`);

ALTER TABLE `mensajes`
  ADD PRIMARY KEY (`id_mensaje`),
  ADD KEY `id_conversacion` (`id_conversacion`),
  ADD KEY `id_emisor` (`id_emisor`);

ALTER TABLE `notificaciones`
  ADD PRIMARY KEY (`id_notificacion`),
  ADD KEY `idx_notif_usuario` (`id_usuario_destino`,`leida`),
  ADD KEY `fk_notif_ruta` (`id_ruta`);

ALTER TABLE `rutas`
  ADD PRIMARY KEY (`id_ruta`),
  ADD KEY `idx_ruta_paseador` (`id_paseador`),
  ADD KEY `idx_ruta_fecha` (`fecha_paseo`),
  ADD KEY `fk_ruta_admin` (`id_admin_creador`),
  ADD KEY `fk_ruta_estado` (`id_estado`);

ALTER TABLE `ruta_clientes`
  ADD PRIMARY KEY (`id_ruta_cliente`),
  ADD UNIQUE KEY `uq_ruta_mascota` (`id_ruta`,`id_mascota`),
  ADD KEY `fk_rc_cliente` (`id_usuario_cliente`),
  ADD KEY `fk_rc_mascota` (`id_mascota`);

ALTER TABLE `ruta_paradas`
  ADD PRIMARY KEY (`id_parada`),
  ADD KEY `idx_parada_ruta` (`id_ruta`),
  ADD KEY `fk_parada_cliente` (`id_usuario_cliente`),
  ADD KEY `fk_parada_mascota` (`id_mascota`),
  ADD KEY `fk_parada_estado` (`id_estado`);


-- ── BLOQUE 3: AUTO_INCREMENT en todas las tablas que lo necesitan ─────
-- (los valores de arranque se calcularon a partir del id máximo real
-- que ya tienes en cada tabla, para no chocar con tus datos actuales)

ALTER TABLE `admin`               MODIFY `id_admin`        int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;
ALTER TABLE `adopcion`            MODIFY `id_adopcion`      int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;
ALTER TABLE `codigos_verificacion` MODIFY `id`              int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;
ALTER TABLE `conversaciones`      MODIFY `id_conversacion`  int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;
ALTER TABLE `estados_parada`      MODIFY `id_estado`        int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;
ALTER TABLE `estados_ruta`        MODIFY `id_estado`        int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;
ALTER TABLE `historial_gps`       MODIFY `id_historial`     int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `info_usuario`        MODIFY `id_info`          int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;
ALTER TABLE `mascota_usuario`     MODIFY `id_mascota`       int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;
ALTER TABLE `membresias`          MODIFY `id_membresia`     int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=43;
ALTER TABLE `mensajes`            MODIFY `id_mensaje`       int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=86;
ALTER TABLE `notificaciones`      MODIFY `id_notificacion`  int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `paseadores`          MODIFY `id_paseador`      int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;
ALTER TABLE `rutas`               MODIFY `id_ruta`          int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `ruta_clientes`       MODIFY `id_ruta_cliente`  int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `ruta_paradas`        MODIFY `id_parada`        int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `usuarios`            MODIFY `id`               int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;


-- ── BLOQUE 4: restaurar las 2 membresías que borramos en el BLOQUE 1 ──

INSERT INTO `membresias` (`id_usuario`, `paseos`, `adiestramiento`, `hospedaje`) VALUES
(8, 0, 0, 0),
(10, 0, 0, 0);


-- ── BLOQUE 5: llaves foráneas (relaciones entre tablas) ───────────────
-- Si alguna de estas da error de "foreign key constraint fails", avísame
-- el mensaje exacto (dice qué tabla/fila) y la limpiamos puntualmente.

ALTER TABLE `admin`
  ADD CONSTRAINT `admin_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `conversaciones`
  ADD CONSTRAINT `conversaciones_ibfk_1` FOREIGN KEY (`id_usuario_1`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `conversaciones_ibfk_2` FOREIGN KEY (`id_usuario_2`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;

ALTER TABLE `gps_paseadores`
  ADD CONSTRAINT `fk_gps_paseador` FOREIGN KEY (`id_paseador`) REFERENCES `paseadores` (`id_paseador`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `historial_gps`
  ADD CONSTRAINT `fk_hist_paseador` FOREIGN KEY (`id_paseador`) REFERENCES `paseadores` (`id_paseador`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_hist_ruta` FOREIGN KEY (`id_ruta`) REFERENCES `rutas` (`id_ruta`) ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE `info_usuario`
  ADD CONSTRAINT `fk_info_usuarios` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;

ALTER TABLE `mascota_usuario`
  ADD CONSTRAINT `fk_mascota_usuarios` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;

ALTER TABLE `membresias`
  ADD CONSTRAINT `fk_memb_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `mensajes`
  ADD CONSTRAINT `mensajes_ibfk_1` FOREIGN KEY (`id_conversacion`) REFERENCES `conversaciones` (`id_conversacion`) ON DELETE CASCADE,
  ADD CONSTRAINT `mensajes_ibfk_2` FOREIGN KEY (`id_emisor`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;

ALTER TABLE `notificaciones`
  ADD CONSTRAINT `fk_notif_ruta` FOREIGN KEY (`id_ruta`) REFERENCES `rutas` (`id_ruta`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_notif_usuario` FOREIGN KEY (`id_usuario_destino`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `paseadores`
  ADD CONSTRAINT `fk_paseadores_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;

ALTER TABLE `rutas`
  ADD CONSTRAINT `fk_ruta_admin` FOREIGN KEY (`id_admin_creador`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ruta_estado` FOREIGN KEY (`id_estado`) REFERENCES `estados_ruta` (`id_estado`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ruta_paseador` FOREIGN KEY (`id_paseador`) REFERENCES `paseadores` (`id_paseador`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `ruta_clientes`
  ADD CONSTRAINT `fk_rc_cliente` FOREIGN KEY (`id_usuario_cliente`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_rc_mascota` FOREIGN KEY (`id_mascota`) REFERENCES `mascota_usuario` (`id_mascota`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_rc_ruta` FOREIGN KEY (`id_ruta`) REFERENCES `rutas` (`id_ruta`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `ruta_paradas`
  ADD CONSTRAINT `fk_parada_cliente` FOREIGN KEY (`id_usuario_cliente`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_parada_estado` FOREIGN KEY (`id_estado`) REFERENCES `estados_parada` (`id_estado`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_parada_mascota` FOREIGN KEY (`id_mascota`) REFERENCES `mascota_usuario` (`id_mascota`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_parada_ruta` FOREIGN KEY (`id_ruta`) REFERENCES `rutas` (`id_ruta`) ON DELETE CASCADE ON UPDATE CASCADE;
