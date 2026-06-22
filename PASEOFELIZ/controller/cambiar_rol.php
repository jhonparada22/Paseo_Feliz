<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include_once '../model/conexion.php';

header('Content-Type: application/json');

if (!isset($_SESSION['usuario_logged']) || $_SESSION['usuario_logged'] !== true) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$id_usuario = intval($_POST['id_usuario'] ?? 0);
$nuevo_rol  = trim($_POST['rol'] ?? '');

if (!$id_usuario || !in_array($nuevo_rol, ['cliente', 'paseador', 'administrador'])) {
    echo json_encode(['success' => false, 'message' => 'Datos inválidos']);
    exit;
}

$stmt = $conn->prepare("SELECT email FROM usuarios WHERE id = ?");
$stmt->bind_param("i", $id_usuario);
$stmt->execute();
$res     = $stmt->get_result();
$usuario = $res->fetch_assoc();
$stmt->close();

if (!$usuario) {
    echo json_encode(['success' => false, 'message' => 'Usuario no encontrado']);
    exit;
}

$email = $usuario['email'];

if ($nuevo_rol === 'paseador') {
    $d = $conn->prepare("DELETE FROM admin WHERE id_usuario = ?");
    $d->bind_param("i", $id_usuario); $d->execute(); $d->close();

    $chk = $conn->prepare("SELECT id_paseador FROM paseadores WHERE id_usuario = ?");
    $chk->bind_param("i", $id_usuario); $chk->execute(); $chk->store_result();
    if ($chk->num_rows === 0) {
        $chk->close();
        $ins = $conn->prepare("INSERT INTO paseadores (id_usuario, correo) VALUES (?, ?)");
        $ins->bind_param("is", $id_usuario, $email);
        if (!$ins->execute()) {
            echo json_encode(['success' => false, 'message' => 'Error al agregar paseador: ' . $ins->error]);
            exit;
        }
        $ins->close();
    } else { $chk->close(); }

} elseif ($nuevo_rol === 'cliente') {
    $d1 = $conn->prepare("DELETE FROM paseadores WHERE id_usuario = ?");
    $d1->bind_param("i", $id_usuario); $d1->execute(); $d1->close();
    $d2 = $conn->prepare("DELETE FROM admin WHERE id_usuario = ?");
    $d2->bind_param("i", $id_usuario); $d2->execute(); $d2->close();

} elseif ($nuevo_rol === 'administrador') {
    $d1 = $conn->prepare("DELETE FROM paseadores WHERE id_usuario = ?");
    $d1->bind_param("i", $id_usuario); $d1->execute(); $d1->close();

    $chk = $conn->prepare("SELECT id_admin FROM admin WHERE id_usuario = ?");
    $chk->bind_param("i", $id_usuario); $chk->execute(); $chk->store_result();
    if ($chk->num_rows === 0) {
        $chk->close();
        $ins = $conn->prepare("INSERT INTO admin (id_usuario, correo) VALUES (?, ?)");
        $ins->bind_param("is", $id_usuario, $email);
        if (!$ins->execute()) {
            echo json_encode(['success' => false, 'message' => 'Error al agregar admin: ' . $ins->error]);
            exit;
        }
        $ins->close();
    } else { $chk->close(); }
}

echo json_encode(['success' => true, 'message' => 'Rol actualizado correctamente']);
$conn->close();
?>
