-- ═══════════════════════════════════════════════════════════════════════
-- FASE 1 — Consolidación de "ruta del día"
-- Ejecutar UNA SOLA VEZ, en orden, vía phpMyAdmin sobre
-- `b17_42313426_paseofeliztest` (byethost). Esta máquina no tiene acceso
-- directo al puerto MySQL remoto, así que este script se preparó a partir
-- del export que compartiste (b17_42313426_paseofeliztest (7).sql).
--
-- Qué hace:
--   0. Detecta rutas activas duplicadas (mismo paseador + mismo día).
--   1. Resuelve los 2 grupos de conflicto reales que existen HOY en tu BD.
--   2. Agrega las columnas nuevas a ruta_paradas.
--   3. Agrega la restricción de unicidad a rutas (1 sola ruta activa/día).
--   4. Rellena (backfill) las columnas nuevas con datos ya existentes.
--
-- Después de correr esto, ningún archivo PHP cambia de comportamiento
-- todavía (eso es la Fase 2 en adelante) — es 100% migración de esquema.
-- ═══════════════════════════════════════════════════════════════════════

-- ───────────────────────────────────────────────────────────────────────
-- PASO 0 — Diagnóstico: confirmar qué rutas activas duplicadas existen
-- (ejecuta esto primero, solo para ver el panorama antes de tocar nada)
-- ───────────────────────────────────────────────────────────────────────
SELECT id_paseador, fecha_paseo, GROUP_CONCAT(id_ruta ORDER BY fecha_creacion) AS rutas_duplicadas,
       COUNT(*) AS n
FROM rutas
WHERE id_estado IN (1,2,3)
GROUP BY id_paseador, fecha_paseo
HAVING n > 1;

-- Con el export que revisamos, esto debía devolver 2 filas:
--   (id_paseador=11, fecha_paseo='2026-07-03', rutas 6 y 8)
--   (id_paseador=11, fecha_paseo='2026-07-07', rutas 11, 13 y 14)
-- Si tu BD ya cambió desde entonces y aparecen grupos distintos, AVISA
-- antes de seguir — los pasos 1.A y 1.B de abajo están escritos a mano
-- para estos IDs concretos, no son un script genérico.


-- ───────────────────────────────────────────────────────────────────────
-- PASO 1.A — Grupo (paseador 11, 2026-07-03): rutas 6 y 8 duplicadas
-- Ninguna de las dos tiene cliente/mascota real (son puntos de prueba
-- puestos a mano en el mapa), así que no hay nada que fusionar: solo se
-- cancela la más nueva (ruta 8) y se deja la más antigua (ruta 6).
-- ───────────────────────────────────────────────────────────────────────
UPDATE rutas SET id_estado = 5 WHERE id_ruta = 8;


-- ───────────────────────────────────────────────────────────────────────
-- PASO 1.B — Grupo (paseador 11, 2026-07-07): rutas 11, 13 y 14 duplicadas
--
-- Ruta 11 (en_curso) es la "ganadora": ya tiene progreso real (pedido 3,
-- cliente 24 / mascota 12, recogida confirmada). Las rutas 13 y 14 son
-- para el pedido 6 (cliente 10 / mascota 13) y están 100% pendientes;
-- 13 y 14 son además duplicado exacto entre sí.
--
-- Se mueven las paradas del pedido 6 (id_parada 16 y 17, hoy en la
-- ruta 13) hacia la ruta 11, continuando el orden (la ruta 11 ya tiene
-- orden 0 y 1, así que las movidas pasan a ser 2 y 3).
-- ───────────────────────────────────────────────────────────────────────
UPDATE ruta_paradas SET id_ruta = 11, orden = 2, etiqueta = 'C' WHERE id_parada = 16;
UPDATE ruta_paradas SET id_ruta = 11, orden = 3, etiqueta = 'D' WHERE id_parada = 17;

