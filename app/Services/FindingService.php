<?php

namespace App\Services;

use App\Enums\FlagStatus;
use App\Models\Finding;
use App\Models\Target;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class FindingService
{
    /** A finding at or above this confidence marks its flag found; below, partial. */
    public const FOUND_CONFIDENCE = 0.7;

    public function __construct(
        private SourceService $sources,
        private FlagBoard $flags,
        private ActivityRecorder $activity,
    ) {}

    /**
     * Record (or update) a finding by its key, attach its sources, and raise
     * its flag's status. Idempotent: the same key twice is one finding.
     *
     * @param  array{key: string, flag_id?: ?string, claim: string, detail?: ?string, confidence?: ?float, content_date?: ?string, sources: array<int, array{url: string, title?: ?string, content_date?: ?string, content_date_basis?: ?string, quote?: ?string}>}  $data
     * @return array{finding: Finding, created: bool, flag_status: ?string}
     */
    public function record(Target $target, User $user, array $data, string $via = 'mcp'): array
    {
        return DB::transaction(function () use ($target, $user, $data, $via) {
            $finding = Finding::query()->firstOrNew(['target_id' => $target->id, 'key' => $data['key']]);
            $created = ! $finding->exists;

            $finding->fill([
                'flag_id' => $data['flag_id'] ?? $finding->flag_id,
                'claim' => $data['claim'],
                'detail' => $data['detail'] ?? $finding->detail,
                'answers_flag' => $data['answers_flag'] ?? $finding->answers_flag,
                'confidence' => $data['confidence'] ?? $finding->confidence ?? 0.5,
                'content_date' => $data['content_date'] ?? $finding->content_date,
                'updated_by' => $user->id,
            ]);

            if ($created) {
                $finding->created_by = $user->id;
            }

            $finding->mergeTranslation('fr', $data['fr'] ?? null);
            $finding->save();

            $attach = [];
            foreach ($data['sources'] as $sourceData) {
                $source = $this->sources->upsert($target, $user, $sourceData);
                $attach[$source->id] = ['quote' => $sourceData['quote'] ?? null];
            }
            $finding->sources()->syncWithoutDetaching($attach);

            $flagStatus = null;
            if ($finding->flag_id !== null) {
                $to = (float) $finding->confidence >= self::FOUND_CONFIDENCE ? FlagStatus::Found : FlagStatus::Partial;
                $flagStatus = $this->flags->promote($target, $user, $finding->flag_id, $to, $via)?->status->value;
            }

            $verb = $created ? 'recorded' : 'updated';
            $flagLabel = $finding->flag_id === null ? '' : "[{$finding->flag_id}] ";
            $this->activity->record($target, $user, "finding.{$verb}", "{$flagLabel}{$finding->claim}", $finding, $via);

            return ['finding' => $finding->load('sources'), 'created' => $created, 'flag_status' => $flagStatus];
        });
    }

    /**
     * @return Collection<int, Finding>
     */
    public function list(Target $target, ?string $flagId = null, ?string $search = null, int $limit = 50): Collection
    {
        return $target->findings()
            ->with(['sources', 'creator:id,name'])
            ->when($flagId, fn ($q) => $q->where('flag_id', $flagId))
            ->when($search, fn ($q) => $q->where(fn ($w) => $w
                ->whereLike('claim', "%{$search}%")
                ->orWhereLike('detail', "%{$search}%")
                ->orWhereLike('key', "%{$search}%")))
            ->latest('updated_at')
            ->limit($limit)
            ->get();
    }

    /**
     * @return array<string, mixed>
     */
    public static function present(Finding $finding): array
    {
        return [
            'id' => $finding->id,
            'key' => $finding->key,
            'flag_id' => $finding->flag_id,
            'claim' => $finding->claim,
            'detail' => $finding->detail,
            'answers_flag' => $finding->answers_flag,
            'confidence' => (float) $finding->confidence,
            'content_date' => $finding->content_date?->toDateString(),
            'by' => $finding->creator?->name,
            'created_at' => $finding->created_at->toIso8601String(),
            'updated_at' => $finding->updated_at->toIso8601String(),
            'sources' => $finding->sources->map(fn ($s) => [
                'url' => $s->url,
                'title' => $s->title,
                'retrieved_at' => $s->retrieved_at->toIso8601String(),
                'content_date' => $s->content_date?->toDateString(),
                'quote' => $s->quote(),
            ])->all(),
        ];
    }
}
