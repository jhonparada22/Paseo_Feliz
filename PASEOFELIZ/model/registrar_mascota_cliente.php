<?php
/**
 * registrar_mascota_cliente.php
 * Registra una mascota nueva del usuario en sesión vía AJAX (FormData).
 * Lo usa el wizard de paseos para registrar una mascota sin salir del
 * flujo de compra. Pide los mismos campos que el formulario del perfil
 * (usuario_info.php): nombre, raza, edad, foto, biografía y enfermedades.
 *
 * POST multipart:
 *   nombre_mascota (obligatorio), raza, edad,
 *   biografia_canina, enfermedades_discapacidades,
 *   avatar_mascota (archivo, opcional)
 *
 * Respuesta: { success, mascota: {id_mascota, nombre, raza, edad, avatar, biografia, notas} }
 */
include_once 'helpers.php';
include_once '../model/conexion.php';
include_once 'ActivityService.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

verificarSesion();
$idUsuario = (int)$_SESSION['usuario_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responder(false, [], 'Método no permitido.');
}

$nombre = substr(trim($_POST['nombre_mascota'] ?? ''), 0, 100);
if ($nombre === '') {
    responder(false, [], 'El nombre de la mascota es obligatorio.');
}

$raza = substr(trim($_POST['raza'] ?? ''), 0, 80);
$raza = $raza !== '' ? $raza : null;
$edad = (isset($_POST['edad']) && $_POST['edad'] !== '') ? intval($_POST['edad']) : null;
if ($edad !== null && ($edad < 0 || $edad > 30)) $edad = null;
$biografia    = substr(trim($_POST['biografia_canina'] ?? ''), 0, 1000);
$biografia    = $biografia !== '' ? $biografia : null;
$enfermedades = substr(trim($_POST['enfermedades_discapacidades'] ?? ''), 0, 1000);

// Avatar: mismo manejo y prefijo que controller/guardar_perfil.php,
// para que las vistas existentes lo lean igual
$directorio_subidas = __DIR__ . '/../view/assets/uploads/';
if (!is_dir($directorio_subidas)) {
    mkdir($directorio_subidas, 0777, true);
}
$avatarMascota = '../assets/default/dog.png';
if (isset($_FILES['avatar_mascota']) && $_FILES['avatar_mascota']['error'] === UPLOAD_ERR_OK) {
    $ext = strtolower(pathinfo($_FILES['avatar_mascota']['name'], PATHINFO_EXTENSION));
    if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
        $nuevoNombre = 'avatar_pet_' . $idUsuario . '_' . time() . '.' . $ext;
        if (move_uploaded_file($_FILES['avatar_mascota']['tmp_name'], $directorio_subidas . $nuevoNombre)) {
            $avatarMascota = '../assets/uploads/' . $nuevoNombre;
        }
    }
}

$stmt = $conn->prepare(
    "INSERT INTO mascota_usuario (id_usuario, nombre_mascota, raza, edad, avatar_mascota, biografia_canina, enfermedades_discapacidades)
     VALUES (?, ?, ?, ?, ?, ?, ?)"
);
$stmt->bind_param("ississs", $idUsuario, $nombre, $raza, $edad, $avatarMascota, $biografia, $enfermedades);
$stmt->execute();
$idMascota = $conn->insert_id;
$stmt->close();

ActivityService::registrar($conn, [
    'servicio' => 'sistema', 'tipo' => 'mascota_registrada',
    'titulo' => 'Nueva mascota registrada: ' . $nombre,
    'descripcion' => $raza ? ('Raza: ' . $raza) : null,
    'id_cliente' => $idUsuario, 'id_mascota' => $idMascota, 'mascota_nombre' => $nombre,
    'id_referencia' => $idMascota,
]);

// Misma forma y normalización de avatar que model/obtener_mascotas.php,
// para que el wizard la consuma sin adaptaciones
responder(true, [
    'mascota' => [
        'id_mascota' => (int)$idMascota,
        'id_usuario' => $idUsuario,
        'nombre'     => $nombre,
        'raza'       => $raza ?? '',
        'edad'       => $edad,
        'avatar'     => 'assets/' . ltrim(preg_replace('#^(\.\./)*assets/#', '', $avatarMascota), '/'),
        'biografia'  => $biografia ?? '',
        'notas'      => $enfermedades,
    ],
], 'Mascota registrada correctamente.');
?>
