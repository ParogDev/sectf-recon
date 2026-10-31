<?php

namespace App\Http\Controllers\App;

use App\Services\FlagBoard;
use App\Services\TargetContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TargetController extends AppController
{
    public function __construct(private TargetContext $context, private FlagBoard $flags) {}

    /**
     * Make this the user's current target: the same server-side state the
     * `select_target` MCP tool sets, so Claude and the dashboard agree.
     * No access is a 403 and leaves the current target unchanged.
     */
    public function select(Request $request, int $target): JsonResponse
    {
        $user = $this->user($request);
        $this->context->select($user, $target);

        return response()->json(SessionController::present($this->context, $user));
    }

    public function show(Request $request, int $target): JsonResponse
    {
        $user = $this->user($request);
        $target = $this->target($request, $target)->loadMissing('owner:id,name');

        return $this->payload($target, [
            'target' => [
                'id' => $target->id,
                'name' => $target->name,
                'url' => $target->url,
                'domains' => $target->domains ?? [],
                'registry_id' => $target->registry_id,
                'notes' => $target->notes,
                'role' => $target->roleFor($user)->value ?? 'viewer',
                'owner' => $target->owner?->name,
            ],
            'summary' => $this->flags->summary($target),
            'flags' => $this->flags->board($target)->values()->all(),
            'next_best' => $this->flags->nextBest($target, 5)->pluck('id')->all(),
        ]);
    }
}
