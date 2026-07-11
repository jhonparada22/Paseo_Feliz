<?php
/**
 * procesar_compra_paseos.php
 * Paso 4 del wizard "Contratar Mensualidad de Paseos".
 * Registra el pedido, procesa el pago (capa simulada, reemplazable por
 * pasarela real) y activa la membresía de paseos del usuario.
 *
 * POST JSON esperado:
 * {
 *   "id_mascota": 6, "cantidad_paseos": 8,
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
include_once '../model/modelotelegram.php';
include_once 'precios_helper.php';

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
// El precio por día y los descuentos por cantidad se configuran desde
// el botón "Precios" del panel admin (tabla precios_servicios /
// descuentos_servicios) — ya no está fijo en el código.
// ═══════════════════════════════════════════════════════════════════
define('MIN_PASEOS_MES', 1);
define('MAX_PASEOS_MES', 31);

// ═══════════════════════════════════════════════════════════════════
// 1. LEER Y VALIDAR LA SOLICITUD
// ═══════════════════════════════════════════════════════════════════
$idUsuario = (int)$_SESSION['usuario_id'];
$data      = leerJsonBody();

$idMascota      = intval($data['id_mascota'] ?? 0);
$cantidadPaseos = intval($data['cantidad_paseos'] ?? 0);
$ubicacion = $data['ubicacion'] ?? [];
$pago      = $data['pago'] ?? [];
$fact      = $data['facturacion'] ?? [];
$conf      = $data['confirmaciones'] ?? [];

// ═══════════════════════════════════════════════════════════════════
// MODO EXPRÉS: "añadir otra mascota al servicio activo"
// Si viene pedido_base, la nueva mascota HEREDA toda la configuración
// del pedido activo (cantidad de paseos, días, franja, duración,
// modalidad y dirección) leída de la BD — lo que mande el cliente para
// esos campos se ignora. La nueva mascota paga y activa SU PROPIA
// membresía (son por mascota) y al final se clona el cronograma del
// pedido base para que salgan a pasear juntas.
// ═══════════════════════════════════════════════════════════════════
$idPedidoBase = intval($data['pedido_base'] ?? 0);
$pedidoBase   = null;

if ($idPedidoBase > 0) {
    $ahoraColombia = "CONVERT_TZ(NOW(), '+00:00', '-05:00')";
    $exprHora = pedidosTienenHoraExacta($conn) ? 'p.hora_paseo' : 'NULL AS hora_paseo';
    $stmt = $conn->prepare(
        "SELECT p.id_pedido, p.cantidad_paseos, p.modalidad, p.duracion_min, p.dias_preferidos,
                p.franja_horaria, $exprHora, p.comportamiento,
                p.direccion, p.barrio, p.referencia, p.instrucciones, p.lat, p.lng
         FROM pedidos_paseo p
         JOIN membresias m ON m.id_usuario = p.id_usuario AND m.id_mascota = p.id_mascota
         WHERE p.id_pedido = ? AND p.id_usuario = ?
           AND p.estado IN ('pagado', 'listo_para_asignar')
           AND m.paseos = 1
           AND m.fecha_fin_paseos IS NOT NULL
           AND m.fecha_fin_paseos > $ahoraColombia"
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
         WHERE id_mascota = ? AND estado IN ('pagado', 'listo_para_asignar', 'en_validacion') LIMIT 1"
    );
    $stmt->bind_param("i", $idMascota);
    $stmt->execute();
    $yaActiva = $stmt->get_result()->num_rows > 0;
    $stmt->close();
    if ($yaActiva) {
        responder(false, [], 'Esta mascota ya tiene un servicio de paseos activo.');
    }

    // Heredar configuración del pedido base (fuente de verdad: la BD)
    $cantidadPaseos            = (int)$pedidoBase['cantidad_paseos'];
    $data['modalidad']         = $pedidoBase['modalidad'];
    $data['duracion_min']      = (int)$pedidoBase['duracion_min'];
    $data['dias_preferidos']   = $pedidoBase['dias_preferidos'];
    $data['franja_horaria']    = $pedidoBase['franja_horaria'];
    $data['hora_paseo']        = $pedidoBase['hora_paseo'] ? substr($pedidoBase['hora_paseo'], 0, 5) : null;
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

if ($cantidadPaseos < MIN_PASEOS_MES || $cantidadPaseos > MAX_PASEOS_MES) {
    responder(false, [], 'La cantidad de paseos al mes debe estar entre ' . MIN_PASEOS_MES . ' y ' . MAX_PASEOS_MES . '.');
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
$stmt = $conn->prepare("SELECT id_mascota, nombre_mascota FROM mascota_usuario WHERE id_mascota = ? AND id_usuario = ?");
$stmt->bind_param("ii", $idMascota, $idUsuario);
$stmt->execute();
$mascotaRow = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$mascotaRow) responder(false, [], 'La mascota seleccionada no pertenece a tu cuenta.');

// ── ANTI DOBLE COMPRA (todos los flujos, no solo el exprés) ──────────
// La mascota no puede tener ya un servicio de paseos activo: pedido pagado
// + membresía vigente. Antes este chequeo solo existía en el modo exprés y
// el frontend era la única barrera: un doble envío (dos pestañas, replay)
// generaba un segundo cobro y el UPSERT de membresías pisaba la vigencia.
if (!$pedidoBase) {
    $ahoraColombiaChk = "CONVERT_TZ(NOW(), '+00:00', '-05:00')";
    $stmt = $conn->prepare(
        "SELECT p.id_pedido
         FROM pedidos_paseo p
         JOIN membresias m ON m.id_usuario = p.id_usuario AND m.id_mascota = p.id_mascota
         WHERE p.id_mascota = ? AND p.id_usuario = ?
           AND p.estado IN ('pagado', 'listo_para_asignar', 'en_validacion')
           AND m.paseos = 1
           AND m.fecha_fin_paseos IS NOT NULL
           AND m.fecha_fin_paseos > $ahoraColombiaChk
         LIMIT 1"
    );
    $stmt->bind_param("ii", $idMascota, $idUsuario);
    $stmt->execute();
    $yaActiva = $stmt->get_result()->num_rows > 0;
    $stmt->close();
    if ($yaActiva) {
        responder(false, [], 'Esta mascota ya tiene un servicio de paseos activo. Si quieres extenderlo, usa la opción Renovar de tu panel.');
    }
}

$stmtU = $conn->prepare("SELECT nombre, email FROM usuarios WHERE id = ? LIMIT 1");
$stmtU->bind_param("i", $idUsuario);
$stmtU->execute();
$usuarioRow = $stmtU->get_result()->fetch_assoc();
$stmtU->close();

// El precio SIEMPRE se calcula en el servidor, usando lo configurado
// en el panel admin (precio por día + descuento por cantidad, si aplica)
$precio = calcularPrecioServicio($conn, 'paseos', $cantidadPaseos);
if (!$precio) responder(false, [], 'El servicio de Paseos no tiene un precio configurado todavía. Contacta al administrador.');
$subtotal  = $precio['subtotal'];
$descuento = $precio['descuento'];
$total     = $precio['total'];

// Campos opcionales del pedido
$modalidad     = in_array($data['modalidad'] ?? '', ['individual', 'grupal']) ? $data['modalidad'] : 'grupal';
$duracion      = in_array(intval($data['duracion_min'] ?? 60), [30, 45, 60]) ? intval($data['duracion_min']) : 60;
$dias          = substr(trim($data['dias_preferidos'] ?? ''), 0, 60);

// Hora exacta del paseo (fase 15): HH:MM entre 6:00 a.m. y 4:30 p.m., en
// pasos de 15 min. La etiqueta franja_horaria se DERIVA de hora + duración
// (lo que mande el cliente en franja_horaria se ignora); si el navegador
// aún tiene el wizard viejo cacheado y solo manda la franja, se toma su
// hora de inicio para no rechazar la compra.
$horaPaseo = trim($data['hora_paseo'] ?? '');
if (!preg_match('/^([01]?\d|2[0-3]):[0-5]\d$/', $horaPaseo)) {
    $horaPaseo = horaInicioDeFranja($data['franja_horaria'] ?? '') ?: '';
}
if ($horaPaseo !== '') {
    list($hh, $mm) = array_map('intval', explode(':', $horaPaseo));
    $totalMin = (int)(round(($hh * 60 + $mm) / 15) * 15); // encajar al cuarto de hora
    if ($totalMin < 6 * 60 || $totalMin > 16 * 60 + 30) {
        responder(false, [], 'La hora del paseo debe estar entre las 6:00 a.m. y las 4:30 p.m.');
    }
    $horaPaseo = sprintf('%02d:%02d', intdiv($totalMin, 60), $totalMin % 60);
}
$franja = $horaPaseo !== ''
    ? etiquetaHorario($horaPaseo, $duracion)
    : substr(trim($data['franja_horaria'] ?? ''), 0, 40);
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
    // 2.1 Pedido en estado pendiente_pago (id_plan queda NULL: ya no se usa).
    // ubicacion_validada refleja la validación ADMINISTRATIVA: solo nace en 1
    // en el modo exprés (dirección heredada de un pedido ya validado); en la
    // compra normal queda en 0 hasta que el admin apruebe el pin.
    $ubicacionValidada = $pedidoBase ? 1 : 0;
    // La columna hora_paseo solo existe tras la migración fase 15; si aún
    // no corre, el pedido se guarda igual (solo con la etiqueta de texto).
    $conHoraExacta = pedidosTienenHoraExacta($conn) && $horaPaseo !== '';
    $colHora  = $conHoraExacta ? 'hora_paseo, ' : '';
    $markHora = $conHoraExacta ? '?, ' : '';
    $stmt = $conn->prepare(
        "INSERT INTO pedidos_paseo
            (id_usuario, id_mascota, cantidad_paseos, modalidad, duracion_min, dias_preferidos,
             franja_horaria, {$colHora}fecha_inicio, comportamiento, observaciones,
             direccion, barrio, referencia, instrucciones, lat, lng, ubicacion_validada,
             subtotal, descuento, total, metodo_pago, estado)
         VALUES (?, ?, ?, ?, ?, ?, ?, {$markHora}?, ?, ?, ?, ?, ?, ?, ?, ?, $ubicacionValidada, ?, ?, ?, ?, 'pendiente_pago')"
    );
    $tiposPedido = 'iii'   // id_usuario, id_mascota, cantidad_paseos
                 . 's'     // modalidad
                 . 'i'     // duracion_min
                 . 'ss'    // dias, franja
                 . ($conHoraExacta ? 's' : '') // hora_paseo
                 . 'sss'   // fecha_inicio, comportamiento, observaciones
                 . 'ssss'  // direccion, barrio, referencia, instrucciones
                 . 'dd'    // lat, lng
                 . 'ddd'   // subtotal, descuento, total
                 . 's';    // metodo_pago
    $params = [$idUsuario, $idMascota, $cantidadPaseos, $modalidad, $duracion, $dias, $franja];
    if ($conHoraExacta) $params[] = $horaPaseo;
    array_push($params,
        $fechaInicio, $comportamiento, $observaciones,
        $direccion, $barrio, $referencia, $instrucciones, $lat, $lng,
        $subtotal, $descuento, $total, $metodo
    );
    $stmt->bind_param($tiposPedido, ...$params);
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
    // El documento PSE se guarda ENMASCARADO (solo últimos 3 dígitos):
    // después del pago no se usa para nada y es un dato personal que no
    // debe quedar completo en la BD (mismo criterio que ultimos4).
    $documento  = null;
    if ($metodo === 'pse') {
        $docCompleto = preg_replace('/\D/', '', $pago['documento'] ?? '');
        $documento   = $docCompleto !== ''
            ? str_repeat('*', max(0, strlen($docCompleto) - 3)) . substr($docCompleto, -3)
            : null;
        $documento   = $documento !== null ? substr($documento, 0, 20) : null;
    }
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
            (id_pedido, id_usuario, id_mascota, metodo, monto, estado_pago, referencia, titular, ultimos4, cuotas,
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

    // 2.4 Pedido pagado. La compra normal pasa a EN VALIDACIÓN: el admin
    //     revisa el pin de la dirección antes de liberarla a la cola de
    //     asignación (el pin lo puso el cliente y Nominatim puede fallar).
    //     El modo exprés hereda una dirección YA validada del pedido base,
    //     así que va directo a listo_para_asignar.
    $estadoPostPago = $pedidoBase ? 'listo_para_asignar' : 'en_validacion';
    $u = $conn->prepare("UPDATE pedidos_paseo SET estado = ? WHERE id_pedido = ?");
    $u->bind_param("si", $estadoPostPago, $idPedido);
    $u->execute();
    $u->close();

    // 2.5 Activar la membresía de paseos PARA ESTA MASCOTA (upsert por
    //     usuario+mascota). fecha_fin_paseos ahora es una columna real
    //     (renovable): en compra nueva es inicio + 30 días.
    $ahoraPago = date('Y-m-d H:i:s');
    $sqlMem = "
        INSERT INTO membresias (id_usuario, id_mascota, paseos, fecha_inicio_paseos, fecha_fin_paseos, id_pago_paseos)
        VALUES (?, ?, 1, ?, DATE_ADD(?, INTERVAL 30 DAY), ?)
        ON DUPLICATE KEY UPDATE
            paseos              = 1,
            id_mascota          = VALUES(id_mascota),
            fecha_inicio_paseos = VALUES(fecha_inicio_paseos),
            fecha_fin_paseos    = VALUES(fecha_fin_paseos),
            id_pago_paseos      = VALUES(id_pago_paseos)
    ";
    $u = $conn->prepare($sqlMem);
    $u->bind_param("iissi", $idUsuario, $idMascota, $ahoraPago, $ahoraPago, $idPago);
    $u->execute();
    $u->close();

    // 2.6 MODO EXPRÉS: clonar el cronograma del pedido base (mismo
    //     paseador, mismos días) para que las mascotas salgan a pasear
    //     juntas. Si el base aún no tiene cronograma (pendiente de
    //     asignación), el nuevo pedido también queda pendiente — coherente.
    if ($pedidoBase) {
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
    }

    $conn->commit();

    $telegram = new ModeloTelegram();
    $telegram->enviarMensajePagos(
        "💳 <b>Nuevo pago registrado (wizard cliente)</b>\n\n" .
        "👤 <b>Usuario:</b> " . htmlspecialchars($usuarioRow['nombre'] ?? '') . "\n" .
        "✉️ <b>Email:</b> "   . htmlspecialchars($usuarioRow['email'] ?? '')  . "\n" .
        "🐾 <b>Mascota:</b> " . htmlspecialchars($mascotaRow['nombre_mascota']) . "\n" .
        "📦 <b>Membresía:</b> 🐶 Paseos\n" .
        "📆 <b>Días solicitados:</b> " . $cantidadPaseos . " paseos al mes\n" .
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
        'estado'     => $estadoPostPago,
    ], 'Pago aprobado. Tu membresía de paseos quedó activa.');

} catch (Exception $e) {
    $conn->rollback();
    responder(false, [], 'Error al procesar la compra: ' . $e->getMessage());
}
?>