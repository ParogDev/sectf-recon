<?php

namespace App\Mcp\Tools;

use App\Services\FindingService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;

#[Title('Record finding')]
#[IsIdempotent]
#[IsDestructive(false)]
#[IsOpenWorld(false)]
#[Description('File one fact about the current target, with the source(s) that prove it. A flag only scores if the judges can open the cited source, so give at least one URL you actually read, plus the content date and its basis when the page shows one (post date, article byline, "last updated"). Put the exact supporting sentence in `quote`. Link it to a flag with flag_id; confidence >= 0.7 marks the flag found, lower marks it partial (a flag is never downgraded). The same key again updates the finding and adds any new sources, so retries never duplicate. Add `fr` (French claim and detail) so the French dossier reads in French. Facts about people: job-relevant only.')]
class RecordFindingTool extends SectfTool
{
    protected string $name = 'record_finding';

    public function __construct(private FindingService $findings) {}

    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'key' => self::KEY_RULE,
            'flag_id' => ['nullable', 'string', 'exists:flags,id'],
            'claim' => ['required', 'string', 'max:2000'],
            'detail' => ['nullable', 'string', 'max:10000'],
            'answers_flag' => ['nullable', 'string', 'max:1000'],
            'confidence' => ['nullable', 'numeric', 'between:0,1'],
            'content_date' => ['nullable', 'date_format:Y-m-d'],
            'fr' => ['nullable', 'array'],
            'fr.claim' => ['nullable', 'string', 'max:2000'],
            'fr.detail' => ['nullable', 'string', 'max:10000'],
            'sources' => ['required', 'array', 'min:1', 'max:10'],
            'sources.*.url' => ['required', 'url:http,https', 'max:2048'],
            'sources.*.title' => ['nullable', 'string', 'max:255'],
            'sources.*.content_date' => ['nullable', 'date_format:Y-m-d'],
            'sources.*.content_date_basis' => ['nullable', 'string', 'max:255'],
            'sources.*.quote' => ['nullable', 'string', 'max:2000'],
        ], [
            'sources.required' => 'Every finding needs at least one source URL the judges can open. Add sources: [{url: "https://..."}].',
            'flag_id.exists' => 'Unknown flag_id. Call list_flags for the ids (format "04-13").',
        ]);

        if (($validated['flag_id'] ?? null) !== null
            && (float) ($validated['confidence'] ?? 0) >= FindingService::FOUND_CONFIDENCE
            && blank($validated['answers_flag'] ?? null)) {
            return Response::error('This confidence marks the flag found, so say how the source answers the flag\'s question in answers_flag. If you cannot, lower the confidence below 0.7 or drop flag_id.');
        }

        $target = $this->currentTarget($request, write: true);
        $result = $this->findings->record($target, $this->user($request), $validated);

        return $this->payload($target, [
            'created' => $result['created'],
            'flag_status' => $result['flag_status'],
            'finding' => FindingService::present($result['finding']->load('creator:id,name')),
        ]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'key' => $schema->string()->max(191)->pattern('^[a-z0-9][a-z0-9-]*$')->required()->description(self::KEY_DESCRIPTION.' e.g. "email-provider-m365".'),
            'flag_id' => $schema->string()->description('Flag this answers, e.g. "05-03". Omit for context that answers no flag.'),
            'claim' => $schema->string()->max(2000)->required()->description('The fact, one or two sentences, as it should read in the report.'),
            'detail' => $schema->string()->max(10000)->description('How it was found and any caveats.'),
            'answers_flag' => $schema->string()->max(1000)->description('One sentence: how the source answers this flag\'s question. Required with flag_id at confidence >= 0.7.'),
            'confidence' => $schema->number()->min(0)->max(1)->description('0..1. Default 0.5. >= 0.7 marks the flag found.'),
            'content_date' => $schema->string()->format('date')->description('YYYY-MM-DD the fact was true / published, if known.'),
            'fr' => $schema->object([
                'claim' => $schema->string(),
                'detail' => $schema->string(),
            ])->description('French version of claim and detail, so the French dossier reads in French. Quotes stay in the source language.'),
            'sources' => $schema->array()->min(1)->max(10)->required()->items($schema->object([
                'url' => $schema->string()->max(2048)->required(),
                'title' => $schema->string()->max(255),
                'content_date' => $schema->string()->format('date')->description('YYYY-MM-DD shown on the page.'),
                'content_date_basis' => $schema->string()->max(255)->description('Where that date came from, e.g. "post timestamp".'),
                'quote' => $schema->string()->max(2000)->description('Exact text on the page that supports the claim.'),
            ]))->description('At least one public URL you read.'),
        ];
    }
}
