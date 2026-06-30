<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

session_start();

include_once '../model/conexion.php';

$data = json_decode(file_get_contents("php://input"));

if (!empty($data->email) && !empty($data->password)) {
    $email = strtolower($conn->real_escape_string($data->email));
    $password = $data->password;

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
        $usuario = $result->fetch_assoc();

        if (password_verify($password, $usuario['password'])) {

            $_SESSION['usuario_logged'] = true;
            $_SESSION['usuario_id']     = $usuario['id'];
            $_SESSION['usuario_nombre'] = $usuario['nombre'];

            // ── Verificar si es admin ─────────────────────────────────────────
            $esAdmin = false;
            $stmtAdmin = $conn->prepare("SELECT id_admin FROM admin WHERE correo = ?");
            $stmtAdmin->bind_param("s", $email);
            $stmtAdmin->execute();
            if ($stmtAdmin->get_result()->num_rows > 0) {
                $esAdmin = true;
                $_SESSION['usuario_admin'] = true;
            }
            $stmtAdmin->close();

            // ── Verificar si es paseador ──────────────────────────────────────
            $esPaseador = false;
            $stmtPaseador = $conn->prepare("SELECT id_paseador FROM paseadores WHERE id_usuario = ? LIMIT 1");
            $stmtPaseador->bind_param("i", $usuario['id']);
            $stmtPaseador->execute();
            if ($stmtPaseador->get_result()->num_rows > 0) {
                $esPaseador = true;
                $_SESSION['es_paseador'] = true;
            }
            $stmtPaseador->close();

            // ── Garantizar fila en membresias ─────────────────────────────────
            $stmtM = $conn->prepare(
                "INSERT IGNORE INTO membresias (id_usuario, paseos, adiestramiento, hospedaje) VALUES (?, 0, 0, 0)"
            );
            if ($stmtM) {
                $stmtM->bind_param("i", $usuario['id']);
                $stmtM->execute();
                $stmtM->close();
            }

            echo json_encode([
                "success"    => true,
                "message"    => "Login correcto",
                "esAdmin"    => $esAdmin,
                "esPaseador" => $esPaseador,
                "usuario"    => [
                    "nombre" => $usuario['nombre'],
                    "email"  => $usuario['email']
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