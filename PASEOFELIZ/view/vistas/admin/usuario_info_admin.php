<?php 
// Iniciamos sesión de forma segura
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// CORRECCIÓN DE RUTAS: Subimos 3 niveles para que conecte en Byethost sin error 500
include_once '../../../controller/control_acceso.php'; 
include_once '../../../model/conexion.php';

$id_usuario = $_SESSION['usuario_id'] ?? null;
$nombre_usuario = $_SESSION['usuario_nombre'] ?? 'Usuario';

$biografia = '';
$cumpleanos = '';
$telefono = '';
$direccion = '';
$profesion = '';

if ($id_usuario) {
    $stmt = $conn->prepare("SELECT * FROM info_usuario WHERE id_usuario = ?");
    $stmt->bind_param("i", $id_usuario);
    $stmt->execute();
    $resultado = $stmt->get_result();
    
    if ($perfil = $resultado->fetch_assoc()) {
        $biografia = $perfil['biografia'];
        $cumpleanos = $perfil['cumpleanos'];
        $telefono = $perfil['telefono'];
        $direccion = $perfil['direccion'];
        $profesion = $perfil['profesion'];
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paseo Feliz - Modificar Perfil</title>
    <link class="favicon" rel="icon" href="../../assets/images/logo.png" type="image/png">
    <link rel="stylesheet" href="../../css/admin/admin.css">
    <link rel="stylesheet" href="../../css/admin/sidebar_admin.css">
    <link rel="stylesheet" href="../../css/principal_css/usuario_info.css">
    <link rel="stylesheet" href="../../css/responsive/responsive_principal.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div id="contenedor_general" class="app-container">
        
        <nav class="sidebar">
            <div class="menu-hamburguesa-container">
                <div class="profile-circle" id="btn-menu"><i class="fas fa-bars"></i></div>
                <nav class="menu-desplegable" id="menu-latente">
                    <ul>
                        <a href="../../pagina_principal/sub_menu/conocenos.php"><li><i class="fas fa-camera"></i><span>Conócenos</span></li></a>
                        <a href="../../pagina_principal/sub_menu/direccion_oficial.php"><li><i class="fas fa-book-open"></i><span>Dirección oficial</span></li></a>
                        <a href="../../pagina_principal/sub_menu/centro_de_ayuda.php"><li><i class="fas fa-sliders-h"></i><span>Centro de ayuda</span></li></a>
                        <a href="../../pagina_principal/sub_menu/configuracion.php"><li><i class="fas fa-gear"></i><span>Configuración</span></li></a>
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
                <li class="active"><a href="usuario_admin.php"><i class="fas fa-user"></i><span>Usuario</span></a></li>
            </ul>
        </nav>

        <main class="edit-main-content">
            <div class="edit-header-title">
                <a href="usuario_admin.php"><i class="fas fa-arrow-left"></i></a>
                <h2>Modificar Datos de Perfil</h2>
            </div>

            <form action="../../../controller/guardar_perfil_admin.php" method="POST" enctype="multipart/form-data">
                <div class="edit-grid-container">

                    <!-- ══ Columna izquierda: Datos del dueño ══ -->
                    <section class="edit-column">
                        <h3><i class="fas fa-user-gear"></i> Datos del Dueño</h3>

                        <!-- Avatar usuario -->
                        <div class="form-group-box">
                            <label>Foto de perfil (Icono)</label>
                            <div class="preview-row">
                                <div class="preview-upload-box" id="preview-avatar-user-wrap">
                                    <img id="preview-avatar-user" src="#" alt="Preview" class="img-preview hidden-preview">
                                    <span class="preview-placeholder" id="placeholder-avatar-user">
                                        <i class="fas fa-user"></i>
                                    </span>
                                </div>
                                <div style="flex:1;">
                                    <input type="file" name="avatar_usuario" accept="image/*"
                                           onchange="previewImg(this,'preview-avatar-user','placeholder-avatar-user')">
                                    <span class="upload-hint"><i class="fas fa-circle-info"></i> Recomendado: <b>200×200 px</b> — Soporta GIF animado</span>
                                </div>
                            </div>
                        </div>

                        <!-- Banner usuario -->
                        <div class="form-group-box">
                            <label>Banner de perfil</label>
                            <div class="banner-preview-box" id="preview-banner-user-wrap">
                                <img id="preview-banner-user" src="#" alt="Preview" class="img-preview hidden-preview">
                                <span class="preview-placeholder" id="placeholder-banner-user">
                                    <i class="fas fa-image"></i>&nbsp; Sin banner seleccionado
                                </span>
                            </div>
                            <input type="file" name="banner_usuario" accept="image/*"
                                   onchange="previewImg(this,'preview-banner-user','placeholder-banner-user')"
                                   style="margin-top:6px;">
                            <span class="upload-hint"><i class="fas fa-circle-info"></i> Recomendado: <b>1280×720 px (16:9)</b> — Soporta GIF animado</span>
                        </div>

                        <div class="form-group-box">
                            <label>Biografía</label>
                            <textarea name="biografia" rows="3" placeholder="Cuéntanos algo sobre ti..."><?php echo htmlspecialchars($biografia); ?></textarea>
                        </div>
                        <div class="form-group-box">
                            <label>Fecha de Nacimiento</label>
                            <input type="date" name="cumpleanos" value="<?php echo htmlspecialchars($cumpleanos); ?>">
                        </div>
                        <div class="form-group-box">
                            <label>Dirección / Ubicación</label>
                            <input type="text" name="direccion" placeholder="Ej: Prados del Este, Cúcuta" value="<?php echo htmlspecialchars($direccion); ?>">
                        </div>
                        <div class="form-group-box">
                            <label for="telefono">Número Celular</label>
                            <input type="tel" id="telefono" name="telefono" value="<?php echo htmlspecialchars($telefono); ?>"
                                   placeholder="Ej: 3123456789" maxlength="10" pattern="[0-9]{10}">
                        </div>
                        <div class="form-group-box">
                            <label>Profesión / Ocupación</label>
                            <input type="text" name="profesion" placeholder="Ej: Estudiante" value="<?php echo htmlspecialchars($profesion); ?>">
                        </div>
                    </section>

                    <!-- ══ Columna derecha: Mascotas ══ -->
                    <section class="edit-column edit-column-right">

                        <!-- Pestañas Editar / Agregar -->
                        <div class="mascota-action-selector">
                            <div class="btn-tab active" id="tab-editar" onclick="switchMascotaTab('editar')">
                                <i class="fas fa-pen"></i> Editar Mascota
                            </div>
                            <div class="btn-tab" id="tab-agregar" onclick="switchMascotaTab('agregar')">
                                <i class="fas fa-plus"></i> Agregar Mascota
                            </div>
                        </div>
                        <input type="hidden" name="mascota_accion" id="mascota_accion" value="editar">

                        <!-- ── Sección Editar ── -->
                        <div id="section-editar-mascota">
                            <h3><i class="fas fa-dog"></i> Modificar Canino</h3>

                            <div class="form-group-box">
                                <label>Selecciona la mascota a modificar</label>
                                <select name="select_id_mascota" id="select_id_mascota">
                                    <option value="">-- Selecciona una mascota --</option>
                                    <?php 
                                    if ($id_usuario) {
                                        $stmt_m = $conn->prepare("SELECT id_mascota, nombre_mascota FROM mascota_usuario WHERE id_usuario = ?");
                                        $stmt_m->bind_param("i", $id_usuario);
                                        $stmt_m->execute();
                                        $res_m = $stmt_m->get_result();
                                        while ($mascota = $res_m->fetch_assoc()) {
                                            echo '<option value="' . $mascota['id_mascota'] . '">'
                                               . htmlspecialchars($mascota['nombre_mascota']) . '</option>';
                                        }
                                        $stmt_m->close();
                                    }
                                    ?>
                                </select>
                            </div>

                            <!-- Botón eliminar — deshabilitado hasta seleccionar mascota -->
                            <div class="form-group-box">
                                <button type="button" id="btn-abrir-modal-eliminar"
                                        class="btn-eliminar-mascota btn-eliminar-disabled"
                                        disabled>
                                    <i class="fas fa-trash-alt"></i> Eliminar esta mascota
                                </button>
                            </div>

                            <div class="form-group-box">
                                <label>Nuevo nombre de la mascota</label>
                                <input type="text" name="nombre_mascota_edit" placeholder="Deja vacío para no cambiar">
                            </div>

                            <div class="form-group-box" style="display:flex; gap:12px;">
                                <div style="flex:1;">
                                    <label>Raza</label>
                                    <input type="text" name="raza_mascota_edit" placeholder="Deja vacío para no cambiar">
                                </div>
                                <div style="flex:1;">
                                    <label>Edad (años)</label>
                                    <input type="number" name="edad_mascota_edit" min="0" max="30" placeholder="Deja vacío para no cambiar">
                                </div>
                            </div>

                            <!-- Preview icono mascota editar -->
                            <div class="form-group-box">
                                <label>Cambiar icono de la mascota</label>
                                <div class="preview-row">
                                    <div class="preview-circle-box" id="preview-pet-edit-wrap">
                                        <img id="preview-pet-edit" src="#" alt="Preview" class="img-preview hidden-preview">
                                        <span class="preview-placeholder" id="placeholder-pet-edit">
                                            <i class="fas fa-dog"></i>
                                        </span>
                                    </div>
                                    <div style="flex:1;">
                                        <input type="file" name="avatar_mascota_edit" accept="image/*"
                                               onchange="previewImg(this,'preview-pet-edit','placeholder-pet-edit')">
                                        <span class="upload-hint"><i class="fas fa-circle-info"></i> Recomendado: <b>200×200 px</b> — Soporta GIF animado</span>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group-box">
                                <label>Biografía Canina</label>
                                <textarea name="biografia_canina_edit" rows="3" placeholder="¿Cómo es su personalidad?"></textarea>
                            </div>
                            <div class="form-group-box">
                                <label>Enfermedades y/o Discapacidades</label>
                                <textarea name="enfermedades_edit" rows="2" placeholder="Si padece alguna enfermedad..."></textarea>
                            </div>
                        </div>

                        <!-- ── Sección Agregar ── -->
                        <div id="section-agregar-mascota" class="hidden-section">
                            <h3><i class="fas fa-plus-circle"></i> Registrar Nueva Mascota</h3>

                            <div class="form-group-box">
                                <label>Nombre de la mascota</label>
                                <input type="text" name="nombre_mascota_nuevo" placeholder="Nombre del peludito">
                            </div>

                            <div class="form-group-box" style="display:flex; gap:12px;">
                                <div style="flex:1;">
                                    <label>Raza</label>
                                    <input type="text" name="raza_mascota_nuevo" placeholder="Ej: Golden Retriever">
                                </div>
                                <div style="flex:1;">
                                    <label>Edad (años)</label>
                                    <input type="number" name="edad_mascota_nuevo" min="0" max="30" placeholder="Ej: 3">
                                </div>
                            </div>

                            <!-- Preview icono mascota nuevo -->
                            <div class="form-group-box">
                                <label>Icono de la mascota</label>
                                <div class="preview-row">
                                    <div class="preview-circle-box" id="preview-pet-nuevo-wrap">
                                        <img id="preview-pet-nuevo" src="#" alt="Preview" class="img-preview hidden-preview">
                                        <span class="preview-placeholder" id="placeholder-pet-nuevo">
                                            <i class="fas fa-dog"></i>
                                        </span>
                                    </div>
                                    <div style="flex:1;">
                                        <input type="file" name="avatar_mascota_nuevo" accept="image/*"
                                               onchange="previewImg(this,'preview-pet-nuevo','placeholder-pet-nuevo')">
                                        <span class="upload-hint"><i class="fas fa-circle-info"></i> Recomendado: <b>200×200 px</b> — Soporta GIF animado</span>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group-box">
                                <label>Biografía Canina</label>
                                <textarea name="biografia_canina_nuevo" rows="3" placeholder="Cuéntanos su historia..."></textarea>
                            </div>
                            <div class="form-group-box">
                                <label>Discapacidades y/o Enfermedades</label>
                                <textarea name="enfermedades_nuevo" rows="2" placeholder="Ninguna / Alergias..."></textarea>
                            </div>
                        </div>
                    </section>

                    <!-- Botón guardar -->
                    <div class="btn-submit-container">
                        <button type="submit" class="btn-confirmar-guardar">
                            <i class="fas fa-circle-check"></i> Confirmar Cambios
                        </button>
                    </div>

                </div>
            </form>
        </main>
    </div>

    <!-- ══ Modal confirmar eliminar mascota ══ -->
    <div class="modal-overlay" id="modal-eliminar">
        <div class="modal-box">
            <div class="modal-icon"><i class="fas fa-paw"></i></div>
            <h4>¿Eliminar mascota?</h4>
            <p>Estás a punto de eliminar a <span class="modal-pet-name" id="modal-nombre-mascota">esta mascota</span>.<br>Esta acción no se puede deshacer.</p>
            <div class="modal-actions">
                <button class="modal-btn-cancel" id="btn-cancelar-modal" type="button">Cancelar</button>
                <button class="modal-btn-delete" id="btn-confirmar-eliminar" type="button">
                    <i class="fas fa-trash-alt"></i> Sí, eliminar
                </button>
            </div>
        </div>
    </div>

    <!-- Form oculto para eliminar mascota (POST independiente) -->
    <form id="form-eliminar-mascota" action="../../../controller/guardar_perfil_admin.php" method="POST" style="display:none;">
        <input type="hidden" name="accion_eliminar_mascota" value="1">
        <input type="hidden" name="mascota_accion" value="eliminar">
        <input type="hidden" name="select_id_mascota" id="hidden-id-mascota-eliminar">
    </form>

    <script src="../../js/js pagina principal/usuario_info.js"></script>
    <script>
        /* ── Pestañas Editar / Agregar ── */
        function switchMascotaTab(accion) {
            document.getElementById('mascota_accion').value = accion;
            const tabEditar  = document.getElementById('tab-editar');
            const tabAgregar = document.getElementById('tab-agregar');
            const secEditar  = document.getElementById('section-editar-mascota');
            const secAgregar = document.getElementById('section-agregar-mascota');

            if (accion === 'agregar') {
                tabEditar.classList.remove('active');
                tabAgregar.classList.add('active');
                secEditar.style.display  = 'none';
                secAgregar.style.display = 'block';
                secAgregar.classList.remove('hidden-section');
            } else {
                tabAgregar.classList.remove('active');
                tabEditar.classList.add('active');
                secAgregar.style.display = 'none';
                secEditar.style.display  = 'block';
            }
        }

        /* ── Preview de imágenes ── */
        function previewImg(input, imgId, placeholderId) {
            const img         = document.getElementById(imgId);
            const placeholder = document.getElementById(placeholderId);
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    img.src = e.target.result;
                    img.classList.remove('hidden-preview');
                    if (placeholder) placeholder.style.display = 'none';
                };
                reader.readAsDataURL(input.files[0]);
            }
        }

        /* ── Habilitar / deshabilitar botón eliminar según selección ── */
        const selectMascota   = document.getElementById('select_id_mascota');
        const btnAbrirModal   = document.getElementById('btn-abrir-modal-eliminar');
        const modalOverlay    = document.getElementById('modal-eliminar');
        const modalNombre     = document.getElementById('modal-nombre-mascota');
        const btnCancelar     = document.getElementById('btn-cancelar-modal');
        const btnConfirmarEl  = document.getElementById('btn-confirmar-eliminar');
        const formEliminar    = document.getElementById('form-eliminar-mascota');
        const hiddenIdEl      = document.getElementById('hidden-id-mascota-eliminar');

        function actualizarBotonEliminar() {
            const val = selectMascota ? selectMascota.value : '';
            if (val) {
                btnAbrirModal.disabled = false;
                btnAbrirModal.classList.remove('btn-eliminar-disabled');
            } else {
                btnAbrirModal.disabled = true;
                btnAbrirModal.classList.add('btn-eliminar-disabled');
            }
        }

        if (selectMascota) {
            selectMascota.addEventListener('change', actualizarBotonEliminar);
            actualizarBotonEliminar(); // estado inicial
        }

        /* ── Abrir modal ── */
        if (btnAbrirModal) {
            btnAbrirModal.addEventListener('click', function() {
                const opt = selectMascota.options[selectMascota.selectedIndex];
                modalNombre.textContent = opt ? opt.text : 'esta mascota';
                modalOverlay.classList.add('active');
            });
        }

        /* ── Cerrar modal ── */
        if (btnCancelar) {
            btnCancelar.addEventListener('click', function() {
                modalOverlay.classList.remove('active');
            });
        }
        if (modalOverlay) {
            modalOverlay.addEventListener('click', function(e) {
                if (e.target === modalOverlay) modalOverlay.classList.remove('active');
            });
        }

        /* ── Confirmar y enviar borrado ── */
        if (btnConfirmarEl) {
            btnConfirmarEl.addEventListener('click', function() {
                hiddenIdEl.value = selectMascota.value;
                formEliminar.submit();
            });
        }

        /* ── Menú hamburguesa ── */
        const btnMenu    = document.getElementById('btn-menu');
        const menuLatente = document.getElementById('menu-latente');
        if (btnMenu && menuLatente) {
            btnMenu.addEventListener('click', () => menuLatente.classList.toggle('show'));
            window.addEventListener('click', (e) => {
                if (!btnMenu.contains(e.target) && !menuLatente.contains(e.target))
                    menuLatente.classList.remove('show');
            });
        }
    </script>
</body>
</html>