<?php

namespace App\Mcp\Tools;

use App\Services\FlagBoard;
use App\Services\TargetContext;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;

#[Title('Select target')]
#[IsIdempotent]
#[IsDestructive(false)]
#[IsOpenWorld(false)]
#[Description('Make one of your targets the current one. Every other tool then reads and writes that target until you select another (the choice is stored server-side and survives new sessions). Takes an id from list_targets. Returns the target and its points summary.')]
class SelectTargetTool extends SectfTool
{
    protected string $name = 'select_target';

    public function __construct(private TargetContext $targets, private FlagBoard $flags) {}

    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'target_id' => ['required', 'integer', 'min:1'],
        ]);

        $user = $this->user($request);
        $target = $this->targets->select($user, (int) $validated['target_id']);

        return $this->payload($target, [
            'url' => $target->url,
            'domains' => $target->domains ?? [],
            'registry_id' => $target->registry_id,
            'notes' => $target->notes,
            'role' => $target->roleFor($user)?->value,
            'summary' => $this->flags->summary($target),
        ]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'target_id' => $schema->integer()->min(1)->required()->description('A target id from list_targets.'),
        ];
    }
}
