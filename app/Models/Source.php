<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $target_id
 * @property string $url
 * @property string $url_hash
 * @property string|null $title
 * @property Carbon $retrieved_at
 * @property Carbon|null $content_date
 * @property string|null $content_date_basis
 * @property string|null $screenshot_path
 * @property int|null $added_by
 */
#[Fillable(['target_id', 'url', 'url_hash', 'title', 'retrieved_at', 'content_date', 'content_date_basis', 'screenshot_path', 'added_by'])]
class Source extends Model
{
    protected function casts(): array
    {
        return ['retrieved_at' => 'datetime', 'content_date' => 'date'];
    }

    public static function hashUrl(string $url): string
    {
        return hash('sha256', $url);
    }

    /** @return BelongsToMany<Finding, $this> */
    public function findings(): BelongsToMany
    {
        return $this->belongsToMany(Finding::class)->withPivot('quote');
    }

    /** @return BelongsTo<User, $this> */
    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by');
    }

    /**
     * The supporting quote on the finding_source row, when this source was
     * loaded through a finding's `sources` relation.
     */
    public function quote(): ?string
    {
        $pivot = $this->getRelationValue('pivot');

        return $pivot instanceof Model ? $pivot->getAttribute('quote') : null;
    }
}
