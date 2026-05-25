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
    
    // Encriptamos la contraseña por seguridad antes de guardarla
    $password_encriptada = password_hash($data->password, PASSWORD_BCRYPT);

    // Verificar si el correo ya existe
    $checkEmail = $conn->query("SELECT id FROM usuarios WHERE email = '$email'");
    if ($checkEmail->num_rows > 0) {
        echo json_encode(["success" => false, "message" => "Este correo ya está registrado"]);
        exit;
    }

    // 1. Preparamos la consulta usando marcadores de posición (?)
    $query = "INSERT INTO usuarios (nombre, email, sexo, password) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($query);

    // 2. Vinculamos las variables reales (las 4 son cadenas de texto: "ssss")
    $stmt->bind_param("ssss", $nombre, $email, $sexo, $password_encriptada);

    // 3. Ejecutamos la inserción y devolvemos la respuesta correcta
    if ($stmt->execute()) {
        echo json_encode(["success" => true, "message" => "Usuario registrado con éxito"]);
    } else {
        echo json_encode(["success" => false, "message" => "Error al guardar en la base de datos: " . $stmt->error]);
    }

    // 4. Cerramos la sentencia preparada
    $stmt->close();

} else {
    // Respuesta en caso de que falten datos en el JSON recibido
    echo json_encode(["success" => false, "message" => "Datos del formulario incompletos."]);
}
?>