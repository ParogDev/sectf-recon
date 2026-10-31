<?php

namespace Tests\Feature\App;

use App\Models\Flag;
use App\Services\DossierService;

class TargetApiTest extends AppApiTestCase
{
    public function test_show_returns_the_target_summary_board_and_next_best(): void
    {
        $user = $this->contestant();
        $target = $this->targetOf($user);
        $this->fileFinding($user);

        $response = $this->actingAs($user)->getJson(route('app.targets.show', $target->id))->assertOk();

        $response
            ->assertJsonPath('target_id', $target->id)
            ->assertJsonPath('target.id', $target->id)
            ->assertJsonPath('target.name', 'Acme Inc')
            ->assertJsonPath('target.url', 'https://acme.example')
            ->assertJsonPath('target.domains', [])
            ->assertJsonPath('target.role', 'owner')
            ->assertJsonPath('target.owner', $user->name)
            ->assertJsonPath('summary.flags_found', 1)
            ->assertJsonStructure([
                'summary' => ['osint_points_banked', 'osint_points_available', 'flags_found', 'flags_partial', 'flags_open', 'by_category'],
                'flags' => [['id', 'category_id', 'category', 'question', 'points', 'osint_points', 'status', 'findings', 'bonus', 'call_only', 'related_flag_id', 'notes']],
                'next_best',
            ]);

        $osint = Flag::query()->findOrFail('05-03')->osintPoints();
        $this->assertEquals($osint, $response->json('summary.osint_points_banked'));

        $flags = collect($response->json('flags'));
        $this->assertSame(Flag::query()->orderBy('sort')->pluck('id')->all(), $flags->pluck('id')->all());

        $row = $flags->firstWhere('id', '05-03');
        $this->assertSame('found', $row['status']);
        $this->assertSame('05', $row['category_id']);
        $this->assertSame(1, $row['findings']);

        $nextBest = $response->json('next_best');
        $this->assertIsArray($nextBest);
        $this->assertNotEmpty($nextBest);
        $this->assertContainsOnlyString($nextBest);
        $this->assertNotContains('05-03', $nextBest);
    }

    public function test_findings_include_claim_sources_and_filter_by_flag(): void
    {
        $user = $this->contestant();
        $target = $this->targetOf($user);
        $this->fileFinding($user);
        $this->fileFinding($user, key: 'ceo-name', claim: 'The CEO is Jane Doe.', flagId: '01-01');

        $this->actingAs($user)->getJson(route('app.targets.findings', $target->id))
            ->assertOk()
            ->assertJsonPath('target_id', $target->id)
            ->assertJsonCount(2, 'findings')
            ->assertJsonStructure(['findings' => [['id', 'key', 'flag_id', 'claim', 'detail', 'confidence', 'content_date', 'by', 'created_at', 'updated_at', 'sources' => [['url', 'title', 'retrieved_at', 'content_date', 'quote']]]]]);

        $this->actingAs($user)->getJson(route('app.targets.findings', ['target' => $target->id, 'flag_id' => '05-03']))
            ->assertOk()
            ->assertJsonCount(1, 'findings')
            ->assertJsonPath('findings.0.claim', 'Mail is hosted on Microsoft 365.')
            ->assertJsonPath('findings.0.sources.0.url', 'https://dns.example/acme.example')
            ->assertJsonPath('findings.0.by', $user->name);

        $this->actingAs($user)->getJson(route('app.targets.findings', ['target' => $target->id, 'search' => 'Jane']))
            ->assertOk()
            ->assertJsonCount(1, 'findings')
            ->assertJsonPath('findings.0.key', 'ceo-name');
    }

