<?php

namespace App\Mcp\Tools;

use App\Services\FindingService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Title('List findings')]
#[IsReadOnly]
#[IsOpenWorld(false)]
#[Description('Read back findings filed on the current target, most recently updated first, each with its key, flag, claim, confidence, content date, who filed it and its sources (URL, retrieved_at, content date, supporting quote). Filter by flag_id, or search text in the key, claim and detail. Check here before record_finding to reuse an existing key instead of filing the same fact twice.')]
class ListFindingsTool extends SectfTool
{
    protected string $name = 'list_findings';

    public function __construct(private FindingService $findings) {}

    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'flag_id' => ['nullable', 'string', 'regex:/^\d{2}-\d{2}$/'],
            'search' => ['nullable', 'string', 'max:200'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $target = $this->currentTarget($request);
        $list = $this->findings->list($target, $validated['flag_id'] ?? null, $validated['search'] ?? null, (int) ($validated['limit'] ?? 50));

        return $this->payload($target, [
            'count' => $list->count(),
            'findings' => $list->map(FindingService::present(...))->all(),
        ]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'flag_id' => $schema->string()->pattern('^\d{2}-\d{2}$')->description('e.g. "04-13".'),
            'search' => $schema->string()->max(200)->description('Text to look for in the key, claim or detail.'),
            'limit' => $schema->integer()->min(1)->max(200)->description('Default 50.'),
        ];
    }
}
