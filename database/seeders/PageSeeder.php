<?php

namespace Database\Seeders;

use App\Helpers\ProtectedPages;
use App\Models\Language;
use App\Models\Page;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Creates the pages listed in PROTECTED_PAGES that do not exist yet.
 */
class PageSeeder extends Seeder
{
    /**
     * Titles for the slugs a starter install ships with. Anything else added to
     * PROTECTED_PAGES gets its slug as a headline in every locale, for the admin
     * to reword — the point of the seeder is that the page exists, not its copy.
     *
     * @var array<string, array<string, string>>
     */
    private const TITLES = [
        'terms' => ['en' => 'Terms & Conditions', 'ar' => 'الشروط والأحكام'],
        'privacy' => ['en' => 'Privacy Policy', 'ar' => 'سياسة الخصوصية'],
    ];

    public function run(): void
    {
        $locales = Language::active()->pluck('code');

        foreach (ProtectedPages::slugs() as $slug) {
            if (Page::where('slug', $slug)->exists()) {
                continue;
            }

            $page = Page::create(['slug' => $slug, 'is_active' => true]);

            $page->saveTranslations([
                'name' => $locales
                    ->mapWithKeys(fn (string $code): array => [
                        $code => self::TITLES[$slug][$code] ?? Str::headline($slug),
                    ])
                    ->all(),
            ]);
        }
    }
}
