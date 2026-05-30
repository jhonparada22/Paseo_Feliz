<?php
include_once 'control_acceso.php';
include_once '../model/conexion.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_usuario = $_SESSION['usuario_id'] ?? null;

    if (!$id_usuario) {
        header("Location: ../view/pagina_principal/usuario.php");
        exit();
    }

    $biografia = isset($_POST['biografia']) ? trim($_POST['biografia']) : null;
    $cumpleanos = !empty($_POST['cumpleanos']) ? $_POST['cumpleanos'] : null;
    $direccion = isset($_POST['direccion']) ? trim($_POST['direccion']) : null;
    $telefono = isset($_POST['telefono']) ? trim($_POST['telefono']) : null;
    $profesion = isset($_POST['profesion']) ? trim($_POST['profesion']) : null;

    // Ruta física para ByetHost basada en tu carpeta creada
    $directorio_subidas = "../view/assets/uploads/";
    if (!is_dir($directorio_subidas)) {
        mkdir($directorio_subidas, 0777, true);
    }

    $stmt_check = $conn->prepare("SELECT id_info, avatar_url, banner_url FROM info_usuario WHERE id_usuario = ?");
    $stmt_check->bind_param("i", $id_usuario);
    $stmt_check->execute();
    $resultado_check = $stmt_check->get_result();
    $datos_perfil_actual = $resultado_check->fetch_assoc();
    $stmt_check->close();

    $avatar_url = $datos_perfil_actual['avatar_url'] ?? '';
    $banner_url = $datos_perfil_actual['banner_url'] ?? '';

    // --- 1. PROCESAR AVATAR DEL USUARIO (ICONO) ---
    if (isset($_FILES['avatar_usuario']) && $_FILES['avatar_usuario']['error'] === UPLOAD_ERR_OK) {
        $ext = pathinfo($_FILES['avatar_usuario']['name'], PATHINFO_EXTENSION);
        $nuevo_nombre = "avatar_user_" . $id_usuario . "_" . time() . "." . $ext;
        
        if (move_uploaded_file($_FILES['avatar_usuario']['tmp_name'], $directorio_subidas . $nuevo_nombre)) {
            
            // 🔥 Si ya existía un avatar viejo registrado, lo borramos del servidor
            if (!empty($datos_perfil_actual['avatar_url'])) {
                // Convertimos la URL relativa de la BD a la ruta física real del servidor
                $archivo_viejo = str_replace("../assets/uploads/", $directorio_subidas, $datos_perfil_actual['avatar_url']);
                if (file_exists($archivo_viejo)) {
                    unlink($archivo_viejo); // Borra el archivo físico huerfano
                }
            }
            
            $avatar_url = "../assets/uploads/" . $nuevo_nombre;
        }
    }

    // --- 2. PROCESAR BANNER DEL USUARIO ---
    if (isset($_FILES['banner_usuario']) && $_FILES['banner_usuario']['error'] === UPLOAD_ERR_OK) {
        $ext = pathinfo($_FILES['banner_usuario']['name'], PATHINFO_EXTENSION);
        $nuevo_nombre = "banner_user_" . $id_usuario . "_" . time() . "." . $ext;
        
        if (move_uploaded_file($_FILES['banner_usuario']['tmp_name'], $directorio_subidas . $nuevo_nombre)) {
            
            // 🔥 Si ya existía un banner viejo registrado, lo borramos del servidor
            if (!empty($datos_perfil_actual['banner_url'])) {
                // Convertimos la URL relativa de la BD a la ruta física real del servidor
                $banner_viejo = str_replace("../assets/uploads/", $directorio_subidas, $datos_perfil_actual['banner_url']);
                if (file_exists($banner_viejo)) {
                    unlink($banner_viejo); // Borra el archivo físico huerfano
                }
            }
            
            $banner_url = "../assets/uploads/" . $nuevo_nombre;
        }
    }

    if ($datos_perfil_actual) {
        $stmt_update = $conn->prepare("UPDATE info_usuario SET biografia = ?, cumpleanos = ?, telefono = ?, direccion = ?, avatar_url = ?, banner_url = ?, profesion = ? WHERE id_usuario = ?");
        $stmt_update->bind_param("sssssssi", $biografia, $cumpleanos, $telefono, $direccion, $avatar_url, $banner_url, $profesion, $id_usuario);
        $stmt_update->execute();
        $stmt_update->close();
    } else {
        $stmt_insert = $conn->prepare("INSERT INTO info_usuario (id_usuario, biografia, cumpleanos, telefono, direccion, avatar_url, banner_url, profesion) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt_insert->bind_param("isssssss", $id_usuario, $biografia, $cumpleanos, $telefono, $direccion, $avatar_url, $banner_url, $profesion);
        $stmt_insert->execute();
        $stmt_insert->close();
    }

    $mascota_accion = $_POST['mascota_accion'] ?? 'editar';

// --- 3. PROCESAR ACCIONES DE MASCOTA (AGREGAR / EDITAR) ---
    $mascota_accion = $_POST['mascota_accion'] ?? 'editar';

    if ($mascota_accion === 'agregar') {
        $nombre_mascota = isset($_POST['nombre_mascota_nuevo']) ? trim($_POST['nombre_mascota_nuevo']) : '';
        $biografia_canina = isset($_POST['biografia_canina_nuevo']) ? trim($_POST['biografia_canina_nuevo']) : null;
        $enfermedades = isset($_POST['enfermedades_nuevo']) ? trim($_POST['enfermedades_nuevo']) : '';

        if (!empty($nombre_mascota)) {
            // Establecemos las imágenes predeterminadas por si no sube nada
            $avatar_mascota = '../assets/default/dog.png';
            $banner_mascota = '../assets/default/dog-banner.png';

            // Subir avatar nuevo si existe
            if (isset($_FILES['avatar_mascota_nuevo']) && $_FILES['avatar_mascota_nuevo']['error'] === UPLOAD_ERR_OK) {
                $ext = pathinfo($_FILES['avatar_mascota_nuevo']['name'], PATHINFO_EXTENSION);
                $nuevo_nombre = "avatar_pet_" . $id_usuario . "_" . time() . "." . $ext;
                if (move_uploaded_file($_FILES['avatar_mascota_nuevo']['tmp_name'], $directorio_subidas . $nuevo_nombre)) {
                    $avatar_mascota = "../assets/uploads/" . $nuevo_nombre;
                }
            }

            // Subir banner nuevo si existe
            if (isset($_FILES['banner_mascota_nuevo']) && $_FILES['banner_mascota_nuevo']['error'] === UPLOAD_ERR_OK) {
                $ext = pathinfo($_FILES['banner_mascota_nuevo']['name'], PATHINFO_EXTENSION);
                $nuevo_nombre = "banner_pet_" . $id_usuario . "_" . time() . "." . $ext;
                if (move_uploaded_file($_FILES['banner_mascota_nuevo']['tmp_name'], $directorio_subidas . $nuevo_nombre)) {
                    $banner_mascota = "../assets/uploads/" . $nuevo_nombre;
                }
            }

            // CORRECCIÓN FIX: Se usa 'biografia_canina' que es el nombre real en tu tabla
            $stmt_pet = $conn->prepare("INSERT INTO mascota_usuario (id_usuario, nombre_mascota, avatar_mascota, banner_mascota, biografia_canina, enfermedades_discapacidades) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt_pet->bind_param("isssss", $id_usuario, $nombre_mascota, $avatar_mascota, $banner_mascota, $biografia_canina, $enfermedades);
            $stmt_pet->execute();
            $stmt_pet->close();
        }

    } elseif ($mascota_accion === 'editar') {
        // Obtenemos el ID de la mascota seleccionada en el <select>
        $id_mascota = isset($_POST['select_id_mascota']) ? intval($_POST['select_id_mascota']) : 0;
        $biografia_canina = isset($_POST['biografia_canina_edit']) ? trim($_POST['biografia_canina_edit']) : null;
        $enfermedades = isset($_POST['enfermedades_edit']) ? trim($_POST['enfermedades_edit']) : '';

        if ($id_mascota > 0) {
            // 1. Consultamos los datos actuales de la mascota para no perder las fotos si no se suben unas nuevas
            $stmt_check_pet = $conn->prepare("SELECT avatar_mascota, banner_mascota FROM mascota_usuario WHERE id_mascota = ? AND id_usuario = ?");
            $stmt_check_pet->bind_param("ii", $id_mascota, $id_usuario);
            $stmt_check_pet->execute();
            $datos_mascota = $stmt_check_pet->get_result()->fetch_assoc();
            $stmt_check_pet->close();

            if ($datos_mascota) {
                $avatar_mascota = $datos_mascota['avatar_mascota'];
                $banner_mascota = $datos_mascota['banner_mascota'];

                // --- PROCESAR EDICIÓN DE AVATAR DE LA MASCOTA ---
                if (isset($_FILES['avatar_mascota_edit']) && $_FILES['avatar_mascota_edit']['error'] === UPLOAD_ERR_OK) {
                    $ext = pathinfo($_FILES['avatar_mascota_edit']['name'], PATHINFO_EXTENSION);
                    $nuevo_nombre = "avatar_pet_" . $id_usuario . "_" . time() . "." . $ext;
                    
                    if (move_uploaded_file($_FILES['avatar_mascota_edit']['tmp_name'], $directorio_subidas . $nuevo_nombre)) {
                        // Borramos la foto física anterior si no era la por defecto
                        if (!empty($datos_mascota['avatar_mascota']) && $datos_mascota['avatar_mascota'] !== '../assets/default/dog.png') {
                            $archivo_viejo_pet = str_replace("../assets/uploads/", $directorio_subidas, $datos_mascota['avatar_mascota']);
                            if (file_exists($archivo_viejo_pet)) {
                                unlink($archivo_viejo_pet);
                            }
                        }
                        $avatar_mascota = "../assets/uploads/" . $nuevo_nombre;
                    }
                }

                // --- PROCESAR EDICIÓN DE BANNER DE LA MASCOTA ---
                if (isset($_FILES['banner_mascota_edit']) && $_FILES['banner_mascota_edit']['error'] === UPLOAD_ERR_OK) {
                    $ext = pathinfo($_FILES['banner_mascota_edit']['name'], PATHINFO_EXTENSION);
                    $nuevo_nombre = "banner_pet_" . $id_usuario . "_" . time() . "." . $ext;
                    
                    if (move_uploaded_file($_FILES['banner_mascota_edit']['tmp_name'], $directorio_subidas . $nuevo_nombre)) {
                        // Borramos el banner físico anterior si no era el por defecto
                        if (!empty($datos_mascota['banner_mascota']) && $datos_mascota['banner_mascota'] !== '../assets/default/dog-banner.png') {
                            $banner_viejo_pet = str_replace("../assets/uploads/", $directorio_subidas, $datos_mascota['banner_mascota']);
                            if (file_exists($banner_viejo_pet)) {
                                unlink($banner_viejo_pet);
                            }
                        }
                        $banner_mascota = "../assets/uploads/" . $nuevo_nombre;
                    }
                }

                // 2. Ejecutamos el UPDATE en la base de datos
                $stmt_update_pet = $conn->prepare("UPDATE mascota_usuario SET avatar_mascota = ?, banner_mascota = ?, biografia_canina = ?, enfermedades_discapacidades = ? WHERE id_mascota = ? AND id_usuario = ?");
                $stmt_update_pet->bind_param("ssssii", $avatar_mascota, $banner_mascota, $biografia_canina, $enfermedades, $id_mascota, $id_usuario);
                $stmt_update_pet->execute();
                $stmt_update_pet->close();
            }
        }
    }

    header("Location: ../view/pagina_principal/usuario.php");
    exit();
} else {
    header("Location: ../view/pagina_principal/usuario.php");
    exit();
}