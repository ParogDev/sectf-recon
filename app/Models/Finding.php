<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $target_id
 * @property string|null $flag_id
 * @property string $key
 * @property string $claim
 * @property string|null $detail
 * @property string $confidence
 * @property Carbon|null $content_date
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable(['target_id', 'flag_id', 'key', 'claim', 'detail', 'answers_flag', 'confidence', 'content_date', 'created_by', 'updated_by'])]
class Finding extends Model
{
    use HasTranslations;

    protected function casts(): array
    {
        return ['confidence' => 'decimal:2', 'content_date' => 'date'];
    }

    /** @return BelongsTo<Flag, $this> */
    public function flag(): BelongsTo
    {
        return $this->belongsTo(Flag::class);
    }

    /** @return BelongsToMany<Source, $this> */
    public function sources(): BelongsToMany
    {
        return $this->belongsToMany(Source::class)->withPivot('quote');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
