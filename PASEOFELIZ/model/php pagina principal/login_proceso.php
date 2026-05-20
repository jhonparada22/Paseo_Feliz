<?php
// PASEOFELIZ/model/php pagina principal/login_proceso.php
include 'conexion.php'; 
session_start();

// Configura tu cédula real para ser el Admin Master
$admin_master_cc = "1092XXXXXX"; 

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        echo json_encode(['status' => 'error', 'message' => 'Por favor llena todos los campos']);
        exit;
    }

    try {
        //datos del usuario
        $sql = "SELECT cc, nombre, password, id_rol FROM usuarios WHERE email = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$email]);
        $user = $stmt->fetch(); // Ya configuraste por defecto FETCH_ASSOC en tu conexión, 

        if ($user && password_verify($password, $user['password'])) {
            
            $_SESSION['usuario_id'] = $user['cc'];
            $_SESSION['nombre'] = $user['nombre'];
            
            // LÓGICA DE REDIRECCIÓN AUTOMÁTICA
            if ($user['cc'] === $admin_master_cc) {
                $_SESSION['id_rol'] = 1;
                $url = "../../view/vistas/admin/index.php";
            } 
            elseif ($user['id_rol'] == 2) {
                $_SESSION['id_rol'] = 2;
                $url = "../../view/vistas/paseador/index.php";
            } 
            else {
                $_SESSION['id_rol'] = 3;
                $url = "../../view/vistas/cliente/index.php";
            }

            echo json_encode([
                'status' => 'success',
                'redirect' => $url
            ]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Correo o contraseña incorrectos']);
        }

    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => 'Error en el servidor: ' . $e->getMessage()]);
    }
}
?>