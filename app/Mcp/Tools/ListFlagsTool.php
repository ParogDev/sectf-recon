<?php

namespace App\Mcp\Tools;

use App\Enums\FlagStatus;
use App\Services\FlagBoard;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Title('List flags')]
#[IsReadOnly]
#[IsOpenWorld(false)]
#[Description('The flag board for the current target: every question in the organiser\'s catalogue with its points, OSINT points (half, what the written report earns), status (open, partial, found, unverifiable) and how many findings back it. Also returns the points summary and `next_best`: unresolved flags ranked partials first, then by points, skipping call-only flags and duplicates of a flag already found. Filter by status or category_id ("01".."07") to shorten the list.')]
class ListFlagsTool extends SectfTool
{
    protected string $name = 'list_flags';

    public function __construct(private FlagBoard $flags) {}

    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'status' => ['nullable', Rule::enum(FlagStatus::class)],
            'category_id' => ['nullable', 'string', 'regex:/^\d{2}$/'],
            'next_best' => ['nullable', 'integer', 'min:0', 'max:20'],
        ]);

        $target = $this->currentTarget($request);
        $status = isset($validated['status']) ? FlagStatus::from($validated['status']) : null;

        return $this->payload($target, [
            'summary' => $this->flags->summary($target),
            'next_best' => $this->flags->nextBest($target, (int) ($validated['next_best'] ?? 5))
                ->map(fn (array $f) => collect($f)->only(['id', 'category', 'question', 'points', 'osint_points', 'status'])->all())
                ->all(),
            'flags' => $this->flags->board($target, $status, $validated['category_id'] ?? null)->all(),
        ]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'status' => $schema->string()->enum(array_column(FlagStatus::cases(), 'value'))->description('Only flags with this status.'),
            'category_id' => $schema->string()->pattern('^\d{2}$')->description('Two-digit category, e.g. "04".'),
            'next_best' => $schema->integer()->min(0)->max(20)->description('How many next-best flags to suggest. Default 5.'),
        ];
    }
}
