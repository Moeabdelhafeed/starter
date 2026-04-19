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
     * Override toArray to include translated _api attributes
     */
    public function toArray(): array
    {
        $array = parent::toArray();

        foreach ($this->getTranslatableFields() as $field) {
            $array[$field.'_api'] = $this->getTranslation($field);
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
