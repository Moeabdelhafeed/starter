<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('scribe:polish')]
#[Description('Fix Postman-collection quirks Scribe itself leaves behind: raw request bodies missing the JSON language hint, and saved response examples missing a Content-Type header (both cause Postman to render them as plain text instead of syntax-highlighted JSON).')]
class PolishApiDocs extends Command
{
    public function handle(): int
    {
        $path = storage_path('app/private/scribe/collection.json');

        if (! file_exists($path)) {
            $this->error("No collection found at {$path}. Run `php artisan scribe:generate` first.");

            return self::FAILURE;
        }

        $collection = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);

        [$requestsFixed, $responsesFixed] = $this->polishItems($collection['item']);

        file_put_contents(
            $path,
            json_encode($collection, JSON_PRETTY_PRINT | JSON_INVALID_UTF8_SUBSTITUTE)
        );

        $this->info("Polished {$requestsFixed} request bodies and {$responsesFixed} response examples.");

        return self::SUCCESS;
    }

    /**
     * Recurse through Postman's folder/item tree (folders nest via an `item` key;
     * leaves are actual requests) and fix every raw request body + response example.
     *
     * @param  array<int, mixed>  $items
     * @return array{0: int, 1: int} [requests fixed, responses fixed]
     */
    private function polishItems(array &$items): array
    {
        $requestsFixed = 0;
        $responsesFixed = 0;

        foreach ($items as &$item) {
            if (isset($item['item'])) {
                [$r, $s] = $this->polishItems($item['item']);
                $requestsFixed += $r;
                $responsesFixed += $s;

                continue;
            }

            if ($this->markRequestBodyAsJson($item)) {
                $requestsFixed++;
            }

            $responsesFixed += $this->addMissingContentTypeHeaders($item);
        }

        return [$requestsFixed, $responsesFixed];
    }

    /**
     * Postman only syntax-highlights a raw body as JSON when told to via
     * body.options.raw.language — Scribe's PostmanCollectionWriter never sets it,
     * so every request with a JSON body renders as plain text by default.
     */
    private function markRequestBodyAsJson(array &$item): bool
    {
        $body = $item['request']['body'] ?? null;

        if (! $body || ($body['mode'] ?? null) !== 'raw' || ($body['raw'] ?? '') === '') {
            return false;
        }

        $item['request']['body']['options'] = ['raw' => ['language' => 'json']];

        return true;
    }

    /**
     * Saved response examples only get syntax-highlighted when their `header` array
     * includes a Content-Type — Scribe's @response tag has no way to declare response
     * headers, so every manually-documented example ships with headers: [] and renders raw.
     */
    private function addMissingContentTypeHeaders(array &$item): int
    {
        if (! isset($item['response'])) {
            return 0;
        }

        $fixed = 0;

        foreach ($item['response'] as $i => $response) {
            $hasContentType = collect($response['header'] ?? [])
                ->contains(fn ($h) => strtolower($h['key']) === 'content-type');

            if (! $hasContentType) {
                array_unshift($item['response'][$i]['header'], [
                    'key' => 'Content-Type',
                    'value' => 'application/json',
                ]);
                $fixed++;
            }
        }

        return $fixed;
    }
}
