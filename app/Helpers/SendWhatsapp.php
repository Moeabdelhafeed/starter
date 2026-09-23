<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Log;

class SendWhatsapp
{
    /**
     * Fake WhatsApp sender helper to simulate message dispatching.
     *
     * @param  string  $to  The recipient's phone number with country code (e.g., +9627XXXXXXXX).
     * @param  string  $message  The content of the message.
     * @return array Simulation result containing success status.
     */
    public static function send(string $to, string $message): array
    {
        Log::info('FAKE WHATSAPP SEND:', [
            'to' => $to,
            'message' => $message,
            'timestamp' => now()->toDateTimeString(),
        ]);

        return [
            'success' => true,
            'message_id' => 'fake_wa_'.uniqid(),
            'message' => 'Message sent via WhatsApp successfully (Fake Implementation)',
        ];
    }
}
