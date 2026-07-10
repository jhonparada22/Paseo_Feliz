<?php
/**
 * reasignar_paseo.php
 * (ADMIN) Reasigna un pedido de paseos a OTRO paseador como operación
 * ATÓMICA. Antes no existía: "reasignar" era agregar puntos a la ruta del
 * paseador B sin quitar los de A, dejando el mismo pedido activo en dos
 * rutas a la vez.
 *
 * POST JSON: { "id_pedido": 5, "id_paseador_destino": 3, "alcance": "hoy" | "permanente" }
 *
 * alcance "hoy" (emergencia puntual, el cronograma no cambia):
 *   - cancela las paradas pendientes del pedido en la ruta del paseador
 *     original (motivo interno "reasignado");
 *   - si el destino YA inició su jornada, agrega las paradas a su ruta
 *     activa; si no, deja la instancia 'asignado' a su nombre y la tomará
 *     al pulsar "Empezar paseos";
 *   - solo aplica si la mascota aún no fue recogida.
 *
 * alcance "permanente":
 *   - valida cupos del destino para CADA día del pedido;
 *   - mueve el cronograma completo y sincroniza las instancias futuras;
 *   - si además hay paseo hoy sin ejecutar, lo mueve también (lógica de "hoy").
 *
 * Requiere migración fase 11 (paseos_programados).
 */
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
include_once 'helpers.php';
include_once 'helpers_paseos_programados.php';
include_once 'helpers_logistica.php';
include_once '../model/conexion.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
date_default_timezone_set('America/Bogota');

verificarAdmin();
$data       = leerJsonBody();
$idPedido   = intval($data['id_pedido'] ?? 0);
$idDestino  = intval($data['id_paseador_destino'] ?? 0);
$alcance    = $data['alcance'] ?? 'hoy';
$hoy        = date('Y-m-d');

if (!$idPedido || !$idDestino) responder(false, [], 'Faltan datos: pedido y paseador destino.');
if (!in_array($alcance, ['hoy', 'permanente'])) responder(false, [], 'Alcance no válido.');

// ── Validaciones base ─────────────────────────────────────────────────
$stmt = $conn->prepare(
    "SELECT p.id_pedido, p.id_usuario, p.id_mascota, p.estado, p.direccion, p.barrio,
            p.lat, p.lng, p.franja_horaria, mu.nombre_mascota
     FROM pedidos_paseo p
     LEFT JOIN mascota_usuario mu ON mu.id_mascota = p.id_mascota
     WHERE p.id_pedido = ?"
);
$stmt->bind_param("i", $idPedido);
$stmt->execute();
$pedido = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$pedido) responder(false, [], 'El pedido no existe.');
if (!in_array($pedido['estado'], ['pagado', 'listo_para_asignar'])) {
    responder(false, [], 'Este pedido no está activo para reasignación (estado: ' . $pedido['estado'] . ').');
}

$stmt = $conn->prepare(
    "SELECT pa.id_paseador, u.nombre FROM paseadores pa JOIN usuarios u ON u.id = pa.id_usuario
     WHERE pa.id_paseador = ?"
);
$stmt->bind_param("i", $idDestino);
$stmt->execute();
$destino = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$destino) responder(false, [], 'El paseador destino no existe.');

$mascota        = $pedido['nombre_mascota'] ?: 'la mascota';
$nombreDestino  = $destino['nombre'];
$idAdmin        = (int)$_SESSION['usuario_id'];

/**
 * Mueve el paseo de HOY al destino (si existe y aún no fue recogido).
 * Devuelve: 'movido_en_ruta' | 'movido_asignado' | 'sin_paseo_hoy' | 'ya_ejecutado'
 */
