<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * A question from the organiser's catalogue. Shared by every target.
 *
 * @property string $id
 * @property string $category_id
 * @property string $category_en
 * @property string $category_fr
 * @property int $points
 * @property string $question_en
 * @property string $question_fr
 * @property bool $bonus
 * @property bool $call_only
 * @property string|null $related_flag_id
 * @property int $sort
 */
#[Fillable(['id', 'category_id', 'category_en', 'category_fr', 'points', 'question_en', 'question_fr', 'bonus', 'call_only', 'related_flag_id', 'sort'])]
class Flag extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return ['bonus' => 'boolean', 'call_only' => 'boolean', 'points' => 'integer'];
    }

    /**
     * OSINT findings in the written report earn half the listed points.
     */
    public function osintPoints(): float
    {
        return $this->points / 2;
    }
}
