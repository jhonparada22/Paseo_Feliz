<?php
/**
 * _perfil_contenido.php — Parcial COMPARTIDO del perfil público.
 * Lo incluyen perfil.php (cliente), perfil_paseador.php y perfil_admin.php
 * DESPUÉS de resolver permisos con ppCargarPerfil(). Espera en scope:
 *   $PP         → array devuelto por ppCargarPerfil() (ya permitido)
 *   $PP_PREFIX  → prefijo de assets según la página ('../' o '../../')
 *   $PP_VOLVER  → href del botón Volver
 * No imprime <html>/<body>: solo el contenido interior.
 */
$objetivo   = $PP['objetivo'];
$rolPerfil  = $PP['rol_perfil'];
$perfil     = $PP['perfil'];
$mascotas   = $PP['mascotas'];
$stats      = $PP['stats'];
$resenas    = $PP['resenas'];
$verOcultos = $PP['ver_ocultos'];
$avatar = ppAsset($perfil['avatar_url'], $PP_PREFIX);
$banner = ppAsset($perfil['banner_url'], $PP_PREFIX);
?>
<div class="pp-shell">
    <a href="<?php echo $PP_VOLVER; ?>" class="pp-volver"><i class="fas fa-arrow-left"></i> Volver</a>

    <!-- Banner + Avatar (mismo diseño que el perfil propio) -->
    <div class="boceto-banner-container">
        <div class="boceto-banner-bg">
            <?php if ($banner): ?><img src="<?php echo htmlspecialchars($banner); ?>" class="boceto-banner-img" alt="Banner"><?php endif; ?>
        </div>
        <?php if ($avatar): ?>
            <img src="<?php echo htmlspecialchars($avatar); ?>" class="boceto-avatar-user" alt="Avatar">
        <?php else: ?>
            <div class="boceto-avatar-user" style="display:flex;align-items:center;justify-content:center;">
                <i class="fas fa-user fa-2x" style="color:#fff;"></i>
            </div>
        <?php endif; ?>
        <div class="boceto-nombre-linea">
            <?php echo htmlspecialchars($objetivo['nombre']); ?>
            <span class="pp-rol-tag pp-rol-<?php echo $rolPerfil; ?>"><?php echo $rolPerfil; ?></span>
        </div>
    </div>
    <div class="profile-subheader"></div>

    <?php if ($stats): ?>
    <!-- Estadísticas del paseador -->
    <div class="pp-stats">
        <div class="pp-stat">
            <div class="v"><?php echo number_format($stats['puntuacion'], 1); ?> <span class="pp-estrellas"><?php
                $llenas = (int)round($stats['puntuacion']);
                echo str_repeat('★', max(0, min(5, $llenas))) . str_repeat('☆', max(0, 5 - $llenas));
            ?></span></div>
            <div class="l">Puntuación (<?php echo $stats['total_resenas']; ?> reseña<?php echo $stats['total_resenas'] === 1 ? '' : 's'; ?>)</div>
        </div>
        <div class="pp-stat">
            <div class="v"><?php echo $stats['paseos_totales']; ?></div>
            <div class="l">Paseos realizados</div>
        </div>
        <div class="pp-stat">
            <div class="v" style="font-size:.95rem;padding-top:6px"><?php echo htmlspecialchars($stats['zona_trabajo']); ?></div>
            <div class="l">Zona de trabajo</div>
        </div>
    </div>
    <?php endif; ?>

    <div class="profile-grid">
        <!-- Columna izquierda: Sobre -->
        <div class="columna-boceto">
            <div class="columna-card">
                <h3 class="seccion-titulo"><i class="fas fa-user-circle"></i> Sobre <?php echo htmlspecialchars($objetivo['nombre']); ?></h3>
                <div class="boceto-caja-datos">
                    <label>Biografía</label>
                    <p><?php echo htmlspecialchars($perfil['biografia']); ?></p>
                </div>
                <div class="boceto-caja-datos">
                    <label>Cumpleaños</label>
                    <p><?php echo htmlspecialchars($perfil['cumpleanos']); ?></p>
                </div>
                <div class="boceto-caja-datos">
                    <label>Profesión</label>
                    <p><?php echo htmlspecialchars($perfil['profesion']); ?></p>
                </div>
                <?php if ($verOcultos): ?>
                <div class="boceto-caja-datos" style="border-left-color:#7f1d34;">
                    <label style="color:#7f1d34;">Teléfono <i class="fas fa-lock" style="font-size:.7rem"></i></label>
                    <p><?php echo htmlspecialchars($perfil['telefono']); ?></p>
                </div>
                <div class="boceto-caja-datos" style="border-left-color:#7f1d34;">
                    <label style="color:#7f1d34;">Dirección <i class="fas fa-lock" style="font-size:.7rem"></i></label>
                    <p><?php echo htmlspecialchars($perfil['direccion']); ?></p>
                </div>
                <?php endif; ?>
            </div>

            <?php if ($stats && $resenas): ?>
            <div class="columna-card" style="margin-top:14px">
                <h3 class="seccion-titulo"><i class="fas fa-star"></i> Reseñas recientes</h3>
                <?php foreach ($resenas as $r): ?>
                <div class="pp-resena">
                    <div class="cab">
                        <span class="nom"><?php echo htmlspecialchars($r['cliente']); ?></span>
                        <span class="pp-estrellas"><?php echo str_repeat('★', (int)$r['estrellas']) . str_repeat('☆', 5 - (int)$r['estrellas']); ?></span>
                    </div>
                    <?php if (!empty($r['comentario'])): ?><div class="txt"><?php echo htmlspecialchars($r['comentario']); ?></div><?php endif; ?>
                    <div class="fec"><?php echo date('d/m/Y', strtotime($r['fecha_creacion'])); ?></div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Columna derecha: Mascotas -->
        <div class="columna-boceto columna-derecha">
            <div class="columna-card">
                <h3 class="seccion-titulo"><i class="fas fa-paw"></i> Sus mascotas</h3>
                <?php if (!$mascotas): ?>
                    <div class="boceto-caja-datos" style="text-align:center;padding:28px 14px;">
                        <i class="fas fa-dog fa-2x" style="color:#cbd5e0;display:block;margin-bottom:10px;"></i>
                        <p style="color:#94a3b8;margin:0;font-size:14px;">No hay mascotas registradas.</p>
                    </div>
                <?php else: ?>
                    <div class="pet-selector-tabs">
                        <?php foreach ($mascotas as $idx => $m): ?>
                        <button class="pet-tab-btn <?php echo $idx === 0 ? 'active' : ''; ?>"
                                onclick="mostrarMascota(<?php echo $idx; ?>)" type="button">
                            <?php echo htmlspecialchars($m['nombre_mascota']); ?>
                        </button>
                        <?php endforeach; ?>
                    </div>

                    <?php foreach ($mascotas as $idx => $m):
                        $pet_avatar = ppAsset($m['avatar_mascota'] ?? '', $PP_PREFIX);
                        $pet_bio    = !empty($m['biografia_canina'])            ? $m['biografia_canina']            : 'Sin descripción.';
                        $pet_health = !empty($m['enfermedades_discapacidades']) ? $m['enfermedades_discapacidades'] : 'Ninguna registrada.';
                        $meta = [];
                        if (!empty($m['raza'])) $meta[] = htmlspecialchars($m['raza']);
                        if (isset($m['edad']) && $m['edad'] !== null && $m['edad'] !== '') $meta[] = $m['edad'] . ' ' . ($m['edad'] == 1 ? 'año' : 'años');
                    ?>
                    <div class="boceto-mascota-card pet-card-panel <?php echo $idx !== 0 ? 'hidden-pet' : ''; ?>" id="pet-panel-<?php echo $idx; ?>">
                        <div class="boceto-mascota-header">
                            <?php if ($pet_avatar): ?>
                                <img src="<?php echo htmlspecialchars($pet_avatar); ?>" class="boceto-avatar-pet" alt="Mascota">
                            <?php else: ?>
                                <div class="boceto-avatar-pet" style="display:flex;align-items:center;justify-content:center;background:#e8f5e9;">
                                    <i class="fas fa-dog" style="color:#27ae60;font-size:22px;"></i>
                                </div>
                            <?php endif; ?>
                            <div style="flex:1;min-width:0;">
                                <div class="boceto-nombre-pet"><?php echo htmlspecialchars($m['nombre_mascota']); ?></div>
                                <?php if ($meta): ?><div class="boceto-mascota-meta"><?php echo implode(' • ', $meta); ?></div><?php endif; ?>
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
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
function mostrarMascota(idx) {
    document.querySelectorAll('.pet-card-panel').forEach((el, i) => el.classList.toggle('hidden-pet', i !== idx));
    document.querySelectorAll('.pet-tab-btn').forEach((btn, i) => btn.classList.toggle('active', i === idx));
}
</script>
