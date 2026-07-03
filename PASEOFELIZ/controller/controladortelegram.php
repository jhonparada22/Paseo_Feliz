<?php
require_once __DIR__ . '/../model/modelotelegram.php';

class ControladorTelegram
{
    private $modelo;

    public function __construct()
    {
        $this->modelo = new ModeloTelegram();
    }

    public function notificarNuevoUsuario($nombre, $email, $sexo)
    {
        date_default_timezone_set('America/Bogota'); // 
        $fecha = date("d/m/Y H:i");

        $mensaje  = "🐾 <b>Nuevo usuario registrado</b>\n\n";
        $mensaje .= "👤 <b>Nombre:</b> " . htmlspecialchars($nombre) . "\n";
        $mensaje .= "✉️ <b>Email:</b> " . htmlspecialchars($email) . "\n";
        $mensaje .= "⚧ <b>Sexo:</b> " . htmlspecialchars($sexo) . "\n";
        $mensaje .= "🕒 <b>Fecha:</b> " . $fecha;

        return $this->modelo->enviarMensaje($mensaje);
    }
}
?>