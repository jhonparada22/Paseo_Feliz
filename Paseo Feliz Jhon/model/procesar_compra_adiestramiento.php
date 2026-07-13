<?php
/**
 * procesar_compra_adiestramiento.php
 * Paso 4 del wizard "Contratar Adiestramiento canino".
 * Mismo patrón que procesar_compra_paseos.php: registra el pedido,
 * procesa el pago (capa simulada) y activa la membresía de adiestramiento.
 *
 * POST JSON esperado: igual que paseos, pero "cantidad_sesiones" en vez
 * de "cantidad_paseos".
 */
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
include_once 'helpers.php';
include_once '../model/conexion.php';
include_once '../model/modelotelegram.php';
include_once 'precios_helper.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

verificarSesion();

function procesarPagoSimuladoAdi($metodo, $monto, $datosPago) {
    return [
        'aprobado'   => true,
        'referencia' => 'PF-SIM-' . strtoupper(bin2hex(random_bytes(5))),
        'mensaje'    => 'Pago aprobado (simulación, sin pasarela real)',
    ];
}

define('MIN_SESIONES_MES', 1);
define('MAX_SESIONES_MES', 31);

$idUsuario = (int)$_SESSION['usuario_id'];
$data      = leerJsonBody();

$idMascota        = intval($data['id_mascota'] ?? 0);
$cantidadSesiones = intval($data['cantidad_sesiones'] ?? 0);
$ubicacion = $data['ubicacion'] ?? [];
$pago      = $data['pago'] ?? [];
$fact      = $data['facturacion'] ?? [];
$conf      = $data['confirmaciones'] ?? [];

// ═══════════════════════════════════════════════════════════════════
// MODO EXPRÉS: "añadir otra mascota al servicio activo"
// Si viene pedido_base, la nueva mascota HEREDA la configuración del
// pedido activo (cantidad de sesiones, días, franja, duración y
// dirección) leída de la BD — lo que mande el cliente para esos campos
// se ignora. La nueva mascota paga y activa SU PROPIA membresía.
// ═══════════════════════════════════════════════════════════════════
$idPedidoBase = intval($data['pedido_base'] ?? 0);
$pedidoBase   = null;

if ($idPedidoBase > 0) {
    $ahoraColombiaBase = "CONVERT_TZ(NOW(), '+00:00', '-05:00')";
    $stmt = $conn->prepare(
        "SELECT p.id_pedido, p.cantidad_sesiones, p.duracion_min, p.dias_preferidos,
                p.franja_horaria, p.comportamiento,
                p.direccion, p.barrio, p.referencia, p.instrucciones, p.lat, p.lng
         FROM pedidos_adiestramiento p
         JOIN membresias m ON m.id_usuario = p.id_usuario AND m.id_mascota = p.id_mascota
         WHERE p.id_pedido = ? AND p.id_usuario = ?
           AND p.estado IN ('pagado', 'listo_para_asignar')
           AND m.adiestramiento = 1
           AND m.fecha_inicio_adiestramiento IS NOT NULL
           AND DATE_ADD(m.fecha_inicio_adiestramiento, INTERVAL 30 DAY) > $ahoraColombiaBase"
    );
    $stmt->bind_param("ii", $idPedidoBase, $idUsuario);
    $stmt->execute();
    $pedidoBase = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$pedidoBase) {
        responder(false, [], 'El servicio activo al que intentas unir la mascota no es válido o ya venció.');
    }

    // La mascota nueva no puede tener ya un servicio de adiestramiento activo
    $stmt = $conn->prepare(
        "SELECT id_pedido FROM pedidos_adiestramiento
         WHERE id_mascota = ? AND estado IN ('pagado', 'listo_para_asignar') LIMIT 1"
    );
    $stmt->bind_param("i", $idMascota);
    $stmt->execute();
    $yaActivaExp = $stmt->get_result()->num_rows > 0;
    $stmt->close();
    if ($yaActivaExp) {
        responder(false, [], 'Esta mascota ya tiene un servicio de adiestramiento activo.');
    }

    // Heredar configuración del pedido base (fuente de verdad: la BD)
    $cantidadSesiones          = (int)$pedidoBase['cantidad_sesiones'];
    $data['duracion_min']      = (int)$pedidoBase['duracion_min'];
    $data['dias_preferidos']   = $pedidoBase['dias_preferidos'];
    $data['franja_horaria']    = $pedidoBase['franja_horaria'];
    $data['comportamiento']    = $pedidoBase['comportamiento'];
    $data['fecha_inicio']      = date('Y-m-d');
    $ubicacion = [
        'direccion'     => $pedidoBase['direccion'],
        'barrio'        => $pedidoBase['barrio'],
        'referencia'    => $pedidoBase['referencia'],
        'instrucciones' => $pedidoBase['instrucciones'],
        'lat'           => (float)$pedidoBase['lat'],
        'lng'           => (float)$pedidoBase['lng'],
        'validada'      => true, // ya se validó al comprar el servicio base
    ];
}

