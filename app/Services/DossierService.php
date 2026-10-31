<?php

namespace App\Services;

use App\Models\Person;
use App\Models\PhoneNumber;
use App\Models\Pretext;
use App\Models\SearchLog;
use App\Models\Source;
use App\Models\Target;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection as BaseCollection;
use Illuminate\Support\Facades\DB;

/**
 * The parts of the report that are not flags: people, the phone numbers to
 * dial on stage, pretexts, and the search log.
 */
class DossierService
{
    public function __construct(
        private SourceService $sources,
        private ActivityRecorder $activity,
    ) {}

    /**
     * @param  array{key: string, name: string, title?: ?string, department?: ?string, tenure?: ?string, profile_url?: ?string, notes?: ?string, source_url: string}  $data
     * @return array{person: Person, created: bool}
     */
    public function upsertPerson(Target $target, User $user, array $data, string $via = 'mcp'): array
    {
        return DB::transaction(function () use ($target, $user, $data, $via) {
            $source = $this->sources->upsert($target, $user, ['url' => $data['source_url']]);

            $person = Person::query()->firstOrNew(['target_id' => $target->id, 'key' => $data['key']]);
            $created = ! $person->exists;

            $person->fill([
                ...array_filter(collect($data)->only(['name', 'title', 'department', 'tenure', 'profile_url', 'notes'])->all(), fn ($v) => $v !== null),
                'source_id' => $source->id,
            ]);
            if ($created) {
                $person->created_by = $user->id;
            }
            $person->mergeTranslation('fr', $data['fr'] ?? null);
            $person->save();

            $title = $person->title ? " ({$person->title})" : '';
            $this->activity->record($target, $user, $created ? 'person.added' : 'person.updated', "{$person->name}{$title}", $person, $via);

            return ['person' => $person, 'created' => $created];
        });
    }

    /**
     * @param  array{number: string, extension?: ?string, label: string, department?: ?string, person_key?: ?string, notes?: ?string, source_url: string}  $data
     * @return array{phone: PhoneNumber, created: bool}
     */
    public function upsertPhone(Target $target, User $user, array $data, string $via = 'mcp'): array
    {
        return DB::transaction(function () use ($target, $user, $data, $via) {
            $source = $this->sources->upsert($target, $user, ['url' => $data['source_url']]);

            $personId = empty($data['person_key']) ? null
                : $target->people()->where('key', $data['person_key'])->value('id');

            $phone = PhoneNumber::query()->firstOrNew([
                'target_id' => $target->id,
                'number' => self::normalisePhone($data['number']),
                'extension' => trim((string) ($data['extension'] ?? '')),
            ]);
            $created = ! $phone->exists;

            $phone->fill([
                'label' => $data['label'],
                'department' => $data['department'] ?? $phone->department,
                'person_id' => $personId ?? $phone->person_id,
                'notes' => $data['notes'] ?? $phone->notes,
                'kind' => $data['kind'] ?? $phone->kind,
                'source_id' => $source->id,
            ]);
            if ($created) {
                $phone->created_by = $user->id;
            }
            $phone->mergeTranslation('fr', $data['fr'] ?? null);
            $phone->save();

            $ext = $phone->extension !== '' ? " x{$phone->extension}" : '';
            $this->activity->record($target, $user, $created ? 'phone.added' : 'phone.updated', "{$phone->number}{$ext} — {$phone->label}", $phone, $via);

            return ['phone' => $phone, 'created' => $created];
        });
    }

    public function markRingVerified(Target $target, User $user, PhoneNumber $phone, string $via = 'web'): PhoneNumber
    {
        $phone->forceFill(['ring_verified_at' => now()])->save();

        $this->activity->record($target, $user, 'phone.verified', "{$phone->number} rings", $phone, $via);

        return $phone;
    }

