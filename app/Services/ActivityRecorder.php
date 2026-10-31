<?php

namespace App\Services;

use App\Events\ActivityRecorded;
use App\Models\Activity;
use App\Models\Target;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * The one place a write is announced: appends to the activity feed (what
 * get_updates returns) and broadcasts it to the dashboard over Reverb.
 */
class ActivityRecorder
{
    public function record(Target $target, ?User $user, string $kind, string $summary, ?Model $subject = null, string $via = 'mcp'): Activity
    {
        $activity = Activity::create([
            'target_id' => $target->id,
            'user_id' => $user?->id,
            'kind' => $kind,
            'subject_type' => $subject === null ? null : class_basename($subject),
            'subject_id' => $subject?->getKey(),
            'summary' => mb_strimwidth($summary, 0, 255, '…'),
            'via' => $via,
        ]);

        // A Reverb outage must never fail the write that triggered it.
        rescue(fn () => ActivityRecorded::dispatch($activity->setRelation('user', $user)));

        return $activity;
    }

    /**
     * The newest activity, newest first (the dashboard feed).
     *
     * @return Collection<int, Activity>
     */
    public function latest(Target $target, int $limit = 30): Collection
    {
        return $target->activities()->with('user:id,name')->latest('id')->limit($limit)->get();
    }

    /**
     * Activity after a cursor, oldest first, so a caller can page forward.
     *
     * @return Collection<int, Activity>
     */
    public function since(Target $target, int $afterId = 0, int $limit = 50): Collection
    {
        return $target->activities()
            ->with('user:id,name')
            ->where('id', '>', $afterId)
            ->orderBy('id')
            ->limit($limit)
            ->get();
    }
}
