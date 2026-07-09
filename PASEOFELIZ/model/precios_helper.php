<?php
/**
 * precios_helper.php
 * Calcula el precio de un servicio (paseos/adiestramiento/hospedaje)
 * según la cantidad pedida, aplicando el mejor descuento por volumen
 * configurado en `descuentos_servicios`. El precio SIEMPRE se calcula
 * aquí (el servidor nunca confía en un total que venga del formulario).
 *
 * Devuelve: ['precio_unidad'=>.., 'cantidad'=>.., 'subtotal'=>..,
 *            'descuento_pct'=>.., 'descuento'=>.., 'total'=>..]
 * o null si el tipo de servicio no tiene precio configurado.
 */
function calcularPrecioServicio($conn, $tipoMembresia, $cantidad) {
    $stmt = $conn->prepare("SELECT precio_unidad FROM precios_servicios WHERE tipo_membresia = ? LIMIT 1");
    $stmt->bind_param('s', $tipoMembresia);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) return null;

    $precioUnidad = (float)$row['precio_unidad'];
    $subtotal     = $precioUnidad * $cantidad;

    // Mejor descuento aplicable: el de mayor cantidad_minima que la
    // cantidad pedida sí alcance a cumplir.
    $descuentoPct = 0;
    $stmtD = $conn->prepare(
        "SELECT descuento_pct FROM descuentos_servicios
         WHERE tipo_membresia = ? AND cantidad_minima <= ?
         ORDER BY cantidad_minima DESC LIMIT 1"
    );
    $stmtD->bind_param('si', $tipoMembresia, $cantidad);
    $stmtD->execute();
    $rowD = $stmtD->get_result()->fetch_assoc();
    $stmtD->close();
    if ($rowD) $descuentoPct = (int)$rowD['descuento_pct'];

    $descuento = round($subtotal * $descuentoPct / 100, 2);
    $total     = $subtotal - $descuento;

    return [
        'precio_unidad' => $precioUnidad,
        'cantidad'      => $cantidad,
        'subtotal'      => $subtotal,
        'descuento_pct' => $descuentoPct,
        'descuento'     => $descuento,
        'total'         => $total,
    ];
}
?>
