<?php
/**
 * procesar_compra_hospedaje.php
 * Paso final del wizard "Contratar Hospedaje canino".
 * A diferencia de Paseos/Adiestramiento (recurrentes, "X al mes"), el
 * Hospedaje es una ESTADÍA con fecha de entrada y salida — la cantidad
 * de noches se calcula sola (salida - entrada), no la manda el cliente.
 *
 * POST JSON esperado:
 * {
 *   "id_mascota": 6, "fecha_entrada": "2026-07-15", "fecha_salida": "2026-07-18",
 *   "comportamiento": "sociable", "observaciones": "...",
 *   "ubicacion": {...}, "pago": {...}, "facturacion": {...}, "confirmaciones": {...}
 * }
 */
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
include_once 'helpers.php';
include_once '../model/conexion.php';
include_once '../model/modelotelegram.php';
include_once 'precios_helper.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

verificarSesion();

function procesarPagoSimuladoHosp($metodo, $monto, $datosPago) {
    return [
        'aprobado'   => true,
        'referencia' => 'PF-SIM-' . strtoupper(bin2hex(random_bytes(5))),
        'mensaje'    => 'Pago aprobado (simulación, sin pasarela real)',
    ];
}

define('MAX_NOCHES_HOSPEDAJE', 60);

$idUsuario = (int)$_SESSION['usuario_id'];
$data      = leerJsonBody();

$idMascota    = intval($data['id_mascota'] ?? 0);
$fechaEntrada = $data['fecha_entrada'] ?? '';
$fechaSalida  = $data['fecha_salida'] ?? '';
$ubicacion = $data['ubicacion'] ?? [];
$pago      = $data['pago'] ?? [];
$fact      = $data['facturacion'] ?? [];
$conf      = $data['confirmaciones'] ?? [];

