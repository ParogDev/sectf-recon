<?php

namespace App\Mcp\Tools;

use App\Models\Target;
use App\Services\TargetContext;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Title('List targets')]
#[IsReadOnly]
#[IsOpenWorld(false)]
#[Description('List the target companies you can use: your own (role owner) and any another contestant shared with you (viewer = read only, editor = can write). `current` marks the one every other tool acts on. Call this first in a session; then select_target to switch, or create_target if the list is empty.')]
class ListTargetsTool extends SectfTool
{
    protected string $name = 'list_targets';

    public function __construct(private TargetContext $targets) {}

    public function handle(Request $request): Response
    {
        $user = $this->user($request);
        $list = $this->targets->listFor($user);

        return $this->payload($list->firstWhere('id', $user->current_target_id), [
            'current_target_id' => $user->current_target_id,
            'targets' => $list->map(fn (Target $target) => [
                'id' => $target->id,
                'name' => $target->name,
                'url' => $target->url,
                'domains' => $target->domains ?? [],
                'owner' => $target->owner?->name,
                'role' => $target->roleFor($user)?->value,
                'current' => $target->id === $user->current_target_id,
            ])->all(),
        ]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
