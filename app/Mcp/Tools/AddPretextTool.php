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

#[Title('Add pretext')]
#[IsIdempotent]
#[IsDestructive(false)]
#[IsOpenWorld(false)]
#[Description('Add or update a call plan for the live phase on the current target: the persona, the flags it aims to capture, the numbers it will dial and an outline script. Phone numbers must already be filed with add_phone; any that are not come back in `unmatched_phone_numbers` and are not linked. Same key updates the pretext. Also give `pivots`: the other flags the conversation can naturally steer to, each with the bridge line, and `fr` (French title, persona, script, bridges) so the French dossier is entirely French. The dossier needs at least 5 pretexts, varied by persona and by number type. This only stores the plan: no call is made.')]
class AddPretextTool extends SectfTool
{
    protected string $name = 'add_pretext';

    public function __construct(private DossierService $dossier) {}

    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'key' => self::KEY_RULE,
            'title' => ['required', 'string', 'max:255'],
            'persona' => ['required', 'string', 'max:5000'],
            'script' => ['nullable', 'string', 'max:20000'],
            'flag_ids' => ['nullable', 'array', 'max:70'],
            'flag_ids.*' => ['string', 'distinct', 'exists:flags,id'],
            'pivots' => ['nullable', 'array', 'max:40'],
            'pivots.*.flag_id' => ['required', 'string', 'exists:flags,id'],
            'pivots.*.bridge' => ['required', 'string', 'max:500'],
            'phone_numbers' => ['nullable', 'array', 'max:20'],
            'phone_numbers.*' => ['string', 'max:32'],
            'confidence' => ['nullable', 'numeric', 'between:0,1'],
            'fr' => ['nullable', 'array'],
            'fr.title' => ['nullable', 'string', 'max:255'],
            'fr.persona' => ['nullable', 'string', 'max:5000'],
            'fr.script' => ['nullable', 'string', 'max:20000'],
            'fr.pivots' => ['nullable', 'array'],
            'fr.pivots.*' => ['string', 'max:500'],
        ], [
            'pivots.*.flag_id.exists' => 'Unknown flag_id in pivots. Call list_flags for the ids (format "04-13").',
        ]);

        $target = $this->currentTarget($request, write: true);
        $result = $this->dossier->upsertPretext($target, $this->user($request), $validated);
        $pretext = $result['pretext'];

        return $this->payload($target, [
            'created' => $result['created'],
            'pretext' => [
                'key' => $pretext->key,
                'title' => $pretext->title,
                'flag_ids' => $pretext->flag_ids ?? [],
                'pivots' => $pretext->pivots ?? [],
                'has_french' => $pretext->isTranslated(['title', 'persona', 'script'], 'fr'),
                'phone_numbers' => $result['phone_numbers'],
                'confidence' => (float) $pretext->confidence,
            ],
            'unmatched_phone_numbers' => $result['unmatched_phone_numbers'],
        ]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'key' => $schema->string()->max(191)->pattern('^[a-z0-9][a-z0-9-]*$')->required()->description(self::KEY_DESCRIPTION.' e.g. "it-vendor-survey".'),
            'title' => $schema->string()->max(255)->required(),
            'persona' => $schema->string()->max(5000)->required()->description('Who the caller claims to be and why they are calling.'),
            'script' => $schema->string()->max(20000)->description('Outline in Markdown: opener, questions mapped to flags, exits.'),
            'flag_ids' => $schema->array()->items($schema->string())->description('Flags this call aims for, e.g. ["04-13", "05-03"].'),
            'pivots' => $schema->array()->items($schema->object([
                'flag_id' => $schema->string()->required(),
                'bridge' => $schema->string()->max(500)->required()->description('The natural line in the conversation that leads here, e.g. "while confirming the start time, ask if a badge is handed out on day one".'),
            ]))->description('Other flags this call can naturally pivot to, each with the bridge line. Aim for 4-8.'),
            'fr' => $schema->object([
                'title' => $schema->string(),
                'persona' => $schema->string(),
                'script' => $schema->string(),
                'pivots' => $schema->object()->description('French bridge lines keyed by flag id, e.g. {"03-09": "..."}'),
            ])->description('French version of title, persona, script and pivot bridges, so the French dossier is entirely French.'),
            'phone_numbers' => $schema->array()->items($schema->string())->description('Numbers already filed with add_phone.'),
            'confidence' => $schema->number()->min(0)->max(1)->description('How likely it is to work, 0..1. Default 0.5.'),
        ];
    }
}