// Validar fechas: entrada hoy o futura, salida después de la entrada
$dEntrada = DateTime::createFromFormat('Y-m-d', $fechaEntrada);
$dSalida  = DateTime::createFromFormat('Y-m-d', $fechaSalida);
if (!$dEntrada || $dEntrada->format('Y-m-d') < date('Y-m-d')) {
    responder(false, [], 'La fecha de entrada no es válida.');
}
if (!$dSalida || $dSalida <= $dEntrada) {
    responder(false, [], 'La fecha de salida debe ser posterior a la fecha de entrada.');
}
$cantidadNoches = (int)$dEntrada->diff($dSalida)->days;
if ($cantidadNoches < 1 || $cantidadNoches > MAX_NOCHES_HOSPEDAJE) {
    responder(false, [], 'La estadía debe ser entre 1 y ' . MAX_NOCHES_HOSPEDAJE . ' noches.');
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
    responder(false, [], 'La ubicación de recogida no ha sido confirmada. Vuelve al paso 2.');
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

// El precio SIEMPRE se calcula en el servidor: precio por noche × noches,
// con descuento por cantidad si el admin configuró alguno.
$precio = calcularPrecioServicio($conn, 'hospedaje', $cantidadNoches);
if (!$precio) responder(false, [], 'El servicio de Hospedaje no tiene un precio configurado todavía. Contacta al administrador.');
$subtotal  = $precio['subtotal'];
$descuento = $precio['descuento'];
$total     = $precio['total'];

$comportamiento= substr(trim($data['comportamiento'] ?? ''), 0, 30);
$observaciones = substr(trim($data['observaciones'] ?? ''), 0, 1000);
$barrio        = substr(trim($ubicacion['barrio'] ?? ''), 0, 100);
$referencia    = substr(trim($ubicacion['referencia'] ?? ''), 0, 255);
$instrucciones = substr(trim($ubicacion['instrucciones'] ?? ''), 0, 255);

$conn->begin_transaction();
try {
    $stmt = $conn->prepare(
        "INSERT INTO pedidos_hospedaje
            (id_usuario, id_mascota, fecha_entrada, fecha_salida, cantidad_noches,
             comportamiento, observaciones, direccion, barrio, referencia, instrucciones,
             lat, lng, ubicacion_validada, subtotal, descuento, total, metodo_pago, estado)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?, ?, ?, 'pendiente_pago')"
    );
    $tipos = 'ii'    // id_usuario, id_mascota
           . 'ss'    // fecha_entrada, fecha_salida
           . 'i'     // cantidad_noches
           . 'ss'    // comportamiento, observaciones
           . 'ssss'  // direccion, barrio, referencia, instrucciones
           . 'dd'    // lat, lng
           . 'ddd'   // subtotal, descuento, total
           . 's';    // metodo_pago
    $stmt->bind_param(
        $tipos,
        $idUsuario, $idMascota, $fechaEntrada, $fechaSalida, $cantidadNoches,
        $comportamiento, $observaciones, $direccion, $barrio, $referencia, $instrucciones,
        $lat, $lng, $subtotal, $descuento, $total, $metodo
    );
    $stmt->execute();
    $idPedido = $conn->insert_id;
    $stmt->close();

    $resultado = procesarPagoSimuladoHosp($metodo, $total, $pago);

    if (!$resultado['aprobado']) {
        $u = $conn->prepare("UPDATE pedidos_hospedaje SET estado = 'pago_fallido' WHERE id_pedido = ?");
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
            (id_pedido_hospedaje, id_usuario, id_mascota, metodo, monto, estado_pago, referencia, titular, ultimos4, cuotas,
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

    $u = $conn->prepare("UPDATE pedidos_hospedaje SET estado = 'listo_para_asignar' WHERE id_pedido = ?");
    $u->bind_param("i", $idPedido);
    $u->execute();
    $u->close();

    // La membresía de hospedaje se activa desde HOY por 30 días, igual
    // que los otros 2 servicios (el resto del sitio ya reconoce esa
    // vigencia vía controller/membresia_estado.php). La estadía puntual
    // vive aparte, en pedidos_hospedaje con sus propias fechas.
    $ahoraPago = date('Y-m-d H:i:s');
    $sqlMem = "
        INSERT INTO membresias (id_usuario, id_mascota, hospedaje, fecha_inicio_hospedaje, id_pago_hospedaje)
        VALUES (?, ?, 1, ?, ?)
        ON DUPLICATE KEY UPDATE
            hospedaje              = 1,
            id_mascota             = VALUES(id_mascota),
            fecha_inicio_hospedaje = VALUES(fecha_inicio_hospedaje),
            id_pago_hospedaje      = VALUES(id_pago_hospedaje)
    ";
    $u = $conn->prepare($sqlMem);
    $u->bind_param("iisi", $idUsuario, $idMascota, $ahoraPago, $idPago);
    $u->execute();
    $u->close();

    $conn->commit();

    $telegram = new ModeloTelegram();
    $telegram->enviarMensajePagos(
        "💳 <b>Nuevo pago registrado (wizard cliente)</b>\n\n" .
        "👤 <b>Usuario:</b> " . htmlspecialchars($usuarioRow['nombre'] ?? '') . "\n" .
        "✉️ <b>Email:</b> "   . htmlspecialchars($usuarioRow['email'] ?? '')  . "\n" .
        "🐾 <b>Mascota:</b> " . htmlspecialchars($mascotaRow['nombre_mascota']) . "\n" .
        "📦 <b>Membresía:</b> 🏠 Hospedaje\n" .
        "📆 <b>Días solicitados:</b> " . $cantidadNoches . " noches (" . date('d/m/Y', strtotime($fechaEntrada)) . " → " . date('d/m/Y', strtotime($fechaSalida)) . ")\n" .
        ($precio['descuento_pct'] > 0 ? "🏷️ <b>Descuento aplicado:</b> " . $precio['descuento_pct'] . "%\n" : "") .
        "💰 <b>Monto:</b> $" . number_format($total, 0, '.', ',') . " COP\n" .
        "🔧 <b>Método:</b> " . htmlspecialchars($metodo) . "\n" .
        "🕒 <b>Fecha pago:</b> " . date('d/m/Y H:i')
    );

    responder(true, [
        'id_pedido'  => $idPedido,
        'id_pago'    => $idPago,
        'referencia' => $resultado['referencia'],
        'total'      => $total,
        'noches'     => $cantidadNoches,
        'estado'     => 'listo_para_asignar',
    ], 'Pago aprobado. Tu reserva de hospedaje quedó confirmada.');

} catch (Exception $e) {
    $conn->rollback();
    responder(false, [], 'Error al procesar la compra: ' . $e->getMessage());
}
?>