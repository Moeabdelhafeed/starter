<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Log;

class SendSMS
{
    /**
     * Fake SMS sender helper to simulate message dispatching.
     *
     * @param  string  $to  The recipient's phone number with country code (e.g., +9627XXXXXXXX).
     * @param  string  $message  The content of the SMS.
     * @param  int|null  $type  Optional: 1 for Plain English, 3 for English + Special, 4 for Arabic.
     * @return array Simulation result containing success status and fake message ID.
     */
    public static function send(string $to, string $message, ?int $type = null): array
    {
        Log::info('FAKE SMS SEND:', [
            'to' => $to,
            'message' => $message,
            'type' => $type ?? 'Auto-detected',
            'timestamp' => now()->toDateTimeString(),
        ]);

        return [
            'success' => true,
            'message_id' => 'fake_sms_'.uniqid(),
            'message' => 'Message sent via SMS successfully (Fake Implementation)',
        ];
    }

    /**
     * Check simulation status for a fake message.
     *
     * @param  string  $messageId  The fake message ID returned by the send method.
     * @return array Status result.
     */
    public static function status(string $messageId): array
    {
        Log::info('FAKE SMS STATUS CHECK:', ['message_id' => $messageId]);

        return [
            'success' => true,
            'status' => 'DELIVRD',
            'raw' => 'Fake delivery successful',
        ];
    }
}
