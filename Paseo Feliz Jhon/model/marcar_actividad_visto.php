<?php
/**
 * marcar_actividad_visto.php
 * (ADMIN) Marca una tarjeta del Centro de Actividad como "vista" para el
 * admin actual: se guarda su hash en actividad_vista y actividad_feed.php
 * deja de devolverla. No borra nada del origen (pagos/pedidos/etc.), solo
 * oculta esa tarjeta puntual del feed.
 *
 * POST JSON: { "id": "<hash md5 de 32 caracteres>" }
 */
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
include_once 'helpers.php';
include_once '../model/conexion.php';

verificarAdmin();
$data = leerJsonBody();
$hash = trim($data['id'] ?? '');
$idAdmin = (int)($_SESSION['usuario_id'] ?? 0);

if (!$idAdmin) responder(false, [], 'Sesión inválida.');
if (!preg_match('/^[a-f0-9]{32}$/', $hash)) responder(false, [], 'id inválido.');

$stmt = $conn->prepare("INSERT IGNORE INTO actividad_vista (id_admin, hash_item) VALUES (?, ?)");
$stmt->bind_param("is", $idAdmin, $hash);
$stmt->execute();
$stmt->close();

responder(true, ['id' => $hash], 'Marcado como visto.');
?>
