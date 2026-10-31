<?php

namespace App\Mcp\Tools;

use App\Services\TargetContext;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;

#[Title('Create target')]
#[IsIdempotent]
#[IsDestructive(false)]
#[IsOpenWorld(false)]
#[Description('Create the target company you were assigned, owned by you, with every catalogue flag open, and make it your current target. Do this once per contestant. If you already own a target with this name it is selected instead of duplicated (`created` is false). Only the organisers assign targets: never create one for a company you were not given.')]
class CreateTargetTool extends SectfTool
{
    protected string $name = 'create_target';

    public function __construct(private TargetContext $targets) {}

    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'url' => ['nullable', 'url:http,https', 'max:255'],
            'domains' => ['nullable', 'array', 'max:20'],
            'domains.*' => ['string', 'max:253', 'regex:/^[a-z0-9.-]+\.[a-z]{2,}$/i'],
            'registry_id' => ['nullable', 'string', 'max:64'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'fr' => ['nullable', 'array'],
            'fr.notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $fr = $validated['fr'] ?? null;
        unset($validated['fr']);

        if (isset($validated['domains'])) {
            $validated['domains'] = array_values(array_unique(array_map('strtolower', $validated['domains'])));
        }

        $target = $this->targets->create($this->user($request), $validated);

        if ($fr !== null) {
            $target->mergeTranslation('fr', $fr);
            $target->save();
        }

        return $this->payload($target, [
            'created' => $target->wasRecentlyCreated,
            'url' => $target->url,
            'domains' => $target->domains ?? [],
            'registry_id' => $target->registry_id,
            'current' => true,
        ]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->max(255)->required()->description('Company name as the organisers gave it.'),
            'url' => $schema->string()->max(255)->description('Main public website, https://...'),
            'domains' => $schema->array()->items($schema->string())->max(20)->description('Domains the company uses, e.g. ["example.com", "example.ca"].'),
            'registry_id' => $schema->string()->max(64)->description('Québec NEQ or federal corporation number, if known.'),
            'notes' => $schema->string()->max(5000),
            'fr' => $schema->object(['notes' => $schema->string()])->description('French version of notes.'),
        ];
    }
}
