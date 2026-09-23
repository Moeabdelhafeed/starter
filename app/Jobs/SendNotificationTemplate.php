<?php

namespace App\Jobs;

use App\Helpers\FCMHelper;
use App\Helpers\FcmTopics;
use App\Models\Language;
use App\Models\NotificationTemplate;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendNotificationTemplate implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $templateId) {}

    /**
     * Send the template's title/body to its topic — once per language when the topic is a
     * base, each in that language.
     * QUEUE_CONNECTION=sync runs this inline. Updates `last_sent_at`.
     */
    public function handle(): void
    {
        $template = NotificationTemplate::find($this->templateId);
        if (! $template || ! $template->is_active) {
            return;
        }

        $defaultLang = Language::defaultCode();

        // A base topic ("all") means everybody in their own language, so it fans out to
        // every variant and each push carries that language's copy — `all_ar` gets the
        // Arabic title, `all_en` the English. Clients only ever subscribe to variants, so
        // sending to the bare base would reach nobody.
        foreach (FcmTopics::sendTargets($template->topic) as $topic => $lang) {
            $locale = $lang ?? $defaultLang;
            $title = $template->getTranslation('title', $locale)
                ?: ($template->getTranslation('title', $defaultLang) ?: 'Notification');
            $body = $template->getTranslation('body', $locale)
                ?: ($template->getTranslation('body', $defaultLang) ?: '');

            FCMHelper::sendToTopic($topic, $title, $body);
        }

        $template->forceFill(['last_sent_at' => now()])->saveQuietly();
    }
}