if ($cantidadSesiones < MIN_SESIONES_MES || $cantidadSesiones > MAX_SESIONES_MES) {
    responder(false, [], 'La cantidad de sesiones al mes debe estar entre ' . MIN_SESIONES_MES . ' y ' . MAX_SESIONES_MES . '.');
}

// ── ANTI DOBLE COMPRA (todos los flujos, no solo el exprés) ──────────
if (!$pedidoBase) {
    $stmt = $conn->prepare(
        "SELECT id_pedido FROM pedidos_adiestramiento
         WHERE id_mascota = ? AND id_usuario = ? AND estado IN ('pagado', 'listo_para_asignar') LIMIT 1"
    );
    $stmt->bind_param("ii", $idMascota, $idUsuario);
    $stmt->execute();
    $yaActiva = $stmt->get_result()->num_rows > 0;
    $stmt->close();
    if ($yaActiva) {
        responder(false, [], 'Esta mascota ya tiene un servicio de adiestramiento activo.');
    }
}

if (empty($conf['datos']) || empty($conf['terminos']) || empty($conf['autorizo'])) {
    responder(false, [], 'Debes aceptar todas las confirmaciones para continuar.');
}

$metodo = $pago['metodo'] ?? '';
if (!in_array($metodo, ['tarjeta', 'pse'])) {
    responder(false, [], $metodo === 'nequi'
        ? 'Nequi / Daviplata estará disponible próximamente.'
        : 'Método de pago no válido.');
}

$titular = trim($pago['titular'] ?? '');
if ($titular === '') responder(false, [], 'El nombre del titular es obligatorio.');
if ($metodo === 'tarjeta' && !preg_match('/^\d{4}$/', $pago['ultimos4'] ?? '')) {
    responder(false, [], 'Datos de tarjeta incompletos.');
}
if ($metodo === 'pse') {
    if (empty($pago['banco']) || empty($pago['documento']) || empty($pago['email_confirmacion'])) {
        responder(false, [], 'Completa los datos de PSE (banco, documento y correo).');
    }
}

$lat = floatval($ubicacion['lat'] ?? 0);
$lng = floatval($ubicacion['lng'] ?? 0);
$direccion = trim($ubicacion['direccion'] ?? '');
if (!$lat || !$lng || $direccion === '' || empty($ubicacion['validada'])) {
    responder(false, [], 'La ubicación no ha sido confirmada. Vuelve al paso 2.');
}

$fechaInicio = $data['fecha_inicio'] ?? '';
$d = DateTime::createFromFormat('Y-m-d', $fechaInicio);
if (!$d || $d->format('Y-m-d') < date('Y-m-d')) {
    responder(false, [], 'La fecha de inicio no es válida.');
}

$stmt = $conn->prepare("SELECT id_mascota, nombre_mascota FROM mascota_usuario WHERE id_mascota = ? AND id_usuario = ?");
$stmt->bind_param("ii", $idMascota, $idUsuario);
$stmt->execute();
$mascotaRow = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$mascotaRow) responder(false, [], 'La mascota seleccionada no pertenece a tu cuenta.');

$stmtU = $conn->prepare("SELECT nombre, email FROM usuarios WHERE id = ? LIMIT 1");
$stmtU->bind_param("i", $idUsuario);
$stmtU->execute();
$usuarioRow = $stmtU->get_result()->fetch_assoc();
$stmtU->close();

