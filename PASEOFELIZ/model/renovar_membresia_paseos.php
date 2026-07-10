<?php
/**
 * renovar_membresia_paseos.php
 * (CLIENTE) Renueva la mensualidad de paseos de UNA mascota.
 *
 * Política acordada: la renovación EXTIENDE la vigencia desde el
 * vencimiento actual — renovar antes de vencer no pierde días:
 *   - membresía vigente  -> fecha_fin = fecha_fin + 30 días
 *   - membresía vencida  -> fecha_fin = ahora + 30 días (reactiva)
 * El pedido original se conserva (misma configuración, mismo cronograma,
 * mismo paseador): renovar NO pasa por el wizard de compra, que bloquea
 * a las mascotas con membresía activa.
 *
 * El precio se calcula SIEMPRE en el servidor con la configuración
 * vigente del admin (precios_servicios / descuentos_servicios), sobre la
 * cantidad de paseos del pedido original.
 *
 * POST JSON: { "id_pedido": 5 }
 * Respuesta: { success, total, referencia, fecha_fin }
 */
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
include_once 'helpers.php';
include_once '../model/conexion.php';
include_once '../model/modelotelegram.php';
include_once 'precios_helper.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
date_default_timezone_set('America/Bogota');

verificarSesion();
$idUsuario = (int)$_SESSION['usuario_id'];

$data     = leerJsonBody();
$idPedido = intval($data['id_pedido'] ?? 0);
if (!$idPedido) responder(false, [], 'id_pedido requerido.');

// ── 1. El pedido debe ser del usuario, no cancelado, y con membresía ──
$stmt = $conn->prepare(
    "SELECT p.id_pedido, p.id_mascota, p.cantidad_paseos, p.metodo_pago,
            m.fecha_fin_paseos, mu.nombre_mascota, u.nombre AS nombre_usuario, u.email
     FROM pedidos_paseo p
     JOIN membresias m       ON m.id_usuario = p.id_usuario AND m.id_mascota = p.id_mascota
     JOIN mascota_usuario mu ON mu.id_mascota = p.id_mascota
     JOIN usuarios u         ON u.id = p.id_usuario
     WHERE p.id_pedido = ? AND p.id_usuario = ?
       AND p.estado IN ('pagado', 'listo_para_asignar', 'en_validacion')"
);
$stmt->bind_param("ii", $idPedido, $idUsuario);
$stmt->execute();
$pedido = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$pedido) responder(false, [], 'No se encontró un servicio renovable para este pedido.');

$idMascota      = (int)$pedido['id_mascota'];
$cantidadPaseos = (int)$pedido['cantidad_paseos'];

// ── 2. Precio vigente configurado por el admin ────────────────────────
$precio = calcularPrecioServicio($conn, 'paseos', $cantidadPaseos);
if (!$precio) responder(false, [], 'El servicio de Paseos no tiene un precio configurado. Contacta al administrador.');
$total = $precio['total'];

// ── 3. Procesar el pago (misma capa simulada de la compra) ────────────
// Al integrar una pasarela real, reemplazar SOLO esta llamada.
$referencia = 'PF-RENOV-' . strtoupper(bin2hex(random_bytes(5)));

$conn->begin_transaction();
try {
    // 3.1 Registrar el pago de la renovación
    $metodo = in_array($pedido['metodo_pago'], ['tarjeta', 'pse']) ? $pedido['metodo_pago'] : 'tarjeta';
    $stmt = $conn->prepare(
        "INSERT INTO pagos (id_pedido, id_usuario, id_mascota, metodo, monto, estado_pago, referencia, titular)
         VALUES (?, ?, ?, ?, ?, 'aprobado', ?, ?)"
    );
    $stmt->bind_param("iiisdss", $idPedido, $idUsuario, $idMascota, $metodo, $total,
                      $referencia, $pedido['nombre_usuario']);
    $stmt->execute();
    $idPago = $conn->insert_id;
    $stmt->close();

    // 3.2 Extender la vigencia: desde el vencimiento si sigue vigente,
    //     desde ahora si ya venció (reactivación). Se compara en hora
    //     Colombia, igual que el resto de endpoints de vigencia.
    $ahoraColombia = "CONVERT_TZ(NOW(), '+00:00', '-05:00')";
    $stmt = $conn->prepare(
        "UPDATE membresias
         SET paseos = 1,
             fecha_fin_paseos = DATE_ADD(
                 IF(fecha_fin_paseos IS NOT NULL AND fecha_fin_paseos > $ahoraColombia,
                    fecha_fin_paseos, $ahoraColombia),
                 INTERVAL 30 DAY),
             id_pago_paseos = ?
         WHERE id_usuario = ? AND id_mascota = ?"
    );
    $stmt->bind_param("iii", $idPago, $idUsuario, $idMascota);
    $stmt->execute();
    $filas = $stmt->affected_rows;
    $stmt->close();

    if ($filas === 0) {
        throw new Exception('No se encontró la membresía de esta mascota.');
    }

    // 3.3 Leer la nueva fecha de vencimiento para devolverla al front
    $stmt = $conn->prepare(
        "SELECT fecha_fin_paseos FROM membresias WHERE id_usuario = ? AND id_mascota = ?"
    );
    $stmt->bind_param("ii", $idUsuario, $idMascota);
    $stmt->execute();
    $fechaFin = $stmt->get_result()->fetch_assoc()['fecha_fin_paseos'];
    $stmt->close();

    $conn->commit();
} catch (Exception $e) {
    $conn->rollback();
    responder(false, [], 'Error al procesar la renovación: ' . $e->getMessage());
}

$telegram = new ModeloTelegram();
$telegram->enviarMensajePagos(
    "🔄 <b>Renovación de membresía (paseos)</b>\n\n" .
    "👤 <b>Usuario:</b> " . htmlspecialchars($pedido['nombre_usuario']) . "\n" .
    "✉️ <b>Email:</b> "   . htmlspecialchars($pedido['email']) . "\n" .
    "🐾 <b>Mascota:</b> " . htmlspecialchars($pedido['nombre_mascota']) . "\n" .
    "📆 <b>Plan:</b> " . $cantidadPaseos . " paseos al mes\n" .
    "💰 <b>Monto:</b> $" . number_format($total, 0, '.', ',') . " COP\n" .
    "📅 <b>Nueva vigencia hasta:</b> " . date('d/m/Y', strtotime($fechaFin)) . "\n" .
    "🕒 <b>Fecha pago:</b> " . date('d/m/Y H:i')
);

responder(true, [
    'total'      => $total,
    'referencia' => $referencia,
    'fecha_fin'  => $fechaFin,
], 'Membresía renovada. Nueva vigencia hasta el ' . date('d/m/Y', strtotime($fechaFin)) . '.');
?>
