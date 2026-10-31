<?php

namespace App\Services;

use App\Enums\FlagStatus;
use App\Models\Flag;
use App\Models\Target;
use App\Models\TargetFlag;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * A target's progress against the flag catalogue, and what to hunt next.
 */
class FlagBoard
{
    public function __construct(private ActivityRecorder $activity) {}

    /**
     * Every flag with its status on this target and how many findings back it.
     *
     * @return Collection<int, array{id: string, category_id: string, category: string, question: string, points: int, osint_points: float, status: string, findings: int, bonus: bool, call_only: bool, related_flag_id: ?string, notes: ?string}>
     */
    public function board(Target $target, ?FlagStatus $status = null, ?string $categoryId = null): Collection
    {
        $statuses = $target->flagStatuses()->get()->keyBy('flag_id');
        $findingCounts = $target->findings()->whereNotNull('flag_id')
            ->selectRaw('flag_id, count(*) as aggregate')->groupBy('flag_id')->pluck('aggregate', 'flag_id');

        // Catalogue text follows the application locale (the dossier can be rendered in French).
        $fr = app()->getLocale() === 'fr';

        return Flag::query()
            ->when($categoryId, fn ($q) => $q->where('category_id', $categoryId))
            ->orderBy('sort')
            ->get()
            ->map(function (Flag $flag) use ($statuses, $findingCounts, $fr) {
                /** @var TargetFlag|null $row */
                $row = $statuses->get($flag->id);

                return [
                    'id' => $flag->id,
                    'category_id' => $flag->category_id,
                    'category' => $fr ? $flag->category_fr : $flag->category_en,
                    'question' => $fr ? $flag->question_fr : $flag->question_en,
                    'points' => $flag->points,
                    'osint_points' => $flag->osintPoints(),
                    'status' => ($row?->status ?? FlagStatus::Open)->value,
                    'findings' => (int) ($findingCounts[$flag->id] ?? 0),
                    'bonus' => $flag->bonus,
                    'call_only' => $flag->call_only,
                    'related_flag_id' => $flag->related_flag_id,
                    'notes' => $row?->notes,
                ];
            })
            ->when($status, fn (Collection $c) => $c->where('status', $status->value)->values());
    }

    /**
     * Banked vs available OSINT points, overall and per category.
     *
     * @return array{osint_points_banked: float, osint_points_available: float, flags_found: int, flags_partial: int, flags_open: int, by_category: array<int, array{category: string, banked: float, available: float, found: int, total: int}>}
     */
    public function summary(Target $target): array
    {
        $board = $this->board($target)->reject(fn (array $f) => $f['call_only']);

        $byCategory = $board->groupBy('category')->map(fn (Collection $flags, string $category) => [
            'category' => $category,
            'banked' => $flags->where('status', FlagStatus::Found->value)->sum('osint_points'),
            'available' => $flags->sum('osint_points'),
            'found' => $flags->where('status', FlagStatus::Found->value)->count(),
            'total' => $flags->count(),
        ])->values()->all();

        return [
            'osint_points_banked' => $board->where('status', FlagStatus::Found->value)->sum('osint_points'),
            'osint_points_available' => $board->sum('osint_points'),
            'flags_found' => $board->where('status', FlagStatus::Found->value)->count(),
            'flags_partial' => $board->where('status', FlagStatus::Partial->value)->count(),
            'flags_open' => $board->where('status', FlagStatus::Open->value)->count(),
            'by_category' => $byCategory,
        ];
    }

    /**
     * Unresolved flags ranked by points, partials first (they are closest to
     * banking), skipping call-only flags and duplicates of a flag already found.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function nextBest(Target $target, int $limit = 5): Collection
    {
        $board = $this->board($target);
        $found = $board->where('status', FlagStatus::Found->value)->pluck('id')->all();

        return $board
            ->reject(fn (array $f) => $f['call_only'])
            ->whereIn('status', [FlagStatus::Open->value, FlagStatus::Partial->value])
            ->reject(fn (array $f) => $f['related_flag_id'] !== null && in_array($f['related_flag_id'], $found, true))
            ->sortBy([
                fn (array $a, array $b) => ($b['status'] === 'partial') <=> ($a['status'] === 'partial'),
                fn (array $a, array $b) => $b['points'] <=> $a['points'],
                fn (array $a, array $b) => $a['id'] <=> $b['id'],
            ])
            ->take($limit)
            ->values();
    }

    public function setStatus(Target $target, User $user, string $flagId, FlagStatus $status, ?string $notes = null, string $via = 'mcp'): TargetFlag
    {
        $row = TargetFlag::query()->updateOrCreate(
            ['target_id' => $target->id, 'flag_id' => $flagId],
            array_filter(['status' => $status, 'notes' => $notes, 'updated_by' => $user->id], fn ($v) => $v !== null),
        );

        $this->activity->record($target, $user, 'flag.status', "Flag {$flagId} → {$status->value}", $row, $via);

        return $row;
    }

    /**
     * Raise a flag's status, never lower it (a later weak finding must not
     * undo a confirmed one).
     */
    public function promote(Target $target, User $user, string $flagId, FlagStatus $to, string $via = 'mcp'): ?TargetFlag
    {
        $rank = [FlagStatus::Open->value => 0, FlagStatus::Unverifiable->value => 0, FlagStatus::Partial->value => 1, FlagStatus::Found->value => 2];

        $current = TargetFlag::query()->where('target_id', $target->id)->where('flag_id', $flagId)->first();

        if ($current !== null && $rank[$current->status->value] >= $rank[$to->value]) {
            return null;
        }

        return $this->setStatus($target, $user, $flagId, $to, null, $via);
    }
}
