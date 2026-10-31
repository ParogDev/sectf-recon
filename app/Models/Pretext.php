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
 * @property string $key
 * @property string $title
 * @property string $persona
 * @property string|null $script
 * @property array<int, string>|null $flag_ids
 * @property array<int, int>|null $phone_number_ids
 * @property string $confidence
 * @property int|null $created_by
 * @property Carbon $updated_at
 */
#[Fillable(['target_id', 'key', 'title', 'persona', 'script', 'flag_ids', 'pivots', 'phone_number_ids', 'confidence', 'created_by'])]
class Pretext extends Model
{
    use HasTranslations;

    protected function casts(): array
    {
        return ['flag_ids' => 'array', 'pivots' => 'array', 'phone_number_ids' => 'array', 'confidence' => 'decimal:2'];
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
