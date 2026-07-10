<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../model/helpers_membresia.php';
require_once __DIR__ . '/../model/conexion.php';
require_once __DIR__ . '/../model/modelotelegram.php';
require_once __DIR__ . '/../model/precios_helper.php';

header('Content-Type: application/json');

// ── id_usuario: admin lo manda por POST, cliente viene de sesión ─
$id_usuario  = intval($_POST['id_usuario']  ?? $_SESSION['id_usuario'] ?? $_SESSION['id'] ?? 0);
$id_mascota  = intval($_POST['id_mascota']  ?? 0);
$tipo        = trim($_POST['tipo_membresia'] ?? '');
$metodo      = trim($_POST['metodo_pago']    ?? 'manual');
$crearPedido = !empty($_POST['crear_pedido']) && $tipo === 'paseos';

$tiposValidos = ['paseos', 'adiestramiento', 'hospedaje'];

if (!$id_usuario || !$id_mascota || !in_array($tipo, $tiposValidos)) {
    echo json_encode(['success' => false, 'message' => 'Datos incompletos o inválidos. Selecciona usuario, mascota y membresía.']);
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

// ── Verificar que la mascota exista Y sea de ese usuario ─────────
// (evita que, por error o manipulación del request, se le active la
// membresía a la mascota de otra persona)
$stmtMa = $conn->prepare("SELECT id_mascota, nombre_mascota FROM mascota_usuario WHERE id_mascota = ? AND id_usuario = ? LIMIT 1");
$stmtMa->bind_param('ii', $id_mascota, $id_usuario);
$stmtMa->execute();
$mascota = $stmtMa->get_result()->fetch_assoc();
$stmtMa->close();

if (!$mascota) {
    echo json_encode(['success' => false, 'message' => 'Esa mascota no existe o no pertenece a este usuario.']);
    exit;
}

date_default_timezone_set('America/Bogota');
$ahora    = date('Y-m-d H:i:s');
$en30dias = date('Y-m-d H:i:s', strtotime('+30 days'));

$labelTipo = [
    'paseos'         => '🐶 Paseos',
    'adiestramiento' => '🎓 Adiestramiento',
    'hospedaje'      => '🏠 Hospedaje',
];

// ═══════════════════════════════════════════════════════════════════
// RAMA A — Paseos registrado por el ADMIN con pedido real (crear_pedido=1)
// Igual que el wizard del cliente (procesar_compra_paseos.php), pero
// manual: sin datos de tarjeta/PSE, aprobado directamente. Así el pedido
// queda en pedidos_paseo y luego se puede asignar cronograma/paseador.
// ═══════════════════════════════════════════════════════════════════
if ($crearPedido) {
    // El precio por día y los descuentos por cantidad ahora se configuran
    // desde el botón "Precios" del panel admin (tabla precios_servicios /
    // descuentos_servicios) — ya no está fijo en el código.
    $cantidadPaseos = intval($_POST['cantidad_paseos'] ?? 0);

    if ($cantidadPaseos < 1 || $cantidadPaseos > 31) {
        echo json_encode(['success' => false, 'message' => 'La cantidad de paseos al mes debe estar entre 1 y 31.']);
        exit;
    }

    $modalidad = in_array($_POST['modalidad'] ?? '', ['individual', 'grupal']) ? $_POST['modalidad'] : 'grupal';
    $duracion  = in_array(intval($_POST['duracion_min'] ?? 60), [30, 60, 120, 180]) ? intval($_POST['duracion_min']) : 60;
    $dias      = substr(trim($_POST['dias_preferidos'] ?? ''), 0, 60);
    $franja    = substr(trim($_POST['franja_horaria'] ?? ''), 0, 40);
    $direccion = trim($_POST['direccion'] ?? '');
    $barrio    = substr(trim($_POST['barrio'] ?? ''), 0, 100);
    $referencia= substr(trim($_POST['referencia'] ?? ''), 0, 255);
    $instrucciones = substr(trim($_POST['instrucciones'] ?? ''), 0, 255);
    $lat = floatval($_POST['lat'] ?? 0);
    $lng = floatval($_POST['lng'] ?? 0);
    $fechaInicio = trim($_POST['fecha_inicio'] ?? '');

    if ($direccion === '') {
        echo json_encode(['success' => false, 'message' => 'La dirección de recogida es obligatoria.']);
        exit;
    }
    if (!$lat || !$lng) {
        echo json_encode(['success' => false, 'message' => 'Marca la ubicación de recogida en el mapa.']);
        exit;
    }
    $d = DateTime::createFromFormat('Y-m-d', $fechaInicio);
    if (!$d) {
        echo json_encode(['success' => false, 'message' => 'La fecha de inicio no es válida.']);
        exit;
    }

    $precio = calcularPrecioServicio($conn, 'paseos', $cantidadPaseos);
    if (!$precio) {
        echo json_encode(['success' => false, 'message' => 'No hay un precio configurado para Paseos. Configúralo con el botón "Precios".']);
        exit;
    }
    $subtotal  = $precio['subtotal'];
    $descuento = $precio['descuento'];
    $total     = $precio['total'];

    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare(
            "INSERT INTO pedidos_paseo
                (id_usuario, id_mascota, cantidad_paseos, modalidad, duracion_min, dias_preferidos,
                 franja_horaria, fecha_inicio, direccion, barrio, referencia, instrucciones,
                 lat, lng, ubicacion_validada, subtotal, descuento, total, metodo_pago, estado)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?, ?, 'nequi', 'listo_para_asignar')"
        );
        $stmt->bind_param(
            'iiisisssssssddddd',
            $id_usuario, $id_mascota, $cantidadPaseos, $modalidad, $duracion, $dias,
            $franja, $fechaInicio, $direccion, $barrio, $referencia, $instrucciones,
            $lat, $lng, $subtotal, $descuento, $total
        );
        $stmt->execute();
        $idPedido = $conn->insert_id;
        $stmt->close();

        $referenciaPago = 'PF-ADMIN-' . strtoupper(bin2hex(random_bytes(5)));

        $stmtP = $conn->prepare(
            "INSERT INTO pagos (id_usuario, id_mascota, id_pedido, tipo_membresia, monto, metodo_pago, metodo, estado_pago, referencia)
             VALUES (?, ?, ?, 'paseos', ?, ?, 'nequi', 'aprobado', ?)"
        );
        $stmtP->bind_param('iiidss', $id_usuario, $id_mascota, $idPedido, $total, $metodo, $referenciaPago);
        $stmtP->execute();
        $id_pago = $conn->insert_id;
        $stmtP->close();

        // Activar membresía de paseos para esta mascota (30 días desde hoy).
        // fecha_fin_paseos es columna real (renovable): aquí, inicio + 30 días.
        $sqlMem = "
            INSERT INTO membresias (id_usuario, id_mascota, paseos, fecha_inicio_paseos, fecha_fin_paseos, id_pago_paseos)
            VALUES (?, ?, 1, ?, DATE_ADD(?, INTERVAL 30 DAY), ?)
            ON DUPLICATE KEY UPDATE
                paseos               = 1,
                id_mascota           = VALUES(id_mascota),
                fecha_inicio_paseos  = VALUES(fecha_inicio_paseos),
                fecha_fin_paseos     = VALUES(fecha_fin_paseos),
                id_pago_paseos       = VALUES(id_pago_paseos)
        ";
        $stmtM = $conn->prepare($sqlMem);
        $stmtM->bind_param('iissi', $id_usuario, $id_mascota, $ahora, $ahora, $id_pago);
        $stmtM->execute();
        $stmtM->close();

        $conn->commit();
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Error al crear el pedido de paseos: ' . $e->getMessage()]);
        exit;
    }

    $telegram = new ModeloTelegram();
    $telegram->enviarMensajePagos(
        "💳 <b>Nuevo pago registrado (admin, con pedido)</b>\n\n" .
        "👤 <b>Usuario:</b> " . htmlspecialchars($usuario['nombre']) . "\n" .
        "✉️ <b>Email:</b> "   . htmlspecialchars($usuario['email'])  . "\n" .
        "🐾 <b>Mascota:</b> " . htmlspecialchars($mascota['nombre_mascota']) . "\n" .
        "📦 <b>Membresía:</b> 🐶 Paseos\n" .
        "📆 <b>Días solicitados:</b> " . $cantidadPaseos . " paseos al mes\n" .
        ($precio['descuento_pct'] > 0 ? "🏷️ <b>Descuento aplicado:</b> " . $precio['descuento_pct'] . "%\n" : "") .
        "💰 <b>Monto:</b> $" . number_format($total, 0, '.', ',') . " COP\n" .
        "🔧 <b>Método:</b> " . htmlspecialchars($metodo) . "\n" .
        "📅 <b>Vigente hasta:</b> " . date('d/m/Y', strtotime($en30dias)) . "\n" .
        "🕒 <b>Fecha pago:</b> "    . date('d/m/Y H:i', strtotime($ahora))
    );

    echo json_encode([
        'success'        => true,
        'message'        => 'Pedido y pago registrados. Membresía de paseos activada para ' . $mascota['nombre_mascota'] . '.',
        'id_pedido'      => $idPedido,
        'id_pago'        => $id_pago,
        'id_mascota'     => $id_mascota,
        'nombre_mascota' => $mascota['nombre_mascota'],
        'vigente_hasta'  => $en30dias,
    ]);
    $conn->close();
    exit;
}

