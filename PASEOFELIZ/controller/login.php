<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

// 1. Iniciar la sesión del lado del servidor antes de cualquier salida
session_start();

// 2. Verificar la ruta de conexión. Según tu estructura está en ../model/conexion.php
include_once '../model/conexion.php';

// Leer los datos que vienen del frontend (React / JS)
$data = json_decode(file_get_contents("php://input"));

if (!empty($data->email) && !empty($data->password)) {
    $email = strtolower($conn->real_escape_string($data->email));
    $password = $data->password;

    // Traemos explícitamente el 'id' (que es como se llama en tu tabla 'usuarios')
    $query = "SELECT id, nombre, email, password FROM usuarios WHERE email = ?";
    $stmt = $conn->prepare($query);
    
    if (!$stmt) {
        echo json_encode(["success" => false, "message" => "Error en la preparación de la consulta: " . $conn->error]);
        exit;
    }

    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        // Guardamos los datos de la fila en la variable $usuario
        $usuario = $result->fetch_assoc();
        
        // Verificamos la contraseña encriptada
        if (password_verify($password, $usuario['password'])) {
            
            // 3. Guardar las variables de sesión usando el array correcto ($usuario)
            $_SESSION['usuario_logged'] = true;
            $_SESSION['usuario_id']     = $usuario['id']; // 'id' según tu esquema de BD
            $_SESSION['usuario_nombre'] = $usuario['nombre'];

            // 4. Verificar si el correo pertenece a un administrador
            $esAdmin = false;
            $queryAdmin = "SELECT id_admin FROM admin WHERE correo = ?";
            $stmtAdmin = $conn->prepare($queryAdmin);
            $stmtAdmin->bind_param("s", $email);
            $stmtAdmin->execute();
            $resultAdmin = $stmtAdmin->get_result();

            if ($resultAdmin->num_rows > 0) {
                $esAdmin = true;
                $_SESSION['usuario_admin'] = true;
            }
            $stmtAdmin->close();

            echo json_encode([
                "success" => true,
                "message" => "Login correcto",
                "esAdmin" => $esAdmin,
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
    $stmt->close();
} else {
    echo json_encode(["success" => false, "message" => "Datos incompletos"]);
}
?>