<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:sync-postman-collection')]
#[Description('Replace hardcoded per-request placeholder values (X-API-TOKEN, X-Device-Id, X-Platform, bearer token) in the generated Postman collection with reusable {{collection variables}}, so users configure auth once in the collection instead of editing every request.')]
class SyncPostmanCollection extends Command
{
    /**
     * Maps a request header name to the collection variable it should be replaced with.
     *
     * @var array<string, string>
     */
    private const HEADER_VARIABLES = [
        'X-API-TOKEN' => 'xApiToken',
        'X-Device-Id' => 'deviceId',
        'X-Platform' => 'platform',
        'X-FCM-Token' => 'fcmToken',
    ];

    public function handle(): int
    {
        $path = storage_path('app/private/scribe/collection.json');

        if (! file_exists($path)) {
            $this->error("No collection found at {$path}. Run `php artisan scribe:generate` first.");

            return self::FAILURE;
        }

        $collection = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);

        /** @var array<string, string> $defaults */
        $defaults = [];
        $this->syncHeaders($collection['item'], $defaults);
        $this->syncCollectionAuth($collection, $defaults);
        $this->appendVariables($collection, $defaults);

        file_put_contents(
            $path,
            json_encode($collection, JSON_PRETTY_PRINT | JSON_INVALID_UTF8_SUBSTITUTE)
        );

        $this->info('Synced '.count($defaults).' collection variable(s): '.implode(', ', array_keys($defaults)));

        return self::SUCCESS;
    }

    /**
     * Recurse through Postman's folder/item tree and replace every known header's literal
     * value with a `{{variable}}` reference, capturing the first value seen as its default.
     *
     * @param  array<int, mixed>  $items
     * @param  array<string, string>  $defaults
     */
    private function syncHeaders(array &$items, array &$defaults): void
    {
        foreach ($items as &$item) {
            if (isset($item['item'])) {
                $this->syncHeaders($item['item'], $defaults);

                continue;
            }

            if (! isset($item['request']['header'])) {
                continue;
            }

            foreach ($item['request']['header'] as &$header) {
                $varKey = self::HEADER_VARIABLES[$header['key']] ?? null;
                $value = $header['value'] ?? '';

                if (! $varKey || str_starts_with($value, '{{')) {
                    continue;
                }

                $defaults[$varKey] ??= $value;
                $header['value'] = "{{{$varKey}}}";
            }
        }
    }

    /**
     * The collection-level bearer auth (inherited by any endpoint without its own `auth`
     * override, e.g. the Broadcasting channel-auth endpoint) points at a hardcoded key with
     * no value. Point it at a `bearerToken` variable instead.
     *
     * @param  array<string, mixed>  $collection
     * @param  array<string, string>  $defaults
     */
    private function syncCollectionAuth(array &$collection, array &$defaults): void
    {
        if (($collection['auth']['type'] ?? null) !== 'bearer') {
            return;
        }

        foreach ($collection['auth']['bearer'] as &$field) {
            if (($field['key'] ?? null) !== 'key' || ($field['value'] ?? null) === '{{bearerToken}}') {
                continue;
            }

            $field['value'] = '{{bearerToken}}';
            $defaults['bearerToken'] = config('scribe.auth.placeholder') ?? '';
        }
    }

    /**
     * @param  array<string, mixed>  $collection
     * @param  array<string, string>  $defaults
     */
    private function appendVariables(array &$collection, array $defaults): void
    {
        $existingKeys = collect($collection['variable'] ?? [])->pluck('key')->all();

        foreach ($defaults as $key => $value) {
            if (in_array($key, $existingKeys, true)) {
                continue;
            }

            $collection['variable'][] = [
                'id' => $key,
                'key' => $key,
                'type' => 'string',
                'name' => $key,
                'value' => $value,
            ];
        }
    }
}
