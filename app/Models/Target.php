<?php

namespace App\Models;

use App\Enums\TargetRole;
use App\Models\Concerns\HasTranslations;
use Database\Factories\TargetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $owner_id
 * @property string $name
 * @property string|null $url
 * @property array<int, string>|null $domains
 * @property string|null $registry_id
 * @property string|null $notes
 */
#[Fillable(['owner_id', 'name', 'url', 'domains', 'registry_id', 'notes'])]
class Target extends Model
{
    /** @use HasFactory<TargetFactory> */
    use HasFactory;

    use HasTranslations;

    protected function casts(): array
    {
        return ['domains' => 'array'];
    }

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /** @return BelongsToMany<User, $this> */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot('role')->withTimestamps();
    }

    /** @return HasMany<TargetFlag, $this> */
    public function flagStatuses(): HasMany
    {
        return $this->hasMany(TargetFlag::class);
    }

    /** @return HasMany<Finding, $this> */
    public function findings(): HasMany
    {
        return $this->hasMany(Finding::class);
    }

    /** @return HasMany<Source, $this> */
    public function sources(): HasMany
    {
        return $this->hasMany(Source::class);
    }

    /** @return HasMany<Person, $this> */
    public function people(): HasMany
    {
        return $this->hasMany(Person::class);
    }

    /** @return HasMany<PhoneNumber, $this> */
    public function phoneNumbers(): HasMany
    {
        return $this->hasMany(PhoneNumber::class);
    }

    /** @return HasMany<Pretext, $this> */
    public function pretexts(): HasMany
    {
        return $this->hasMany(Pretext::class);
    }

    /** @return HasMany<SearchLog, $this> */
    public function searchLogs(): HasMany
    {
        return $this->hasMany(SearchLog::class);
    }

    /** @return HasMany<Activity, $this> */
    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class);
    }

    /**
     * Targets the user owns or has been given access to.
     *
     * @param  Builder<Target>  $query
     */
    public function scopeAccessibleBy(Builder $query, User $user): void
    {
        $query->where(fn (Builder $q) => $q
            ->where('owner_id', $user->id)
            ->orWhereHas('members', fn (Builder $m) => $m->where('users.id', $user->id)));
    }

    public function roleFor(User $user): ?TargetRole
    {
        if ($this->owner_id === $user->id) {
            return TargetRole::Owner;
        }

        $role = $this->members()->where('users.id', $user->id)->value('target_user.role');

        return $role === null ? null : TargetRole::from($role);
    }
}
