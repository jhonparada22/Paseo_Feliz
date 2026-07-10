<?php
/**
 * obtener_pedidos_paseos.php
 * Lista para el ADMIN los pedidos de mensualidad pagados y listos para
 * asignación de rutas (los usa el panel de paseadores/mapa).
 * GET ?estado=listo_para_asignar (por defecto) | todos
 */
header("Access-Control-Allow-Origin: *");
include_once 'helpers.php';
include_once '../model/conexion.php';

verificarAdmin();

$estado = $_GET['estado'] ?? 'listo_para_asignar';

// Vigencia de la membresía de paseos de la mascota del pedido (hora Colombia,
// mismo criterio que el dashboard del cliente). La cola de asignación
// EXCLUYE pedidos con membresía vencida: el cliente ya no los ve en su
// panel y asignarles paseador crearía una asignación fantasma.
$ahoraColombia = "CONVERT_TZ(NOW(), '+00:00', '-05:00')";
$exprVigente   = "(mem.paseos = 1 AND mem.fecha_fin_paseos IS NOT NULL AND mem.fecha_fin_paseos > $ahoraColombia)";

$sqlBase =
    "SELECT p.id_pedido, p.id_usuario, p.id_mascota, p.cantidad_paseos,
            p.modalidad, p.duracion_min, p.dias_preferidos, p.franja_horaria,
            p.fecha_inicio, p.comportamiento, p.observaciones,
            p.direccion, p.barrio, p.referencia, p.instrucciones,
            p.lat, p.lng, p.total, p.estado, p.fecha_creacion,
            u.nombre AS cliente, i.telefono,
            m.nombre_mascota, m.avatar_mascota,
            $exprVigente AS membresia_vigente
     FROM pedidos_paseo p
     JOIN usuarios u        ON u.id = p.id_usuario
     LEFT JOIN info_usuario i ON i.id_usuario = p.id_usuario
     JOIN mascota_usuario m ON m.id_mascota = p.id_mascota
     LEFT JOIN membresias mem ON mem.id_usuario = p.id_usuario AND mem.id_mascota = p.id_mascota";

if ($estado === 'todos') {
    $stmt = $conn->prepare("$sqlBase ORDER BY p.fecha_creacion DESC");
} elseif ($estado === 'listo_para_asignar') {
    // Cola de trabajo: solo pedidos que el cliente todavía tiene activos
    $stmt = $conn->prepare("$sqlBase WHERE p.estado = ? AND $exprVigente ORDER BY p.fecha_creacion DESC");
    $stmt->bind_param("s", $estado);
} else {
    $stmt = $conn->prepare("$sqlBase WHERE p.estado = ? ORDER BY p.fecha_creacion DESC");
    $stmt->bind_param("s", $estado);
}
$stmt->execute();
$res = $stmt->get_result();

// ── Asignaciones de cronograma por pedido (si la tabla ya existe) ─────
// {id_pedido: {paseador, id_paseador, dias:[1..7]}}
$asignaciones = [];
$resC = @$conn->query(
    "SELECT c.id_pedido, c.dia_semana, c.id_paseador, u.nombre AS paseador
     FROM cronograma_paseos c
     JOIN paseadores pa ON pa.id_paseador = c.id_paseador
     JOIN usuarios u ON u.id = pa.id_usuario
     ORDER BY c.dia_semana ASC"
);
if ($resC) {
    while ($c = $resC->fetch_assoc()) {
        $idP = (int)$c['id_pedido'];
        if (!isset($asignaciones[$idP])) {
            $asignaciones[$idP] = [
                'id_paseador' => (int)$c['id_paseador'],
                'paseador'    => $c['paseador'],
                'dias'        => [],
            ];
        }
        $asignaciones[$idP]['dias'][] = (int)$c['dia_semana'];
    }
}

$pedidos = [];
while ($row = $res->fetch_assoc()) {
    $pedidos[] = [
        'id_pedido'      => (int)$row['id_pedido'],
        'id_usuario'     => (int)$row['id_usuario'],
        'cliente'        => $row['cliente'],
        'telefono'       => $row['telefono'] ?? '',
        'id_mascota'     => (int)$row['id_mascota'],
        'mascota'        => $row['nombre_mascota'],
        'avatar_mascota' => $row['avatar_mascota'] ?? '',
        'paseos_mes'     => (int)$row['cantidad_paseos'], // clave igual que antes, por compatibilidad con mapa_admin.js
        'modalidad'      => $row['modalidad'],
        'duracion_min'   => (int)$row['duracion_min'],
        'dias_preferidos'=> $row['dias_preferidos'] ?? '',
        'franja_horaria' => $row['franja_horaria'] ?? '',
        'fecha_inicio'   => $row['fecha_inicio'],
        'comportamiento' => $row['comportamiento'] ?? '',
        'observaciones'  => $row['observaciones'] ?? '',
        'direccion'      => $row['direccion'],
        'barrio'         => $row['barrio'] ?? '',
        'referencia'     => $row['referencia'] ?? '',
        'instrucciones'  => $row['instrucciones'] ?? '',
        'lat'            => (float)$row['lat'],
        'lng'            => (float)$row['lng'],
        'total'          => (float)$row['total'],
        'estado'         => $row['estado'],
        'membresia_vigente' => (int)$row['membresia_vigente'] === 1,
        'fecha_compra'   => $row['fecha_creacion'],
        'asignacion'     => $asignaciones[(int)$row['id_pedido']] ?? null,
    ];
}
$stmt->close();

responder(true, ['pedidos' => $pedidos]);
?>