$precio = calcularPrecioServicio($conn, 'adiestramiento', $cantidadSesiones);
if (!$precio) responder(false, [], 'El servicio de Adiestramiento no tiene un precio configurado todavía. Contacta al administrador.');
$subtotal  = $precio['subtotal'];
$descuento = $precio['descuento'];
$total     = $precio['total'];

$duracion      = in_array(intval($data['duracion_min'] ?? 60), [30, 45, 60, 90]) ? intval($data['duracion_min']) : 60;
$dias          = substr(trim($data['dias_preferidos'] ?? ''), 0, 60);
$franja        = substr(trim($data['franja_horaria'] ?? ''), 0, 40);
$comportamiento= substr(trim($data['comportamiento'] ?? ''), 0, 30);
$observaciones = substr(trim($data['observaciones'] ?? ''), 0, 1000);
$barrio        = substr(trim($ubicacion['barrio'] ?? ''), 0, 100);
$referencia    = substr(trim($ubicacion['referencia'] ?? ''), 0, 255);
$instrucciones = substr(trim($ubicacion['instrucciones'] ?? ''), 0, 255);

$conn->begin_transaction();
try {
    $stmt = $conn->prepare(
        "INSERT INTO pedidos_adiestramiento
            (id_usuario, id_mascota, cantidad_sesiones, duracion_min, dias_preferidos,
             franja_horaria, fecha_inicio, comportamiento, observaciones,
             direccion, barrio, referencia, instrucciones, lat, lng, ubicacion_validada,
             subtotal, descuento, total, metodo_pago, estado)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?, ?, ?, 'pendiente_pago')"
    );
    $tipos = 'ii'      // id_usuario, id_mascota
           . 'i'        // cantidad_sesiones
           . 'i'        // duracion_min
           . 'sssss'    // dias, franja, fecha_inicio, comportamiento, observaciones
           . 'ssss'     // direccion, barrio, referencia, instrucciones
           . 'dd'       // lat, lng
           . 'ddd'      // subtotal, descuento, total
           . 's';       // metodo_pago
    $stmt->bind_param(
        $tipos,
        $idUsuario, $idMascota, $cantidadSesiones, $duracion, $dias,
        $franja, $fechaInicio, $comportamiento, $observaciones,
        $direccion, $barrio, $referencia, $instrucciones, $lat, $lng,
        $subtotal, $descuento, $total, $metodo
    );
    $stmt->execute();
    $idPedido = $conn->insert_id;
    $stmt->close();

    $resultado = procesarPagoSimuladoAdi($metodo, $total, $pago);

    if (!$resultado['aprobado']) {
        $u = $conn->prepare("UPDATE pedidos_adiestramiento SET estado = 'pago_fallido' WHERE id_pedido = ?");
        $u->bind_param("i", $idPedido);
        $u->execute();
        $u->close();
        $conn->commit();
        responder(false, ['id_pedido' => $idPedido], 'El pago fue rechazado: ' . $resultado['mensaje']);
    }

    $ultimos4   = $metodo === 'tarjeta' ? ($pago['ultimos4'] ?? null) : null;
    $cuotas     = $metodo === 'tarjeta' ? intval($pago['cuotas'] ?? 1) : null;
    $banco      = $metodo === 'pse' ? substr(trim($pago['banco'] ?? ''), 0, 60) : null;
    $tipoPers   = $metodo === 'pse' && in_array($pago['tipo_persona'] ?? '', ['natural', 'juridica'])
                    ? $pago['tipo_persona'] : null;
    $documento  = $metodo === 'pse' ? substr(trim($pago['documento'] ?? ''), 0, 20) : null;
    $emailConf  = $metodo === 'pse' ? substr(trim($pago['email_confirmacion'] ?? ''), 0, 100) : null;

    $usarPerfil = !empty($fact['usar_perfil']) ? 1 : 0;
    $fPais      = $usarPerfil ? null : substr(trim($fact['pais'] ?? ''), 0, 60);
    $fCiudad    = $usarPerfil ? null : substr(trim($fact['ciudad'] ?? ''), 0, 60);
    $fDepto     = $usarPerfil ? null : substr(trim($fact['departamento'] ?? ''), 0, 60);
    $fDir       = $usarPerfil ? null : substr(trim($fact['direccion'] ?? ''), 0, 255);
    $fComp      = $usarPerfil ? null : substr(trim($fact['complemento'] ?? ''), 0, 100);
    $fCP        = $usarPerfil ? null : substr(trim($fact['codigo_postal'] ?? ''), 0, 12);

    $stmt = $conn->prepare(
        "INSERT INTO pagos
            (id_pedido_adiestramiento, id_usuario, id_mascota, metodo, monto, estado_pago, referencia, titular, ultimos4, cuotas,
             banco, tipo_persona, documento, email_confirmacion,
             fact_usar_perfil, fact_pais, fact_ciudad, fact_departamento,
             fact_direccion, fact_complemento, fact_codigo_postal)
         VALUES (?, ?, ?, ?, ?, 'aprobado', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->bind_param(
        "iiisdsssissssissssss",
        $idPedido, $idUsuario, $idMascota, $metodo, $total, $resultado['referencia'],
        $titular, $ultimos4, $cuotas,
        $banco, $tipoPers, $documento, $emailConf,
        $usarPerfil, $fPais, $fCiudad, $fDepto, $fDir, $fComp, $fCP
    );
    $stmt->execute();
    $idPago = $conn->insert_id;
    $stmt->close();

    $u = $conn->prepare("UPDATE pedidos_adiestramiento SET estado = 'listo_para_asignar' WHERE id_pedido = ?");
    $u->bind_param("i", $idPedido);
    $u->execute();
    $u->close();

    $ahoraPago = date('Y-m-d H:i:s');
    $sqlMem = "
        INSERT INTO membresias (id_usuario, id_mascota, adiestramiento, fecha_inicio_adiestramiento, id_pago_adiestramiento)
        VALUES (?, ?, 1, ?, ?)
        ON DUPLICATE KEY UPDATE
            adiestramiento              = 1,
            id_mascota                  = VALUES(id_mascota),
            fecha_inicio_adiestramiento = VALUES(fecha_inicio_adiestramiento),
            id_pago_adiestramiento      = VALUES(id_pago_adiestramiento)
    ";
    $u = $conn->prepare($sqlMem);
    $u->bind_param("iisi", $idUsuario, $idMascota, $ahoraPago, $idPago);
    $u->execute();
    $u->close();

    $conn->commit();

    $telegram = new ModeloTelegram();
    $telegram->enviarMensajePagos(
        "💳 <b>Nuevo pago registrado</b>\n\n" .
        "👤 <b>Usuario:</b> " . htmlspecialchars($usuarioRow['nombre'] ?? '') . "\n" .
        "✉️ <b>Email:</b> "   . htmlspecialchars($usuarioRow['email'] ?? '')  . "\n" .
        "🐾 <b>Mascota:</b> " . htmlspecialchars($mascotaRow['nombre_mascota']) . "\n" .
        "📦 <b>Membresía:</b> 🎓 Adiestramiento\n" .
        "📆 <b>Días solicitados:</b> " . $cantidadSesiones . " sesiones al mes\n" .
        ($precio['descuento_pct'] > 0 ? "🏷️ <b>Descuento aplicado:</b> " . $precio['descuento_pct'] . "%\n" : "") .
        "💰 <b>Monto:</b> $" . number_format($total, 0, '.', ',') . " COP\n" .
        "🔧 <b>Método:</b> " . htmlspecialchars($metodo) . "\n" .
        "📅 <b>Inicio:</b> " . date('d/m/Y', strtotime($fechaInicio)) . "\n" .
        "🕒 <b>Fecha pago:</b> " . date('d/m/Y H:i')
    );

    responder(true, [
        'id_pedido'  => $idPedido,
        'id_pago'    => $idPago,
        'referencia' => $resultado['referencia'],
        'total'      => $total,
        'estado'     => 'listo_para_asignar',
    ], 'Pago aprobado. Tu membresía de adiestramiento quedó activa.');

} catch (Exception $e) {
    $conn->rollback();
    responder(false, [], 'Error al procesar la compra: ' . $e->getMessage());
}
?>