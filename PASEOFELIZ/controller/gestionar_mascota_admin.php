<?php
// ═══════════════════════════════════════════════════════════════
//  gestionar_mascota_admin.php
//  Permite al ADMIN editar o eliminar la mascota de CUALQUIER usuario
//  desde el panel de detalle de usuarios_admin.php (no requiere ser el
//  dueño de la mascota, a diferencia de guardar_perfil.php/_paseador/_admin
//  que solo dejan a cada usuario gestionar sus propias mascotas).
//
//  POST accion=editar   -> id_mascota, nombre_mascota, raza, edad,
//                           biografia_canina, enfermedades_discapacidades,
//                           avatar_mascota (archivo, opcional)
//  POST accion=eliminar -> id_mascota
// ═══════════════════════════════════════════════════════════════
include_once '../model/helpers.php';
include_once '../model/conexion.php';

verificarAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responder(false, [], 'Método no permitido.');
}

$directorio_subidas = "../view/assets/uploads/";
if (!is_dir($directorio_subidas)) {
    mkdir($directorio_subidas, 0777, true);
}

$accion = $_POST['accion'] ?? '';

if ($accion === 'eliminar') {
    $idMascota = intval($_POST['id_mascota'] ?? 0);
    if ($idMascota <= 0) {
        responder(false, [], 'id_mascota inválido.');
    }

    $stmt = $conn->prepare("SELECT avatar_mascota FROM mascota_usuario WHERE id_mascota = ?");
    $stmt->bind_param("i", $idMascota);
    $stmt->execute();
    $mascota = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$mascota) {
        responder(false, [], 'Mascota no encontrada.');
    }

    $stmt = $conn->prepare("DELETE FROM mascota_usuario WHERE id_mascota = ?");
    $stmt->bind_param("i", $idMascota);
    $stmt->execute();
    $stmt->close();

    if (!empty($mascota['avatar_mascota'])
        && strpos($mascota['avatar_mascota'], 'assets/default/') === false) {
        $archivo = str_replace("../assets/uploads/", $directorio_subidas, $mascota['avatar_mascota']);
        if (file_exists($archivo)) { unlink($archivo); }
    }

    responder(true, [], 'Mascota eliminada correctamente.');
}

if ($accion === 'editar') {
    $idMascota = intval($_POST['id_mascota'] ?? 0);
    if ($idMascota <= 0) {
        responder(false, [], 'id_mascota inválido.');
    }

    $stmt = $conn->prepare("SELECT nombre_mascota, raza, edad, avatar_mascota FROM mascota_usuario WHERE id_mascota = ?");
    $stmt->bind_param("i", $idMascota);
    $stmt->execute();
    $datosMascota = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$datosMascota) {
        responder(false, [], 'Mascota no encontrada.');
    }

    $nombreNuevo = isset($_POST['nombre_mascota']) ? trim($_POST['nombre_mascota']) : '';
    $razaNueva   = isset($_POST['raza']) ? trim($_POST['raza']) : '';
    $edadNueva   = isset($_POST['edad']) ? trim($_POST['edad']) : '';
    $biografia   = isset($_POST['biografia_canina']) ? trim($_POST['biografia_canina']) : null;
    $notas       = isset($_POST['enfermedades_discapacidades']) ? trim($_POST['enfermedades_discapacidades']) : '';

    $nombreFinal = $nombreNuevo !== '' ? $nombreNuevo : $datosMascota['nombre_mascota'];
    $razaFinal   = $razaNueva !== '' ? $razaNueva : $datosMascota['raza'];
    $edadFinal   = $edadNueva !== '' ? intval($edadNueva) : $datosMascota['edad'];
    $avatarMascota = $datosMascota['avatar_mascota'];

    if (isset($_FILES['avatar_mascota']) && $_FILES['avatar_mascota']['error'] === UPLOAD_ERR_OK) {
        $ext = pathinfo($_FILES['avatar_mascota']['name'], PATHINFO_EXTENSION);
        $nuevoNombre = "avatar_pet_" . $idMascota . "_" . time() . "." . $ext;
        if (move_uploaded_file($_FILES['avatar_mascota']['tmp_name'], $directorio_subidas . $nuevoNombre)) {
            if (!empty($datosMascota['avatar_mascota'])
                && strpos($datosMascota['avatar_mascota'], 'assets/default/') === false) {
                $archivoViejo = str_replace("../assets/uploads/", $directorio_subidas, $datosMascota['avatar_mascota']);
                if (file_exists($archivoViejo)) { unlink($archivoViejo); }
            }
            $avatarMascota = "../assets/uploads/" . $nuevoNombre;
        }
    }

    $stmt = $conn->prepare(
        "UPDATE mascota_usuario SET nombre_mascota = ?, raza = ?, edad = ?, avatar_mascota = ?,
                biografia_canina = ?, enfermedades_discapacidades = ? WHERE id_mascota = ?"
    );
    $stmt->bind_param("ssisssi", $nombreFinal, $razaFinal, $edadFinal, $avatarMascota, $biografia, $notas, $idMascota);
    $stmt->execute();
    $stmt->close();

    responder(true, [], 'Mascota actualizada correctamente.');
}

responder(false, [], 'Acción no reconocida.');
?>
