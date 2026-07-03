<?php

class ModeloTelegram
{
    // ── Bot y grupo REGISTRO (usuarios nuevos) ────────────────────
    private string $token;
    private string $chatIdRegistro;

    // ── Grupo PAGOS ───────────────────────────────────────────────
    private string $chatIdPagos;

    public function __construct()
    {
        $this->token          = "8841157520:AAHxPLSW15VEmK_votsOFfNnd7H4Xk2XXQk";
        $this->chatIdRegistro = "-5277355059";   // grupo original (registro)
        $this->chatIdPagos    = "-5329955299";   // grupo de pagos
    }

    // ── Enviar a cualquier chat ───────────────────────────────────
    private function enviar(string $chatId, string $mensaje): array
    {
        $url    = "https://api.telegram.org/bot{$this->token}/sendMessage";
        $params = [
            "chat_id"    => $chatId,
            "text"       => $mensaje,
            "parse_mode" => "HTML",
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST,           true);
        curl_setopt($ch, CURLOPT_POSTFIELDS,     http_build_query($params));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT,        5);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_RESOLVE, ["api.telegram.org:443:149.154.167.220"]);

        $respuesta = curl_exec($ch);
        $error     = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return ['success' => false, 'response' => null, 'error' => $error];
        }
        return ['success' => true, 'response' => $respuesta, 'error' => null];
    }

    // ── Público: notificar nuevo usuario (grupo registro) ─────────
    public function enviarMensaje(string $mensaje): array
    {
        return $this->enviar($this->chatIdRegistro, $mensaje);
    }

    // ── Público: notificar nuevo pago (grupo pagos) ───────────────
    public function enviarMensajePagos(string $mensaje): array
    {
        return $this->enviar($this->chatIdPagos, $mensaje);
    }
}
