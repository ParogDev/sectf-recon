<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An employee of the target. Job-relevant facts only (event rules + Loi 25).
 *
 * @property int $id
 * @property int $target_id
 * @property string $key
 * @property string $name
 * @property string|null $title
 * @property string|null $department
 * @property string|null $tenure
 * @property string|null $profile_url
 * @property string|null $notes
 * @property int|null $source_id
 * @property int|null $created_by
 */
#[Fillable(['target_id', 'key', 'name', 'title', 'department', 'tenure', 'profile_url', 'notes', 'source_id', 'created_by'])]
class Person extends Model
{
    use HasTranslations;

    protected $table = 'people';

    /** @return BelongsTo<Source, $this> */
    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class);
    }

    /** @return HasMany<PhoneNumber, $this> */
    public function phoneNumbers(): HasMany
    {
        return $this->hasMany(PhoneNumber::class);
    }
}
