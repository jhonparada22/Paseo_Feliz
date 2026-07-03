<?php
/**
 * asignar_ruta.php
 * Reasigna una ruta existente a otro paseador (botón "Reasignar").
 * POST JSON: { "id_ruta": 1, "id_paseador": 3 }
 */
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
include_once 'helpers.php';
include_once '../model/conexion.php';

verificarAdmin();
$data = leerJsonBody();
$idRuta     = intval($data['id_ruta'] ?? 0);
$idPaseador = intval($data['id_paseador'] ?? 0);

if (!$idRuta || !$idPaseador) responder(false, [], 'id_ruta e id_paseador son requeridos.');

$stmt = $conn->prepare("UPDATE rutas SET id_paseador = ?, id_estado = 1 WHERE id_ruta = ?");
$stmt->bind_param("ii", $idPaseador, $idRuta);
$ok = $stmt->execute();
$stmt->close();

responder($ok, [], $ok ? 'Ruta reasignada correctamente.' : 'No se pudo reasignar.');
?>