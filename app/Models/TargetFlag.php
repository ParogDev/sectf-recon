<?php

namespace App\Models;

use App\Enums\FlagStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $target_id
 * @property string $flag_id
 * @property FlagStatus $status
 * @property string|null $notes
 * @property int|null $updated_by
 */
#[Fillable(['target_id', 'flag_id', 'status', 'notes', 'updated_by'])]
class TargetFlag extends Model
{
    protected $table = 'target_flag';

    protected function casts(): array
    {
        return ['status' => FlagStatus::class];
    }

    /** @return BelongsTo<Flag, $this> */
    public function flag(): BelongsTo
    {
        return $this->belongsTo(Flag::class);
    }

    /** @return BelongsTo<Target, $this> */
    public function target(): BelongsTo
    {
        return $this->belongsTo(Target::class);
    }
}
