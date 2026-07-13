<?php
/**
 * guardar_precios.php (controller/)
 * Solo el admin puede cambiar el precio por unidad y los descuentos por
 * cantidad de un servicio (paseos/adiestramiento/hospedaje).
 *
 * POST:
 *   tipo_membresia = paseos|adiestramiento|hospedaje
 *   precio_unidad  = 18000
 *   descuentos     = JSON: [{"cantidad_minima":8,"descuento_pct":5}, ...]
 */
ini_set('display_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../model/conexion.php';

header('Content-Type: application/json');

// Mismo criterio de admin que usa el resto del panel
if (!isset($_SESSION['usuario_logged']) || $_SESSION['usuario_logged'] !== true
    || !isset($_SESSION['usuario_admin']) || $_SESSION['usuario_admin'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Acceso restringido a administradores.']);
    exit;
}

$tipo         = trim($_POST['tipo_membresia'] ?? '');
$precioUnidad = floatval($_POST['precio_unidad'] ?? 0);
$descuentosJson = $_POST['descuentos'] ?? '[]';

$tiposValidos = ['paseos', 'adiestramiento', 'hospedaje'];
if (!in_array($tipo, $tiposValidos) || $precioUnidad <= 0) {
    echo json_encode(['success' => false, 'message' => 'Datos inválidos.']);
    exit;
}

$descuentos = json_decode($descuentosJson, true);
if (!is_array($descuentos)) $descuentos = [];

$conn->begin_transaction();
try {
    // 1. Precio por unidad (upsert)
    $stmt = $conn->prepare("
        INSERT INTO precios_servicios (tipo_membresia, precio_unidad)
        VALUES (?, ?)
        ON DUPLICATE KEY UPDATE precio_unidad = VALUES(precio_unidad)
    ");
    $stmt->bind_param('sd', $tipo, $precioUnidad);
    $stmt->execute();
    $stmt->close();

    // 2. Descuentos: se reemplazan todos los de este tipo (más simple y
    //    seguro que intentar hacer diffs fila por fila)
    $del = $conn->prepare("DELETE FROM descuentos_servicios WHERE tipo_membresia = ?");
    $del->bind_param('s', $tipo);
    $del->execute();
    $del->close();

    if (!empty($descuentos)) {
        $ins = $conn->prepare("INSERT INTO descuentos_servicios (tipo_membresia, cantidad_minima, descuento_pct) VALUES (?, ?, ?)");
        foreach ($descuentos as $d) {
            $cantMin = intval($d['cantidad_minima'] ?? 0);
            $pct     = intval($d['descuento_pct'] ?? 0);
            if ($cantMin <= 0 || $pct <= 0 || $pct > 100) continue; // fila inválida, se ignora
            $ins->bind_param('sii', $tipo, $cantMin, $pct);
            $ins->execute();
        }
        $ins->close();
    }

    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Precio y descuentos guardados correctamente.']);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Error al guardar: ' . $e->getMessage()]);
}

$conn->close();
?>
