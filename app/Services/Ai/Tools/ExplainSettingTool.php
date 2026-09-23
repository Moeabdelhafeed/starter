<?php

namespace App\Services\Ai\Tools;

use App\Models\User;
use Illuminate\Support\Facades\Validator;

/**
 * What a feature flag does, and whether it is on right now.
 *
 * The support case this exists for: a client asks their developer "what is
 * dynamic storage and do we need it", and answering means reading CLAUDE.md.
 * The descriptions are kept here rather than generated, because a model
 * paraphrasing a flag it half-remembers is exactly the failure to avoid.
 *
 * Deliberately says only what a flag *does* — never its value if that value is
 * a secret. Only booleans and the topic list are exposed; no tokens, no
 * credentials, nothing from .env directly.
 */
final class ExplainSettingTool implements AgentTool
{
    /** @var array<string, array{0: string, 1: string}> flag => [config key, description] */
    private const SETTINGS = [
        'app_users' => ['features.app_users', 'Accounts in the mobile app: whether people can sign up and sign in.'],
        'app_guests' => ['features.app_guests', 'Lets someone use the app before creating an account.'],
        'translations' => ['features.translations', 'Translating the app\'s wording into other languages.'],
        'notification_templates' => ['features.notification_templates', 'The wording of the notifications the app sends, editable in each language.'],
        'pages' => ['features.pages', 'Content pages such as Terms and Privacy that the app shows.'],
        'app_settings' => ['features.app_settings', 'Your social, contact and app-store links.'],
        'dynamic_storage' => ['features.dynamic_storage', 'The media library: pictures and files the app loads by name, so you can swap them without releasing a new version of the app.'],
        'activity_logs' => ['features.activity_logs', 'The record of who changed what. Changes are always recorded; this only shows or hides the screen.'],
        'content_seeding' => ['features.content_seeding', 'Lets the app fill the CMS with its own wording and pictures. Turn it off once your content is in — while it is on, anyone with a copy of the app could change or delete it.'],
        'testing_mode' => ['app.is_testing', 'Testing mode, for trying the app without sending real messages. Always off on the live site.'],
    ];

    public function name(): string
    {
        return 'explain_setting';
    }

    public function description(): string
    {
        return 'Explain what a feature flag does and report whether it is currently on. Use for questions about how this install is configured.';
    }

    public function schema(User $admin): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'setting' => [
                    'type' => 'string',
                    'enum' => array_keys(self::SETTINGS),
                    'description' => 'Which setting to explain.',
                ],
            ],
            'required' => ['setting'],
            'additionalProperties' => false,
        ];
    }

    public function availableTo(User $admin): bool
    {
        // Which features are switched on is already obvious from the sidebar,
        // and nothing here is a secret — only booleans and prose.
        return true;
    }

    public function run(array $input, User $admin): string
    {
        $validator = Validator::make($input, [
            'setting' => ['required', 'string', 'in:'.implode(',', array_keys(self::SETTINGS))],
        ]);

        if ($validator->fails()) {
            return 'Error: `setting` must be one of '.implode(', ', array_keys(self::SETTINGS)).'.';
        }

        [$key, $description] = self::SETTINGS[$input['setting']];

        return $input['setting'].' is currently '.(config($key) ? 'ON' : 'OFF').'. '.$description;
    }
}
