<?php
/**
 * procesar_compra_paseos.php
 * Paso 4 del wizard "Contratar Mensualidad de Paseos".
 * Registra el pedido, procesa el pago (capa simulada, reemplazable por
 * pasarela real) y activa la membresía de paseos del usuario.
 *
 * POST JSON esperado:
 * {
 *   "id_mascota": 6, "id_plan": 3,
 *   "modalidad": "grupal", "duracion_min": 60,
 *   "dias_preferidos": "lun,mie,vie", "franja_horaria": "8:00 a.m. – 11:00 a.m.",
 *   "fecha_inicio": "2026-07-10",
 *   "comportamiento": "sociable", "observaciones": "...",
 *   "ubicacion": { "direccion": "...", "barrio": "...", "referencia": "...",
 *                  "instrucciones": "...", "lat": 7.89, "lng": -72.50, "validada": true },
 *   "pago": { "metodo": "tarjeta", "titular": "...", "ultimos4": "4242", "cuotas": 1,
 *             "banco": null, "tipo_persona": null, "documento": null, "email_confirmacion": null },
 *   "facturacion": { "usar_perfil": true, "pais": null, "ciudad": null, "departamento": null,
 *                    "direccion": null, "complemento": null, "codigo_postal": null },
 *   "confirmaciones": { "datos": true, "terminos": true, "autorizo": true }
 * }
 *
 * NOTA DE SEGURIDAD: el frontend NUNCA envía el número completo de la
 * tarjeta ni el CVV — solo el titular y los últimos 4 dígitos.
 */
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
include_once 'helpers.php';
include_once '../model/conexion.php';

// Errores SQL como excepciones -> los captura el try/catch y responden JSON limpio
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

verificarSesion();

// ═══════════════════════════════════════════════════════════════════
// CAPA DE PROCESAMIENTO DE PAGO (SIMULADA)
// Cuando exista una pasarela real (Wompi, MercadoPago, PayU, etc.),
// reemplazar SOLO esta función: recibe el método y los datos no
// sensibles, y devuelve el resultado de la transacción.
// ═══════════════════════════════════════════════════════════════════
function procesarPagoSimulado($metodo, $monto, $datosPago) {
    // Simulación controlada: la transacción siempre se aprueba y se
    // genera una referencia única identificable como simulada.
    return [
        'aprobado'   => true,
        'referencia' => 'PF-SIM-' . strtoupper(bin2hex(random_bytes(5))),
        'mensaje'    => 'Pago aprobado (simulación, sin pasarela real)',
    ];
}

// ═══════════════════════════════════════════════════════════════════
// 1. LEER Y VALIDAR LA SOLICITUD
// ═══════════════════════════════════════════════════════════════════
$idUsuario = (int)$_SESSION['usuario_id'];
$data      = leerJsonBody();

$idMascota = intval($data['id_mascota'] ?? 0);
$idPlan    = intval($data['id_plan'] ?? 0);
$ubicacion = $data['ubicacion'] ?? [];
$pago      = $data['pago'] ?? [];
$fact      = $data['facturacion'] ?? [];
$conf      = $data['confirmaciones'] ?? [];

// ═══════════════════════════════════════════════════════════════════
// MODO EXPRÉS: "añadir otra mascota al servicio activo"
// Si viene pedido_base, la nueva mascota HEREDA toda la configuración
// del pedido activo (plan, días, franja, duración, modalidad, dirección)
// leída de la BD — lo que mande el cliente para esos campos se ignora.
// Además NO se toca la membresía (ya vigente) y al final se clona el
// cronograma del pedido base para que salgan a pasear juntas.
// ═══════════════════════════════════════════════════════════════════
$idPedidoBase = intval($data['pedido_base'] ?? 0);
$pedidoBase   = null;

if ($idPedidoBase > 0) {
    $ahoraColombia = "CONVERT_TZ(NOW(), '+00:00', '-05:00')";
    $stmt = $conn->prepare(
        "SELECT p.id_pedido, p.id_plan, p.modalidad, p.duracion_min, p.dias_preferidos,
                p.franja_horaria, p.comportamiento,
                p.direccion, p.barrio, p.referencia, p.instrucciones, p.lat, p.lng
         FROM pedidos_paseo p
         JOIN membresias m ON m.id_usuario = p.id_usuario
         WHERE p.id_pedido = ? AND p.id_usuario = ?
           AND p.estado IN ('pagado', 'listo_para_asignar')
           AND m.paseos = 1
           AND m.fecha_inicio_paseos IS NOT NULL
           AND DATE_ADD(m.fecha_inicio_paseos, INTERVAL 30 DAY) > $ahoraColombia"
    );
    $stmt->bind_param("ii", $idPedidoBase, $idUsuario);
    $stmt->execute();
    $pedidoBase = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$pedidoBase) {
        responder(false, [], 'El servicio activo al que intentas unir la mascota no es válido o ya venció.');
    }

    // La mascota nueva no puede tener ya un servicio activo
    $stmt = $conn->prepare(
        "SELECT id_pedido FROM pedidos_paseo
         WHERE id_mascota = ? AND estado IN ('pagado', 'listo_para_asignar') LIMIT 1"
    );
    $stmt->bind_param("i", $idMascota);
    $stmt->execute();
    $yaActiva = $stmt->get_result()->num_rows > 0;
    $stmt->close();
    if ($yaActiva) {
        responder(false, [], 'Esta mascota ya tiene un servicio de paseos activo.');
    }

    // Heredar configuración del pedido base (fuente de verdad: la BD)
    $idPlan                    = (int)$pedidoBase['id_plan'];
    $data['modalidad']         = $pedidoBase['modalidad'];
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

