<?php

namespace App\Jobs;

use App\Helpers\FCMHelper;
use App\Models\Language;
use App\Models\NotificationTemplate;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendNotificationTemplate implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $templateId) {}

    /**
     * Send the template's translated title/body to its configured topic.
     * QUEUE_CONNECTION=sync runs this inline. Updates `last_sent_at`.
     */
    public function handle(): void
    {
        $template = NotificationTemplate::find($this->templateId);
        if (! $template || ! $template->is_active) {
            return;
        }

        $defaultLang = Language::getDefault()?->code ?? 'en';
        $title = $template->getTranslation('title', $defaultLang) ?: 'Notification';
        $body = $template->getTranslation('body', $defaultLang) ?: '';

        FCMHelper::sendToTopic($template->topic, $title, $body);

        $template->forceFill(['last_sent_at' => now()])->saveQuietly();
    }
}
