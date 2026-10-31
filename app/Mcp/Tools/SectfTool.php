<?php

namespace App\Mcp\Tools;

use App\Exceptions\TargetContextException;
use App\Models\Target;
use App\Models\User;
use App\Services\TargetContext;
use Illuminate\Auth\AuthenticationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

/**
 * Shared behaviour for every SECTF tool: who is calling, which target they
 * act on, and the single way a result goes back.
 */
abstract class SectfTool extends Tool
{
    /** Lowercase slug a caller picks so a retry updates instead of duplicating. */
    protected const KEY_RULE = ['required', 'string', 'max:191', 'regex:/^[a-z0-9][a-z0-9-]*$/'];

    protected const KEY_DESCRIPTION = 'Stable lowercase slug you choose (a-z, 0-9, dashes), unique per target. Calling again with the same key updates that record instead of creating a new one.';

    protected function user(Request $request): User
    {
        $user = $request->user();

        if (! $user instanceof User) {
            throw new AuthenticationException('Not signed in. Reconnect the MCP server to log in again.');
        }

        return $user;
    }

    /**
     * The caller's current target, authorized for reading or writing.
     *
     * @throws TargetContextException with a message that says what to call next
     */
    protected function currentTarget(Request $request, bool $write = false): Target
    {
        return app(TargetContext::class)->forUser($this->user($request), $write);
    }

    /**
     * The one way a tool hands data back. Every payload names the target it
     * applies to, so a reader can never confuse two contestants' data.
     *
     * @param  array<string, mixed>  $data
     */
    protected function payload(?Target $target, array $data): Response
    {
        return Response::text(json_encode(
            ['target_id' => $target?->id, 'target_name' => $target?->name, ...$data],
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        ));
    }
}
