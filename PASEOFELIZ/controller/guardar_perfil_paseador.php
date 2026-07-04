<?php
// ═══════════════════════════════════════════════════════════════
//  guardar_perfil_paseador.php
//  Guarda los cambios del perfil del PASEADOR (datos del dueño + mascotas).
//  Espeja a guardar_perfil.php / guardar_perfil_admin.php, pero además:
//   - permite RENOMBRAR la mascota (nombre_mascota_edit)
//   - permite ELIMINAR la mascota (accion_eliminar_mascota)
//  Redirige de vuelta a la vista de perfil del paseador.
// ═══════════════════════════════════════════════════════════════
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include_once '../model/conexion.php';

// Vista de perfil del paseador (a donde volvemos siempre)
$vista_perfil = "../view/vistas/paseador/usuario_paseador.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: $vista_perfil");
    exit();
}

$id_usuario = $_SESSION['usuario_id'] ?? null;
if (!$id_usuario) {
    header("Location: ../registro/acceso.html");
    exit();
}

// Rutas de imágenes que se guardan pensando en cómo las lee la vista
// (view/vistas/paseador/ está dos niveles bajo view/, igual que admin)
$directorio_subidas = "../view/assets/uploads/";
$prefijo_bd         = "../../assets/uploads/";
if (!is_dir($directorio_subidas)) {
    mkdir($directorio_subidas, 0777, true);
}

// ── CASO 1: Eliminar una mascota (form independiente) ──────────────
if (isset($_POST['accion_eliminar_mascota'])) {
    $id_mascota = isset($_POST['select_id_mascota']) ? intval($_POST['select_id_mascota']) : 0;

    if ($id_mascota > 0) {
        // Solo puede borrar una mascota suya; recuperamos su avatar para limpiarlo
        $stmt = $conn->prepare("SELECT avatar_mascota FROM mascota_usuario WHERE id_mascota = ? AND id_usuario = ?");
        $stmt->bind_param("ii", $id_mascota, $id_usuario);
        $stmt->execute();
        $mascota = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($mascota) {
            $stmt_del = $conn->prepare("DELETE FROM mascota_usuario WHERE id_mascota = ? AND id_usuario = ?");
            $stmt_del->bind_param("ii", $id_mascota, $id_usuario);
            $stmt_del->execute();
            $stmt_del->close();

            // Borrar el archivo del avatar si no es el genérico
            if (!empty($mascota['avatar_mascota'])
                && strpos($mascota['avatar_mascota'], 'assets/default/') === false) {
                $archivo = str_replace($prefijo_bd, $directorio_subidas, $mascota['avatar_mascota']);
                if (file_exists($archivo)) { unlink($archivo); }
            }
        }
    }

    header("Location: $vista_perfil");
    exit();
}

// ── CASO 2: Guardar datos del dueño + mascota (editar/agregar) ─────
$biografia  = isset($_POST['biografia']) ? trim($_POST['biografia']) : null;
$cumpleanos = !empty($_POST['cumpleanos']) ? $_POST['cumpleanos'] : null;
$direccion  = isset($_POST['direccion']) ? trim($_POST['direccion']) : null;

if (isset($_POST['telefono'])) {
    $telefono = preg_replace('/[^0-9]/', '', trim($_POST['telefono']));
    $telefono = empty($telefono) ? null : $telefono;
} else {
    $telefono = null;
}

$profesion = isset($_POST['profesion']) ? trim($_POST['profesion']) : null;

// Datos actuales para no perder avatar/banner si no se suben nuevos
$stmt_check = $conn->prepare("SELECT id_info, avatar_url, banner_url FROM info_usuario WHERE id_usuario = ?");
$stmt_check->bind_param("i", $id_usuario);
$stmt_check->execute();
$datos_perfil_actual = $stmt_check->get_result()->fetch_assoc();
$stmt_check->close();

$avatar_url = $datos_perfil_actual['avatar_url'] ?? '';
$banner_url = $datos_perfil_actual['banner_url'] ?? '';

// PROCESAR AVATAR DEL DUEÑO
if (isset($_FILES['avatar_usuario']) && $_FILES['avatar_usuario']['error'] === UPLOAD_ERR_OK) {
    $ext = pathinfo($_FILES['avatar_usuario']['name'], PATHINFO_EXTENSION);
    $nuevo_nombre = "avatar_user_" . $id_usuario . "_" . time() . "." . $ext;
    if (move_uploaded_file($_FILES['avatar_usuario']['tmp_name'], $directorio_subidas . $nuevo_nombre)) {
        if (!empty($datos_perfil_actual['avatar_url'])) {
            $archivo_viejo = str_replace($prefijo_bd, $directorio_subidas, $datos_perfil_actual['avatar_url']);
            if (file_exists($archivo_viejo)) { unlink($archivo_viejo); }
        }
        $avatar_url = $prefijo_bd . $nuevo_nombre;
    }
}

// PROCESAR BANNER DEL DUEÑO
if (isset($_FILES['banner_usuario']) && $_FILES['banner_usuario']['error'] === UPLOAD_ERR_OK) {
    $ext = pathinfo($_FILES['banner_usuario']['name'], PATHINFO_EXTENSION);
    $nuevo_nombre = "banner_user_" . $id_usuario . "_" . time() . "." . $ext;
    if (move_uploaded_file($_FILES['banner_usuario']['tmp_name'], $directorio_subidas . $nuevo_nombre)) {
        if (!empty($datos_perfil_actual['banner_url'])) {
            $banner_viejo = str_replace($prefijo_bd, $directorio_subidas, $datos_perfil_actual['banner_url']);
            if (file_exists($banner_viejo)) { unlink($banner_viejo); }
        }
        $banner_url = $prefijo_bd . $nuevo_nombre;
    }
}

