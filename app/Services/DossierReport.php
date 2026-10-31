<?php

namespace App\Services;

use App\Enums\FlagStatus;
use App\Enums\PhoneKind;
use App\Models\Finding;
use App\Models\Flag;
use App\Models\Person;
use App\Models\PhoneNumber;
use App\Models\Source;
use App\Models\Target;
use Illuminate\Support\Collection;

/**
 * Assembles the Phase 1 dossier for a target: everything the written report
 * needs, in document order, with every source numbered once so findings,
 * phones and people can cite it as [n] and the evidence appendix lists it.
 */
class DossierReport
{
    /** A dossier needs this many call plans, and numbers of at least this many kinds. */
    public const MIN_PRETEXTS = 5;

    public const MIN_PHONE_KINDS = 3;

    public function __construct(
        private FlagBoard $flags,
        private DossierService $dossier,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(Target $target): array
    {
        $target->loadMissing('owner:id,name');

        $board = $this->flags->board($target);
        $summary = $this->flags->summary($target);

        // Document order: flagged findings in board order (strongest first), then general findings.
        $order = $board->pluck('id')->flip();
        $findings = $target->findings()
            ->with(['sources', 'creator:id,name'])
            ->get()
            ->sortBy([
                fn (Finding $a, Finding $b) => ($a->flag_id === null) <=> ($b->flag_id === null),
                fn (Finding $a, Finding $b) => ($order[$a->flag_id] ?? 0) <=> ($order[$b->flag_id] ?? 0),
                fn (Finding $a, Finding $b) => (float) $b->confidence <=> (float) $a->confidence,
                fn (Finding $a, Finding $b) => $a->id <=> $b->id,
            ])
            ->values();

        // Phones are grouped by department and given a short reference (A-1, D-12) that the
        // pretexts and the call sheet use, so a number can be named out loud in the booth.
        $phones = $target->phoneNumbers()->with(['source', 'person:id,key,name'])->get()
            ->sortBy([
                fn (PhoneNumber $a, PhoneNumber $b) => ($a->department === null) <=> ($b->department === null),
                fn (PhoneNumber $a, PhoneNumber $b) => strcasecmp($a->department ?? '', $b->department ?? ''),
                fn (PhoneNumber $a, PhoneNumber $b) => strcasecmp($a->label ?? '', $b->label ?? ''),
                fn (PhoneNumber $a, PhoneNumber $b) => strcmp($a->number, $b->number),
            ])
            ->values();
        $phoneRefs = $this->phoneRefs($phones);
        $people = $target->people()->with(['source', 'phoneNumbers:id,person_id'])->orderBy('name')->get();

        $pretexts = [];
        $pretextRefsByPhone = [];
        foreach ($this->dossier->pretexts($target) as $i => $pretext) {
            $pretext['ref'] = 'P'.($i + 1);
            $pretext['phone_numbers'] = array_map(fn (array $phone) => [
                ...$phone,
                'ref' => $phoneRefs[$phone['id']]['ref'] ?? null,
            ], $pretext['phone_numbers']);
            foreach ($pretext['phone_numbers'] as $phone) {
                $pretextRefsByPhone[$phone['id']][] = $pretext['ref'];
            }
            $pretexts[] = $pretext;
        }

        $citations = $this->numberSources($findings, $phones, $people);

        $boardById = $board->keyBy('id');
        $flagInfo = fn (string $id) => [
            'id' => $id,
            'question' => $boardById[$id]['question'] ?? null,
            'points' => $boardById[$id]['osint_points'] ?? null,
            'status' => $boardById[$id]['status'] ?? null,
        ];
        $presentedPretexts = array_map(fn (array $pretext) => [
            ...$pretext,
            'flags' => array_map(fn (string $id) => $flagInfo($id), $pretext['flag_ids']),
            'pivot_flags' => array_map(fn (array $pivot) => [...$flagInfo($pivot['flag_id']), 'bridge' => $pivot['bridge']], $pretext['pivots']),
        ], $pretexts);
        $coverage = $this->coverage($presentedPretexts, $board);

        $findingsByFlag = $findings->groupBy(fn (Finding $f) => $f->flag_id ?? '');
        $locale = app()->getLocale();
        $questions = Flag::query()->pluck($locale === 'fr' ? 'question_fr' : 'question_en', 'id');

        $categories = $board
            ->groupBy('category_id')
            ->map(function (Collection $flags) use ($findingsByFlag, $citations) {
                $first = $flags->first();
                $scored = $flags->reject(fn (array $f) => $f['call_only']);

                return [
                    'id' => $first['category_id'],
                    'name' => $first['category'],
                    'banked' => $scored->where('status', FlagStatus::Found->value)->sum('osint_points'),
                    'available' => $scored->sum('osint_points'),
                    'flags' => $flags->map(fn (array $flag) => [
                        ...$flag,
                        'findings' => $findingsByFlag->get($flag['id'], collect())
                            ->map(fn (Finding $f) => $this->presentFinding($f, $citations))
                            ->values()
                            ->all(),
                    ])->values()->all(),
                ];
            })
            ->values()
            ->all();

        $general = $findingsByFlag->get('', collect())
            ->map(fn (Finding $f) => $this->presentFinding($f, $citations))
            ->values()
            ->all();

        $readiness = $this->readiness($target, $findings, $phones, $people, $presentedPretexts, $board);

        $searches = $target->searchLogs()->get(['channel', 'created_at']);
        $contributors = $findings->pluck('creator.name')->filter()->unique()->values()->all();

        return [
            'target_id' => $target->id,
            'locale' => $locale,
            'generated_at' => now()->toIso8601String(),
            'event' => ['name' => 'HackFest 2026 SECTF', 'phase' => __('dossier.phase')],
            'target' => [
                'id' => $target->id,
                'name' => $target->name,
                'url' => $target->url,
                'domains' => $target->domains ?? [],
                'registry_id' => $target->registry_id,
                'notes' => $target->translated('notes'),
                'owner' => $target->owner?->name,
            ],
            'summary' => [
                ...$summary,
                'findings' => $findings->count(),
                'sources' => count($citations),
                'phones' => $phones->count(),
                'phones_ring_verified' => $phones->whereNotNull('ring_verified_at')->count(),
                'people' => $people->count(),
                'pretexts' => count($pretexts),
                'searches' => $searches->count(),
            ],
            'categories' => $categories,
            'general_findings' => $general,
            'phones' => $presentedPhones = $phones->map(fn (PhoneNumber $p) => [
                ...DossierService::presentPhone($p),
                'label' => $p->translated('label'),
                'department' => $p->translated('department'),
                'notes' => $p->translated('notes'),
                'untranslated' => ! $p->isTranslated(['label', 'department', 'notes']),
                ...$phoneRefs[$p->id],
                'pretexts' => $pretextRefsByPhone[$p->id] ?? [],
                'citation' => $this->citationFor($p->source, $citations),
            ])->values()->all(),
            'phone_groups' => collect($presentedPhones)
                ->groupBy('group')
                ->map(fn (Collection $group) => [
                    'letter' => $group->first()['group'],
                    'name' => $group->first()['group_name'],
                    'phones' => $group->values()->all(),
                ])
                ->values()
                ->all(),
            'people' => $people->map(fn ($p) => [
                ...DossierService::presentPerson($p),
                'title' => $p->translated('title'),
                'department' => $p->translated('department'),
                'notes' => $p->translated('notes'),
                'untranslated' => ! $p->isTranslated(['title', 'department', 'notes']),
                'citation' => $this->citationFor($p->source, $citations),
            ])->values()->all(),
            'pretexts' => $presentedPretexts,
            'pretext_coverage' => $coverage,
            'readiness' => $readiness,
            'methodology' => [
                'contributors' => $contributors,
                'searches' => $searches->count(),
                'searches_by_channel' => $searches->countBy('channel')->sortDesc()->all(),
                'first_search_at' => $searches->min('created_at')?->toIso8601String(),
                'last_search_at' => $searches->max('created_at')?->toIso8601String(),
                'first_finding_at' => $findings->min('created_at')?->toIso8601String(),
                'last_finding_at' => $findings->max('updated_at')?->toIso8601String(),
            ],
            'sources' => array_values($citations),
        ];
    }

    /**
     * Give every phone a reference such as A-1 or D-12: one letter per department
     * (in the order the phones are already sorted), numbered within the group.
     *
     * @param  Collection<int, PhoneNumber>  $phones
     * @return array<int, array{ref: string, group: string, group_name: string}> keyed by phone id
     */
    private function phoneRefs(Collection $phones): array
    {
        $refs = [];
        $letters = [];

        foreach ($phones as $phone) {
            $name = $phone->translated('department') ?: __('dossier.phones.other_group');

            if (! isset($letters[$name])) {
                $n = count($letters);
                $letters[$name] = ['letter' => $n < 26 ? chr(65 + $n) : 'Z'.($n - 25), 'count' => 0];
            }

            $letters[$name]['count']++;

            $refs[$phone->id] = [
                'ref' => $letters[$name]['letter'].'-'.$letters[$name]['count'],
                'group' => $letters[$name]['letter'],
                'group_name' => $name,
            ];
        }

        return $refs;
    }

    /**
     * Number every cited source once, in the order the report first uses it:
     * findings (by flag order), then phones, then people.
     *
     * @param  Collection<int, Finding>  $findings
     * @param  Collection<int, PhoneNumber>  $phones
     * @param  Collection<int, Person>  $people
     * @return array<int, array{n: int, id: int, url: string, title: ?string, retrieved_at: string, content_date: ?string, content_date_basis: ?string, cited_by: array<int, string>}> keyed by source id
     */
    private function numberSources(Collection $findings, Collection $phones, Collection $people): array
    {
        $citations = [];

        $add = function (?Source $source, string $citedBy) use (&$citations): void {
            if ($source === null) {
                return;
            }

            if (! isset($citations[$source->id])) {
                $citations[$source->id] = [
                    'n' => count($citations) + 1,
                    'id' => $source->id,
                    'url' => $source->url,
                    'title' => $source->title,
                    'retrieved_at' => $source->retrieved_at->toIso8601String(),
                    'content_date' => $source->content_date?->toDateString(),
                    'content_date_basis' => $source->content_date_basis,
                    'cited_by' => [],
                ];
            }

            if (! in_array($citedBy, $citations[$source->id]['cited_by'], true)) {
                $citations[$source->id]['cited_by'][] = $citedBy;
            }
        };

        foreach ($findings as $finding) {
            foreach ($finding->sources as $source) {
                $add($source, $finding->flag_id === null ? "finding {$finding->key}" : "flag {$finding->flag_id}");
            }
        }

        foreach ($phones as $phone) {
            $add($phone->source, "phone {$phone->number}");
        }

        foreach ($people as $person) {
            $add($person->source, "person {$person->name}");
        }

        return $citations;
    }

    /**
     * @param  array<int, array<string, mixed>>  $citations
     * @return array<string, mixed>
     */
    private function presentFinding(Finding $finding, array $citations): array
    {
        $presented = FindingService::present($finding);
        $presented['claim'] = $finding->translated('claim');
        $presented['detail'] = $finding->translated('detail');
        $presented['untranslated'] = ! $finding->isTranslated(['claim', 'detail']);

        $presented['sources'] = $finding->sources->map(fn (Source $s) => [
            'n' => $citations[$s->id]['n'] ?? null,
            'url' => $s->url,
            'title' => $s->title,
            'retrieved_at' => $s->retrieved_at->toIso8601String(),
            'content_date' => $s->content_date?->toDateString(),
            'quote' => $s->quote(),
        ])->values()->all();

        return $presented;
    }

    /**
     * @param  array<int, array<string, mixed>>  $citations
     */
    private function citationFor(?Source $source, array $citations): ?int
    {
        return $source === null ? null : ($citations[$source->id]['n'] ?? null);
    }

    /**
     * Which pretext can reach which flag (as a target or a natural pivot), and the
     * unresolved flags no pretext touches.
     *
     * @param  array<int, array<string, mixed>>  $pretexts
     * @return array{by_flag: array<string, array<int, array{ref: string, role: string}>>, uncovered: array<int, array<string, mixed>>}
     */
    private function coverage(array $pretexts, Collection $board): array
    {
        $byFlag = [];

        foreach ($pretexts as $pretext) {
            foreach ($pretext['flag_ids'] as $id) {
                $byFlag[$id][] = ['ref' => $pretext['ref'], 'role' => 'primary'];
            }
            foreach ($pretext['pivots'] as $pivot) {
                if (! in_array($pivot['flag_id'], $pretext['flag_ids'], true)) {
                    $byFlag[$pivot['flag_id']][] = ['ref' => $pretext['ref'], 'role' => 'pivot'];
                }
            }
        }

        $uncovered = $board
            ->reject(fn (array $f) => isset($byFlag[$f['id']]))
            ->where('status', '!=', FlagStatus::Found->value)
            ->map(fn (array $f) => ['id' => $f['id'], 'question' => $f['question'], 'points' => $f['points'], 'status' => $f['status']])
            ->values()
            ->all();

        return ['by_flag' => $byFlag, 'uncovered' => $uncovered];
    }

    /**
     * What is still thin in the dossier. `gaps` are codes plus numbers; the view
     * turns them into sentences in the document language.
     *
     * @param  Collection<int, Finding>  $findings
     * @param  Collection<int, PhoneNumber>  $phones
     * @param  Collection<int, Person>  $people
     * @param  array<int, array<string, mixed>>  $pretexts
     * @return array<string, mixed>
     */
    private function readiness(Target $target, Collection $findings, Collection $phones, Collection $people, array $pretexts, Collection $board): array
    {
        // A flag still needs a person when its latest search was blocked or failed and nothing has since answered it.
        $answered = $board->where('status', FlagStatus::Found->value)->pluck('id')->all();
        $incomplete = $target->searchLogs()->whereNotNull('flag_id')->orderBy('id')->get(['flag_id', 'outcome'])
            ->keyBy('flag_id')
            ->reject(fn ($log) => $log->outcome === 'completed' || in_array($log->flag_id, $answered, true))
            ->keys()->sort()->values()->all();

        $need = self::MIN_PRETEXTS;
        $kinds = $phones->pluck('kind')->filter()->countBy()->all();
        $untranslated = [
            'findings' => $findings->reject(fn (Finding $f) => $f->isTranslated(['claim', 'detail'], 'fr'))->count(),
            'phones' => $phones->reject(fn (PhoneNumber $p) => $p->isTranslated(['label', 'department', 'notes'], 'fr'))->count(),
            'people' => $people->reject(fn ($p) => $p->isTranslated(['title', 'department', 'notes'], 'fr'))->count(),
            'pretexts' => $target->pretexts()->get()->reject(fn ($p) => $p->isTranslated(['title', 'persona', 'script'], 'fr'))->count(),
            'target' => $target->isTranslated(['notes'], 'fr') ? 0 : 1,
        ];
        $untranslatedTotal = array_sum($untranslated);
        $noPivots = collect($pretexts)->filter(fn (array $p) => $p['pivots'] === [])->pluck('ref')->values()->all();
        $onlySmallStores = ($kinds[PhoneKind::SmallFormatStore->value] ?? 0) > 0 && ($kinds[PhoneKind::Store->value] ?? 0) === 0;

        $gaps = [];
        if (count($pretexts) < $need) {
            $gaps[] = ['code' => 'pretexts_min', 'have' => count($pretexts), 'need' => $need];
        }
        if ($noPivots !== []) {
            $gaps[] = ['code' => 'pretexts_no_pivots', 'refs' => $noPivots];
        }
        if (count($kinds) < self::MIN_PHONE_KINDS) {
            $gaps[] = ['code' => 'phone_kinds', 'have' => count($kinds), 'need' => self::MIN_PHONE_KINDS];
        }
        if ($onlySmallStores) {
            $gaps[] = ['code' => 'small_format_only'];
        }
        if ($untranslatedTotal > 0) {
            $gaps[] = ['code' => 'fr_missing', 'count' => $untranslatedTotal];
        }
        if ($incomplete !== []) {
            $gaps[] = ['code' => 'research_incomplete', 'flags' => $incomplete];
        }

        return [
            'ok' => $gaps === [],
            'pretexts' => ['have' => count($pretexts), 'need' => $need],
            'phone_kinds' => ['have' => $kinds, 'distinct' => count($kinds), 'need' => self::MIN_PHONE_KINDS],
            'french_missing' => ['total' => $untranslatedTotal, ...$untranslated],
            'gaps' => $gaps,
        ];
    }
}
