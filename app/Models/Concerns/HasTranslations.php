<?php

namespace App\Models\Concerns;

/**
 * Narrative fields are stored in English; `translations` holds {"fr": {field: text}}.
 * `translated()` returns the text for a locale and falls back to English.
 *
 * @property array<string, array<string, mixed>>|null $translations
 */
trait HasTranslations
{
    public function initializeHasTranslations(): void
    {
        $this->mergeCasts(['translations' => 'array']);
        $this->mergeFillable(['translations']);
    }

    /** The field in $locale, or the English value when no translation was filed. */
    public function translated(string $field, ?string $locale = null): mixed
    {
        $locale ??= app()->getLocale();

        if ($locale !== 'en') {
            $text = $this->translations[$locale][$field] ?? null;

            if ($text !== null && $text !== '') {
                return $text;
            }
        }

        return $this->getAttribute($field);
    }

    /** True when every non-empty English field has a rendering in $locale (always true for English). */
    public function isTranslated(array $fields, ?string $locale = null): bool
    {
        $locale ??= app()->getLocale();

        if ($locale === 'en') {
            return true;
        }

        foreach ($fields as $field) {
            $english = $this->getAttribute($field);
            $other = $this->translations[$locale][$field] ?? null;

            if ($english !== null && $english !== '' && ($other === null || $other === '')) {
                return false;
            }
        }

        return true;
    }

    /**
     * Merge a French (or other) rendering into the stored translations.
     *
     * @param  array<string, mixed>|null  $fields
     */
    public function mergeTranslation(string $locale, ?array $fields): void
    {
        $fields = array_filter($fields ?? [], fn ($v) => $v !== null && $v !== '' && $v !== []);

        if ($fields === []) {
            return;
        }

        $all = $this->translations ?? [];
        $all[$locale] = array_replace($all[$locale] ?? [], $fields);
        $this->translations = $all;
    }
}