// GUARDAR / ACTUALIZAR DATOS DEL DUEÑO
if ($datos_perfil_actual) {
    $stmt_update = $conn->prepare("UPDATE info_usuario SET biografia = ?, cumpleanos = ?, telefono = ?, direccion = ?, profesion = ?, avatar_url = ?, banner_url = ? WHERE id_usuario = ?");
    $stmt_update->bind_param("sssssssi", $biografia, $cumpleanos, $telefono, $direccion, $profesion, $avatar_url, $banner_url, $id_usuario);
    $stmt_update->execute();
    $stmt_update->close();
} else {
    $stmt_insert = $conn->prepare("INSERT INTO info_usuario (id_usuario, biografia, cumpleanos, telefono, direccion, profesion, avatar_url, banner_url) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt_insert->bind_param("isssssss", $id_usuario, $biografia, $cumpleanos, $telefono, $direccion, $profesion, $avatar_url, $banner_url);
    $stmt_insert->execute();
    $stmt_insert->close();
}

// ── SECCIÓN MASCOTAS ──────────────────────────────────────────────
$mascota_accion = $_POST['mascota_accion'] ?? 'editar';

if ($mascota_accion === 'agregar') {
    $nombre_mascota   = isset($_POST['nombre_mascota_nuevo']) ? trim($_POST['nombre_mascota_nuevo']) : '';
    $biografia_canina = isset($_POST['biografia_canina_nuevo']) ? trim($_POST['biografia_canina_nuevo']) : null;
    $enfermedades     = isset($_POST['enfermedades_nuevo']) ? trim($_POST['enfermedades_nuevo']) : '';

    if (!empty($nombre_mascota)) {
        $avatar_mascota = '../../assets/default/dog.png';

        if (isset($_FILES['avatar_mascota_nuevo']) && $_FILES['avatar_mascota_nuevo']['error'] === UPLOAD_ERR_OK) {
            $ext = pathinfo($_FILES['avatar_mascota_nuevo']['name'], PATHINFO_EXTENSION);
            $nuevo_nombre = "avatar_pet_" . $id_usuario . "_" . time() . "." . $ext;
            if (move_uploaded_file($_FILES['avatar_mascota_nuevo']['tmp_name'], $directorio_subidas . $nuevo_nombre)) {
                $avatar_mascota = $prefijo_bd . $nuevo_nombre;
            }
        }

        $stmt_pet = $conn->prepare("INSERT INTO mascota_usuario (id_usuario, nombre_mascota, avatar_mascota, biografia_canina, enfermedades_discapacidades) VALUES (?, ?, ?, ?, ?)");
        $stmt_pet->bind_param("issss", $id_usuario, $nombre_mascota, $avatar_mascota, $biografia_canina, $enfermedades);
        $stmt_pet->execute();
        $stmt_pet->close();
    }

} elseif ($mascota_accion === 'editar') {
    $id_mascota       = isset($_POST['select_id_mascota']) ? intval($_POST['select_id_mascota']) : 0;
    $nombre_nuevo     = isset($_POST['nombre_mascota_edit']) ? trim($_POST['nombre_mascota_edit']) : '';
    $biografia_canina = isset($_POST['biografia_canina_edit']) ? trim($_POST['biografia_canina_edit']) : null;
    $enfermedades     = isset($_POST['enfermedades_edit']) ? trim($_POST['enfermedades_edit']) : '';

    if ($id_mascota > 0) {
        $stmt_check_pet = $conn->prepare("SELECT nombre_mascota, avatar_mascota FROM mascota_usuario WHERE id_mascota = ? AND id_usuario = ?");
        $stmt_check_pet->bind_param("ii", $id_mascota, $id_usuario);
        $stmt_check_pet->execute();
        $datos_mascota = $stmt_check_pet->get_result()->fetch_assoc();
        $stmt_check_pet->close();

        if ($datos_mascota) {
            $avatar_mascota = $datos_mascota['avatar_mascota'];
            // Si dejó el nombre vacío, se conserva el actual
            $nombre_final = $nombre_nuevo !== '' ? $nombre_nuevo : $datos_mascota['nombre_mascota'];

            if (isset($_FILES['avatar_mascota_edit']) && $_FILES['avatar_mascota_edit']['error'] === UPLOAD_ERR_OK) {
                $ext = pathinfo($_FILES['avatar_mascota_edit']['name'], PATHINFO_EXTENSION);
                $nuevo_nombre = "avatar_pet_" . $id_usuario . "_" . time() . "." . $ext;
                if (move_uploaded_file($_FILES['avatar_mascota_edit']['tmp_name'], $directorio_subidas . $nuevo_nombre)) {
                    if (!empty($datos_mascota['avatar_mascota'])
                        && strpos($datos_mascota['avatar_mascota'], 'assets/default/') === false) {
                        $archivo_viejo_pet = str_replace($prefijo_bd, $directorio_subidas, $datos_mascota['avatar_mascota']);
                        if (file_exists($archivo_viejo_pet)) { unlink($archivo_viejo_pet); }
                    }
                    $avatar_mascota = $prefijo_bd . $nuevo_nombre;
                }
            }

            $stmt_update_pet = $conn->prepare("UPDATE mascota_usuario SET nombre_mascota = ?, avatar_mascota = ?, biografia_canina = ?, enfermedades_discapacidades = ? WHERE id_mascota = ? AND id_usuario = ?");
            $stmt_update_pet->bind_param("ssssii", $nombre_final, $avatar_mascota, $biografia_canina, $enfermedades, $id_mascota, $id_usuario);
            $stmt_update_pet->execute();
            $stmt_update_pet->close();
        }
    }
}

header("Location: $vista_perfil");
exit();