-- La ruta 14 es duplicado exacto de la 13 (mismo pedido 6) — sus paradas
-- (18 y 19) no se mueven, se marcan omitidas y la ruta queda cancelada.
UPDATE ruta_paradas SET id_estado = 4 WHERE id_parada IN (18, 19);

-- ruta_clientes: agregar la pareja (cliente 10 / mascota 13) a la ruta 11
-- ganadora, y limpiar las referencias de las rutas 13/14 que van a quedar
-- canceladas.
INSERT INTO ruta_clientes (id_ruta, id_usuario_cliente, id_mascota)
SELECT 11, 10, 13
WHERE NOT EXISTS (
    SELECT 1 FROM ruta_clientes WHERE id_ruta = 11 AND id_mascota = 13
);
DELETE FROM ruta_clientes WHERE id_ruta IN (13, 14);

-- Cancelar las rutas 13 y 14 (ya sin paradas activas propias).
UPDATE rutas SET id_estado = 5 WHERE id_ruta IN (13, 14);

-- Verificación: el PASO 0 debe devolver 0 filas ahora.
SELECT id_paseador, fecha_paseo, GROUP_CONCAT(id_ruta ORDER BY fecha_creacion) AS rutas_duplicadas,
       COUNT(*) AS n
FROM rutas
WHERE id_estado IN (1,2,3)
GROUP BY id_paseador, fecha_paseo
HAVING n > 1;
-- Si esto NO devuelve 0 filas, DETENTE aquí y avisa antes de seguir con el PASO 2.


-- ───────────────────────────────────────────────────────────────────────
-- PASO 2 — Columnas nuevas en ruta_paradas
-- ───────────────────────────────────────────────────────────────────────
ALTER TABLE ruta_paradas
  ADD COLUMN IF NOT EXISTS id_pedido INT NULL DEFAULT NULL COMMENT 'pedidos_paseo.id_pedido de origen' AFTER id_mascota,
  ADD COLUMN IF NOT EXISTS hora_estimada TIME NULL DEFAULT NULL COMMENT 'ETA de esta parada' AFTER etiqueta,
  ADD COLUMN IF NOT EXISTS hora_recogida DATETIME NULL DEFAULT NULL COMMENT 'Confirmación manual de recogida' AFTER hora_completado,
  ADD COLUMN IF NOT EXISTS hora_entrega DATETIME NULL DEFAULT NULL COMMENT 'Confirmación manual de entrega' AFTER hora_recogida,
  ADD COLUMN IF NOT EXISTS hora_cancelacion DATETIME NULL DEFAULT NULL AFTER hora_entrega,
  ADD COLUMN IF NOT EXISTS motivo_cancelacion VARCHAR(120) NULL DEFAULT NULL AFTER hora_cancelacion;

ALTER TABLE ruta_paradas
  ADD KEY IF NOT EXISTS idx_parada_pedido (id_pedido);


-- ───────────────────────────────────────────────────────────────────────
-- PASO 3 — Restricción de unicidad en rutas: 1 sola ruta activa por
-- (paseador, fecha). Solo puede aplicarse tras el PASO 1 (sin duplicados).
--
-- Paso previo obligatorio: `id_estado` e `id_paseador` tienen sus FK con
-- ON UPDATE CASCADE, y MySQL/MariaDB no permite que una columna generada
-- (activa_key) dependa de una columna que participa en un FK con esa
-- acción (error 1901). Se cambia a RESTRICT — no afecta nada real, porque
-- ningún código actualiza el id_estado de estados_ruta ni el id_paseador
-- de paseadores (son IDs autoincrementales fijos).
-- ───────────────────────────────────────────────────────────────────────
ALTER TABLE rutas
  DROP FOREIGN KEY fk_ruta_estado;
ALTER TABLE rutas
  ADD CONSTRAINT fk_ruta_estado FOREIGN KEY (id_estado) REFERENCES estados_ruta (id_estado) ON UPDATE RESTRICT;

ALTER TABLE rutas
  DROP FOREIGN KEY fk_ruta_paseador;
