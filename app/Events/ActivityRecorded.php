<?php

namespace App\Events;

use App\Models\Activity;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Pushed to everyone watching a target's dashboard. Listen on the private
 * channel `targets.{id}` for `.activity.recorded`.
 */
class ActivityRecorded implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(public Activity $activity) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel("targets.{$this->activity->target_id}")];
    }

    public function broadcastAs(): string
    {
        return 'activity.recorded';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return $this->activity->toFeedArray();
    }
}
