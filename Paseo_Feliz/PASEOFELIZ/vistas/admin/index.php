<?php
// vistas/admin/index.php
include '../../Pagina_principal/php/conexion.php';
session_start();

// Seguridad: Solo tú (el Admin) puedes estar aquí
// Reemplaza '1092XXXXXX' por tu cédula real para la validación maestra
$admin_master_cc = "1092XXXXXX"; 

if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_id'] != $admin_master_cc) {
    header("Location: ../../Registro/form.html");
    exit();
}

// Consultar todos los usuarios menos a ti mismo
$query = $pdo->query("SELECT cc, nombre, email, id_rol FROM usuarios WHERE cc != '$admin_master_cc'");
$usuarios = $query->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Panel Administrativo - Paseo Feliz</title>
    <link rel="stylesheet" href="../../Registro/form.css"> <!-- Reutilizamos tus estilos -->
    <style>
        .admin-container { padding: 50px; background: #f4f7f6; min-height: 100vh; font-family: 'Montserrat', sans-serif; }
        .user-table { width: 100%; border-collapse: collapse; background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
        .user-table th, .user-table td { padding: 15px; text-align: left; border-bottom: 1px solid #eee; }
        .user-table th { background-color: #0a4e63; color: white; }
        .badge { padding: 5px 10px; border-radius: 20px; font-size: 12px; font-weight: bold; }
        .badge-cliente { background: #e2e2e2; color: #555; }
        .badge-paseador { background: #29fd53; color: #000; }
        .btn-promote { background: #226daa; color: white; border: none; padding: 8px 15px; border-radius: 5px; cursor: pointer; transition: 0.3s; }
        .btn-promote:hover { background: #0a4e63; transform: scale(1.05); }
    </style>
</head>
<body>

<div class="admin-container">
    <h1 style="margin-bottom: 30px; color: #0a4e63;">Gestión de Personal - Paseo Feliz</h1>
    
    <table class="user-table">
        <thead>
            <tr>
                <th>Cédula</th>
                <th>Nombre</th>
                <th>Correo</th>
                <th>Rol Actual</th>
                <th>Acción</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($usuarios as $user): ?>
            <tr>
                <td><?php echo $user['cc']; ?></td>
                <td><?php echo $user['nombre']; ?></td>
                <td><?php echo $user['email']; ?></td>
                <td>
                    <span class="badge <?php echo ($user['id_rol'] == 2) ? 'badge-paseador' : 'badge-cliente'; ?>">
                        <?php echo ($user['id_rol'] == 2) ? 'PASEADOR' : 'CLIENTE'; ?>
                    </span>
                </td>
                <td>
                    <?php if ($user['id_rol'] != 2): ?>
                        <button class="btn-promote" onclick="promoverUsuario('<?php echo $user['cc']; ?>')">
                            Ascender a Paseador
                        </button>
                    <?php else: ?>
                        <span style="color: #2e8d78; font-size: 14px;">Ya es parte del equipo</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    
    <a href="../../Pagina_principal/sub_menu/cerrar_sesion.html" class="button" style="display:inline-block; margin-top:30px; text-decoration:none; line-height:45px; text-align:center;">Cerrar Sesión</a>
</div>

<!-- Reutilizamos tu lógica de notificaciones -->
<div class="notification-overlay hidden" id="notification-overlay">
    <div class="notification-box">
        <p id="notification-message"></p>
    </div>
</div>

<script>
function promoverUsuario(cedula) {
    const formData = new FormData();
    formData.append('cc', cedula);

    fetch('../../Pagina_principal/php/asignar_rol.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.status === 'success') {
            mostrarAlertaLocal("Usuario actualizado con éxito");
            setTimeout(() => location.reload(), 1500);
        } else {
            alert("Error: " + data.message);
        }
    });
}

function mostrarAlertaLocal(msj) {
    const overlay = document.getElementById("notification-overlay");
    const message = document.getElementById("notification-message");
    message.textContent = msj;
    overlay.classList.remove("hidden");
}
</script>

</body>
</html>