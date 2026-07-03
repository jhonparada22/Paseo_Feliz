<?php
/**
 * editar_ruta.php
 * Reemplaza las paradas y/o el horario de una ruta que aún no ha sido finalizada.
 * POST JSON: { "id_ruta":1, "fecha":"...", "hora":"...", "puntos":[...] }
 */
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
include_once 'helpers.php';
include_once '../model/conexion.php';

// Errores SQL como excepciones -> los captura el try/catch y responden JSON limpio
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

verificarAdmin();
$data = leerJsonBody();

$idRuta = intval($data['id_ruta'] ?? 0);
$puntos = $data['puntos'] ?? [];
if (!$idRuta) responder(false, [], 'id_ruta requerido.');

// No permitir editar una ruta ya finalizada o cancelada
$chk = $conn->prepare("SELECT id_estado FROM rutas WHERE id_ruta = ?");
$chk->bind_param("i", $idRuta);
$chk->execute();
$row = $chk->get_result()->fetch_assoc();
$chk->close();
if (!$row) responder(false, [], 'Ruta no encontrada.');
if (in_array((int)$row['id_estado'], [4, 5])) {
    responder(false, [], 'No se puede editar una ruta finalizada o cancelada.');
}

$conn->begin_transaction();
try {
    if (!empty($data['fecha']) && !empty($data['hora'])) {
        $stmt = $conn->prepare("UPDATE rutas SET fecha_paseo = ?, hora_inicio = ? WHERE id_ruta = ?");
        $stmt->bind_param("ssi", $data['fecha'], $data['hora'], $idRuta);
        $stmt->execute();
        $stmt->close();
    }

    if (!empty($puntos)) {
        // Se reemplazan todas las paradas (más simple y seguro que hacer diffs)
        $del = $conn->prepare("DELETE FROM ruta_paradas WHERE id_ruta = ?");
        $del->bind_param("i", $idRuta);
        $del->execute();
        $del->close();

        $stmtParada = $conn->prepare(
            "INSERT INTO ruta_paradas (id_ruta, orden, etiqueta, tipo, direccion, lat, lng, id_usuario_cliente, id_mascota, id_estado)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)"
        );
        foreach ($puntos as $i => $p) {
            $etiqueta = $p['etiqueta'] ?? chr(65 + $i);
            $tipo     = in_array($p['tipo'] ?? '', ['recogida', 'paseo', 'entrega']) ? $p['tipo'] : 'paseo';
            $idCliente = !empty($p['id_usuario_cliente']) ? intval($p['id_usuario_cliente']) : null;
            $idMascota = !empty($p['id_mascota']) ? intval($p['id_mascota']) : null;
            $stmtParada->bind_param(
                "iisssddii", $idRuta, $i, $etiqueta, $tipo, $p['addr'], $p['lat'], $p['lng'], $idCliente, $idMascota
            );
            $stmtParada->execute();
        }
        $stmtParada->close();
    }

    $conn->commit();
    responder(true, [], 'Ruta actualizada correctamente.');
} catch (Exception $e) {
    $conn->rollback();
    responder(false, [], 'Error al editar la ruta: ' . $e->getMessage());
}
?>