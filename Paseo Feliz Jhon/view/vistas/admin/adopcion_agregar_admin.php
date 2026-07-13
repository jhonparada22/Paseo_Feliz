<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include_once '../../../controller/control_acceso.php';
include_once '../../../model/conexion.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre     = trim($_POST['nombre']     ?? '');
    $edad       = trim($_POST['edad']       ?? '');
    $tamano     = trim($_POST['tamano']     ?? '');
    $raza       = trim($_POST['raza']       ?? '');
    $color      = trim($_POST['color']      ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $requisitos  = trim($_POST['requisitos']  ?? '');

    // Validar campos básicos
    if (!$nombre || !$edad || !$tamano || !$raza || !$color || !$descripcion || !$requisitos) {
        $error = 'Por favor completa todos los campos.';
    } elseif (empty($_FILES['img_adop']['name'])) {
        $error = 'Debes subir una imagen.';
    } else {
        // Subir imagen
        $ext_permitidas = ['jpg','jpeg','png','webp','gif'];
        $ext = strtolower(pathinfo($_FILES['img_adop']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $ext_permitidas)) {
            $error = 'Formato de imagen no permitido. Usa JPG, PNG o WEBP.';
        } else {
            $nombre_archivo = preg_replace('/[^a-zA-Z0-9_-]/', '', str_replace(' ', '_', $nombre)) . '_' . time() . '.' . $ext;
            $carpeta        = "../../assets/recursos_pagina_principal/adopcion_imagenes/";
            if (!is_dir($carpeta)) mkdir($carpeta, 0755, true);
            $ruta_destino   = $carpeta . $nombre_archivo;

            if (move_uploaded_file($_FILES['img_adop']['tmp_name'], $ruta_destino)) {
                $stmt = $conn->prepare(
                    "INSERT INTO adopcion (nombre, img_adop, edad, tamano, raza, color, descripcion, requisitos)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
                );
                $stmt->bind_param("ssssssss", $nombre, $nombre_archivo, $edad, $tamano, $raza, $color, $descripcion, $requisitos);
                $stmt->execute();
                $stmt->close();

                header("Location: adopcion_admin.php?msg=guardado");
                exit();
            } else {
                $error = 'Error al subir la imagen. Verifica los permisos de la carpeta.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paseo Feliz – Agregar Adopción</title>
    <link rel="icon" href="../../assets/images/logo.png" type="image/png">
    <link rel="stylesheet" href="../../css/admin/admin.css?v=<?php echo @filemtime(__DIR__ . '/../../css/admin/admin.css'); ?>">
    <link rel="stylesheet" href="../../css/admin/sidebar_admin.css?v=<?php echo @filemtime(__DIR__ . '/../../css/admin/sidebar_admin.css'); ?>">
    <link rel="stylesheet" href="../../css/admin/adopcion.css?v=<?php echo @filemtime(__DIR__ . '/../../css/admin/adopcion.css'); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
<div class="app-container">

    <!-- ══ SIDEBAR ══════════════════════════════════════════════ -->
    <nav class="sidebar">
        <div class="menu-hamburguesa-container">
            <div class="profile-circle" id="btn-menu"><i class="fas fa-bars"></i></div>
            <nav class="menu-desplegable" id="menu-latente">
                <ul>
                    <a href="./sub_menu/conocenos.php"><li><i class="fas fa-camera"></i><span>Conócenos</span></li></a>
                    <a href="./sub_menu/estado_bd.php"><li><i class="fas fa-database"></i><span>Estado del sistema</span></li></a>
                    <a href="./sub_menu/manual.php"><li><i class="fas fa-book-open"></i><span>Manual de uso</span></li></a>
                    
                    <li><a href="../../../controller/logout.php" style="color:#fff"><i class="fas fa-sign-out-alt"></i><span>Cerrar Sesión</span></a></li>
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
            <li class="active"><a href="adopcion_admin.php"><i class="fas fa-bone"></i><span>Adopción</span></a></li>
            <li><div class="nav-sep"></div></li>
            <li><a href="usuario_admin.php"><i class="fas fa-user"></i><span>Usuario</span></a></li>
        </ul>
    </nav>

    <!-- ══ CONTENIDO ══════════════════════════════════════════════ -->
    <main class="form-main">
        <div class="form-header">
            <a href="adopcion_admin.php"><i class="fas fa-arrow-left"></i></a>
            <h1>Agregar Nueva Adopción</h1>
        </div>

        <?php if ($error): ?>
            <div class="error-msg"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="form-card">
            <form method="POST" enctype="multipart/form-data">
                <div class="form-grid">

                    <!-- Imagen -->
                    <div class="form-group full">
                        <label><i class="fas fa-image"></i> Imagen del animal</label>
                        <div class="img-preview-wrap" onclick="document.getElementById('img_adop').click()">
                            <img id="imgPreview" src="" alt="Vista previa">
                            <div class="placeholder" id="imgPlaceholder">
                                <i class="fas fa-cloud-upload-alt"></i>
                                Haz clic para subir una imagen (JPG, PNG, WEBP)
                            </div>
                        </div>
                        <input type="file" id="img_adop" name="img_adop" accept="image/*" style="display:none" required>
                    </div>

                    <!-- Nombre -->
                    <div class="form-group">
                        <label>Nombre</label>
                        <input type="text" name="nombre" placeholder="Ej: Luna" required>
                    </div>

                    <!-- Edad -->
                    <div class="form-group">
                        <label>Edad</label>
                        <input type="text" name="edad" placeholder="Ej: 2 años">
                    </div>

                    <!-- Tamaño -->
                    <div class="form-group">
                        <label>Tamaño</label>
                        <select name="tamano">
                            <option value="Pequeño">Pequeño</option>
                            <option value="Mediano" selected>Mediano</option>
                            <option value="Grande">Grande</option>
                        </select>
                    </div>

                    <!-- Raza -->
                    <div class="form-group">
                        <label>Raza</label>
                        <input type="text" name="raza" placeholder="Ej: Beagle">
                    </div>

                    <!-- Color -->
                    <div class="form-group full">
                        <label>Color</label>
                        <input type="text" name="color" placeholder="Ej: Tricolor">
                    </div>

                    <!-- Descripción -->
                    <div class="form-group full">
                        <label>Descripción</label>
                        <textarea name="descripcion" placeholder="Cuéntanos sobre su personalidad..."></textarea>
                    </div>

                    <!-- Requisitos -->
                    <div class="form-group full">
                        <label>Requisitos para adoptar</label>
                        <textarea name="requisitos" style="min-height:120px" placeholder="Escribe cada requisito en una línea separada..."></textarea>
                    </div>

                </div>

                <button type="submit" class="btn-guardar">
                    <i class="fas fa-circle-check"></i> Guardar adopción
                </button>
            </form>
        </div>
    </main>
</div>

<script>
    // Menú hamburguesa
    const btnMenu = document.getElementById('btn-menu');
    const menuLatente = document.getElementById('menu-latente');
    btnMenu.addEventListener('click', (e) => { e.stopPropagation(); menuLatente.classList.toggle('show'); });
    window.addEventListener('click', (e) => {
        if (!btnMenu.contains(e.target) && !menuLatente.contains(e.target)) menuLatente.classList.remove('show');
    });

    // Preview imagen
    document.getElementById('img_adop').addEventListener('change', function() {
        const file = this.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = (e) => {
                document.getElementById('imgPreview').src = e.target.result;
                document.getElementById('imgPreview').style.display = 'block';
                document.getElementById('imgPlaceholder').style.display = 'none';
            };
            reader.readAsDataURL(file);
        }
    });
</script>
</body>
</html>