ALTER TABLE rutas
  ADD CONSTRAINT fk_ruta_paseador FOREIGN KEY (id_paseador) REFERENCES paseadores (id_paseador) ON DELETE CASCADE ON UPDATE RESTRICT;

ALTER TABLE rutas
  ADD COLUMN IF NOT EXISTS activa_key VARCHAR(30) GENERATED ALWAYS AS (
    CASE WHEN id_estado IN (1,2,3) THEN CONCAT(id_paseador, '-', fecha_paseo) ELSE NULL END
  ) STORED;

-- Nota: si tu versión de MariaDB no acepta "IF NOT EXISTS" en ADD UNIQUE KEY,
-- quita esas 2 palabras de la siguiente línea y corre solo esta (el script
-- es de un solo uso, no hace falta que sea repetible).
ALTER TABLE rutas
  ADD UNIQUE KEY IF NOT EXISTS uq_ruta_activa (activa_key);

-- Prueba de humo (debe fallar con error de duplicado; si falla, el
-- constraint quedó bien puesto). Ejecutar y luego ignorar el error:
-- INSERT INTO rutas (id_admin_creador, id_paseador, id_estado, fecha_paseo, hora_inicio)
-- VALUES (8, 11, 1, (SELECT fecha_paseo FROM rutas WHERE id_ruta = 11), '00:00:00');


-- ───────────────────────────────────────────────────────────────────────
-- PASO 4 — Backfill de las columnas nuevas con datos ya existentes
-- ───────────────────────────────────────────────────────────────────────

-- 4.1 id_pedido: cruce best-effort por (cliente, mascota) contra pedidos_paseo.
--     Si una pareja cliente/mascota tiene más de un pedido, toma el más reciente.
UPDATE ruta_paradas rp
JOIN (
    SELECT rp2.id_parada, pp.id_pedido
    FROM ruta_paradas rp2
    JOIN pedidos_paseo pp
      ON pp.id_usuario = rp2.id_usuario_cliente AND pp.id_mascota = rp2.id_mascota
    WHERE rp2.id_pedido IS NULL
    GROUP BY rp2.id_parada
) x ON x.id_parada = rp.id_parada
SET rp.id_pedido = x.id_pedido;

-- 4.2 hora_recogida: heredar de paseos_dia (para no perder lo ya capturado).
UPDATE ruta_paradas rp
JOIN rutas r ON r.id_ruta = rp.id_ruta
JOIN paseos_dia pd ON pd.id_pedido = rp.id_pedido AND pd.fecha = r.fecha_paseo
SET rp.hora_recogida = pd.hora_recogida
WHERE rp.tipo = 'recogida' AND pd.hora_recogida IS NOT NULL AND rp.hora_recogida IS NULL;

-- 4.3 hora_cancelacion / motivo_cancelacion: heredar de paseos_dia.
UPDATE ruta_paradas rp
JOIN rutas r ON r.id_ruta = rp.id_ruta
JOIN paseos_dia pd ON pd.id_pedido = rp.id_pedido AND pd.fecha = r.fecha_paseo
SET rp.hora_cancelacion = pd.hora_cancelacion, rp.motivo_cancelacion = pd.motivo_cancelacion
WHERE pd.hora_cancelacion IS NOT NULL AND rp.hora_cancelacion IS NULL;

-- No hay backfill de hora_entrega: ningún registro real de paseos_dia
-- tiene estado='entregado' hoy (ese estado nunca se llegó a escribir).


-- ───────────────────────────────────────────────────────────────────────
-- Verificación final: correr y confirmar visualmente
-- ───────────────────────────────────────────────────────────────────────
DESCRIBE ruta_paradas;
DESCRIBE rutas;
SELECT id_ruta, id_paseador, fecha_paseo, id_estado, activa_key FROM rutas ORDER BY id_ruta;
SELECT id_parada, id_ruta, tipo, id_pedido, hora_recogida, hora_entrega, hora_cancelacion, motivo_cancelacion
FROM ruta_paradas ORDER BY id_ruta, orden;
