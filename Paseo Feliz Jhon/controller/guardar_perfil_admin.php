<?php
// Iniciamos sesión por si necesitas usar el ID del usuario actual de la sesión
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// CONEXIÓN: Subimos un nivel para salir de controller/ y entramos a model/
include_once '../model/conexion.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Si no existe ID en la sesión, definimos el ID 1 por defecto para pruebas
    $id_usuario = $_SESSION['usuario_id'] ?? 1; 

    // ── CASO: Eliminar una mascota (form independiente "Eliminar esta mascota") ──
    // Igual que en guardar_perfil.php: antes no existía este bloque, así que
    // el botón redirigía "normal" sin borrar nada. Debe ir antes de lo demás.
    if (isset($_POST['accion_eliminar_mascota'])) {
        $id_mascota = isset($_POST['select_id_mascota']) ? intval($_POST['select_id_mascota']) : 0;

        if ($id_mascota > 0) {
            $stmt_chk = $conn->prepare("SELECT avatar_mascota FROM mascota_usuario WHERE id_mascota = ? AND id_usuario = ?");
            $stmt_chk->bind_param("ii", $id_mascota, $id_usuario);
            $stmt_chk->execute();
            $mascota_a_borrar = $stmt_chk->get_result()->fetch_assoc();
            $stmt_chk->close();

            if ($mascota_a_borrar) {
                $stmt_del = $conn->prepare("DELETE FROM mascota_usuario WHERE id_mascota = ? AND id_usuario = ?");
                $stmt_del->bind_param("ii", $id_mascota, $id_usuario);
                $stmt_del->execute();
                $stmt_del->close();

                if (!empty($mascota_a_borrar['avatar_mascota'])
                    && strpos($mascota_a_borrar['avatar_mascota'], 'assets/default/') === false) {
                    $archivo = str_replace("../../assets/uploads/", "../view/assets/uploads/", $mascota_a_borrar['avatar_mascota']);
                    if (file_exists($archivo)) {
                        unlink($archivo);
                    }
                }
            }
        }

        header("Location: ../view/vistas/admin/usuario_admin.php");
        exit();
    }

    // Sanitización de datos recibidos del formulario
    $biografia = isset($_POST['biografia']) ? trim($_POST['biografia']) : null;
    $cumpleanos = !empty($_POST['cumpleanos']) ? $_POST['cumpleanos'] : null;
    $direccion = isset($_POST['direccion']) ? trim($_POST['direccion']) : null;
    
    if (isset($_POST['telefono'])) {
        $telefono = preg_replace('/[^0-9]/', '', trim($_POST['telefono']));
        $telefono = empty($telefono) ? null : $telefono;
    } else {
        $telefono = null;
    }
    
    $profesion = isset($_POST['profesion']) ? trim($_POST['profesion']) : null;

    // RUTA FÍSICA: Subimos un nivel (salimos de controller/) y entramos a view/assets/uploads/
    $directorio_subidas = "../view/assets/uploads/";
    if (!is_dir($directorio_subidas)) {
        mkdir($directorio_subidas, 0777, true);
    }

    // Consultar datos actuales del perfil para no perder el avatar/banner si vienen vacíos
    $stmt_check = $conn->prepare("SELECT id_info, avatar_url, banner_url FROM info_usuario WHERE id_usuario = ?");
    $stmt_check->bind_param("i", $id_usuario);
    $stmt_check->execute();
    $resultado_check = $stmt_check->get_result();
    $datos_perfil_actual = $resultado_check->fetch_assoc();
    $stmt_check->close();

    $avatar_url = $datos_perfil_actual['avatar_url'] ?? '';
    $banner_url = $datos_perfil_actual['banner_url'] ?? '';

    // PROCESAR AVATAR
    if (isset($_FILES['avatar_usuario']) && $_FILES['avatar_usuario']['error'] === UPLOAD_ERR_OK) {
        $ext = pathinfo($_FILES['avatar_usuario']['name'], PATHINFO_EXTENSION);
        $nuevo_nombre = "avatar_user_" . $id_usuario . "_" . time() . "." . $ext;
        
        if (move_uploaded_file($_FILES['avatar_usuario']['tmp_name'], $directorio_subidas . $nuevo_nombre)) {
            // Eliminar avatar antiguo físicamente si existe para no acumular basura
            if (!empty($datos_perfil_actual['avatar_url'])) {
                $archivo_viejo = str_replace("../../assets/uploads/", $directorio_subidas, $datos_perfil_actual['avatar_url']);
                if (file_exists($archivo_viejo)) { unlink($archivo_viejo); }
            }
            // Guardamos la ruta relativa pensando en cómo la leerá 'view/vistas/admin/usuario_admin.php'
            $avatar_url = "../../assets/uploads/" . $nuevo_nombre;
        }
    }

    // PROCESAR BANNER
    if (isset($_FILES['banner_usuario']) && $_FILES['banner_usuario']['error'] === UPLOAD_ERR_OK) {
        $ext = pathinfo($_FILES['banner_usuario']['name'], PATHINFO_EXTENSION);
        $nuevo_nombre = "banner_user_" . $id_usuario . "_" . time() . "." . $ext;
        
        if (move_uploaded_file($_FILES['banner_usuario']['tmp_name'], $directorio_subidas . $nuevo_nombre)) {
            // Eliminar banner antiguo físicamente si existe
            if (!empty($datos_perfil_actual['banner_url'])) {
                $banner_viejo = str_replace("../../assets/uploads/", $directorio_subidas, $datos_perfil_actual['banner_url']);
                if (file_exists($banner_viejo)) { unlink($banner_viejo); }
            }
            $banner_url = "../../assets/uploads/" . $nuevo_nombre;
        }
    }

    // GUARDAR O ACTUALIZAR EN LA BASE DE DATOS
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

    // SECCIÓN DE MASCOTAS
    $mascota_accion = $_POST['mascota_accion'] ?? 'editar';

    if ($mascota_accion === 'agregar') {
        $nombre_mascota = isset($_POST['nombre_mascota_nuevo']) ? trim($_POST['nombre_mascota_nuevo']) : '';
        $biografia_canina = isset($_POST['biografia_canina_nuevo']) ? trim($_POST['biografia_canina_nuevo']) : null;
        $enfermedades = isset($_POST['enfermedades_nuevo']) ? trim($_POST['enfermedades_nuevo']) : '';
        $raza_mascota = isset($_POST['raza_mascota_nuevo']) ? trim($_POST['raza_mascota_nuevo']) : '';
        $raza_mascota = $raza_mascota === '' ? null : $raza_mascota;
        $edad_mascota = (isset($_POST['edad_mascota_nuevo']) && $_POST['edad_mascota_nuevo'] !== '')
            ? intval($_POST['edad_mascota_nuevo']) : null;

        if (!empty($nombre_mascota)) {
            $avatar_mascota = '../../assets/default/dog.png'; 

            if (isset($_FILES['avatar_mascota_nuevo']) && $_FILES['avatar_mascota_nuevo']['error'] === UPLOAD_ERR_OK) {
                $ext = pathinfo($_FILES['avatar_mascota_nuevo']['name'], PATHINFO_EXTENSION);
                $nuevo_nombre = "avatar_pet_" . $id_usuario . "_" . time() . "." . $ext;
                if (move_uploaded_file($_FILES['avatar_mascota_nuevo']['tmp_name'], $directorio_subidas . $nuevo_nombre)) {
                    $avatar_mascota = "../../assets/uploads/" . $nuevo_nombre;
                }
            }

            $stmt_pet = $conn->prepare("INSERT INTO mascota_usuario (id_usuario, nombre_mascota, raza, edad, avatar_mascota, biografia_canina, enfermedades_discapacidades) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt_pet->bind_param("ississs", $id_usuario, $nombre_mascota, $raza_mascota, $edad_mascota, $avatar_mascota, $biografia_canina, $enfermedades);
            $stmt_pet->execute();
            $stmt_pet->close();
        }

    } elseif ($mascota_accion === 'editar') {
        $id_mascota = isset($_POST['select_id_mascota']) ? intval($_POST['select_id_mascota']) : 0;
        $biografia_canina = isset($_POST['biografia_canina_edit']) ? trim($_POST['biografia_canina_edit']) : null;
        $enfermedades = isset($_POST['enfermedades_edit']) ? trim($_POST['enfermedades_edit']) : '';

        if ($id_mascota > 0) {
            $stmt_check_pet = $conn->prepare("SELECT avatar_mascota, raza, edad FROM mascota_usuario WHERE id_mascota = ? AND id_usuario = ?");
            $stmt_check_pet->bind_param("ii", $id_mascota, $id_usuario);
            $stmt_check_pet->execute();
            $datos_mascota = $stmt_check_pet->get_result()->fetch_assoc();
            $stmt_check_pet->close();

            if ($datos_mascota) {
                $avatar_mascota = $datos_mascota['avatar_mascota'];

                // "Deja vacío para no cambiar": si no escribió nada, conservamos el valor actual
                $raza_mascota = (isset($_POST['raza_mascota_edit']) && trim($_POST['raza_mascota_edit']) !== '')
                    ? trim($_POST['raza_mascota_edit']) : $datos_mascota['raza'];
                $edad_mascota = (isset($_POST['edad_mascota_edit']) && $_POST['edad_mascota_edit'] !== '')
                    ? intval($_POST['edad_mascota_edit']) : $datos_mascota['edad'];

                if (isset($_FILES['avatar_mascota_edit']) && $_FILES['avatar_mascota_edit']['error'] === UPLOAD_ERR_OK) {
                    $ext = pathinfo($_FILES['avatar_mascota_edit']['name'], PATHINFO_EXTENSION);
                    $nuevo_nombre = "avatar_pet_" . $id_usuario . "_" . time() . "." . $ext;
                    if (move_uploaded_file($_FILES['avatar_mascota_edit']['tmp_name'], $directorio_subidas . $nuevo_nombre)) {
                        if (!empty($datos_mascota['avatar_mascota']) && $datos_mascota['avatar_mascota'] !== '../../assets/default/dog.png') {
                            $archivo_viejo_pet = str_replace("../../assets/uploads/", $directorio_subidas, $datos_mascota['avatar_mascota']);
                            if (file_exists($archivo_viejo_pet)) { unlink($archivo_viejo_pet); }
                        }
                        $avatar_mascota = "../../assets/uploads/" . $nuevo_nombre;
                    }
                }

                $stmt_update_pet = $conn->prepare("UPDATE mascota_usuario SET avatar_mascota = ?, raza = ?, edad = ?, biografia_canina = ?, enfermedades_discapacidades = ? WHERE id_mascota = ? AND id_usuario = ?");
                $stmt_update_pet->bind_param("ssissii", $avatar_mascota, $raza_mascota, $edad_mascota, $biografia_canina, $enfermedades, $id_mascota, $id_usuario);
                $stmt_update_pet->execute();
                $stmt_update_pet->close();
            }
        }
    }

    // REDIRECCIÓN: Desde controller/ subimos un nivel y entramos a las vistas admin
    header("Location: ../view/vistas/admin/usuario_admin.php");
    exit();
}