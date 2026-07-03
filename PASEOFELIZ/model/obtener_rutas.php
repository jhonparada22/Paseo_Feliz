<?php
/**
 * obtener_rutas.php
 * Lista las rutas de una fecha (por defecto hoy), con sus paradas resumidas.
 * GET ?fecha=2026-06-30 (opcional)
 */
header("Access-Control-Allow-Origin: *");
include_once 'helpers.php';
include_once '../model/conexion.php';

verificarSesion();

$fecha = $_GET['fecha'] ?? date('Y-m-d');

$stmt = $conn->prepare(
    "SELECT r.id_ruta, r.id_paseador, r.hora_inicio, er.nombre AS estado, u.nombre AS paseador
     FROM rutas r
     JOIN estados_ruta er ON er.id_estado = r.id_estado
     JOIN paseadores p ON p.id_paseador = r.id_paseador
     JOIN usuarios u ON u.id = p.id_usuario
     WHERE r.fecha_paseo = ?
     ORDER BY r.hora_inicio ASC"
);
$stmt->bind_param("s", $fecha);
$stmt->execute();
$res = $stmt->get_result();

$rutas = [];
while ($row = $res->fetch_assoc()) {
    $idRuta = (int)$row['id_ruta'];

    $stmtP = $conn->prepare("SELECT direccion FROM ruta_paradas WHERE id_ruta = ? ORDER BY orden ASC");
    $stmtP->bind_param("i", $idRuta);
    $stmtP->execute();
    $direcciones = [];
    $resP = $stmtP->get_result();
    while ($p = $resP->fetch_assoc()) $direcciones[] = $p['direccion'];
    $stmtP->close();

    $rutas[] = [
        'id_ruta'     => $idRuta,
        'id_paseador' => (int)$row['id_paseador'],
        'paseador'    => $row['paseador'],
        'hora'        => substr($row['hora_inicio'], 0, 5),
        'estado'      => $row['estado'],
        'puntos'      => $direcciones,
    ];
}
$stmt->close();

responder(true, ['rutas' => $rutas]);
?>