<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

include_once 'conexion.php';

$data = json_decode(file_get_contents("php://input"));

if (!empty($data->email) && !empty($data->password)) {
    $email = strtolower($conn->real_escape_string($data->email));
    $password = $data->password;

    // 1. Usamos consultas preparadas para buscar al usuario por su correo
    $query = "SELECT nombre, email, password FROM usuarios WHERE email = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    
    // Obtener el resultado de la consulta
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $usuario = $result->fetch_assoc();
        
        // 2. Verificamos si la contraseña escrita coincide con la encriptada en la BD
        if (password_verify($password, $usuario['password'])) {
            echo json_encode([
                "success" => true,
                "message" => "Login correcto",
                "usuario" => [
                    "nombre" => $usuario['nombre'],
                    "email" => $usuario['email']
                ]
            ]);
        } else {
            echo json_encode(["success" => false, "message" => "Contraseña incorrecta"]);
        }
    } else {
        echo json_encode(["success" => false, "message" => "El correo no está registrado"]);
    }

    // 3. Cerramos la sentencia preparada
    $stmt->close();

} else {
    echo json_encode(["success" => false, "message" => "Campos vacíos"]);
}
?>