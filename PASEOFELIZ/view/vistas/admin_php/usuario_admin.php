<?php 
// Iniciamos sesión o verificamos si ya existe
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// CORRECCIÓN DE RUTAS: Subimos 3 niveles para conectar correctamente en producción
include_once '../../../controller/control_acceso.php'; 
include_once '../../../model/conexion.php';

$id_usuario = $_SESSION['usuario_id'] ?? null;
$nombre_usuario = $_SESSION['usuario_nombre'] ?? 'Usuario';

$biografia = 'Sin biografía registrada.';
$cumpleanos = 'No especificado';
$profesion = 'No especificada';
$avatar_url = '';
$banner_url = '';

if ($id_usuario) {
    // Buscamos los datos de perfil del dueño de manera limpia
    $stmt = $conn->prepare("SELECT * FROM info_usuario WHERE id_usuario = ?");
    $stmt->bind_param("i", $id_usuario);
    $stmt->execute();
    $resultado = $stmt->get_result();
    
    if ($perfil = $resultado->fetch_assoc()) {
        if (!empty($perfil['biografia'])) $biografia = $perfil['biografia'];
        if (!empty($perfil['cumpleanos'])) $cumpleanos = $perfil['cumpleanos'];
        if (!empty($perfil['profesion'])) $profesion = $perfil['profesion'];
        if (!empty($perfil['avatar_url'])) $avatar_url = $perfil['avatar_url'];
        if (!empty($perfil['banner_url'])) $banner_url = $perfil['banner_url'];
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paseo Feliz - Perfil de Usuario</title>
    <link class="favicon" rel="icon" href="../../assets/images/logo.png" type="image/png">
    
    <link rel="stylesheet" href="../../css/admin/admin.css">
    <link rel="stylesheet" href="../../css/admin/sidebar_admin.css">
    <link rel="stylesheet" href="../../css/principal_css/usuario.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div id="contenedor_general" class="app-container">
        
        <nav class="sidebar">
            <div class="menu-hamburguesa-container">
                <div class="profile-circle" id="btn-menu"><i class="fas fa-bars"></i></div>
                <nav class="menu-desplegable" id="menu-latente">
                    <ul>
                        <a href="./sub_menu/conocenos.php"><li><i class="fas fa-camera"></i><span>Conócenos</span></li></a>
                        <a href="./sub_menu/direccion_oficial.php"><li><i class="fas fa-book-open"></i><span>Dirección oficial</span></li></a>
                        <a href="./sub_menu/centro_de_ayuda.php"><li><i class="fas fa-sliders-h"></i><span>Centro de ayuda</span></li></a>
                        <a href="./sub_menu/configuracion.php"><li><i class="fas fa-gear"></i><span>Configuración</span></li></a>
                        <li>
                            <a href="../../../controller/logout.php" style="color: #000000;">
                                <i class="fas fa-sign-out-alt"></i>
                                <span>Cerrar Sesión</span>
                            </a>
                        </li>
                    </ul>
                </nav>
            </div>
            <ul class="nav-links">
                <li><a href="index_admin.php"><i class="fas fa-house"></i><span>Inicio</span></a></li>
                <li><a href="usuarios_admin.php"><i class="fas fa-users"></i><span>Usuarios</span></a></li>
                <li><a href="paseadores_admin.php"><i class="fas fa-person-walking"></i><span>Paseadores</span></a></li>
                <li><div class="nav-sep"></div></li>
                <li><a href="paseos_admin.php"><i class="fas fa-route"></i><span>Paseos</span></a></li>
                <li><a href="pagos_admin.php"><i class="fas fa-credit-card"></i><span>Pagos</span></a></li>
                <li><div class="nav-sep"></div></li>
                <li><a href="Chat_admin.php"><i class="far fa-comment-alt"></i><span>Chat</span></a></li>
                <li><a href="mapa_admin.php"><i class="fas fa-map-location-dot"></i><span>Mapa</span></a></li>
                <li><a href="adopcion_admin.php"><i class="fas fa-bone"></i><span>Adopción</span></a></li>
                <li><div class="nav-sep"></div></li>
                <li class="active"><a href="#"><i class="fas fa-user"></i><span>Usuario</span></a></li>
            </ul>
        </nav>

        <main class="profile-main-content">
            <div class="boceto-banner-container">
                <?php if(!empty($banner_url)): ?>
                    <img src="<?php echo htmlspecialchars($banner_url); ?>" class="boceto-banner-img" alt="Banner">
                <?php endif; ?>
                
                <?php if(!empty($avatar_url)): ?>
                    <img src="<?php echo htmlspecialchars($avatar_url); ?>" class="boceto-avatar-user" alt="Avatar">
                <?php else: ?>
                    <div class="boceto-avatar-user" style="display: flex; align-items: center; justify-content: center; background: #9d9d9d;">
                        <i class="fas fa-user fa-3x" style="color: #fff;"></i>
                    </div>
                <?php endif; ?>
                
                <div class="boceto-nombre-linea"><?php echo htmlspecialchars($nombre_usuario); ?></div>
                <a href="usuario_info_admin.php" class="boceto-btn-lapiz"><i class="fas fa-pencil-alt"></i></a>
            </div>

            <div class="profile-grid">
                <div class="columna-boceto">
                    <div class="boceto-caja-datos">
                        <label>Biografía</label>
                        <p><?php echo htmlspecialchars($biografia); ?></p>
                    </div>

                    <div class="boceto-caja-datos">
                        <label>Cumpleaños</label>
                        <p><?php echo htmlspecialchars($cumpleanos); ?></p>
                    </div>

                    <div class="boceto-caja-datos">
                        <label>Profesión</label>
                        <p><?php echo htmlspecialchars($profesion); ?></p>
                    </div>
                </div>

                <div class="columna-boceto columna-derecha">
                    <?php 
                    if ($id_usuario) {
                        $stmt_m = $conn->prepare("SELECT * FROM mascota_usuario WHERE id_usuario = ?");
                        $stmt_m->bind_param("i", $id_usuario);
                        $stmt_m->execute();
                        $res_m = $stmt_m->get_result();
                        
                        if ($res_m->num_rows === 0) {
                            echo '<div class="boceto-caja-datos" style="text-align: center;">';
                            echo '<p style="color: #7f8c8d; margin: 0;"><i class="fas fa-dog"></i> No hay mascotas registradas.</p>';
                            echo '</div>';
                        } else {
                            while ($mascota = $res_m->fetch_assoc()) {
                                $pet_avatar = $mascota['avatar_mascota'] ?? '';
                                $pet_bio = !empty($mascota['biografia_canina']) ? $mascota['biografia_canina'] : 'Sin descripción.';
                                $pet_health = !empty($mascota['enfermedades_discapacidades']) ? $mascota['enfermedades_discapacidades'] : 'Ninguna registrada.';
                                ?>
                                <div class="boceto-mascota-card">
                                    <div class="boceto-mascota-header">
                                        <?php if(!empty($pet_avatar)): ?>
                                            <img src="<?php echo htmlspecialchars($pet_avatar); ?>" class="boceto-avatar-pet" alt="Mascota">
                                        <?php else: ?>
                                            <div class="boceto-avatar-pet" style="display: flex; align-items: center; justify-content: center; background: #ccc;">
                                                <i class="fas fa-dog fa-lg"></i>
                                            </div>
                                        <?php endif; ?>
                                        <div class="boceto-nombre-pet"><?php echo htmlspecialchars($mascota['nombre_mascota']); ?></div>
                                    </div>
                                    <div class="boceto-caja-datos" style="margin-bottom: 10px; border-width: 2px;">
                                        <label>Biografía Canina</label>
                                        <p><?php echo htmlspecialchars($pet_bio); ?></p>
                                    </div>
                                    <div class="boceto-caja-datos" style="border-width: 2px; background: #fff5f5;">
                                        <label style="color: #c53030;">Salud / Condiciones</label>
                                        <p><?php echo htmlspecialchars($pet_health); ?></p>
                                    </div>
                                </div>
                                <?php
                            }
                        }
                        $stmt_m->close();
                    }
                    ?>
                </div>
            </div>
        </main>
    </div>

    <script>
        const btnMenu = document.getElementById('btn-menu');
        const menuLatente = document.getElementById('menu-latente');

        if (btnMenu && menuLatente) {
            btnMenu.addEventListener('click', () => {
                menuLatente.classList.toggle('show');
            });

            window.addEventListener('click', (e) => {
                if (!btnMenu.contains(e.target) && !menuLatente.contains(e.target)) {
                    menuLatente.classList.remove('show');
                }
            });
        }
    </script>
</body>
</html>