// Confirmaciones obligatorias
if (empty($conf['datos']) || empty($conf['terminos']) || empty($conf['autorizo'])) {
    responder(false, [], 'Debes aceptar todas las confirmaciones para continuar.');
}

// Método de pago soportado
$metodo = $pago['metodo'] ?? '';
if (!in_array($metodo, ['tarjeta', 'pse'])) {
    responder(false, [], $metodo === 'nequi'
        ? 'Nequi / Daviplata estará disponible próximamente.'
        : 'Método de pago no válido.');
}

// Datos mínimos del pago según método
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

// Ubicación validada en el paso 2
$lat = floatval($ubicacion['lat'] ?? 0);
$lng = floatval($ubicacion['lng'] ?? 0);
$direccion = trim($ubicacion['direccion'] ?? '');
if (!$lat || !$lng || $direccion === '' || empty($ubicacion['validada'])) {
    responder(false, [], 'La ubicación de recogida no ha sido confirmada. Vuelve al paso 2.');
}

// Fecha de inicio válida (hoy o futura)
$fechaInicio = $data['fecha_inicio'] ?? '';
$d = DateTime::createFromFormat('Y-m-d', $fechaInicio);
if (!$d || $d->format('Y-m-d') < date('Y-m-d')) {
    responder(false, [], 'La fecha de inicio de la membresía no es válida.');
}

// La mascota debe pertenecer al usuario en sesión
$stmt = $conn->prepare("SELECT id_mascota FROM mascota_usuario WHERE id_mascota = ? AND id_usuario = ?");
$stmt->bind_param("ii", $idMascota, $idUsuario);
$stmt->execute();
$mascotaOk = $stmt->get_result()->num_rows > 0;
$stmt->close();
if (!$mascotaOk) responder(false, [], 'La mascota seleccionada no pertenece a tu cuenta.');

// El plan debe existir y estar activo — el precio SIEMPRE sale de la BD
$stmt = $conn->prepare("SELECT paseos_mes, precio_paseo, descuento_pct FROM planes_paseos WHERE id_plan = ? AND activo = 1");
$stmt->bind_param("i", $idPlan);
$stmt->execute();
$plan = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$plan) responder(false, [], 'El plan seleccionado no está disponible.');

$subtotal  = (float)$plan['precio_paseo'] * (int)$plan['paseos_mes'];
$descuento = round($subtotal * (int)$plan['descuento_pct'] / 100, 2);
$total     = $subtotal - $descuento;

// Campos opcionales del pedido
$modalidad     = in_array($data['modalidad'] ?? '', ['individual', 'grupal']) ? $data['modalidad'] : 'grupal';
$duracion      = in_array(intval($data['duracion_min'] ?? 60), [30, 45, 60]) ? intval($data['duracion_min']) : 60;
$dias          = substr(trim($data['dias_preferidos'] ?? ''), 0, 60);
$franja        = substr(trim($data['franja_horaria'] ?? ''), 0, 40);
$comportamiento= substr(trim($data['comportamiento'] ?? ''), 0, 30);
$observaciones = substr(trim($data['observaciones'] ?? ''), 0, 1000);
$barrio        = substr(trim($ubicacion['barrio'] ?? ''), 0, 100);
$referencia    = substr(trim($ubicacion['referencia'] ?? ''), 0, 255);
$instrucciones = substr(trim($ubicacion['instrucciones'] ?? ''), 0, 255);

