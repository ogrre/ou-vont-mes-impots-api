<?php

namespace App\Services\Api;

use App\Models\EditorialExplanation;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;

class EditorialExplanationCatalog
{
    public function text(string $key, string $fallback, string $locale = 'fr'): string
    {
        try {
            return (string) Cache::remember(
                'editorial-explanation:'.$locale.':'.$key,
                now()->addDay(),
                fn (): string => EditorialExplanation::query()
                    ->where('key', $key)
                    ->where('locale', $locale)
                    ->value('body') ?? $fallback,
            );
        } catch (QueryException) {
            // Les présentateurs unitaires peuvent être utilisés sans migration ;
            // le texte de secours garde le contrat stable dans ce contexte.
            return $fallback;
        }
    }

    /** @param array<string,string> $replace */
    public function template(string $key, string $fallback, array $replace = [], string $locale = 'fr'): string
    {
        return strtr($this->text($key, $fallback, $locale), $replace);
    }
}
