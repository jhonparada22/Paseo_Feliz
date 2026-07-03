<?php
/**
 * eliminar_ruta.php
 * Cancela (borrado lógico) o elimina físicamente una ruta.
 * POST JSON: { "id_ruta": 1, "definitivo": false }
 */
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
include_once 'helpers.php';
include_once '../model/conexion.php';

verificarAdmin();
$data = leerJsonBody();
$idRuta = intval($data['id_ruta'] ?? 0);
$definitivo = !empty($data['definitivo']);

if (!$idRuta) responder(false, [], 'id_ruta requerido.');

if ($definitivo) {
    // Elimina la ruta y, en cascada, sus paradas/clientes/historial relacionado
    $stmt = $conn->prepare("DELETE FROM rutas WHERE id_ruta = ?");
    $stmt->bind_param("i", $idRuta);
    $ok = $stmt->execute();
    $stmt->close();
    responder($ok, [], $ok ? 'Ruta eliminada definitivamente.' : 'No se pudo eliminar.');
} else {
    // Cancelación lógica (id_estado = 5 -> cancelada), conserva el historial
    $stmt = $conn->prepare("UPDATE rutas SET id_estado = 5 WHERE id_ruta = ?");
    $stmt->bind_param("i", $idRuta);
    $ok = $stmt->execute();
    $stmt->close();
    responder($ok, [], $ok ? 'Ruta cancelada.' : 'No se pudo cancelar.');
}
?>