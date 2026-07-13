<?php
/**
 * Apaga servicios de membresía vencidos.
 * Se llama al inicio de cada endpoint — no necesita cron.
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
 * Calcula el estado de membresía.
 * Devuelve servicios como objeto asociativo { "Paseos" => 28, "Adiestramiento" => 15 }
 * para que el JS pueda mostrar los días restantes por cada servicio.
 *
 * @param array $row Fila con columnas paseos/adiestramiento/hospedaje y sus fecha_fin_*
 * @return array ['activa' => bool, 'dias_restantes' => int|null, 'servicios' => array]
 */
function calcularEstadoMembresia($row)
{
    $tiposActivos = [
        'paseos'         => ['flag' => 'paseos',         'fin' => 'fecha_fin_paseos',         'label' => 'Paseos'],
        'adiestramiento' => ['flag' => 'adiestramiento', 'fin' => 'fecha_fin_adiestramiento', 'label' => 'Adiestramiento'],
        'hospedaje'      => ['flag' => 'hospedaje',      'fin' => 'fecha_fin_hospedaje',      'label' => 'Hospedaje'],
    ];

    // Objeto asociativo: { "Paseos" => 28, "Adiestramiento" => 15 }
    $servicios     = [];
    $diasRestantes = null;

    foreach ($tiposActivos as $tipo) {
        if (!empty($row[$tipo['flag']]) && !empty($row[$tipo['fin']])) {
            $fin       = strtotime($row[$tipo['fin']]);
            $restantes = max(0, (int) ceil(($fin - time()) / 86400));

            $servicios[$tipo['label']] = $restantes;

            // dias_restantes = el mayor (para compatibilidad con status activa/inactiva)
            if ($diasRestantes === null || $restantes > $diasRestantes) {
                $diasRestantes = $restantes;
            }
        }
    }

    return [
        'activa'         => count($servicios) > 0,
        'dias_restantes' => $diasRestantes,   // máximo, por compatibilidad
        'servicios'      => $servicios,        // { "Paseos": 28, "Adiestramiento": 15 }
    ];
}