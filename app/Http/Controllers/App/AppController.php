<?php

namespace App\Http\Controllers\App;

use App\Exceptions\TargetContextException;
use App\Http\Controllers\Controller;
use App\Models\Target;
use App\Models\User;
use App\Services\TargetContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Base for the dashboard SPA's session-authenticated JSON API. Controllers
 * validate, resolve the target, call a service and return JSON; the target
 * in the URL is always re-authorized through TargetContext.
 */
abstract class AppController extends Controller
{
    protected function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }

    /**
     * The target in the URL, authorized for reading or (when $write) writing.
     * No access or read-only access throws a TargetContextException, which
     * renders as a 403.
     *
     * @throws TargetContextException
     */
    protected function target(Request $request, int $targetId, bool $write = false): Target
    {
        $user = $this->user($request);
        $target = app(TargetContext::class)->accessible($user, $targetId);

        if ($write && ! ($target->roleFor($user)?->canWrite() ?? false)) {
            throw TargetContextException::readOnly($target->name);
        }

        return $target;
    }

    /**
     * Every target-scoped payload names the target it applies to.
     *
     * @param  array<string, mixed>  $data
     */
    protected function payload(Target $target, array $data): JsonResponse
    {
        return response()->json(['target_id' => $target->id, ...$data]);
    }
}
