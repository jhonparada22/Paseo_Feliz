<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

include_once 'conexion.php';

$data = json_decode(file_get_contents("php://input"));

if (!empty($data->nombre) && !empty($data->email) && !empty($data->sexo) && !empty($data->password)) {
    $nombre = $conn->real_escape_string($data->nombre);
    $email = strtolower($conn->real_escape_string($data->email));
    $sexo = $conn->real_escape_string($data->sexo);
    
    // Encriptamos la contraseña por seguridad
    $password_encriptada = password_hash($data->password, PASSWORD_BCRYPT);

    // Verificar si el correo ya existe en MySQL
    $checkEmail = $conn->query("SELECT id FROM usuarios WHERE email = '$email'");
    if ($checkEmail->num_rows > 0) {
        echo json_encode(["success" => false, "message" => "Este correo ya está registrado"]);
        exit;
    }

    // Consulta exacta con las 4 columnas reales de tu tabla
    $query = "INSERT INTO usuarios (nombre, email, sexo, password) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($query);

    // Vinculamos las 4 variables de tipo string ("ssss")
    $stmt->bind_param("ssss", $nombre, $email, $sexo, $password_encriptada);

    if ($stmt->execute()) {
        echo json_encode(["success" => true, "message" => "Usuario registrado con éxito"]);
    } else {
        echo json_encode(["success" => false, "message" => "Error al guardar en la base de datos: " . $stmt->error]);
    }
    $stmt->close();
} else {
    echo json_encode(["success" => false, "message" => "Datos incompletos"]);
}
?>