<?php
/**
 * obtener_mi_perfil.php
 * Devuelve los datos básicos del usuario en sesión (para prellenar la
 * dirección del paso 2 y la facturación del paso 4 del wizard de paseos).
 */
header("Access-Control-Allow-Origin: *");
include_once 'helpers.php';
include_once '../model/conexion.php';

verificarSesion();

$idUsuario = (int)$_SESSION['usuario_id'];

$stmt = $conn->prepare(
    "SELECT u.id, u.nombre, u.email, i.telefono, i.direccion
     FROM usuarios u
     LEFT JOIN info_usuario i ON i.id_usuario = u.id
     WHERE u.id = ?"
);
$stmt->bind_param("i", $idUsuario);
$stmt->execute();
$perfil = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$perfil) responder(false, [], 'Usuario no encontrado.');

responder(true, ['perfil' => [
    'id'        => (int)$perfil['id'],
    'nombre'    => $perfil['nombre'],
    'email'     => $perfil['email'],
    'telefono'  => $perfil['telefono'] ?? '',
    'direccion' => $perfil['direccion'] ?? '',
]]);
?>
