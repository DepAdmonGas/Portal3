<?php
// Sólo para el fixture SQLite: nunca enviar notificaciones reales desde pruebas.
namespace App\Services;

class TelegramService
{
    public function sendToken($id, $message) { return true; }
    public function getUserIdsByStation($station, $exclude) { return []; }
    public function sendMessageToMultiple($ids, $message) { return true; }
    public function getUserIdsComercializadora($exclude) { return []; }
}