// ═══════════════════════════════════════════════════════════════════
// RAMA B — flujo simple existente (Adiestramiento / Hospedaje, o Paseos
// sin crear_pedido): solo activa la membresía, sin pedido asociado.
// ═══════════════════════════════════════════════════════════════════
$monto = floatval($_POST['monto'] ?? 0);
if ($monto <= 0) {
    echo json_encode(['success' => false, 'message' => 'Monto inválido.']);
    exit;
}

// ── Insertar pago (ligado a la mascota) ──────────────────────────
$stmtP = $conn->prepare("
    INSERT INTO pagos (id_usuario, id_mascota, tipo_membresia, monto, metodo_pago)
    VALUES (?, ?, ?, ?, ?)
");
$stmtP->bind_param('iisds', $id_usuario, $id_mascota, $tipo, $monto, $metodo);
if (!$stmtP->execute()) {
    echo json_encode(['success' => false, 'message' => 'Error al guardar el pago: ' . $stmtP->error]);
    $stmtP->close();
    exit;
}
$id_pago = $stmtP->insert_id;
$stmtP->close();

// ── Verificación de seguridad: confirmar que el ENUM guardó bien el tipo ─
$check = $conn->prepare("SELECT tipo_membresia FROM pagos WHERE id_pago = ? LIMIT 1");
$check->bind_param('i', $id_pago);
$check->execute();
$rowCheck = $check->get_result()->fetch_assoc();
$check->close();

if (!$rowCheck || empty($rowCheck['tipo_membresia'])) {
    $del = $conn->prepare("DELETE FROM pagos WHERE id_pago = ?");
    $del->bind_param('i', $id_pago);
    $del->execute();
    $del->close();

    echo json_encode([
        'success' => false,
        'message' => 'Error al guardar el tipo de membresía. Intenta de nuevo. (tipo recibido: "' . $tipo . '")',
    ]);
    exit;
}

$colFlag   = $tipo;
$colInicio = "fecha_inicio_{$tipo}";
$colPago   = "id_pago_{$tipo}";

// ── Upsert membresía: la fila es por (usuario, MASCOTA) ──────────
$sqlMem = "
    INSERT INTO membresias (id_usuario, id_mascota, {$colFlag}, {$colInicio}, {$colPago})
    VALUES (?, ?, 1, ?, ?)
    ON DUPLICATE KEY UPDATE
        {$colFlag}   = 1,
        id_mascota   = VALUES(id_mascota),
        {$colInicio} = VALUES({$colInicio}),
        {$colPago}   = VALUES({$colPago})
";
$stmtM = $conn->prepare($sqlMem);
$stmtM->bind_param('iisi', $id_usuario, $id_mascota, $ahora, $id_pago);
if (!$stmtM->execute()) {
    echo json_encode(['success' => false, 'message' => 'Pago guardado pero error al activar membresía: ' . $stmtM->error]);
    $stmtM->close();
    exit;
}
$stmtM->close();

$telegram = new ModeloTelegram();
$telegram->enviarMensajePagos(
    "💳 <b>Nuevo pago registrado</b>\n\n" .
    "👤 <b>Usuario:</b> "      . htmlspecialchars($usuario['nombre']) . "\n" .
    "✉️ <b>Email:</b> "        . htmlspecialchars($usuario['email'])  . "\n" .
    "🐾 <b>Mascota:</b> "      . htmlspecialchars($mascota['nombre_mascota']) . "\n" .
    "📦 <b>Membresía:</b> "    . $labelTipo[$tipo] . "\n" .
    "💰 <b>Monto:</b> $"       . number_format($monto, 0, '.', ',') . " COP\n" .
    "🔧 <b>Método:</b> "       . htmlspecialchars($metodo) . "\n" .
    "📅 <b>Vigente hasta:</b> ". date('d/m/Y', strtotime($en30dias)) . "\n" .
    "🕒 <b>Fecha pago:</b> "   . date('d/m/Y H:i', strtotime($ahora))
);

echo json_encode([
    'success'        => true,
    'message'        => 'Pago registrado y membresía activada correctamente para ' . $mascota['nombre_mascota'] . '.',
    'id_pago'        => $id_pago,
    'id_mascota'     => $id_mascota,
    'nombre_mascota' => $mascota['nombre_mascota'],
    'vigente_hasta'  => $en30dias,
]);

$conn->close();