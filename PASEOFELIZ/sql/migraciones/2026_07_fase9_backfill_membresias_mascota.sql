-- ═══════════════════════════════════════════════════════════════════
-- Fase 9 — Backfill de membresías por mascota
--
-- Antes de la migración de Fase 8 solo existía UNA fila de membresía
-- por usuario (columna id_mascota no existía). Esas filas "legado"
-- quedaron con id_mascota = NULL tras el ALTER. El nuevo flujo de
-- compra (procesar_compra_paseos.php) y el dashboard del cliente
-- (model/estado_servicio_paseos.php) buscan la membresía por el par
-- exacto (id_usuario, id_mascota), así que ningún pedido de paseo
-- comprado ANTES de la Fase 8 encuentra su membresía: NULL nunca es
-- igual a un id_mascota real. Resultado visible: el dashboard de
-- paseos no aparece aunque el cliente tenga un servicio activo.
--
-- Este script crea, para cada pedido de paseo activo (pagado o
-- listo_para_asignar) que todavía no tiene su fila específica en
-- membresias, esa fila con paseos=1 y fecha_inicio_paseos = la fecha
-- real en que se pagó ese pedido. Es idempotente: si se corre dos
-- veces, la segunda vez no inserta nada (NOT EXISTS ya encuentra la
-- fila creada la primera vez).
-- ═══════════════════════════════════════════════════════════════════

INSERT INTO membresias (id_usuario, id_mascota, paseos, fecha_inicio_paseos)
SELECT p.id_usuario, p.id_mascota, 1, p.fecha_creacion
FROM pedidos_paseo p
WHERE p.estado IN ('pagado', 'listo_para_asignar')
  AND NOT EXISTS (
    SELECT 1 FROM membresias m
    WHERE m.id_usuario = p.id_usuario AND m.id_mascota = p.id_mascota
  );

-- Nota: las filas legado (id_mascota = NULL) NO se borran ni se tocan;
-- se dejan existir sin uso (mismo criterio que se usó con paseos_dia
-- en las fases anteriores) porque membresia_estado.php ya las suma en
-- su resumen global por compatibilidad, y no afectan al JOIN por
-- mascota que usa estado_servicio_paseos.php.
