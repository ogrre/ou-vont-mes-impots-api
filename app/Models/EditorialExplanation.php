<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Textes pédagogiques stables, indépendants des observations financières annuelles.
 *
 * @property int $id
 * @property string $key
 * @property string $locale
 * @property string|null $title
 * @property string $body
 * @property array<string,mixed>|null $metadata
 */
class EditorialExplanation extends Model
{
    protected $guarded = [];

    /** @return array<string,string> */
    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }
}