function moverPaseoDeHoy($conn, $pedido, $idDestino, $idAdmin, $hoy) {
    $idPedido = (int)$pedido['id_pedido'];

    // Instancia de hoy aún movible (no recogida/completada/cancelada)
    $stmt = $conn->prepare(
        "SELECT id_paseo, estado, id_ruta FROM paseos_programados
         WHERE id_pedido = ? AND fecha = ?"
    );
    $stmt->bind_param("is", $idPedido, $hoy);
    $stmt->execute();
    $pp = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$pp) return 'sin_paseo_hoy';
    if (in_array($pp['estado'], ['recogido', 'completado', 'cancelado', 'no_ejecutado'], true)) {
        return 'ya_ejecutado';
    }
    $idPaseo = (int)$pp['id_paseo'];

    // 1. Cancelar las paradas pendientes en la ruta original (si las hay)
    $stmt = $conn->prepare(
        "UPDATE ruta_paradas rp
         JOIN rutas r ON r.id_ruta = rp.id_ruta
         SET rp.hora_cancelacion = NOW(), rp.motivo_cancelacion = 'Reasignado a otro paseador', rp.id_estado = 4
         WHERE rp.id_pedido = ? AND r.fecha_paseo = ? AND r.id_estado IN (1,2,3)
           AND rp.hora_recogida IS NULL AND rp.hora_entrega IS NULL AND rp.hora_cancelacion IS NULL"
    );
    $stmt->bind_param("is", $idPedido, $hoy);
    $stmt->execute();
    $stmt->close();

    // 2. ¿El destino ya tiene ruta activa hoy? -> sumar paradas a su ruta
    $idRutaDestino = obtenerRutaActivaHoy($conn, $idDestino, $hoy);
    if ($idRutaDestino) {
        $stmt = $conn->prepare("SELECT COALESCE(MAX(orden), -1) AS maxOrden FROM ruta_paradas WHERE id_ruta = ?");
        $stmt->bind_param("i", $idRutaDestino);
        $stmt->execute();
        $orden = (int)$stmt->get_result()->fetch_assoc()['maxOrden'] + 1;
        $stmt->close();

        $direccion = $pedido['direccion'] . ($pedido['barrio'] ? ', ' . $pedido['barrio'] : '');
        $stmtP = $conn->prepare(
            "INSERT INTO ruta_paradas (id_ruta, orden, etiqueta, tipo, direccion, lat, lng, id_usuario_cliente, id_mascota, id_pedido, id_paseo, id_estado)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)"
        );
        foreach (['recogida', 'entrega'] as $tipo) {
            $etiqueta  = chr(65 + ($orden % 26));
            $lat = (float)$pedido['lat']; $lng = (float)$pedido['lng'];
            $idCli = (int)$pedido['id_usuario']; $idMas = (int)$pedido['id_mascota'];
            $stmtP->bind_param(
                "iisssddiiii",
                $idRutaDestino, $orden, $etiqueta, $tipo, $direccion, $lat, $lng, $idCli, $idMas, $idPedido, $idPaseo
            );
            $stmtP->execute();
            $orden++;
        }
        $stmtP->close();

        $conn->query("INSERT IGNORE INTO ruta_clientes (id_ruta, id_usuario_cliente, id_mascota)
                      VALUES ($idRutaDestino, {$pedido['id_usuario']}, {$pedido['id_mascota']})");

        reordenarParadasPendientes($conn, $idRutaDestino);
        recalcularDistanciaYDuracion($conn, $idRutaDestino);

        $stmt = $conn->prepare(
            "UPDATE paseos_programados SET id_paseador = ?, id_ruta = ?, estado = 'en_ruta' WHERE id_paseo = ?"
        );
        $stmt->bind_param("iii", $idDestino, $idRutaDestino, $idPaseo);
        $stmt->execute();
        $stmt->close();
        ppEvento($conn, $idPaseo, 'reasignado', 'admin', 'Movido a la ruta activa del nuevo paseador');
        return 'movido_en_ruta';
    }

    // 3. Destino sin ruta: la instancia queda a su nombre, la tomará al iniciar
    $stmt = $conn->prepare(
        "UPDATE paseos_programados SET id_paseador = ?, id_ruta = NULL, estado = 'asignado' WHERE id_paseo = ?"
    );
    $stmt->bind_param("ii", $idDestino, $idPaseo);
    $stmt->execute();
    $stmt->close();
    ppEvento($conn, $idPaseo, 'reasignado', 'admin', 'Asignado al nuevo paseador (tomará el paseo al iniciar su jornada)');
    return 'movido_asignado';
}

