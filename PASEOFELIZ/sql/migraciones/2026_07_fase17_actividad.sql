-- ═══════════════════════════════════════════════════════════════════
-- FASE 17 — CENTRO DE ACTIVIDAD DEL SISTEMA + APROBACIÓN DE CANCELACIONES
-- Ejecutar en phpMyAdmin UNA sola vez, DESPUÉS de las fases 7 y 11.
--
-- 1) actividad_sistema: read-model denormalizado que alimenta el
--    "Centro de Actividad" del dashboard admin. TODO el sistema escribe
--    aquí (model/ActivityService.php) y el dashboard solo lee esta tabla
--    (paginada, filtrada, con poll incremental por id). Denormaliza los
--    nombres para no hacer JOINs por fila (evita N+1).
-- 2) solicitudes_cancelacion: el paseador ya no cancela directo; SOLICITA
--    y el admin aprueba (cancela de verdad) o rechaza (el paseo sigue).
-- 3) Backfill idempotente: siembra el feed con el histórico ya fechado
--    (pagos, pedidos, eventos_paseo, calificaciones, usuarios, rutas).
--    Guardas NOT EXISTS por (servicio, familia de tipo, id_referencia)
--    para poder re-ejecutar sin duplicar.
-- ═══════════════════════════════════════════════════════════════════

