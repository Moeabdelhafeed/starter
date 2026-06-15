<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;

class FCMHelper
{
    /**
     * Send FCM push notification via Firebase Cloud Messaging.
     *
     * @param  string|array  $tokens  Single device token or array of tokens.
     * @param  string  $title  The notification title.
     * @param  string  $body  The notification body text.
     * @param  array  $data  Optional: Key-value pairs for additional payload data.
     * @return array Result containing success status.
     */
    public static function send(string|array $tokens, string $title, string $body, array $data = []): array
    {
        $tokens = is_array($tokens) ? $tokens : [$tokens];
        $tokens = array_filter($tokens);

        if (empty($tokens)) {
            return ['success' => false, 'message' => 'No tokens provided'];
        }

        try {
            $messaging = app(Messaging::class);

            $message = CloudMessage::new()
                ->withNotification(Notification::create($title, $body))
                ->withData(empty($data) ? [] : array_map('strval', $data));

            $report = $messaging->sendMulticast($message, $tokens);

            $successCount = $report->successes()->count();
            $failureCount = $report->failures()->count();

            if ($failureCount > 0) {
                foreach ($report->failures()->getItems() as $failure) {
                    Log::warning('FCM: Failed to send', [
                        'token' => substr($failure->target()->value(), 0, 10).'...',
                        'error' => $failure->error()?->getMessage(),
                    ]);
                }
            }

            return [
                'success' => $successCount > 0,
                'sent' => $successCount,
                'failed' => $failureCount,
            ];
        } catch (\Throwable $e) {
            Log::error('FCM: Send failed', ['error' => $e->getMessage()]);

            return [
                'success' => false,
                'message' => 'FCM send failed: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Broadcast an FCM notification to every device subscribed to a topic.
     * Caller should use {@see FcmTopics} constants for built-in topics
     * (`FcmTopics::GUESTS`, `FcmTopics::USERS`) or pass a custom topic name
     * registered via DevSettings.
     *
     * @param  array<string, scalar>  $data
     * @return array<string, mixed>
     */
    public static function sendToTopic(string $topic, string $title, string $body, array $data = []): array
    {
        if (! FcmTopics::isValidName($topic)) {
            return ['success' => false, 'message' => 'Invalid topic name'];
        }

        try {
            $messaging = app(Messaging::class);

            $message = CloudMessage::fromArray([
                'topic' => $topic,
                'notification' => ['title' => $title, 'body' => $body],
                'data' => empty($data) ? [] : array_map('strval', $data),
            ]);

            $messaging->send($message);

            return ['success' => true, 'topic' => $topic];
        } catch (\Throwable $e) {
            Log::error('FCM: Topic send failed', ['topic' => $topic, 'error' => $e->getMessage()]);

            return ['success' => false, 'message' => 'FCM topic send failed: '.$e->getMessage()];
        }
    }
}
