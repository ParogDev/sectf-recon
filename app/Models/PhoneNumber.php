<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $target_id
 * @property string $number
 * @property string $extension
 * @property string $label
 * @property string|null $department
 * @property int|null $person_id
 * @property int|null $source_id
 * @property Carbon|null $ring_verified_at
 * @property string|null $notes
 * @property int|null $created_by
 */
#[Fillable(['target_id', 'number', 'extension', 'label', 'department', 'person_id', 'source_id', 'ring_verified_at', 'notes', 'kind', 'created_by'])]
class PhoneNumber extends Model
{
    use HasTranslations;

    protected function casts(): array
    {
        return ['ring_verified_at' => 'datetime'];
    }

    /** @return BelongsTo<Person, $this> */
    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    /** @return BelongsTo<Source, $this> */
    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class);
    }
}
