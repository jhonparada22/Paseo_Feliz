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
$telefono = 'No registrado';
$direccion = 'No registrada';
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
        if (!empty($perfil['telefono'])) $telefono = $perfil['telefono'];
        if (!empty($perfil['direccion'])) $direccion = $perfil['direccion'];
        // Normalizar: quitar cualquier ../ para dejar solo assets/uploads/foto.jpg
        if (!empty($perfil['avatar_url']))
            $avatar_url = 'assets/' . ltrim(preg_replace('#^(\.\./)*assets/#', '', $perfil['avatar_url']), '/');
        if (!empty($perfil['banner_url']))
            $banner_url = 'assets/' . ltrim(preg_replace('#^(\.\./)*assets/#', '', $perfil['banner_url']), '/');
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
    
    <link rel="stylesheet" href="../../css/admin/admin.css?v=<?php echo @filemtime(__DIR__ . '/../../css/admin/admin.css'); ?>">
    <link rel="stylesheet" href="../../css/admin/sidebar_admin.css?v=<?php echo @filemtime(__DIR__ . '/../../css/admin/sidebar_admin.css'); ?>">
    <link rel="stylesheet" href="../../css/principal_css/usuario.css?v=<?php echo @filemtime(__DIR__ . '/../../css/principal_css/usuario.css'); ?>">
    <link rel="stylesheet" href="../../css/responsive/responsive_principal.css?v=<?php echo @filemtime(__DIR__ . '/../../css/responsive/responsive_principal.css'); ?>">
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
                        <a href="./sub_menu/estado_bd.php"><li><i class="fas fa-database"></i><span>Estado del sistema</span></li></a>
                        <a href="./sub_menu/manual.php"><li><i class="fas fa-book-open"></i><span>Manual de uso</span></li></a>
                        
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
                <li><a href="adiestramiento_admin.php"><i class="fas fa-graduation-cap"></i><span>Adiestramiento</span></a></li>
                <li><a href="hospedaje_admin.php"><i class="fas fa-house"></i><span>Hospedaje</span></a></li>
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
            <!-- Banner + Avatar -->
            <div class="boceto-banner-container">
                <div class="boceto-banner-bg">
                    <?php if(!empty($banner_url)): ?>
                        <img src="../../<?php echo htmlspecialchars($banner_url); ?>" class="boceto-banner-img" alt="Banner">
                    <?php endif; ?>
                </div>
                
                <?php if(!empty($avatar_url)): ?>
                    <img src="../../<?php echo htmlspecialchars($avatar_url); ?>" class="boceto-avatar-user" alt="Avatar">
                <?php else: ?>
                    <div class="boceto-avatar-user" style="display:flex;align-items:center;justify-content:center;">
                        <i class="fas fa-user fa-2x" style="color:#fff;"></i>
                    </div>
                <?php endif; ?>
                
                <div class="boceto-nombre-linea"><?php echo htmlspecialchars($nombre_usuario); ?></div>
                <a href="usuario_info_admin.php" class="boceto-btn-lapiz"><i class="fas fa-pencil-alt"></i></a>
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
                        <div class="boceto-caja-datos">
                            <label>Teléfono</label>
                            <p><?php echo htmlspecialchars($telefono); ?></p>
                        </div>
                        <div class="boceto-caja-datos">
                            <label>Dirección</label>
                            <p><?php echo htmlspecialchars($direccion); ?></p>
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
                                while ($mascota = $res_m->fetch_assoc()) {
                                    $mascotas_arr[] = $mascota;
                                }
                                ?>
                                <!-- Pestañas de selección -->
                                <div class="pet-selector-tabs">
                                    <?php foreach ($mascotas_arr as $idx => $m): ?>
                                    <button class="pet-tab-btn <?php echo $idx === 0 ? 'active' : ''; ?>"
                                            onclick="mostrarMascota(<?php echo $idx; ?>)" type="button">
                                        <?php echo htmlspecialchars($m['nombre_mascota']); ?>
                                    </button>
                                    <?php endforeach; ?>
                                </div>

                                <!-- Tarjetas -->
                                <?php foreach ($mascotas_arr as $idx => $mascota):
                                    $pet_avatar_raw = $mascota['avatar_mascota'] ?? '';
                                    $pet_avatar = !empty($pet_avatar_raw)
                                        ? 'assets/' . ltrim(preg_replace('#^(\.\./)*assets/#', '', $pet_avatar_raw), '/')
                                        : '';
                                    $pet_bio    = !empty($mascota['biografia_canina'])            ? $mascota['biografia_canina']            : 'Sin descripción.';
                                    $pet_health = !empty($mascota['enfermedades_discapacidades']) ? $mascota['enfermedades_discapacidades'] : 'Ninguna registrada.';
                                    $pet_raza   = !empty($mascota['raza']) ? $mascota['raza'] : null;
                                    $pet_edad   = (isset($mascota['edad']) && $mascota['edad'] !== null && $mascota['edad'] !== '') ? $mascota['edad'] : null;
                                    // Línea "Raza • Edad" — se arma solo con los datos que sí existan
                                    $pet_meta_parts = [];
                                    if ($pet_raza !== null) $pet_meta_parts[] = htmlspecialchars($pet_raza);
                                    if ($pet_edad !== null) $pet_meta_parts[] = $pet_edad . ' ' . ($pet_edad == 1 ? 'año' : 'años');
                                    $pet_meta = implode(' • ', $pet_meta_parts);
                                ?>
                                <div class="boceto-mascota-card pet-card-panel <?php echo $idx !== 0 ? 'hidden-pet' : ''; ?>"
                                     id="pet-panel-<?php echo $idx; ?>">
                                    <div class="boceto-mascota-header">
                                        <?php if(!empty($pet_avatar)): ?>
                                            <img src="../../<?php echo htmlspecialchars($pet_avatar); ?>" class="boceto-avatar-pet" alt="Mascota">
                                        <?php else: ?>
                                            <div class="boceto-avatar-pet" style="display:flex;align-items:center;justify-content:center;background:#e8f5e9;">
                                                <i class="fas fa-dog" style="color:#27ae60;font-size:22px;"></i>
                                            </div>
                                        <?php endif; ?>
                                        <div style="flex:1;min-width:0;">
                                            <div class="boceto-nombre-pet"><?php echo htmlspecialchars($mascota['nombre_mascota']); ?></div>
                                            <?php if ($pet_meta !== ''): ?>
                                                <div class="boceto-mascota-meta"><?php echo $pet_meta; ?></div>
                                            <?php endif; ?>
                                        </div>
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