-- ── 1. Feed de actividad ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `actividad_sistema` (
  `id_actividad`    INT(11) NOT NULL AUTO_INCREMENT,
  `servicio`        ENUM('paseos','adiestramiento','hospedaje','sistema') NOT NULL DEFAULT 'paseos',
  `tipo`            VARCHAR(40) NOT NULL,
  `estado`          ENUM('nuevo','en_proceso','pendiente','completado','cancelado','urgente','incidencia') NOT NULL DEFAULT 'nuevo',
  `prioridad`       ENUM('alta','media','baja') NOT NULL DEFAULT 'media',
  `titulo`          VARCHAR(160) NOT NULL,
  `descripcion`     VARCHAR(255) DEFAULT NULL,
  `id_cliente`      INT(11) DEFAULT NULL,
  `cliente_nombre`  VARCHAR(100) DEFAULT NULL,
  `id_paseador`     INT(11) DEFAULT NULL,
  `paseador_nombre` VARCHAR(100) DEFAULT NULL,
  `id_mascota`      INT(11) DEFAULT NULL,
  `mascota_nombre`  VARCHAR(100) DEFAULT NULL,
  `id_pedido`       INT(11) DEFAULT NULL,
  `id_ruta`         INT(11) DEFAULT NULL,
  `id_referencia`   INT(11) DEFAULT NULL COMMENT 'id contextual de la fuente (pago, evento, solicitud...)',
  `direccion`       VARCHAR(160) DEFAULT NULL,
  `resuelto`        TINYINT(1) NOT NULL DEFAULT 1 COMMENT '0 = requiere acción del admin (p.ej. cancelación pendiente)',
  `creado_en`       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_actividad`),
  KEY `idx_servicio_creado` (`servicio`, `creado_en`),
  KEY `idx_creado_id` (`creado_en`, `id_actividad`),
  KEY `idx_estado` (`estado`),
  KEY `idx_resuelto` (`resuelto`),
  KEY `idx_busca` (`cliente_nombre`, `mascota_nombre`, `paseador_nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ── 2. Solicitudes de cancelación (workflow de aprobación) ───────────
CREATE TABLE IF NOT EXISTS `solicitudes_cancelacion` (
  `id_solicitud`  INT(11) NOT NULL AUTO_INCREMENT,
  `id_paseo`      INT(11) NOT NULL COMMENT 'instancia en paseos_programados',
  `id_pedido`     INT(11) NOT NULL,
  `id_ruta`       INT(11) DEFAULT NULL,
  `id_paseador`   INT(11) DEFAULT NULL,
  `id_cliente`    INT(11) DEFAULT NULL,
  `id_mascota`    INT(11) DEFAULT NULL,
  `motivo`        VARCHAR(160) NOT NULL,
  `estado_paseo_al_solicitar` VARCHAR(20) DEFAULT NULL,
  `estado`        ENUM('pendiente','aprobada','rechazada') NOT NULL DEFAULT 'pendiente',
  `resuelto_por`  INT(11) DEFAULT NULL COMMENT 'id del admin que resolvió',
  `nota_admin`    VARCHAR(160) DEFAULT NULL,
  `creado_en`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `resuelto_en`   DATETIME DEFAULT NULL,
  PRIMARY KEY (`id_solicitud`),
  KEY `idx_sc_estado` (`estado`),
  KEY `idx_sc_paseo` (`id_paseo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ═══════════════════════════════════════════════════════════════════
-- 3. BACKFILL IDEMPOTENTE (histórico ya fechado)
-- ═══════════════════════════════════════════════════════════════════

-- 3.1 Pagos → pago_aprobado / pago_rechazado ─────────────────────────
INSERT INTO `actividad_sistema`
  (servicio, tipo, estado, prioridad, titulo, descripcion,
   id_cliente, cliente_nombre, id_mascota, mascota_nombre,
   id_pedido, id_referencia, creado_en, resuelto)
SELECT
  pg.tipo_membresia,
  IF(pg.estado_pago = 'rechazado', 'pago_rechazado', 'pago_aprobado'),
  IF(pg.estado_pago = 'rechazado', 'cancelado', 'completado'),
  IF(pg.estado_pago = 'rechazado', 'alta', 'baja'),
  CONCAT(IF(pg.estado_pago = 'rechazado', 'Pago rechazado', 'Pago recibido'),
         ' — $', FORMAT(pg.monto, 0)),
  CONCAT('Método: ', COALESCE(pg.metodo, pg.metodo_pago, 'manual'),
         COALESCE(CONCAT(' · Ref. ', pg.referencia), '')),
  pg.id_usuario, u.nombre, pg.id_mascota, mu.nombre_mascota,
  COALESCE(pg.id_pedido, pg.id_pedido_adiestramiento, pg.id_pedido_hospedaje),
  pg.id_pago, pg.fecha_pago, 1
FROM `pagos` pg
LEFT JOIN `usuarios` u        ON u.id = pg.id_usuario
LEFT JOIN `mascota_usuario` mu ON mu.id_mascota = pg.id_mascota
WHERE NOT EXISTS (
  SELECT 1 FROM `actividad_sistema` a
  WHERE a.id_referencia = pg.id_pago
    AND a.tipo IN ('pago_aprobado', 'pago_rechazado')
);

-- 3.2 Compras (los 3 servicios) → compra ─────────────────────────────
INSERT INTO `actividad_sistema`
  (servicio, tipo, estado, prioridad, titulo, descripcion,
   id_cliente, cliente_nombre, id_mascota, mascota_nombre,
   id_pedido, id_referencia, direccion, creado_en, resuelto)
SELECT 'paseos', 'compra', 'nuevo', 'media',
  CONCAT('Nuevo servicio de paseos — ', COALESCE(mu.nombre_mascota, 'mascota')),
  CONCAT(p.cantidad_paseos, ' paseos/mes · ', COALESCE(p.modalidad, '')),
  p.id_usuario, u.nombre, p.id_mascota, mu.nombre_mascota,
  p.id_pedido, p.id_pedido, p.direccion, p.fecha_creacion, 1
FROM `pedidos_paseo` p
LEFT JOIN `usuarios` u         ON u.id = p.id_usuario
LEFT JOIN `mascota_usuario` mu ON mu.id_mascota = p.id_mascota
WHERE NOT EXISTS (
  SELECT 1 FROM `actividad_sistema` a
  WHERE a.servicio = 'paseos' AND a.tipo = 'compra' AND a.id_referencia = p.id_pedido
);

INSERT INTO `actividad_sistema`
  (servicio, tipo, estado, prioridad, titulo, descripcion,
   id_cliente, cliente_nombre, id_mascota, mascota_nombre,
   id_pedido, id_referencia, direccion, creado_en, resuelto)
SELECT 'adiestramiento', 'compra', 'nuevo', 'media',
  CONCAT('Nuevo servicio de adiestramiento — ', COALESCE(mu.nombre_mascota, 'mascota')),
  CONCAT(p.cantidad_sesiones, ' sesiones'),
  p.id_usuario, u.nombre, p.id_mascota, mu.nombre_mascota,
  p.id_pedido, p.id_pedido, p.direccion, p.fecha_creacion, 1
FROM `pedidos_adiestramiento` p
LEFT JOIN `usuarios` u         ON u.id = p.id_usuario
LEFT JOIN `mascota_usuario` mu ON mu.id_mascota = p.id_mascota
WHERE NOT EXISTS (
  SELECT 1 FROM `actividad_sistema` a
  WHERE a.servicio = 'adiestramiento' AND a.tipo = 'compra' AND a.id_referencia = p.id_pedido
);

INSERT INTO `actividad_sistema`
  (servicio, tipo, estado, prioridad, titulo, descripcion,
   id_cliente, cliente_nombre, id_mascota, mascota_nombre,
   id_pedido, id_referencia, direccion, creado_en, resuelto)
SELECT 'hospedaje', 'compra', 'nuevo', 'media',
  CONCAT('Nuevo servicio de hospedaje — ', COALESCE(mu.nombre_mascota, 'mascota')),
  CONCAT(p.cantidad_noches, ' noches'),
  p.id_usuario, u.nombre, p.id_mascota, mu.nombre_mascota,
  p.id_pedido, p.id_pedido, p.direccion, p.fecha_creacion, 1
FROM `pedidos_hospedaje` p
LEFT JOIN `usuarios` u         ON u.id = p.id_usuario
LEFT JOIN `mascota_usuario` mu ON mu.id_mascota = p.id_mascota
WHERE NOT EXISTS (
  SELECT 1 FROM `actividad_sistema` a
  WHERE a.servicio = 'hospedaje' AND a.tipo = 'compra' AND a.id_referencia = p.id_pedido
);

-- 3.3 Ejecución de paseos ← eventos_paseo (un solo INSERT con CASE) ───
INSERT INTO `actividad_sistema`
  (servicio, tipo, estado, prioridad, titulo, descripcion,
   id_cliente, cliente_nombre, id_paseador, paseador_nombre,
   id_mascota, mascota_nombre, id_pedido, id_ruta, id_referencia, creado_en, resuelto)
SELECT 'paseos',
  CASE ev.tipo WHEN 'reasignado' THEN 'reprogramado'
               WHEN 'cancelado'  THEN 'cancelacion_aprobada'
               ELSE ev.tipo END,
  CASE ev.tipo
    WHEN 'entregado'    THEN 'completado'
    WHEN 'no_ejecutado' THEN 'cancelado'
    WHEN 'cancelado'    THEN 'cancelado'
    WHEN 'incidencia'   THEN 'incidencia'
    ELSE 'en_proceso' END,
  CASE ev.tipo
    WHEN 'incidencia'   THEN 'alta'
    WHEN 'cancelado'    THEN 'alta'
    WHEN 'no_ejecutado' THEN 'alta'
    WHEN 'evidencia'    THEN 'baja'
    ELSE 'media' END,
  CASE ev.tipo
    WHEN 'recogido'     THEN CONCAT('Mascota recogida: ', COALESCE(mu.nombre_mascota, '—'))
    WHEN 'entregado'    THEN CONCAT('Paseo finalizado: ', COALESCE(mu.nombre_mascota, '—'))
    WHEN 'en_ruta'      THEN CONCAT('Paseo en curso: ', COALESCE(mu.nombre_mascota, '—'))
    WHEN 'incidencia'   THEN CONCAT('Incidencia — ', COALESCE(mu.nombre_mascota, '—'))
    WHEN 'evidencia'    THEN CONCAT('Foto del paseo de ', COALESCE(mu.nombre_mascota, '—'))
    WHEN 'no_ejecutado' THEN CONCAT('Paseo no ejecutado: ', COALESCE(mu.nombre_mascota, '—'))
    WHEN 'reasignado'   THEN CONCAT('Paseo reasignado: ', COALESCE(mu.nombre_mascota, '—'))
    WHEN 'cancelado'    THEN CONCAT('Paseo cancelado: ', COALESCE(mu.nombre_mascota, '—'))
    ELSE COALESCE(mu.nombre_mascota, '—') END,
  ev.detalle,
  pp.id_usuario_cliente, uc.nombre,
  pp.id_paseador, up.nombre,
  pp.id_mascota, mu.nombre_mascota,
  pp.id_pedido, pp.id_ruta, ev.id_evento, ev.creado_en, 1
FROM `eventos_paseo` ev
JOIN `paseos_programados` pp ON pp.id_paseo = ev.id_paseo
LEFT JOIN `usuarios` uc        ON uc.id = pp.id_usuario_cliente
LEFT JOIN `paseadores` pa      ON pa.id_paseador = pp.id_paseador
LEFT JOIN `usuarios` up        ON up.id = pa.id_usuario
LEFT JOIN `mascota_usuario` mu ON mu.id_mascota = pp.id_mascota
WHERE ev.tipo IN ('recogido','entregado','en_ruta','incidencia','evidencia','no_ejecutado','reasignado','cancelado')
  AND NOT EXISTS (
    SELECT 1 FROM `actividad_sistema` a
    WHERE a.id_referencia = ev.id_evento
      AND a.tipo IN ('recogido','entregado','en_ruta','incidencia','evidencia',
                     'no_ejecutado','reprogramado','cancelacion_aprobada')
  );

-- 3.4 Calificaciones ← calificaciones_paseo ──────────────────────────
INSERT INTO `actividad_sistema`
  (servicio, tipo, estado, prioridad, titulo, descripcion,
   id_cliente, cliente_nombre, id_paseador, paseador_nombre,
   id_pedido, id_ruta, id_referencia, creado_en, resuelto)
SELECT 'paseos', 'calificacion', 'completado', 'baja',
  CONCAT('Cliente calificó el paseo: ', REPEAT('★', c.estrellas), REPEAT('☆', 5 - c.estrellas)),
  c.comentario,
  c.id_usuario_cliente, uc.nombre,
  c.id_paseador, up.nombre,
  c.id_pedido, c.id_ruta, c.id_calificacion, c.fecha_creacion, 1
FROM `calificaciones_paseo` c
LEFT JOIN `usuarios` uc   ON uc.id = c.id_usuario_cliente
LEFT JOIN `paseadores` pa ON pa.id_paseador = c.id_paseador
LEFT JOIN `usuarios` up   ON up.id = pa.id_usuario
WHERE NOT EXISTS (
  SELECT 1 FROM `actividad_sistema` a
  WHERE a.tipo = 'calificacion' AND a.id_referencia = c.id_calificacion
);

-- 3.5 Registros de clientes y paseadores ← usuarios ──────────────────
INSERT INTO `actividad_sistema`
  (servicio, tipo, estado, prioridad, titulo, descripcion,
   id_cliente, cliente_nombre, id_paseador, paseador_nombre,
   id_referencia, creado_en, resuelto)
SELECT 'sistema',
  IF(pa.id_paseador IS NULL, 'cliente_registrado', 'paseador_registrado'),
  'nuevo', 'baja',
  IF(pa.id_paseador IS NULL,
     CONCAT('Nuevo cliente registrado: ', u.nombre),
     CONCAT('Nuevo paseador registrado: ', u.nombre)),
  u.email,
  IF(pa.id_paseador IS NULL, u.id, NULL),
  IF(pa.id_paseador IS NULL, u.nombre, NULL),
  IF(pa.id_paseador IS NULL, NULL, pa.id_paseador),
  IF(pa.id_paseador IS NULL, NULL, u.nombre),
  u.id, u.fecha_registro, 1
FROM `usuarios` u
LEFT JOIN `admin` ad      ON ad.id_usuario = u.id
LEFT JOIN `paseadores` pa ON pa.id_usuario = u.id
WHERE ad.id_usuario IS NULL
  AND NOT EXISTS (
    SELECT 1 FROM `actividad_sistema` a
    WHERE a.servicio = 'sistema' AND a.id_referencia = u.id
      AND a.tipo IN ('cliente_registrado', 'paseador_registrado')
  );

-- 3.6 Rutas creadas ← rutas ──────────────────────────────────────────
INSERT INTO `actividad_sistema`
  (servicio, tipo, estado, prioridad, titulo, descripcion,
   id_paseador, paseador_nombre, id_ruta, id_referencia, creado_en, resuelto)
SELECT 'paseos', 'ruta_creada', 'nuevo', 'baja',
  CONCAT('Nueva ruta del ', DATE_FORMAT(r.fecha_paseo, '%d/%m')),
  NULL,
  r.id_paseador, up.nombre, r.id_ruta, r.id_ruta, r.fecha_creacion, 1
FROM `rutas` r
LEFT JOIN `paseadores` pa ON pa.id_paseador = r.id_paseador
LEFT JOIN `usuarios` up   ON up.id = pa.id_usuario
WHERE NOT EXISTS (
  SELECT 1 FROM `actividad_sistema` a
  WHERE a.tipo = 'ruta_creada' AND a.id_referencia = r.id_ruta
);
