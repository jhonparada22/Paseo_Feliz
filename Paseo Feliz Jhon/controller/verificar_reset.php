<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

include_once '../model/conexion.php';

$data = json_decode(file_get_contents("php://input"));

// ---- PASO: Verificar código ----
if (!empty($data->accion) && $data->accion === 'verificar') {
    if (empty($data->email) || empty($data->codigo)) {
        echo json_encode(["success" => false, "message" => "Datos incompletos."]);
        exit;
    }

    $email  = strtolower($conn->real_escape_string($data->email));
    $codigo = $conn->real_escape_string($data->codigo);
    $now    = date('Y-m-d H:i:s');

    $result = $conn->query("SELECT id FROM codigos_verificacion WHERE email = '$email' AND codigo = '$codigo' AND tipo = 'recuperacion' AND expiracion > '$now'");

    if ($result->num_rows === 0) {
        echo json_encode(["success" => false, "message" => "Código incorrecto o expirado."]);
    } else {
        echo json_encode(["success" => true, "message" => "Código correcto."]);
    }
    exit;
}

// ---- PASO: Cambiar contraseña ----
if (!empty($data->accion) && $data->accion === 'cambiar') {
    if (empty($data->email) || empty($data->codigo) || empty($data->nueva_password)) {
        echo json_encode(["success" => false, "message" => "Datos incompletos."]);
        exit;
    }

    $email    = strtolower($conn->real_escape_string($data->email));
    $codigo   = $conn->real_escape_string($data->codigo);
    $now      = date('Y-m-d H:i:s');

    // Re-verificar el código antes de cambiar
    $result = $conn->query("SELECT id FROM codigos_verificacion WHERE email = '$email' AND codigo = '$codigo' AND tipo = 'recuperacion' AND expiracion > '$now'");
    if ($result->num_rows === 0) {
        echo json_encode(["success" => false, "message" => "Sesión expirada. Solicita un nuevo código."]);
        exit;
    }

    $nueva_password = password_hash($data->nueva_password, PASSWORD_BCRYPT);

    $stmt = $conn->prepare("UPDATE usuarios SET password = ? WHERE email = ?");
    $stmt->bind_param("ss", $nueva_password, $email);

    if ($stmt->execute() && $stmt->affected_rows > 0) {
        $conn->query("DELETE FROM codigos_verificacion WHERE email = '$email' AND tipo = 'recuperacion'");
        echo json_encode(["success" => true, "message" => "Contraseña cambiada exitosamente."]);
    } else {
        echo json_encode(["success" => false, "message" => "Error al actualizar la contraseña."]);
    }
    $stmt->close();
    exit;
}

echo json_encode(["success" => false, "message" => "Acción no reconocida."]);
?>