    /**
     * @param  array{key: string, title: string, persona: string, script?: ?string, flag_ids?: ?array<int, string>, phone_numbers?: ?array<int, string>, confidence?: ?float}  $data
     * @return array{pretext: Pretext, created: bool, phone_numbers: array<int, string>, unmatched_phone_numbers: array<int, string>}
     */
    public function upsertPretext(Target $target, User $user, array $data, string $via = 'mcp'): array
    {
        $numbers = collect($data['phone_numbers'] ?? [])->map(fn (string $n) => self::normalisePhone($n))->unique();
        $known = $target->phoneNumbers()->whereIn('number', $numbers->all())->get(['id', 'number']);
        $phoneIds = $known->pluck('id')->all();
        $unmatched = $numbers->diff($known->pluck('number'))->values()->all();

        $pretext = Pretext::query()->firstOrNew(['target_id' => $target->id, 'key' => $data['key']]);
        $created = ! $pretext->exists;

        $pretext->fill([
            'title' => $data['title'],
            'persona' => $data['persona'],
            'script' => $data['script'] ?? $pretext->script,
            'flag_ids' => $data['flag_ids'] ?? $pretext->flag_ids,
            'pivots' => isset($data['pivots']) ? self::cleanPivots($data['pivots']) : $pretext->pivots,
            'phone_number_ids' => $phoneIds ?: $pretext->phone_number_ids,
            'confidence' => $data['confidence'] ?? $pretext->confidence ?? 0.5,
        ]);
        if ($created) {
            $pretext->created_by = $user->id;
        }

        $fr = $data['fr'] ?? null;
        if (is_array($fr) && isset($fr['pivots']) && is_array($fr['pivots'])) {
            // French bridges are keyed by flag id: {"03-09": "..."}.
            $fr['pivots'] = collect($fr['pivots'])->filter(fn ($b) => is_string($b) && $b !== '')->all();
        }
        $pretext->mergeTranslation('fr', $fr);
        $pretext->save();

        $this->activity->record($target, $user, $created ? 'pretext.added' : 'pretext.updated', $pretext->title, $pretext, $via);

        return [
            'pretext' => $pretext,
            'created' => $created,
            'phone_numbers' => $target->phoneNumbers()->whereIn('id', $pretext->phone_number_ids ?? [])->pluck('number')->all(),
            'unmatched_phone_numbers' => $unmatched,
        ];
    }

    /**
     * One entry per flag, the first bridge wins.
     *
     * @param  array<int, array{flag_id: string, bridge: string}>  $pivots
     * @return array<int, array{flag_id: string, bridge: string}>
     */
    public static function cleanPivots(array $pivots): array
    {
        return collect($pivots)
            ->filter(fn ($p) => ! empty($p['flag_id']) && ! empty($p['bridge']))
            ->unique('flag_id')
            ->map(fn ($p) => ['flag_id' => $p['flag_id'], 'bridge' => $p['bridge']])
            ->values()
            ->all();
    }

    /**
     * @param  array{channel: string, query: string, flag_id?: ?string, result_count?: ?int, outcome?: ?string, notes?: ?string}  $data
     */
    public function logSearch(Target $target, User $user, array $data): SearchLog
    {
        // A retry of the same search shortly after is the same search.
        $recent = $target->searchLogs()
            ->where('user_id', $user->id)
            ->where('channel', $data['channel'])
            ->where('query', $data['query'])
            ->where('created_at', '>=', now()->subMinutes(10))
            ->latest('id')
            ->first();

        if ($recent !== null) {
            $recent->fill(array_filter(collect($data)->only(['flag_id', 'result_count', 'outcome', 'notes'])->all(), fn ($v) => $v !== null))->save();

            return $recent;
        }

        // Not an Activity: searches are frequent and would drown the feed.
        return SearchLog::create([...$data, 'target_id' => $target->id, 'user_id' => $user->id]);
    }

    /**
     * @return Collection<int, SearchLog>
     */
    public function searches(Target $target, ?string $contains = null, ?string $flagId = null, int $limit = 50): Collection
    {
        return $target->searchLogs()
            ->with('user:id,name')
            ->when($contains, fn ($q) => $q->where(fn ($w) => $w->whereLike('query', "%{$contains}%")->orWhereLike('notes', "%{$contains}%")))
            ->when($flagId, fn ($q) => $q->where('flag_id', $flagId))
            ->latest('id')
            ->limit($limit)
            ->get();
    }

    /**
     * @return Collection<int, Person>
     */
    public function people(Target $target): Collection
    {
        return $target->people()
            ->with(['source:id,url,title,retrieved_at', 'phoneNumbers:id,person_id'])
            ->orderBy('name')
            ->get();
    }

    /**
     * @return Collection<int, PhoneNumber>
     */
    public function phones(Target $target): Collection
    {
        return $target->phoneNumbers()
            ->with(['source:id,url,title,retrieved_at', 'person:id,key,name'])
            ->orderBy('label')
            ->orderBy('number')
            ->get();
    }

