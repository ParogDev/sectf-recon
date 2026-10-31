<?php

namespace App\Http\Controllers\App;

use App\Models\Activity;
use App\Services\ActivityRecorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ActivityController extends AppController
{
    public function __construct(private ActivityRecorder $activity) {}

    /**
     * Without `after`: the newest activity, newest first (initial load).
     * With `after`: everything past that id, oldest first (catch-up after a
     * Reverb event or a reconnect).
     */
    public function index(Request $request, int $target): JsonResponse
    {
        $validated = $request->validate([
            'limit' => ['nullable', 'integer', 'min:1', 'max:200'],
            'after' => ['nullable', 'integer', 'min:0'],
        ]);

        $target = $this->target($request, $target);
        $limit = (int) ($validated['limit'] ?? 50);

        $list = isset($validated['after'])
            ? $this->activity->since($target, (int) $validated['after'], $limit)
            : $this->activity->latest($target, $limit);

        return $this->payload($target, [
            'activity' => $list->map(fn (Activity $a) => $a->toFeedArray())->all(),
        ]);
    }
}
