<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

include_once 'conexion.php';
include_once '../controller/controladortelegram.php'; // 👈 nuevo

$data = json_decode(file_get_contents("php://input"));

if (empty($data->email) || empty($data->codigo) || empty($data->nombre) || empty($data->sexo) || empty($data->password)) {
    echo json_encode(["success" => false, "message" => "Datos incompletos."]);
    exit;
}

$email    = strtolower($conn->real_escape_string($data->email));
$codigo   = $conn->real_escape_string($data->codigo);
$nombre   = $conn->real_escape_string($data->nombre);
$sexo     = $conn->real_escape_string($data->sexo);
$password = password_hash($data->password, PASSWORD_BCRYPT);

// Buscar código válido y no expirado
$now = date('Y-m-d H:i:s');
$result = $conn->query("SELECT id FROM codigos_verificacion WHERE email = '$email' AND codigo = '$codigo' AND tipo = 'registro' AND expiracion > '$now'");

if ($result->num_rows === 0) {
    echo json_encode(["success" => false, "message" => "Código incorrecto o expirado."]);
    exit;
}

// Verificar que el correo no haya sido registrado ya (doble check)
$checkEmail = $conn->query("SELECT id FROM usuarios WHERE email = '$email'");
if ($checkEmail->num_rows > 0) {
    echo json_encode(["success" => false, "message" => "Este correo ya está registrado."]);
    exit;
}

// Registrar al usuario
$stmt = $conn->prepare("INSERT INTO usuarios (nombre, email, sexo, password) VALUES (?, ?, ?, ?)");
$stmt->bind_param("ssss", $nombre, $email, $sexo, $password);

if ($stmt->execute()) {
    $nuevo_id = $stmt->insert_id;

    // Borrar el código usado
    $conn->query("DELETE FROM codigos_verificacion WHERE email = '$email' AND tipo = 'registro'");

    // Crear fila en membresias automáticamente (todo en 0 = sin membresía)
    $stmtM = $conn->prepare(
        "INSERT IGNORE INTO membresias (id_usuario, paseos, adiestramiento, hospedaje) VALUES (?, 0, 0, 0)"
    );
    if ($stmtM) {
        $stmtM->bind_param("i", $nuevo_id);
        $stmtM->execute();
        $stmtM->close();
    }

    // ── Notificar al grupo de Telegram (no debe romper el registro si falla) ──
    try {
        $telegram = new ControladorTelegram();
        $telegram->notificarNuevoUsuario($nombre, $email, $sexo);
    } catch (Exception $e) {
        // Silenciamos el error: el usuario ya quedó registrado correctamente,
        // un fallo de Telegram no debe afectar la respuesta al cliente.
    }

    echo json_encode(["success" => true, "message" => "Usuario registrado con éxito."]);
} else {
    echo json_encode(["success" => false, "message" => "Error al guardar: " . $stmt->error]);
}
$stmt->close();
?>