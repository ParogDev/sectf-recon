<?php

namespace Tests\Feature\App;

use App\Enums\TargetRole;
use App\Models\Target;
use App\Services\DossierService;
use App\Services\TargetContext;

class AuthorizationTest extends AppApiTestCase
{
    public function test_a_contestant_cannot_read_or_select_another_contestants_target(): void
    {
        $alice = $this->contestant('Alice Corp');
        $bob = $this->contestant('Bob Ltd');
        $aliceTarget = $this->targetOf($alice);
        $bobTargetId = $bob->current_target_id;

        $this->actingAs($bob)->getJson(route('app.targets.show', $aliceTarget->id))->assertForbidden();
        $this->actingAs($bob)->getJson(route('app.targets.findings', $aliceTarget->id))->assertForbidden();
        $this->actingAs($bob)->getJson(route('app.targets.activity', $aliceTarget->id))->assertForbidden();
        $this->actingAs($bob)->getJson(route('app.targets.phones', $aliceTarget->id))->assertForbidden();
        $this->actingAs($bob)->postJson(route('app.targets.select', $aliceTarget->id))->assertForbidden();
        $this->actingAs($bob)->getJson(route('app.targets.show', 999999))->assertForbidden();

        $this->assertSame($bobTargetId, $bob->refresh()->current_target_id);

        $this->actingAs($bob)->getJson(route('app.session'))
            ->assertOk()
            ->assertJsonMissing(['name' => 'Alice Corp']);
    }

    public function test_a_viewer_can_select_and_read_a_shared_target_but_not_write_to_it(): void
    {
        $owner = $this->contestant('Shared Co');
        $this->fileFinding($owner, claim: 'Shared-only fact');
        $shared = $this->targetOf($owner);
        $phone = app(DossierService::class)->upsertPhone($shared, $owner, [
            'number' => '514-555-0100', 'label' => 'Reception', 'source_url' => 'https://acme.example/contact',
        ], 'test')['phone'];

        $viewer = $this->contestant('Viewer Own Ltd');
        app(TargetContext::class)->share($shared, $viewer, TargetRole::Viewer);

        $this->actingAs($viewer)->postJson(route('app.targets.select', $shared->id))
            ->assertOk()
            ->assertJsonPath('current_target_id', $shared->id);

        $this->actingAs($viewer)->getJson(route('app.targets.show', $shared->id))
            ->assertOk()
            ->assertJsonPath('target.name', 'Shared Co')
            ->assertJsonPath('target.role', 'viewer')
            ->assertJsonPath('target.owner', $owner->name);

        $this->actingAs($viewer)->getJson(route('app.targets.findings', $shared->id))
            ->assertOk()
            ->assertJsonPath('findings.0.claim', 'Shared-only fact');

        $this->actingAs($viewer)->patchJson(route('app.targets.flags.update', [$shared->id, '01-01']), ['status' => 'found'])
            ->assertForbidden()
            ->assertJsonPath('message', fn (string $m) => str_contains($m, 'read-only'));

        $this->actingAs($viewer)->postJson(route('app.targets.phones.ring-verified', [$shared->id, $phone->id]))
            ->assertForbidden();

        $this->assertDatabaseHas('target_flag', ['target_id' => $shared->id, 'flag_id' => '01-01', 'status' => 'open']);
        $this->assertNull($phone->refresh()->ring_verified_at);
        $this->assertDatabaseMissing('activities', ['user_id' => $viewer->id, 'target_id' => $shared->id]);

        // The owner can.
        $this->actingAs($owner)->patchJson(route('app.targets.flags.update', [$shared->id, '01-01']), ['status' => 'found'])
            ->assertOk()
            ->assertJsonPath('flag.status', 'found');

        $this->assertDatabaseHas('activities', ['target_id' => $shared->id, 'user_id' => $owner->id, 'kind' => 'flag.status', 'via' => 'web']);
    }

    public function test_an_editor_can_write_to_a_shared_target(): void
    {
        $owner = $this->contestant('Shared Co');
        $shared = $this->targetOf($owner);
        $editor = $this->contestant('Editor Own Ltd');
        app(TargetContext::class)->share($shared, $editor, TargetRole::Editor);

        $this->actingAs($editor)->patchJson(route('app.targets.flags.update', [$shared->id, '01-01']), ['status' => 'partial'])
            ->assertOk()
            ->assertJsonPath('flag.status', 'partial');

        $this->assertSame('partial', Target::query()->findOrFail($shared->id)->flagStatuses()->where('flag_id', '01-01')->value('status')->value);
    }
}