// ═══════════════════════════════════════════════════════════════════
// 2. REGISTRAR PEDIDO + PAGO + ACTIVAR MEMBRESÍA (transacción)
// ═══════════════════════════════════════════════════════════════════
$conn->begin_transaction();
try {
    // 2.1 Pedido en estado pendiente_pago
    $stmt = $conn->prepare(
        "INSERT INTO pedidos_paseo
            (id_usuario, id_mascota, id_plan, modalidad, duracion_min, dias_preferidos,
             franja_horaria, fecha_inicio, comportamiento, observaciones,
             direccion, barrio, referencia, instrucciones, lat, lng, ubicacion_validada,
             subtotal, descuento, total, metodo_pago, estado)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?, ?, ?, 'pendiente_pago')"
    );
    $tiposPedido = 'iii'   // id_usuario, id_mascota, id_plan
                 . 's'     // modalidad
                 . 'i'     // duracion_min
                 . 'sssss' // dias, franja, fecha_inicio, comportamiento, observaciones
                 . 'ssss'  // direccion, barrio, referencia, instrucciones
                 . 'dd'    // lat, lng
                 . 'ddd'   // subtotal, descuento, total
                 . 's';    // metodo_pago
    $stmt->bind_param(
        $tiposPedido,
        $idUsuario, $idMascota, $idPlan, $modalidad, $duracion, $dias,
        $franja, $fechaInicio, $comportamiento, $observaciones,
        $direccion, $barrio, $referencia, $instrucciones, $lat, $lng,
        $subtotal, $descuento, $total, $metodo
    );
    $stmt->execute();
    $idPedido = $conn->insert_id;
    $stmt->close();

    // 2.2 Procesar el pago (capa simulada, reemplazable)
    $resultado = procesarPagoSimulado($metodo, $total, $pago);

    if (!$resultado['aprobado']) {
        // Registrar el intento fallido y salir limpio
        $u = $conn->prepare("UPDATE pedidos_paseo SET estado = 'pago_fallido' WHERE id_pedido = ?");
        $u->bind_param("i", $idPedido);
        $u->execute();
        $u->close();
        $conn->commit();
        responder(false, ['id_pedido' => $idPedido], 'El pago fue rechazado: ' . $resultado['mensaje']);
    }

    // 2.3 Registrar el pago aprobado (sin datos sensibles de tarjeta)
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
            (id_pedido, id_usuario, metodo, monto, estado, referencia, titular, ultimos4, cuotas,
             banco, tipo_persona, documento, email_confirmacion,
             fact_usar_perfil, fact_pais, fact_ciudad, fact_departamento,
             fact_direccion, fact_complemento, fact_codigo_postal)
         VALUES (?, ?, ?, ?, 'aprobado', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->bind_param(
        "iisdsssissssissssss",
        $idPedido, $idUsuario, $metodo, $total, $resultado['referencia'],
        $titular, $ultimos4, $cuotas,
        $banco, $tipoPers, $documento, $emailConf,
        $usarPerfil, $fPais, $fCiudad, $fDepto, $fDir, $fComp, $fCP
    );
    $stmt->execute();
    $idPago = $conn->insert_id;
    $stmt->close();

    // 2.4 Pedido pagado y con ubicación validada -> listo para asignación
    $u = $conn->prepare("UPDATE pedidos_paseo SET estado = 'listo_para_asignar' WHERE id_pedido = ?");
    $u->bind_param("i", $idPedido);
    $u->execute();
    $u->close();

    if ($pedidoBase) {
        // 2.5 (modo exprés) La membresía YA está vigente: no se toca, porque
        //     resetear fecha_inicio_paseos correría la renovación y el conteo
        //     de paseos usados de la primera mascota.
        //     En su lugar, clonar el cronograma del pedido base (mismo
        //     paseador, mismos días) para que salgan a pasear juntas.
        $stmt = $conn->prepare(
            "SELECT id_paseador, dia_semana FROM cronograma_paseos WHERE id_pedido = ?"
        );
        $stmt->bind_param("i", $idPedidoBase);
        $stmt->execute();
        $res = $stmt->get_result();
        $filasCrono = [];
        while ($row = $res->fetch_assoc()) $filasCrono[] = $row;
        $stmt->close();

        if ($filasCrono) {
            $ins = $conn->prepare(
                "INSERT IGNORE INTO cronograma_paseos (id_paseador, id_pedido, dia_semana) VALUES (?, ?, ?)"
            );
            foreach ($filasCrono as $fila) {
                $idPaseadorClon = (int)$fila['id_paseador'];
                $diaClon        = (int)$fila['dia_semana'];
                $ins->bind_param("iii", $idPaseadorClon, $idPedido, $diaClon);
                $ins->execute();
            }
            $ins->close();
        }
        // Si el base aún no tiene cronograma (pendiente de asignación),
        // el nuevo pedido también queda pendiente — coherente.
    } else {
        // 2.5 Activar la membresía de paseos (el resto del sitio ya la reconoce
        //     vía controller/membresia_estado.php: 30 días desde fecha_inicio_paseos)
        $u = $conn->prepare("UPDATE membresias SET paseos = 1, fecha_inicio_paseos = ? WHERE id_usuario = ?");
        $fechaInicioDT = $fechaInicio . ' 00:00:00';
        $u->bind_param("si", $fechaInicioDT, $idUsuario);
        $u->execute();
        $u->close();
    }

    $conn->commit();

    responder(true, [
        'id_pedido'  => $idPedido,
        'id_pago'    => $idPago,
        'referencia' => $resultado['referencia'],
        'total'      => $total,
        'estado'     => 'listo_para_asignar',
    ], $pedidoBase
        ? 'Pago aprobado. Tu mascota se unió al servicio de paseos.'
        : 'Pago aprobado. Tu membresía de paseos quedó activa.');

} catch (Exception $e) {
    $conn->rollback();
    responder(false, [], 'Error al procesar la compra: ' . $e->getMessage());
}
?>
