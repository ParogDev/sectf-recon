<?php

namespace App\Http\Controllers\App;

use App\Models\Target;
use App\Models\User;
use App\Services\TargetContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SessionController extends AppController
{
    public function __construct(private TargetContext $context) {}

    public function show(Request $request): JsonResponse
    {
        return response()->json(self::present($this->context, $this->user($request)));
    }

    /**
     * Who is signed in, the targets they can see, and which one is current.
     * If none is current (or it is no longer visible) but some are, the
     * first becomes current, so the dashboard is never blank for no reason.
     *
     * @return array{user: array{id: int, name: string, email: string, initials: string}, current_target_id: ?int, targets: array<int, array<string, mixed>>}
     */
    public static function present(TargetContext $context, User $user): array
    {
        $targets = $context->listFor($user);
        $currentId = $user->current_target_id;

        if ($currentId === null || ! $targets->contains('id', $currentId)) {
            $first = $targets->first();
            $currentId = $first === null ? null : $context->select($user, $first->id)->id;
        }

        return [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'initials' => $user->initials(),
            ],
            'current_target_id' => $currentId,
            'targets' => $targets->map(fn (Target $target) => [
                'id' => $target->id,
                'name' => $target->name,
                'url' => $target->url,
                'role' => $target->roleFor($user)->value ?? 'viewer',
                'owner' => $target->owner?->name,
            ])->all(),
        ];
    }
}
