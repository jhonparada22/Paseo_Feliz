<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

include_once 'conexion.php';

// Cargamos PHPMailer (ajusta la ruta según tu estructura)
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/PHPMailer/Exception.php';
require_once __DIR__ . '/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/SMTP.php';

$data = json_decode(file_get_contents("php://input"));

if (empty($data->email)) {
    echo json_encode(["success" => false, "message" => "Correo no proporcionado."]);
    exit;
}

$email = strtolower($conn->real_escape_string($data->email));

// Verificar si el correo ya está registrado
$checkEmail = $conn->query("SELECT id FROM usuarios WHERE email = '$email'");
if ($checkEmail->num_rows > 0) {
    echo json_encode(["success" => false, "message" => "Este correo ya está registrado."]);
    exit;
}

// Generar código de 5 dígitos
$codigo = str_pad(rand(0, 99999), 5, '0', STR_PAD_LEFT);
$expiracion = date('Y-m-d H:i:s', strtotime('+10 minutes'));

// Guardar/actualizar código en tabla temporal
$conn->query("CREATE TABLE IF NOT EXISTS codigos_verificacion (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    codigo VARCHAR(10) NOT NULL,
    tipo ENUM('registro','recuperacion') NOT NULL DEFAULT 'registro',
    expiracion DATETIME NOT NULL,
    INDEX(email)
)");

// Borrar códigos anteriores del mismo correo y tipo
$conn->query("DELETE FROM codigos_verificacion WHERE email = '$email' AND tipo = 'registro'");

$stmt = $conn->prepare("INSERT INTO codigos_verificacion (email, codigo, tipo, expiracion) VALUES (?, ?, 'registro', ?)");
$stmt->bind_param("sss", $email, $codigo, $expiracion);
if (!$stmt->execute()) {
    echo json_encode(["success" => false, "message" => "Error al guardar código."]);
    exit;
}
$stmt->close();

// Enviar correo con PHPMailer usando Gmail SMTP
$mail = new PHPMailer(true);
try {
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'soporte.paseofeliz@gmail.com'; // <-- Tu correo Gmail
    $mail->Password   = 'yifcncxxumdujwkv';          // <-- Contraseña de aplicación Gmail (App Password)
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;
    $mail->CharSet    = 'UTF-8';

    $mail->setFrom('paseofelizoficial@gmail.com', 'Paseo Feliz');
    $mail->addAddress($email);

    $mail->isHTML(true);
    $mail->Subject = 'Código de verificación - Paseo Feliz';
    $mail->Body    = "
        <div style='font-family: Montserrat, sans-serif; max-width: 500px; margin: auto; padding: 30px; border-radius: 10px; background: #f5f5f5;'>
            <h2 style='color: #0a4e63; text-align:center;'>Paseo Feliz</h2>
            <p>Hola, gracias por registrarte.</p>
            <p>Tu código de verificación es:</p>
            <div style='font-size: 36px; font-weight: bold; text-align: center; letter-spacing: 10px; color: #0a4e63; padding: 20px; background: #fff; border-radius: 8px; border: 2px solid #0a4e63; margin: 20px 0;'>
                $codigo
            </div>
            <p style='font-size: 12px; color: #888;'>Este código expira en 10 minutos. Si no solicitaste este registro, ignora este mensaje.</p>
        </div>
    ";
    $mail->AltBody = "Tu código de verificación de Paseo Feliz es: $codigo (válido por 10 minutos)";

    $mail->send();
    echo json_encode(["success" => true, "message" => "Código enviado al correo."]);
} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => "Error al enviar correo: " . $mail->ErrorInfo]);
}
?>
