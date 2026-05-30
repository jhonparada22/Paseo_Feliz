<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

// 1. Iniciar la sesión del lado del servidor
session_start();

include_once '../model/conexion.php';

$data = json_decode(file_get_contents("php://input"));

if (!empty($data->email) && !empty($data->password)) {
    $email = strtolower($conn->real_escape_string($data->email));
    $password = $data->password;

    $query = "SELECT nombre, email, password FROM usuarios WHERE email = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $usuario = $result->fetch_assoc();
        
        if (password_verify($password, $usuario['password'])) {
            
            // 2. Guardar los datos en la sesión para proteger las demás páginas
            $_SESSION['usuario_logged'] = true;
            $_SESSION['usuario_nombre'] = $usuario['nombre'];
            $_SESSION['usuario_email'] = $usuario['email'];

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
        echo json_encode(["success" => false, "message" => "El usuario no existe"]);
    }
} else {
    echo json_encode(["success" => false, "message" => "Datos incompletos"]);
}
?>