    public function test_findings_validate_their_filters(): void
    {
        $user = $this->contestant();
        $target = $this->targetOf($user);

        $this->actingAs($user)->getJson(route('app.targets.findings', ['target' => $target->id, 'flag_id' => 'nope', 'limit' => 500]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['flag_id', 'limit']);
    }

    public function test_activity_returns_newest_first_or_pages_forward_after_a_cursor(): void
    {
        $user = $this->contestant();
        $target = $this->targetOf($user);
        $this->fileFinding($user);

        $latest = $this->actingAs($user)->getJson(route('app.targets.activity', $target->id))
            ->assertOk()
            ->assertJsonPath('target_id', $target->id)
            ->json('activity');

        // target.created, flag.status, finding.recorded
        $this->assertCount(3, $latest);
        $this->assertSame('finding.recorded', $latest[0]['kind']);
        $this->assertSame('target.created', $latest[2]['kind']);
        $this->assertSame(['id', 'kind', 'summary', 'subject', 'by', 'via', 'at'], array_keys($latest[0]));

        $this->actingAs($user)->getJson(route('app.targets.activity', ['target' => $target->id, 'after' => $latest[2]['id']]))
            ->assertOk()
            ->assertJsonCount(2, 'activity')
            ->assertJsonPath('activity.0.kind', 'flag.status')
            ->assertJsonPath('activity.1.kind', 'finding.recorded');

        $this->actingAs($user)->getJson(route('app.targets.activity', ['target' => $target->id, 'limit' => 1]))
            ->assertOk()
            ->assertJsonCount(1, 'activity');
    }

    public function test_searches_list_newest_first_with_ids_and_filters(): void
    {
        $user = $this->contestant();
        $target = $this->targetOf($user);
        $dossier = app(DossierService::class);
        $first = $dossier->logSearch($target, $user, ['channel' => 'google', 'query' => 'acme mx records', 'flag_id' => '05-03', 'result_count' => 3]);
        $dossier->logSearch($target, $user, ['channel' => 'linkedin', 'query' => 'acme ceo', 'flag_id' => '01-01']);

        $this->actingAs($user)->getJson(route('app.targets.searches', $target->id))
            ->assertOk()
            ->assertJsonPath('target_id', $target->id)
            ->assertJsonCount(2, 'searches')
            ->assertJsonPath('searches.0.query', 'acme ceo')
            ->assertJsonStructure(['searches' => [['id', 'channel', 'query', 'flag_id', 'result_count', 'notes', 'by', 'at']]]);

        $this->actingAs($user)->getJson(route('app.targets.searches', ['target' => $target->id, 'flag_id' => '05-03']))
            ->assertOk()
            ->assertJsonCount(1, 'searches')
            ->assertJsonPath('searches.0.id', $first->id)
            ->assertJsonPath('searches.0.by', $user->name);

        $this->actingAs($user)->getJson(route('app.targets.searches', ['target' => $target->id, 'contains' => 'ceo']))
            ->assertOk()
            ->assertJsonCount(1, 'searches')
            ->assertJsonPath('searches.0.channel', 'linkedin');
    }

    public function test_the_owner_can_set_a_flag_status_with_notes(): void
    {
        $user = $this->contestant();
        $target = $this->targetOf($user);

        $this->actingAs($user)->patchJson(route('app.targets.flags.update', [$target->id, '01-01']), ['status' => 'unverifiable', 'notes' => 'No public source.'])
            ->assertOk()
            ->assertJsonPath('target_id', $target->id)
            ->assertJsonPath('flag.id', '01-01')
            ->assertJsonPath('flag.category_id', '01')
            ->assertJsonPath('flag.status', 'unverifiable')
            ->assertJsonPath('flag.notes', 'No public source.');

        $this->assertDatabaseHas('target_flag', ['target_id' => $target->id, 'flag_id' => '01-01', 'status' => 'unverifiable', 'updated_by' => $user->id]);
        $this->assertDatabaseHas('activities', ['target_id' => $target->id, 'user_id' => $user->id, 'kind' => 'flag.status', 'via' => 'web']);
    }

    public function test_flag_update_rejects_unknown_flags_and_bad_statuses(): void
    {
        $user = $this->contestant();
        $target = $this->targetOf($user);

        $this->actingAs($user)->patchJson("/api/app/targets/{$target->id}/flags/99-99", ['status' => 'found'])->assertNotFound();
        $this->actingAs($user)->patchJson("/api/app/targets/{$target->id}/flags/not-a-flag", ['status' => 'found'])->assertNotFound();

        $this->actingAs($user)->patchJson(route('app.targets.flags.update', [$target->id, '01-01']), ['status' => 'banked', 'notes' => str_repeat('x', 501)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status', 'notes']);
    }
}
