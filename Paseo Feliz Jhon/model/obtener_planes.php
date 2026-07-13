<?php
/**
 * obtener_planes.php
 * Devuelve el catálogo de planes de mensualidad de paseos activos.
 * GET sin parámetros. Lo consume el paso 1 del wizard de compra.
 */
header("Access-Control-Allow-Origin: *");
include_once 'helpers.php';
include_once '../model/conexion.php';

verificarSesion();

$res = $conn->query(
    "SELECT id_plan, nombre, paseos_mes, precio_paseo, descuento_pct
     FROM planes_paseos WHERE activo = 1 ORDER BY paseos_mes ASC"
);

if (!$res) responder(false, [], 'Error al consultar los planes: ' . $conn->error);

$planes = [];
while ($row = $res->fetch_assoc()) {
    $precio    = (float)$row['precio_paseo'];
    $cantidad  = (int)$row['paseos_mes'];
    $descPct   = (int)$row['descuento_pct'];
    $subtotal  = $precio * $cantidad;
    $descuento = round($subtotal * $descPct / 100, 2);

    $planes[] = [
        'id_plan'       => (int)$row['id_plan'],
        'nombre'        => $row['nombre'],
        'paseos_mes'    => $cantidad,
        'precio_paseo'  => $precio,
        'descuento_pct' => $descPct,
        'subtotal'      => $subtotal,
        'descuento'     => $descuento,
        'total'         => $subtotal - $descuento,
    ];
}

responder(true, ['planes' => $planes]);
?>
