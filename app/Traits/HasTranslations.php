<?php

namespace App\Traits;

use App\Models\Language;
use App\Models\ModelTranslation;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\App;

trait HasTranslations
{
    public static function bootHasTranslations(): void
    {
        static::deleting(function ($model) {
            $model->deleteTranslations();
        });
    }

    public function translations(): MorphMany
    {
        return $this->morphMany(ModelTranslation::class, 'translatable');
    }

    public function getTranslatableFields(): array
    {
        return $this->translatable ?? [];
    }

    protected function isTranslatableField(string $field): bool
    {
        return in_array($field, $this->getTranslatableFields());
    }

    public function setTranslation(string $field, string $locale, ?string $value): void
    {
        if (! $this->isTranslatableField($field)) {
            throw new \InvalidArgumentException("Field '{$field}' is not defined as translatable.");
        }

        if ($value === null || $value === '') {
            $this->translations()
                ->where('field', $field)
                ->where('locale', $locale)
                ->delete();

            return;
        }

        $this->translations()->updateOrCreate(
            [
                'field' => $field,
                'locale' => $locale,
            ],
            ['value' => $value]
        );
    }

    public function getTranslation(string $field, ?string $locale = null): ?string
    {
        if (! $this->isTranslatableField($field)) {
            return null;
        }

        $locale = $locale ?? App::getLocale();

        // Load translations if not loaded
        if (! $this->relationLoaded('translations')) {
            $this->load('translations');
        }

        $translation = $this->translations
            ->where('field', $field)
            ->where('locale', $locale)
            ->first();

        if ($translation) {
            return $translation->value;
        }

        $defaultLocale = Language::getDefault()?->code ?? 'en';

        if ($locale !== $defaultLocale) {
            $translation = $this->translations
                ->where('field', $field)
                ->where('locale', $defaultLocale)
                ->first();

            if ($translation) {
                return $translation->value;
            }
        }

        return null;
    }

    public function getFieldTranslations(string $field): array
    {
        if (! $this->isTranslatableField($field)) {
            return [];
        }

        return $this->translations
            ->where('field', $field)
            ->pluck('value', 'locale')
            ->toArray();
    }

    public function saveTranslations(array $translations): void
    {
        foreach ($translations as $field => $locales) {
            if (! $this->isTranslatableField($field)) {
                continue;
            }

            if (! is_array($locales)) {
                continue;
            }

            foreach ($locales as $locale => $value) {
                $this->setTranslation($field, $locale, $value);
            }
        }
    }

    public function deleteTranslations(): void
    {
        $this->translations()->delete();
    }

    public function getAllTranslations(): array
    {
        $result = [];

        foreach ($this->getTranslatableFields() as $field) {
            $result[$field] = $this->getFieldTranslations($field);
        }

        return $result;
    }

    public function scopeWithTranslations($query, ?string $locale = null)
    {
        return $query->with(['translations' => function ($q) use ($locale) {
            if ($locale) {
                $q->where('locale', $locale);
            }
        }]);
    }

    /**
     * Active language codes, memoized per request.
     *
     * @var array<string>|null
     */
    protected static ?array $cachedActiveLocales = null;

    public static function activeLocales(): array
    {
        if (static::$cachedActiveLocales === null) {
            static::$cachedActiveLocales = Language::active()->pluck('code')->all();
        }

        return static::$cachedActiveLocales;
    }

    /**
     * Which translatable fields must be filled for a row to count as "complete".
     * Defaults to all translatable fields; override with `protected array
     * $translationRequired = [...]` to only require certain fields.
     */
    public function translationCompletenessFields(): array
    {
        return ! empty($this->translationRequired)
            ? $this->translationRequired
            : $this->getTranslatableFields();
    }

    /**
     * Locales for which this row is missing a required translation value.
     *
     * @return array<string>
     */
    public function missingTranslationLocales(?array $locales = null): array
    {
        $locales = $locales ?? static::activeLocales();

        if (empty($locales)) {
            return [];
        }

        if (! $this->relationLoaded('translations')) {
            $this->load('translations');
        }

        $missing = [];
        foreach ($locales as $locale) {
            foreach ($this->translationCompletenessFields() as $field) {
                $value = $this->translations
                    ->first(fn ($t) => $t->field === $field && $t->locale === $locale)?->value;

                if ($value === null || $value === '') {
                    $missing[] = $locale;
                    break;
                }
            }
        }

        return $missing;
    }

    /**
     * Count rows that are missing a required translation in any active locale.
     * Used for the navbar warning indicator.
     */
    public static function incompleteTranslationCount(?array $locales = null): int
    {
        $locales = $locales ?? static::activeLocales();

        if (empty($locales)) {
            return 0;
        }

        return static::query()
            ->with('translations')
            ->get()
            ->filter(fn ($model) => count($model->missingTranslationLocales($locales)) > 0)
            ->count();
    }

    /**
     * Override toArray to include translated _api attributes
     */
    public function toArray(): array
    {
        $array = parent::toArray();

        foreach ($this->getTranslatableFields() as $field) {
            $array[$field.'_api'] = $this->getTranslation($field);
        }

        // Surface which active locales are missing a required translation so the
        // admin table/row can flag it. Only computed when translations are loaded
        // to avoid lazy-loading on every serialization (e.g. API list mapping).
        if ($this->relationLoaded('translations')) {
            $array['missing_translations'] = $this->missingTranslationLocales();
        }

        return $array;
    }

    /**
     * Handle dynamic attribute access for {field}_api
     */
    public function __get($key)
    {
        if (str_ends_with($key, '_api')) {
            $field = substr($key, 0, -4);
            if ($this->isTranslatableField($field)) {
                return $this->getTranslation($field);
            }
        }

        return parent::__get($key);
    }
}