try {
    if ($alcance === 'permanente') {
        // Días actuales del pedido en el cronograma
        $stmt = $conn->prepare("SELECT DISTINCT dia_semana FROM cronograma_paseos WHERE id_pedido = ?");
        $stmt->bind_param("i", $idPedido);
        $stmt->execute();
        $res = $stmt->get_result();
        $dias = [];
        while ($row = $res->fetch_assoc()) $dias[] = (int)$row['dia_semana'];
        $stmt->close();
        if (!$dias) responder(false, [], 'Este pedido no está en ningún cronograma: usa "Asignar al cronograma".');

        // Cupos del destino para cada día (excluyendo el propio pedido)
        $DIAS_NOMBRE = [1 => 'lunes', 2 => 'martes', 3 => 'miércoles', 4 => 'jueves', 5 => 'viernes', 6 => 'sábado', 7 => 'domingo'];
        foreach ($dias as $dia) {
            $conjunto = pedidosDelDia($conn, $idDestino, $dia);
            if (!in_array($idPedido, $conjunto, true)) $conjunto[] = $idPedido;
            $err = validarConjuntoDia($conn, $idDestino, $dia, $conjunto);
            if ($err) responder(false, [], "No se puede reasignar ({$DIAS_NOMBRE[$dia]}): $err");
        }

        // Mover el cronograma completo y sincronizar instancias futuras
        $conn->begin_transaction();
        $stmt = $conn->prepare("UPDATE cronograma_paseos SET id_paseador = ? WHERE id_pedido = ?");
        $stmt->bind_param("ii", $idDestino, $idPedido);
        $stmt->execute();
        $stmt->close();
        $conn->commit();

        sincronizarInstanciasConCronograma($conn);
        materializarPaseosProgramados($conn, true);

        // Si hoy hay paseo pendiente, moverlo también
        $resultadoHoy = moverPaseoDeHoy($conn, $pedido, $idDestino, $idAdmin, $hoy);

        crearNotificacionInterna($conn, (int)$pedido['id_usuario'], null,
            'sistema', "Tu paseador cambió: ahora $nombreDestino atenderá los paseos de $mascota.");

        $msgHoy = $resultadoHoy === 'ya_ejecutado'
            ? ' El paseo de hoy ya estaba en ejecución y no se movió.'
            : '';
        responder(true, ['alcance' => 'permanente', 'hoy' => $resultadoHoy],
            "Cronograma de $mascota reasignado a $nombreDestino.$msgHoy");
    }

    // ── alcance "hoy" ─────────────────────────────────────────────────
    $resultado = moverPaseoDeHoy($conn, $pedido, $idDestino, $idAdmin, $hoy);

    if ($resultado === 'sin_paseo_hoy') {
        responder(false, [], "$mascota no tiene paseo programado hoy. Usa el alcance \"permanente\" para cambiar el cronograma.");
    }
    if ($resultado === 'ya_ejecutado') {
        responder(false, [], "El paseo de hoy de $mascota ya fue recogido, completado o cancelado: no se puede reasignar.");
    }

    crearNotificacionInterna($conn, (int)$pedido['id_usuario'], null,
        'sistema', "El paseo de hoy de $mascota será atendido por $nombreDestino.");

    responder(true, ['alcance' => 'hoy', 'resultado' => $resultado],
        $resultado === 'movido_en_ruta'
            ? "Paseo de hoy movido a la ruta activa de $nombreDestino."
            : "Paseo de hoy asignado a $nombreDestino: lo tomará al iniciar su jornada.");
} catch (mysqli_sql_exception $e) {
    if (ppTablaFaltante($e)) {
        responder(false, [], 'Falta ejecutar la migración fase 11 (paseos_programados).');
    }
    throw $e;
}
?>
