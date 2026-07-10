<?php
/**
 * guardar_cronograma.php
 * El ADMIN administra el cronograma semanal de los paseadores.
 *
 * POST JSON, dos acciones:
 *  1) { "accion":"reemplazar_dia", "id_paseador":3, "dia_semana":1, "ids_pedidos":[5,8] }
 *     -> reemplaza la lista de pedidos de ese día para ese paseador
 *        (modal Cronograma de la página Paseadores)
 *  2) { "accion":"asignar_pedido", "id_pedido":5, "id_paseador":3, "dias":[1,3,5] }
 *     -> agrega un pedido a esos días del paseador
 *        (modal Asignar de la página Paseos)
 */
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
include_once 'helpers.php';
include_once 'helpers_paseos_programados.php';
include_once 'helpers_logistica.php';
include_once '../model/conexion.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

verificarAdmin();
$data   = leerJsonBody();
$accion = $data['accion'] ?? '';

// ── Validar que el paseador exista ────────────────────────────────────
function validarPaseador($conn, $idPaseador) {
    $s = $conn->prepare("SELECT id_paseador FROM paseadores WHERE id_paseador = ?");
    $s->bind_param("i", $idPaseador);
    $s->execute();
    $ok = $s->get_result()->num_rows > 0;
    $s->close();
    if (!$ok) responder(false, [], 'El paseador no existe.');
}

// ── Validar que un pedido sea asignable (pagado y con dirección validada) ─
function validarPedido($conn, $idPedido) {
    $s = $conn->prepare("SELECT estado FROM pedidos_paseo WHERE id_pedido = ?");
    $s->bind_param("i", $idPedido);
    $s->execute();
    $row = $s->get_result()->fetch_assoc();
    $s->close();
    if (!$row) responder(false, [], "El pedido #$idPedido no existe.");
    if ($row['estado'] === 'en_validacion') {
        responder(false, [], "El pedido #$idPedido tiene la dirección pendiente de validar. Apruébala primero desde el detalle del pedido.");
    }
    if (!in_array($row['estado'], ['listo_para_asignar', 'pagado'])) {
        responder(false, [], "El pedido #$idPedido no está pagado/listo para asignar (estado: {$row['estado']}).");
    }
}

// ── Detectar conflicto: pedido ya asignado ese día a OTRO paseador ────
function conflictoDia($conn, $idPedido, $dia, $idPaseador) {
    $s = $conn->prepare(
        "SELECT u.nombre FROM cronograma_paseos c
         JOIN paseadores p ON p.id_paseador = c.id_paseador
         JOIN usuarios u ON u.id = p.id_usuario
         WHERE c.id_pedido = ? AND c.dia_semana = ? AND c.id_paseador <> ?"
    );
    $s->bind_param("iii", $idPedido, $dia, $idPaseador);
    $s->execute();
    $row = $s->get_result()->fetch_assoc();
    $s->close();
    return $row ? $row['nombre'] : null;
}

$DIAS_NOMBRE = [1 => 'lunes', 2 => 'martes', 3 => 'miércoles', 4 => 'jueves', 5 => 'viernes', 6 => 'sábado', 7 => 'domingo'];

