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
 * @property string $kind
 * @property string|null $subject_type
 * @property int|null $subject_id
 * @property string $summary
 * @property string $via
 * @property Carbon $created_at
 */
#[Fillable(['target_id', 'user_id', 'kind', 'subject_type', 'subject_id', 'summary', 'via'])]
class Activity extends Model
{
    public const UPDATED_AT = null;

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function toFeedArray(): array
    {
        return [
            'id' => $this->id,
            'kind' => $this->kind,
            'summary' => $this->summary,
            'subject' => $this->subject_type === null ? null : ['type' => $this->subject_type, 'id' => $this->subject_id],
            'by' => $this->user?->name,
            'via' => $this->via,
            'at' => $this->created_at->toIso8601String(),
        ];
    }
}
