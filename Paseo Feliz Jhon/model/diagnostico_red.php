<?php
header("Content-Type: text/plain; charset=UTF-8");

echo "=== Diagnóstico de red saliente ===\n\n";

// 1. Resolución DNS
$ip = gethostbyname("api.telegram.org");
echo "1) Resolución DNS de api.telegram.org: ";
echo ($ip === "api.telegram.org") ? "FALLÓ (no resuelve)\n" : "OK -> $ip\n";

// 2. Resolución DNS a un dominio distinto (google) para descartar problema general de DNS
$ip2 = gethostbyname("www.google.com");
echo "2) Resolución DNS de www.google.com: ";
echo ($ip2 === "www.google.com") ? "FALLÓ (no resuelve)\n" : "OK -> $ip2\n";

// 3. allow_url_fopen
echo "3) allow_url_fopen: " . (ini_get('allow_url_fopen') ? "activado\n" : "DESACTIVADO\n");

// 4. Extensión curl cargada
echo "4) Extensión curl cargada: " . (extension_loaded('curl') ? "sí\n" : "no\n");

// 5. Intento de conexión socket directa (bypass DNS, usando IP pública conocida de Telegram)
echo "5) Conexión socket directa a 149.154.167.220:443: ";
$conn = @fsockopen("149.154.167.220", 443, $errno, $errstr, 5);
if ($conn) {
    echo "OK (el puerto 443 está abierto)\n";
    fclose($conn);
} else {
    echo "FALLÓ -> $errstr ($errno)\n";
}
?>
