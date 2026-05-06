<?php
// Pagina_principal/php/login_proceso.php
include 'conexion.php'; // Conecta con tu DB de Paseo Feliz
session_start();

// Configura aquí tu cédula real para ser el Admin Master
$admin_master_cc = "1092XXXXXX"; 

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Recibimos los datos del fetch de form.js
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        echo json_encode(['status' => 'error', 'message' => 'Por favor llena todos los campos']);
        exit;
    }

    try {
        // Buscamos al usuario por correo
        $sql = "SELECT cc, nombre, password, id_rol FROM usuarios WHERE email = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // Verificamos si el usuario existe y la contraseña es correcta
        if ($user && password_verify($password, $user['password'])) {
            
            // Creamos las variables de sesión[cite: 1]
            $_SESSION['usuario_id'] = $user['cc'];
            $_SESSION['nombre'] = $user['nombre'];
            
            // LÓGICA DE REDIRECCIÓN
            // 1. Si es tu cédula, vas a Admin sin importar qué diga la BD
            if ($user['cc'] === $admin_master_cc) {
                $_SESSION['id_rol'] = 1;
                $url = "../vistas/admin/index.php";
            } 
            // 2. Si el Administrador ya lo ascendió a Paseador (Rol 2)
            elseif ($user['id_rol'] == 2) {
                $_SESSION['id_rol'] = 2;
                $url = "../vistas/paseador/index.php";
            } 
            // 3. Por defecto es un Cliente (Rol 3)
            else {
                $_SESSION['id_rol'] = 3;
                $url = "../vistas/cliente/index.php";
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