<?php
/**
 * obtener_precios.php
 * Devuelve el precio por unidad (día/sesión/noche) y los descuentos por
 * cantidad de los 3 servicios. Lo consume tanto el wizard del cliente
 * como el panel admin (para calcular el total en vivo).
 */
if (session_status() === PHP_SESSION_NONE) session_start();
include_once 'conexion.php';

header('Content-Type: application/json');

if (!isset($_SESSION['usuario_logged']) || $_SESSION['usuario_logged'] !== true) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$precios = [];
$res = $conn->query("SELECT tipo_membresia, precio_unidad, unidad_label FROM precios_servicios");
while ($row = $res->fetch_assoc()) {
    $precios[$row['tipo_membresia']] = [
        'precio_unidad' => (float)$row['precio_unidad'],
        'unidad_label'  => $row['unidad_label'],
    ];
}

$descuentos = ['paseos' => [], 'adiestramiento' => [], 'hospedaje' => []];
$res2 = $conn->query("SELECT tipo_membresia, cantidad_minima, descuento_pct FROM descuentos_servicios ORDER BY cantidad_minima ASC");
while ($row = $res2->fetch_assoc()) {
    $descuentos[$row['tipo_membresia']][] = [
        'cantidad_minima' => (int)$row['cantidad_minima'],
        'descuento_pct'   => (int)$row['descuento_pct'],
    ];
}

echo json_encode(['success' => true, 'precios' => $precios, 'descuentos' => $descuentos]);
$conn->close();
?>
