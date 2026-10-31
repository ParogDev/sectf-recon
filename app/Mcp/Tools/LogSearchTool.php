<?php

namespace App\Mcp\Tools;

use App\Services\DossierService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;

#[Title('Log search')]
#[IsIdempotent]
#[IsDestructive(false)]
#[IsOpenWorld(false)]
#[Description('Note a search you just ran for the current target (web search, site visited, registry lookup, DNS query...), including ones that found nothing, so neither you nor anyone else repeats it. Logging the same channel and query again within 10 minutes updates that entry instead of adding one. This only records the search; it does not run it. If research on a flag was refused, blocked or crashed, log it with outcome "blocked" or "failed" and that flag\'s id, and move on: do not retry it on another model. The dossier lists those flags as needing a person until a later completed search or a found finding clears them.')]
class LogSearchTool extends SectfTool
{
    protected string $name = 'log_search';

    public function __construct(private DossierService $dossier) {}

    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'channel' => ['required', 'string', 'max:64'],
            'query' => ['required', 'string', 'max:2000'],
            'flag_id' => ['nullable', 'required_if:outcome,blocked,failed', 'string', 'exists:flags,id'],
            'result_count' => ['nullable', 'integer', 'min:0'],
            'outcome' => ['nullable', 'string', 'in:completed,blocked,failed'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], [
            'flag_id.required_if' => 'A blocked or failed search needs the flag_id it was for, so the dossier can list that flag as needing a person.',
        ]);

        $validated['channel'] = strtolower(trim($validated['channel']));

        $target = $this->currentTarget($request, write: true);
        $log = $this->dossier->logSearch($target, $this->user($request), $validated);

        return $this->payload($target, [
            'logged' => [
                'id' => $log->id,
                'channel' => $log->channel,
                'query' => $log->query,
                'flag_id' => $log->flag_id,
                'result_count' => $log->result_count,
            ],
        ]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'channel' => $schema->string()->max(64)->required()->description('Where: google, bing, linkedin, req, crt.sh, careers, wayback, site...'),
            'query' => $schema->string()->max(2000)->required()->description('The exact query string or URL.'),
            'flag_id' => $schema->string()->description('Flag it was for, if any.'),
            'result_count' => $schema->integer()->min(0)->description('Useful results; 0 if nothing.'),
            'outcome' => $schema->string()->enum(['completed', 'blocked', 'failed'])->description('Default completed. blocked: refused or stopped by a safeguard or a site. failed: crashed or never delivered.'),
            'notes' => $schema->string()->max(2000)->description('What came of it.'),
        ];
    }
}