if ($accion === 'reemplazar_dia') {
    $idPaseador = intval($data['id_paseador'] ?? 0);
    $dia        = intval($data['dia_semana'] ?? 0);
    $idsPedidos = array_values(array_unique(array_map('intval', $data['ids_pedidos'] ?? [])));

    if (!$idPaseador || $dia < 1 || $dia > 7) responder(false, [], 'Datos incompletos (paseador y día).');
    validarPaseador($conn, $idPaseador);
    foreach ($idsPedidos as $idPedido) {
        validarPedido($conn, $idPedido);
        $otro = conflictoDia($conn, $idPedido, $dia, $idPaseador);
        if ($otro) responder(false, [], "El pedido #$idPedido ya está asignado el {$DIAS_NOMBRE[$dia]} al paseador $otro. Quítalo de su cronograma primero.");
    }

    // Cupos y modalidad: se valida el conjunto COMPLETO que quedaría ese día
    $errCupo = validarConjuntoDia($conn, $idPaseador, $dia, $idsPedidos);
    if ($errCupo) responder(false, [], $errCupo);

    $conn->begin_transaction();
    try {
        $d = $conn->prepare("DELETE FROM cronograma_paseos WHERE id_paseador = ? AND dia_semana = ?");
        $d->bind_param("ii", $idPaseador, $dia);
        $d->execute();
        $d->close();

        if ($idsPedidos) {
            $i = $conn->prepare("INSERT INTO cronograma_paseos (id_paseador, id_pedido, dia_semana) VALUES (?, ?, ?)");
            foreach ($idsPedidos as $idPedido) {
                $i->bind_param("iii", $idPaseador, $idPedido, $dia);
                $i->execute();
            }
            $i->close();
        }
        $conn->commit();
        // Reflejar el cambio en los paseos programados de los próximos días
        // (instancias con fecha concreta que ven el cliente y el paseador)
        sincronizarInstanciasConCronograma($conn);
        materializarPaseosProgramados($conn, true);
        responder(true, ['asignados' => count($idsPedidos)], 'Cronograma del ' . $DIAS_NOMBRE[$dia] . ' actualizado.');
    } catch (Exception $e) {
        $conn->rollback();
        responder(false, [], 'Error al guardar el cronograma: ' . $e->getMessage());
    }

} elseif ($accion === 'asignar_pedido') {
    $idPedido   = intval($data['id_pedido'] ?? 0);
    $idPaseador = intval($data['id_paseador'] ?? 0);
    $dias       = array_values(array_unique(array_map('intval', $data['dias'] ?? [])));
    $dias       = array_values(array_filter($dias, function ($d) { return $d >= 1 && $d <= 7; }));

    if (!$idPedido || !$idPaseador || !$dias) responder(false, [], 'Faltan datos: pedido, paseador y al menos un día.');
    validarPaseador($conn, $idPaseador);
    validarPedido($conn, $idPedido);
    foreach ($dias as $dia) {
        $otro = conflictoDia($conn, $idPedido, $dia, $idPaseador);
        if ($otro) responder(false, [], "Ese pedido ya está asignado el {$DIAS_NOMBRE[$dia]} al paseador $otro.");
    }

    // Cupos y modalidad por cada día: lo ya asignado al paseador + este pedido
    foreach ($dias as $dia) {
        $conjunto = pedidosDelDia($conn, $idPaseador, $dia);
        if (!in_array($idPedido, $conjunto, true)) $conjunto[] = $idPedido;
        $errCupo = validarConjuntoDia($conn, $idPaseador, $dia, $conjunto);
        if ($errCupo) responder(false, [], "{$DIAS_NOMBRE[$dia]}: $errCupo");
    }

    $conn->begin_transaction();
    try {
        // INSERT IGNORE: si ya estaba asignado ese día al MISMO paseador, no duplica
        $i = $conn->prepare("INSERT IGNORE INTO cronograma_paseos (id_paseador, id_pedido, dia_semana) VALUES (?, ?, ?)");
        foreach ($dias as $dia) {
            $i->bind_param("iii", $idPaseador, $idPedido, $dia);
            $i->execute();
        }
        $i->close();
        $conn->commit();
        // Generar de inmediato los paseos programados de los próximos días:
        // el cliente ve sus fechas concretas apenas el admin asigna
        sincronizarInstanciasConCronograma($conn);
        materializarPaseosProgramados($conn, true);
        $nombres = implode(', ', array_map(function ($d) use ($DIAS_NOMBRE) { return $DIAS_NOMBRE[$d]; }, $dias));
        responder(true, [], "Pedido asignado al cronograma ($nombres).");
    } catch (Exception $e) {
        $conn->rollback();
        responder(false, [], 'Error al asignar el pedido: ' . $e->getMessage());
    }

} else {
    responder(false, [], 'Acción no válida.');
}
?>
