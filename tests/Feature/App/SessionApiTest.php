<?php

namespace Tests\Feature\App;

use App\Enums\TargetRole;
use App\Models\Target;
use App\Models\User;
use App\Services\TargetContext;

class SessionApiTest extends AppApiTestCase
{
    public function test_a_user_without_a_target_gets_an_empty_list_and_no_current_target(): void
    {
        $user = User::factory()->create(['name' => 'Ada Lovelace']);

        $this->actingAs($user)->getJson(route('app.session'))
            ->assertOk()
            ->assertExactJson([
                'user' => ['id' => $user->id, 'name' => 'Ada Lovelace', 'email' => $user->email, 'initials' => 'AL'],
                'current_target_id' => null,
                'targets' => [],
            ]);
    }

    public function test_it_lists_targets_with_role_and_owner(): void
    {
        $user = $this->contestant();
        $target = $this->targetOf($user);

        $this->actingAs($user)->getJson(route('app.session'))
            ->assertOk()
            ->assertJsonPath('current_target_id', $target->id)
            ->assertJsonPath('targets.0', [
                'id' => $target->id,
                'name' => 'Acme Inc',
                'url' => 'https://acme.example',
                'role' => 'owner',
                'owner' => $user->name,
            ]);
    }

    public function test_a_user_with_targets_but_none_selected_lands_on_the_first_one(): void
    {
        $user = $this->contestant();
        $user->forceFill(['current_target_id' => null])->save();
        $id = Target::query()->sole()->id;

        $this->actingAs($user)->getJson(route('app.session'))
            ->assertOk()
            ->assertJsonPath('current_target_id', $id);

        $this->assertSame($id, $user->refresh()->current_target_id);
    }

    public function test_a_current_target_no_longer_shared_falls_back_to_the_first_visible_one(): void
    {
        $owner = $this->contestant('Shared Co');
        $viewer = $this->contestant('Viewer Own Ltd');
        $shared = $this->targetOf($owner);
        app(TargetContext::class)->share($shared, $viewer, TargetRole::Viewer);
        app(TargetContext::class)->select($viewer, $shared->id);

        $shared->members()->detach($viewer->id);

        $this->actingAs($viewer)->getJson(route('app.session'))
            ->assertOk()
            ->assertJsonCount(1, 'targets')
            ->assertJsonPath('current_target_id', Target::query()->where('name', 'Viewer Own Ltd')->value('id'));
    }

    public function test_select_switches_the_current_target_and_returns_the_session(): void
    {
        $owner = $this->contestant('Shared Co');
        $viewer = $this->contestant('Viewer Own Ltd');
        $shared = $this->targetOf($owner);
        app(TargetContext::class)->share($shared, $viewer, TargetRole::Viewer);

        $this->actingAs($viewer)->postJson(route('app.targets.select', $shared->id))
            ->assertOk()
            ->assertJsonPath('current_target_id', $shared->id)
            ->assertJsonCount(2, 'targets')
            ->assertJsonFragment(['id' => $shared->id, 'name' => 'Shared Co', 'role' => 'viewer', 'owner' => $owner->name]);

        $this->assertSame($shared->id, $viewer->refresh()->current_target_id);
    }
}
