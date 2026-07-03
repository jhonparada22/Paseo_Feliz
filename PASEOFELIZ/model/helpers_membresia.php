<?php
/**
 * Apaga (paseos/adiestramiento/hospedaje = 0) cualquier servicio de
 * membresía cuya fecha_fin ya pasó. Se llama al inicio de cada
 * endpoint que lee pagos/usuarios, así no depende de un cron
 * (ByetHost gratuito no garantiza tareas programadas confiables).
 *
 * @param mysqli $conn
 */
function desactivarMembresiasVencidas($conn)
{
    $conn->query("
        UPDATE membresias
        SET paseos = 0
        WHERE paseos = 1 AND fecha_fin_paseos IS NOT NULL AND fecha_fin_paseos < NOW()
    ");

    $conn->query("
        UPDATE membresias
        SET adiestramiento = 0
        WHERE adiestramiento = 1 AND fecha_fin_adiestramiento IS NOT NULL AND fecha_fin_adiestramiento < NOW()
    ");

    $conn->query("
        UPDATE membresias
        SET hospedaje = 0
        WHERE hospedaje = 1 AND fecha_fin_hospedaje IS NOT NULL AND fecha_fin_hospedaje < NOW()
    ");
}

/**
 * Calcula el estado de membresía de una fila devuelta por el JOIN
 * usuarios + membresias (ver model/obtener_usuarios_membresia.php).
 *
 * @param array $row Fila asociativa con las columnas paseos/adiestramiento/
 *                    hospedaje y sus fecha_fin_* correspondientes.
 * @return array ['activa' => bool, 'dias_restantes' => int|null, 'servicios' => string[]]
 */
function calcularEstadoMembresia($row)
{
    $servicios = [];
    $diasRestantes = null;
    $tiposActivos = [
        'paseos'         => ['flag' => 'paseos',         'fin' => 'fecha_fin_paseos',         'label' => 'Paseos'],
        'adiestramiento' => ['flag' => 'adiestramiento', 'fin' => 'fecha_fin_adiestramiento', 'label' => 'Adiestramiento'],
        'hospedaje'      => ['flag' => 'hospedaje',      'fin' => 'fecha_fin_hospedaje',      'label' => 'Hospedaje'],
    ];

    foreach ($tiposActivos as $tipo) {
        if (!empty($row[$tipo['flag']]) && !empty($row[$tipo['fin']])) {
            $servicios[] = $tipo['label'];
            $fin = strtotime($row[$tipo['fin']]);
            $restantes = (int) ceil(($fin - time()) / 86400);
            if ($restantes < 0) $restantes = 0;
            if ($diasRestantes === null || $restantes > $diasRestantes) {
                $diasRestantes = $restantes;
            }
        }
    }

    return [
        'activa'         => count($servicios) > 0,
        'dias_restantes' => $diasRestantes,
        'servicios'      => $servicios,
    ];
}