    /**
     * A phone on this target, or a 404.
     *
     * @throws ModelNotFoundException<PhoneNumber>
     */
    public function phone(Target $target, int $phoneId): PhoneNumber
    {
        return $target->phoneNumbers()->findOrFail($phoneId);
    }

    /**
     * Pretexts, most recently updated first, each with the phone numbers it
     * dials resolved (one query for all of them).
     *
     * @return array<int, array<string, mixed>>
     */
    public function pretexts(Target $target): array
    {
        $pretexts = $target->pretexts()->with('creator:id,name')->latest('updated_at')->latest('id')->get();

        $phones = $target->phoneNumbers()
            ->whereIn('id', $pretexts->pluck('phone_number_ids')->flatten()->filter()->unique()->values()->all())
            ->get(['id', 'number', 'extension', 'label'])
            ->keyBy('id');

        return $pretexts->map(fn (Pretext $pretext) => self::presentPretext($pretext, $phones))->all();
    }

    /**
     * @return array<string, mixed>
     */
    public static function presentPerson(Person $person): array
    {
        return [
            'id' => $person->id,
            'key' => $person->key,
            'name' => $person->name,
            'title' => $person->title,
            'department' => $person->department,
            'tenure' => $person->tenure,
            'profile_url' => $person->profile_url,
            'notes' => $person->notes,
            'source' => self::presentSource($person->source),
            'phone_ids' => $person->phoneNumbers->pluck('id')->all(),
            'updated_at' => $person->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function presentPhone(PhoneNumber $phone): array
    {
        return [
            'id' => $phone->id,
            'number' => $phone->number,
            'extension' => $phone->extension,
            'label' => $phone->label,
            'department' => $phone->department,
            'person' => $phone->person === null ? null : [
                'id' => $phone->person->id,
                'key' => $phone->person->key,
                'name' => $phone->person->name,
            ],
            'kind' => $phone->kind,
            'ring_verified_at' => $phone->ring_verified_at?->toIso8601String(),
            'notes' => $phone->notes,
            'source' => self::presentSource($phone->source),
            'updated_at' => $phone->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @param  BaseCollection<int, PhoneNumber>  $phonesById
     * @return array<string, mixed>
     */
    public static function presentPretext(Pretext $pretext, BaseCollection $phonesById): array
    {
        return [
            'id' => $pretext->id,
            'key' => $pretext->key,
            'title' => $pretext->translated('title'),
            'persona' => $pretext->translated('persona'),
            'script' => $pretext->translated('script'),
            'untranslated' => ! $pretext->isTranslated(['title', 'persona', 'script']),
            'flag_ids' => array_values($pretext->flag_ids ?? []),
            'pivots' => collect($pretext->pivots ?? [])->map(fn (array $p) => [
                'flag_id' => $p['flag_id'],
                'bridge' => app()->getLocale() !== 'en' ? ($pretext->translations[app()->getLocale()]['pivots'][$p['flag_id']] ?? $p['bridge']) : $p['bridge'],
            ])->values()->all(),
            'phone_numbers' => collect($pretext->phone_number_ids ?? [])
                ->map(fn ($id) => $phonesById->get($id))
                ->filter()
                ->map(fn (PhoneNumber $p) => ['id' => $p->id, 'number' => $p->number, 'extension' => $p->extension, 'label' => $p->label])
                ->values()
                ->all(),
            'confidence' => (float) $pretext->confidence,
            'by' => $pretext->creator?->name,
            'updated_at' => $pretext->updated_at->toIso8601String(),
        ];
    }

    /**
     * @return array{url: string, title: ?string, retrieved_at: string}|null
     */
    public static function presentSource(?Source $source): ?array
    {
        return $source === null ? null : [
            'url' => $source->url,
            'title' => $source->title,
            'retrieved_at' => $source->retrieved_at->toIso8601String(),
        ];
    }

    /**
     * Keep digits and a leading +; assume North America for 10-digit numbers.
     */
    public static function normalisePhone(string $number): string
    {
        $digits = preg_replace('/\D+/', '', $number) ?? '';

        if (strlen($digits) === 10) {
            $digits = '1'.$digits;
        }

        return '+'.$digits;
    }
}
