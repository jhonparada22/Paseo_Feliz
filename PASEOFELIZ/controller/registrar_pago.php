<?php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../model/helpers_membresia.php';
require_once __DIR__ . '/../model/conexion.php';
require_once __DIR__ . '/../model/modelotelegram.php';

header('Content-Type: application/json');

// ── id_usuario: admin lo manda por POST, cliente viene de sesión ─
$id_usuario = intval($_POST['id_usuario'] ?? $_SESSION['id_usuario'] ?? $_SESSION['id'] ?? 0);
$tipo       = trim($_POST['tipo_membresia'] ?? '');
$monto      = floatval($_POST['monto']      ?? 0);
$metodo     = trim($_POST['metodo_pago']    ?? 'manual');

$tiposValidos = ['paseos', 'adiestramiento', 'hospedaje'];

if (!$id_usuario || !in_array($tipo, $tiposValidos) || $monto <= 0) {
    echo json_encode(['success' => false, 'message' => 'Datos incompletos o inválidos.']);
    exit;
}

// ── Verificar que el usuario existe ─────────────────────────────
$stmtU = $conn->prepare("SELECT id, nombre, email FROM usuarios WHERE id = ? LIMIT 1");
$stmtU->bind_param('i', $id_usuario);
$stmtU->execute();
$usuario = $stmtU->get_result()->fetch_assoc();
$stmtU->close();

if (!$usuario) {
    echo json_encode(['success' => false, 'message' => 'Usuario no encontrado.']);
    exit;
}

// ── Insertar pago ────────────────────────────────────────────────
$stmtP = $conn->prepare("
    INSERT INTO pagos (id_usuario, tipo_membresia, monto, metodo_pago)
    VALUES (?, ?, ?, ?)
");
$stmtP->bind_param('idss', $id_usuario, $tipo, $monto, $metodo);
if (!$stmtP->execute()) {
    echo json_encode(['success' => false, 'message' => 'Error al guardar el pago: ' . $stmtP->error]);
    $stmtP->close();
    exit;
}
$id_pago = $stmtP->insert_id;
$stmtP->close();

// ── Calcular fechas (+30 días) ───────────────────────────────────
date_default_timezone_set('America/Bogota');
$ahora    = date('Y-m-d H:i:s');
$en30dias = date('Y-m-d H:i:s', strtotime('+30 days'));

$colFlag   = $tipo;
$colInicio = "fecha_inicio_{$tipo}";
$colFin    = "fecha_fin_{$tipo}";
$colPago   = "id_pago_{$tipo}";

// ── Upsert membresía ─────────────────────────────────────────────
$sqlMem = "
    INSERT INTO membresias (id_usuario, {$colFlag}, {$colInicio}, {$colFin}, {$colPago})
    VALUES (?, 1, ?, ?, ?)
    ON DUPLICATE KEY UPDATE
        {$colFlag}   = 1,
        {$colInicio} = VALUES({$colInicio}),
        {$colFin}    = VALUES({$colFin}),
        {$colPago}   = VALUES({$colPago})
";
$stmtM = $conn->prepare($sqlMem);
$stmtM->bind_param('issi', $id_usuario, $ahora, $en30dias, $id_pago);
if (!$stmtM->execute()) {
    echo json_encode(['success' => false, 'message' => 'Pago guardado pero error al activar membresía: ' . $stmtM->error]);
    $stmtM->close();
    exit;
}
$stmtM->close();

// ── Notificación Telegram ────────────────────────────────────────
$labelTipo = [
    'paseos'         => '🐶 Paseos',
    'adiestramiento' => '🎓 Adiestramiento',
    'hospedaje'      => '🏠 Hospedaje',
];

$telegram = new ModeloTelegram();
$telegram->enviarMensajePagos(
    "💳 <b>Nuevo pago registrado</b>\n\n" .
    "👤 <b>Usuario:</b> "      . htmlspecialchars($usuario['nombre']) . "\n" .
    "✉️ <b>Email:</b> "        . htmlspecialchars($usuario['email'])  . "\n" .
    "📦 <b>Membresía:</b> "    . $labelTipo[$tipo] . "\n" .
    "💰 <b>Monto:</b> $"       . number_format($monto, 0, '.', ',') . " COP\n" .
    "🔧 <b>Método:</b> "       . htmlspecialchars($metodo) . "\n" .
    "📅 <b>Vigente hasta:</b> ". date('d/m/Y', strtotime($en30dias)) . "\n" .
    "🕒 <b>Fecha pago:</b> "   . date('d/m/Y H:i', strtotime($ahora))
);

echo json_encode([
    'success'       => true,
    'message'       => 'Pago registrado y membresía activada correctamente.',
    'id_pago'       => $id_pago,
    'vigente_hasta' => $en30dias,
]);

$conn->close();
