<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class EmailHelper
{
    /**
     * Send email using Laravel Mail.
     *
     * @param  string  $to  The recipient's email address.
     * @param  string  $subject  The email subject line.
     * @param  string  $view  The blade template view path.
     * @param  array  $data  The data to pass to the view.
     * @return array Result containing success status.
     */
    public static function send(string $to, string $subject, string $view, array $data = []): array
    {
        try {
            Mail::send($view, $data, function ($message) use ($to, $subject) {
                $message->to($to)->subject($subject);
            });

            Log::info('EMAIL SENT:', [
                'to' => $to,
                'subject' => $subject,
                'view' => $view,
                'timestamp' => now()->toDateTimeString(),
            ]);

            return [
                'success' => true,
                'message' => 'Email sent successfully',
            ];
        } catch (\Throwable $e) {
            Log::error('EMAIL SEND FAILED:', [
                'to' => $to,
                'subject' => $subject,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Email failed to send: '.$e->getMessage(),
            ];
        }
    }
}
