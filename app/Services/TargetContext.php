<?php

namespace App\Services;

use App\Enums\TargetRole;
use App\Exceptions\TargetContextException;
use App\Models\Flag;
use App\Models\Target;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Resolves and authorizes the target a user is acting on.
 *
 * The current target lives on the user row (server-side state), so no write
 * tool ever takes a target name: a slip cannot file a finding under another
 * contestant's company.
 */
class TargetContext
{
    public function __construct(private ActivityRecorder $activity) {}

    /**
     * The user's current target, authorized for reading or writing.
     *
     * @throws TargetContextException
     */
    public function forUser(User $user, bool $write = false): Target
    {
        if ($user->current_target_id === null) {
            throw TargetContextException::noneSelected();
        }

        $target = $this->accessible($user, $user->current_target_id);

        if ($write && ! ($target->roleFor($user)?->canWrite() ?? false)) {
            throw TargetContextException::readOnly($target->name);
        }

        return $target;
    }

    /**
     * @throws TargetContextException
     */
    public function accessible(User $user, int $targetId): Target
    {
        $target = Target::query()->accessibleBy($user)->find($targetId);

        if ($target === null) {
            throw TargetContextException::notAccessible($targetId);
        }

        return $target;
    }

    /**
     * @return Collection<int, Target>
     */
    public function listFor(User $user): Collection
    {
        return Target::query()->accessibleBy($user)->with('owner:id,name')->orderBy('name')->get();
    }

    /**
     * @throws TargetContextException
     */
    public function select(User $user, int $targetId): Target
    {
        $target = $this->accessible($user, $targetId);

        $user->forceFill(['current_target_id' => $target->id])->save();

        return $target;
    }

    /**
     * Create a target owned by the user, open every catalogue flag on it, and
     * make it their current target. Idempotent on the name: if the user
     * already owns a target called that, it is selected instead
     * (`wasRecentlyCreated` tells the two apart).
     *
     * @param  array{name: string, url?: ?string, domains?: ?array<int, string>, registry_id?: ?string, notes?: ?string}  $data
     */
    public function create(User $user, array $data, string $via = 'mcp'): Target
    {
        $existing = $user->ownedTargets()->whereRaw('lower(name) = ?', [mb_strtolower(trim($data['name']))])->first();

        if ($existing !== null) {
            return $this->select($user, $existing->id);
        }

        return DB::transaction(function () use ($user, $data, $via) {
            $target = Target::create([...$data, 'name' => trim($data['name']), 'owner_id' => $user->id]);

            $now = now();
            $target->flagStatuses()->insert(
                Flag::query()->pluck('id')->map(fn (string $flagId) => [
                    'target_id' => $target->id,
                    'flag_id' => $flagId,
                    'status' => 'open',
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all(),
            );

            $user->forceFill(['current_target_id' => $target->id])->save();

            $this->activity->record($target, $user, 'target.created', "Target {$target->name} created", $target, $via);

            return $target;
        });
    }

    /**
     * Give another user access to a target the owner holds.
     */
    public function share(Target $target, User $with, TargetRole $role = TargetRole::Viewer): void
    {
        $target->members()->syncWithoutDetaching([$with->id => ['role' => $role->value]]);
    }
}
