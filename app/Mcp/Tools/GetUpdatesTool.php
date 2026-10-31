<?php

namespace App\Mcp\Tools;

use App\Models\Activity;
use App\Services\ActivityRecorder;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Title('Get updates')]
#[IsReadOnly]
#[IsOpenWorld(false)]
#[Description('What changed on the current target after a cursor, oldest first: findings recorded or updated, flag status changes, people, phone numbers and pretexts added, by anyone (including another person\'s Claude). Pass the `cursor` from your previous call as `since_id` (0 or omitted the first time). If `has_more` is true, call again with the new cursor.')]
class GetUpdatesTool extends SectfTool
{
    protected string $name = 'get_updates';

    public function __construct(private ActivityRecorder $activity) {}

    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'since_id' => ['nullable', 'integer', 'min:0'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $target = $this->currentTarget($request);
        $since = (int) ($validated['since_id'] ?? 0);
        $limit = (int) ($validated['limit'] ?? 50);

        $items = $this->activity->since($target, $since, $limit + 1);
        $hasMore = $items->count() > $limit;
        $items = $items->take($limit);

        return $this->payload($target, [
            'updates' => $items->map(fn (Activity $a) => $a->toFeedArray())->values()->all(),
            'cursor' => $items->last()->id ?? $since,
            'has_more' => $hasMore,
        ]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'since_id' => $schema->integer()->min(0)->description('The `cursor` from your last get_updates call; 0 for everything.'),
            'limit' => $schema->integer()->min(1)->max(200)->description('Default 50.'),
        ];
    }
}
