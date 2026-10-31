<?php

namespace App\Mcp\Tools;

use App\Models\SearchLog;
use App\Services\DossierService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Title('List searches')]
#[IsReadOnly]
#[IsOpenWorld(false)]
#[Description('Searches already run on the current target, newest first, by anyone: channel, query, flag, result count, notes, who and when. Check before searching so you do not repeat work; filter by text in the query or notes, or by flag_id.')]
class ListSearchesTool extends SectfTool
{
    protected string $name = 'list_searches';

    public function __construct(private DossierService $dossier) {}

    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'contains' => ['nullable', 'string', 'max:200'],
            'flag_id' => ['nullable', 'string', 'regex:/^\d{2}-\d{2}$/'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $target = $this->currentTarget($request);
        $list = $this->dossier->searches($target, $validated['contains'] ?? null, $validated['flag_id'] ?? null, (int) ($validated['limit'] ?? 50));

        return $this->payload($target, [
            'count' => $list->count(),
            'searches' => $list->map(fn (SearchLog $s) => [
                'channel' => $s->channel,
                'query' => $s->query,
                'flag_id' => $s->flag_id,
                'result_count' => $s->result_count,
                'notes' => $s->notes,
                'by' => $s->user?->name,
                'at' => $s->created_at->toIso8601String(),
            ])->all(),
        ]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'contains' => $schema->string()->max(200)->description('Text to look for in the query or notes.'),
            'flag_id' => $schema->string()->pattern('^\d{2}-\d{2}$'),
            'limit' => $schema->integer()->min(1)->max(200)->description('Default 50.'),
        ];
    }
}
