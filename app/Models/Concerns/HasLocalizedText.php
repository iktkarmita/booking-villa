<?php

namespace App\Models\Concerns;

/**
 * Teks dua bahasa disimpan sebagai JSON {"id": "...", "en": "..."} (cast array).
 * localized() mengambil bahasa aktif, lalu fallback ke bahasa cadangan, lalu teks apa pun yang terisi.
 */
trait HasLocalizedText
{
    public function localized(string $attribute, ?string $locale = null): ?string
    {
        $value = $this->getAttribute($attribute);

        if (! is_array($value)) {
            return $value === null ? null : (string) $value;
        }

        $locale ??= app()->getLocale();

        foreach ([$locale, config('app.fallback_locale')] as $candidate) {
            if (filled($value[$candidate] ?? null)) {
                return $value[$candidate];
            }
        }

        return collect($value)->filter(fn ($text) => filled($text))->first();
    }
}
