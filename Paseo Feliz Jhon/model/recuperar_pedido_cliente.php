<?php
/**
 * recuperar_pedido_cliente.php
 * (CLIENTE) Un servicio cancelado (por el admin o por quien sea) se puede
 * recuperar SIN pagar de nuevo, porque ya se pagó: el pedido vuelve a
 * 'listo_para_asignar' con su configuración original y la membresía de esa
 * mascota se reactiva. Solo aplica a pedidos que tengan un pago aprobado
 * asociado — un pedido cancelado que nunca se pagó no da derecho a nada.
 *
 * GET  ?tipo=paseos|adiestramiento|hospedaje
 *      → lista los pedidos recuperables del usuario en sesión:
 *        { success, pedidos: [ { id_pedido, mascota, motivo, fecha_cancelacion } ] }
 *
 * POST JSON { tipo, id_pedido }
 *      → recupera ese pedido (valida dueño + cancelado + pagado).
 */
header("Access-Control-Allow-Origin: *");
include_once 'helpers.php';
include_once '../model/conexion.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

verificarSesion();
$idUsuario = (int)$_SESSION['usuario_id'];

// Por servicio: tabla de pedidos, columna FK en pagos y columna de membresía.
// Whitelist fija — el valor de $tipo nunca se interpola sin pasar por aquí.
$CFG = [
    'paseos'         => ['tabla' => 'pedidos_paseo',          'fk_pago' => 'id_pedido',                'membresia' => 'paseos'],
    'adiestramiento' => ['tabla' => 'pedidos_adiestramiento', 'fk_pago' => 'id_pedido_adiestramiento', 'membresia' => 'adiestramiento'],
    'hospedaje'      => ['tabla' => 'pedidos_hospedaje',      'fk_pago' => 'id_pedido_hospedaje',      'membresia' => 'hospedaje'],
];

$tipo = $_SERVER['REQUEST_METHOD'] === 'POST'
    ? (leerJsonBody()['tipo'] ?? '')
    : ($_GET['tipo'] ?? '');
if (!isset($CFG[$tipo])) responder(false, [], 'Tipo de servicio no válido.');
$cfg = $CFG[$tipo];

// ── GET: listar pedidos recuperables ───────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $stmt = $conn->prepare(
        "SELECT p.id_pedido, p.motivo_cancelacion, p.fecha_cancelacion, mu.nombre_mascota
         FROM `{$cfg['tabla']}` p
         LEFT JOIN mascota_usuario mu ON mu.id_mascota = p.id_mascota
         WHERE p.id_usuario = ? AND p.estado = 'cancelado'
           AND EXISTS (SELECT 1 FROM pagos pg
                       WHERE pg.`{$cfg['fk_pago']}` = p.id_pedido AND pg.estado_pago = 'aprobado')
         ORDER BY p.fecha_cancelacion DESC"
    );
    $stmt->bind_param("i", $idUsuario);
    $stmt->execute();
    $res = $stmt->get_result();
    $pedidos = [];
    while ($row = $res->fetch_assoc()) {
        $pedidos[] = [
            'id_pedido'         => (int)$row['id_pedido'],
            'mascota'           => $row['nombre_mascota'] ?: 'tu mascota',
            'motivo'            => $row['motivo_cancelacion'] ?: '',
            'fecha_cancelacion' => $row['fecha_cancelacion'],
        ];
    }
    $stmt->close();
    responder(true, ['pedidos' => $pedidos]);
}

// ── POST: recuperar un pedido ──────────────────────────────────────────
$data     = leerJsonBody();
$idPedido = intval($data['id_pedido'] ?? 0);
if (!$idPedido) responder(false, [], 'id_pedido requerido.');

$stmt = $conn->prepare(
    "SELECT p.id_pedido, p.id_usuario, p.id_mascota, p.estado, mu.nombre_mascota
     FROM `{$cfg['tabla']}` p
     LEFT JOIN mascota_usuario mu ON mu.id_mascota = p.id_mascota
     WHERE p.id_pedido = ? AND p.id_usuario = ?"
);
$stmt->bind_param("ii", $idPedido, $idUsuario);
$stmt->execute();
$pedido = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$pedido) responder(false, [], 'Ese pedido no existe o no es tuyo.');
if ($pedido['estado'] !== 'cancelado') responder(false, [], 'Ese pedido no está cancelado.');

// Debe existir un pago aprobado para este pedido (ya lo pagaste)
$stmt = $conn->prepare(
    "SELECT 1 FROM pagos WHERE `{$cfg['fk_pago']}` = ? AND estado_pago = 'aprobado' LIMIT 1"
);
$stmt->bind_param("i", $idPedido);
$stmt->execute();
$pagado = $stmt->get_result()->num_rows > 0;
$stmt->close();
if (!$pagado) responder(false, [], 'Este pedido no tiene un pago aprobado, no se puede recuperar sin costo.');

$idMascota = (int)$pedido['id_mascota'];
$mascota   = $pedido['nombre_mascota'] ?: 'tu mascota';

$conn->begin_transaction();
try {
    $stmt = $conn->prepare(
        "UPDATE `{$cfg['tabla']}`
         SET estado = 'listo_para_asignar', motivo_cancelacion = NULL,
             cancelado_por = NULL, fecha_cancelacion = NULL
         WHERE id_pedido = ?"
    );
    $stmt->bind_param("i", $idPedido);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare(
        "UPDATE membresias SET `{$cfg['membresia']}` = 1 WHERE id_usuario = ? AND id_mascota = ?"
    );
    $stmt->bind_param("ii", $idUsuario, $idMascota);
    $stmt->execute();
    $stmt->close();

    $conn->commit();
    responder(true, ['id_pedido' => $idPedido],
        "¡Listo! El servicio de $mascota fue recuperado. El equipo volverá a asignarlo pronto.");
} catch (Exception $e) {
    $conn->rollback();
    responder(false, [], 'Error al recuperar el pedido: ' . $e->getMessage());
}
?>
