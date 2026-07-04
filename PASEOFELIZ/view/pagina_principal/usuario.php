<?php 
include_once '../../controller/control_acceso.php'; 
include_once '../../model/conexion.php';

$id_usuario = $_SESSION['usuario_id'] ?? null;
$nombre_usuario = $_SESSION['usuario_nombre'] ?? 'Usuario';

$biografia = 'Sin biografía registrada.';
$cumpleanos = 'No especificado';
$profesion = 'No especificada';
$avatar_url = '';
$banner_url = '';

if ($id_usuario) {
    // 1. Buscamos los datos de perfil del dueño
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
    <link rel="icon" href="../assets/images/logo.png" type="image/png">
    
    <link rel="stylesheet" href="../css/principal_css/global.css">
    <link rel="stylesheet" href="../css/principal_css/usuario.css">
    <link rel="stylesheet" href="../css/principal_css/sidebar_usuario.css">
    <link rel="stylesheet" href="../css/responsive/responsive_principal.css?v=<?php echo @filemtime(__DIR__ . '/../css/principal_css/responsive_principal.css'); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="usuario-page">
    <div id="contenedor_general" class="app-container">
        
               <nav class="sidebar">
            <div class="menu-hamburguesa-container">
                <div class="profile-circle" id="btn-menu"><i class="fas fa-bars"></i></div>
                <nav class="menu-desplegable" id="menu-latente">
                    <ul>
                        <li><a href="./sub_menu/conocenos.php"><i class="fas fa-camera"></i> <span>Conocenos</span></a></li>
                        <li><a href="./sub_menu/direccion_oficial.php"><i class="fas fa-book-open"></i> <span>Direccion oficial</span></a></li>
                        <li><a href="./sub_menu/centro_de_ayuda.php"><i class="fas fa-sliders-h"></i> <span>Centro de ayuda</span></a></li>
                        <li><a href="./sub_menu/configuracion.php"><i class="fas fa-gear"></i> <span>Configuracion</span></a></li>
                        <li><a href="../../controller/logout.php" class="logout-link"><i class="fas fa-sign-out-alt"></i> <span>Cerrar Sesión</span></a></li>
                    </ul>
                </nav>
            </div>
            <ul class="nav-links">
                <li><a href="inicio.php"><i class="fas fa-paw"></i> <span>Servicios</span></a></li>
                <li><a href="Chat.php"><i class="far fa-comment-alt"></i> <span>Chat</span></a></li>
                <li><a href="mapa.php"><i class="fas fa-map-marker-alt"></i> <span>Mapa</span></a></li>
                <li><a href="adopcion.php"><i class="fas fa-bone"></i> <span>Adopción</span></a></li>
                <li class="active"><a href="#"><i class="fas fa-user"></i> <span>Usuario</span></a></li>
            </ul>
        </nav>

        <main class="profile-main-content">

            <!-- Banner + Avatar -->
            <div class="boceto-banner-container">
                <div class="boceto-banner-bg">
                    <?php if(!empty($banner_url)): ?>
                        <img src="<?php echo htmlspecialchars($banner_url); ?>" class="boceto-banner-img" alt="Banner">
                    <?php endif; ?>
                </div>

                <?php if(!empty($avatar_url)): ?>
                    <img src="<?php echo htmlspecialchars($avatar_url); ?>" class="boceto-avatar-user" alt="Avatar">
                <?php else: ?>
                    <div class="boceto-avatar-user" style="display:flex;align-items:center;justify-content:center;">
                        <i class="fas fa-user fa-2x" style="color:#fff;"></i>
                    </div>
                <?php endif; ?>

                <div class="boceto-nombre-linea"><?php echo htmlspecialchars($nombre_usuario); ?></div>
                <a href="usuario_info.php" class="boceto-btn-lapiz"><i class="fas fa-pencil-alt"></i></a>
            </div>
            <div class="profile-subheader"></div>

            <!-- Grid de contenido -->
            <div class="profile-grid">

                <!-- Columna izquierda: Sobre mí -->
                <div class="columna-boceto">
                    <div class="columna-card">
                        <h3 class="seccion-titulo">
                            <i class="fas fa-user-circle"></i> Sobre mí
                        </h3>
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
                </div>

                <!-- Columna derecha: Mascotas -->
                <div class="columna-boceto columna-derecha">
                    <div class="columna-card">
                        <h3 class="seccion-titulo">
                            <i class="fas fa-paw"></i> Sobre mis mascotas
                        </h3>
                        <?php
                        if ($id_usuario) {
                            $stmt_m = $conn->prepare("SELECT * FROM mascota_usuario WHERE id_usuario = ?");
                            $stmt_m->bind_param("i", $id_usuario);
                            $stmt_m->execute();
                            $res_m = $stmt_m->get_result();

                            if ($res_m->num_rows === 0) {
                                echo '<div class="boceto-caja-datos" style="text-align:center;padding:28px 14px;">';
                                echo '<i class="fas fa-dog fa-2x" style="color:#cbd5e0;display:block;margin-bottom:10px;"></i>';
                                echo '<p style="color:#94a3b8;margin:0;font-size:14px;">No hay mascotas registradas.</p>';
                                echo '</div>';
                            } else {
                                $mascotas_arr = [];
                                while ($m = $res_m->fetch_assoc()) $mascotas_arr[] = $m;
                                ?>
                                <div class="pet-selector-tabs">
                                    <?php foreach ($mascotas_arr as $idx => $m): ?>
                                    <button class="pet-tab-btn <?php echo $idx === 0 ? 'active' : ''; ?>"
                                            onclick="mostrarMascota(<?php echo $idx; ?>)" type="button">
                                        <?php echo htmlspecialchars($m['nombre_mascota']); ?>
                                    </button>
                                    <?php endforeach; ?>
                                </div>

                                <?php foreach ($mascotas_arr as $idx => $mascota):
                                    $pet_avatar = $mascota['avatar_mascota'] ?? '';
                                    $pet_bio    = !empty($mascota['biografia_canina'])            ? $mascota['biografia_canina']            : 'Sin descripción.';
                                    $pet_health = !empty($mascota['enfermedades_discapacidades']) ? $mascota['enfermedades_discapacidades'] : 'Ninguna registrada.';
                                ?>
                                <div class="boceto-mascota-card pet-card-panel <?php echo $idx !== 0 ? 'hidden-pet' : ''; ?>"
                                     id="pet-panel-<?php echo $idx; ?>">
                                    <div class="boceto-mascota-header">
                                        <?php if(!empty($pet_avatar)): ?>
                                            <img src="<?php echo htmlspecialchars($pet_avatar); ?>" class="boceto-avatar-pet" alt="Mascota">
                                        <?php else: ?>
                                            <div class="boceto-avatar-pet" style="display:flex;align-items:center;justify-content:center;background:#e8f5e9;">
                                                <i class="fas fa-dog" style="color:#27ae60;font-size:22px;"></i>
                                            </div>
                                        <?php endif; ?>
                                        <div class="boceto-nombre-pet"><?php echo htmlspecialchars($mascota['nombre_mascota']); ?></div>
                                    </div>
                                    <div class="boceto-caja-datos" style="margin-bottom:10px;">
                                        <label>Biografía Canina</label>
                                        <p><?php echo htmlspecialchars($pet_bio); ?></p>
                                    </div>
                                    <div class="boceto-caja-datos" style="background:#fff5f5;border-left-color:#e53e3e;">
                                        <label style="color:#c53030;">Salud / Condiciones</label>
                                        <p><?php echo htmlspecialchars($pet_health); ?></p>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                                <?php
                            }
                            $stmt_m->close();
                        }
                        ?>
                    </div>
                </div>
            </div>

            <script>
            function mostrarMascota(idx) {
                document.querySelectorAll('.pet-card-panel').forEach((el, i) => {
                    el.classList.toggle('hidden-pet', i !== idx);
                });
                document.querySelectorAll('.pet-tab-btn').forEach((btn, i) => {
                    btn.classList.toggle('active', i === idx);
                });
            }
            </script>
        </main>
    </div>

    <script>
        (function() {
            const btn  = document.getElementById('btn-menu');
            const menu = document.getElementById('menu-latente');
            if (!btn || !menu) return;
            btn.addEventListener('click', function(e) {
                e.stopPropagation();
                menu.classList.toggle('show');
            });
            document.addEventListener('click', function(e) {
                if (!menu.contains(e.target) && e.target !== btn)
                    menu.classList.remove('show');
            });
        })();
    </script>
</body>
</html>