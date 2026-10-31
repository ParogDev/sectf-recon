<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $target_id
 * @property int|null $user_id
 * @property string $channel
 * @property string $query
 * @property string|null $flag_id
 * @property int|null $result_count
 * @property string|null $notes
 * @property Carbon $created_at
 */
#[Fillable(['target_id', 'user_id', 'channel', 'query', 'flag_id', 'result_count', 'outcome', 'notes'])]
class SearchLog extends Model
{
    public const UPDATED_AT = null;

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
