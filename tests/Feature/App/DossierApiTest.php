<?php

namespace Tests\Feature\App;

use App\Models\User;
use App\Services\DossierService;
use App\Services\FindingService;
use App\Services\TargetContext;

class DossierApiTest extends AppApiTestCase
{
    private function populate(User $user): void
    {
        $target = $this->targetOf($user);

        $this->fileFinding($user, 'email-provider', 'Mail is hosted on Microsoft 365.', 0.9, '05-03');
        $this->fileFinding($user, 'pdf-reader', 'Staff open PDFs in Adobe Acrobat Reader.', 0.4, '05-04');

        app(FindingService::class)->record($target, $user, [
            'key' => 'hq-address',
            'claim' => 'Head office is at 1 Acme Way.',
            'confidence' => 0.8,
            'sources' => [['url' => 'https://acme.example/contact', 'title' => 'Contact us', 'content_date' => '2026-01-10']],
        ], 'test');

        $dossier = app(DossierService::class);
        $dossier->upsertPerson($target, $user, [
            'key' => 'jane-doe', 'name' => 'Jane Doe', 'title' => 'CEO', 'source_url' => 'https://acme.example/about',
        ], 'test');
        $dossier->upsertPhone($target, $user, [
            'number' => '514-555-0100', 'label' => 'Reception', 'person_key' => 'jane-doe', 'source_url' => 'https://acme.example/contact',
        ], 'test');
        $dossier->upsertPretext($target, $user, [
            'key' => 'it-callback', 'title' => 'IT callback', 'persona' => 'New helpdesk tech',
            'flag_ids' => ['05-03'], 'phone_numbers' => ['514-555-0100'], 'confidence' => 0.6,
        ], 'test');
        $dossier->logSearch($target, $user, ['channel' => 'web', 'query' => 'acme.example mx records', 'flag_id' => '05-03', 'result_count' => 3]);
    }

    public function test_json_report_lists_flags_findings_directory_and_numbered_sources(): void
    {
        $user = $this->contestant();
        $target = $this->targetOf($user);
        $this->populate($user);

        $response = $this->actingAs($user)->getJson(route('app.targets.dossier', $target->id))
            ->assertOk()
            ->assertJsonPath('target_id', $target->id)
            ->assertJsonPath('target.name', 'Acme Inc')
            ->assertJsonPath('summary.flags_found', 1)
            ->assertJsonPath('summary.flags_partial', 1)
            ->assertJsonPath('summary.findings', 3)
            ->assertJsonPath('summary.phones', 1)
            ->assertJsonPath('summary.people', 1)
            ->assertJsonPath('summary.pretexts', 1)
            ->assertJsonPath('summary.searches', 1)
            ->assertJsonCount(7, 'categories')
            ->assertJsonPath('general_findings.0.key', 'hq-address')
            ->assertJsonPath('phones.0.number', '+15145550100')
            ->assertJsonPath('people.0.name', 'Jane Doe')
            ->assertJsonPath('pretexts.0.flags.0.question', 'Which application opens PDF documents?')
            ->assertJsonPath('methodology.searches_by_channel.web', 1)
            ->assertJsonStructure(['generated_at', 'event' => ['name', 'phase'], 'sources' => [['n', 'url', 'retrieved_at', 'content_date', 'cited_by']]]);

        $category = collect($response->json('categories'))->firstWhere('id', '05');
        $flag = collect($category['flags'])->firstWhere('id', '05-03');
        $this->assertSame('found', $flag['status']);
        $this->assertSame('email-provider', $flag['findings'][0]['key']);
        $this->assertSame(1, $flag['findings'][0]['sources'][0]['n']);

        // The DNS source backs two findings but is numbered once; the contact page backs a finding and a phone; the about page backs the person.
        $sources = collect($response->json('sources'));
        $this->assertSame([1, 2, 3], $sources->pluck('n')->all());
        $this->assertSame(['person Jane Doe'], $sources->firstWhere('url', 'https://acme.example/about')['cited_by']);
        $this->assertSame(3, $response->json('people.0.citation'));
        $this->assertSame(['flag 05-03', 'flag 05-04'], $sources->firstWhere('url', 'https://dns.example/acme.example')['cited_by']);
        $this->assertSame(['finding hq-address', 'phone +15145550100'], $sources->firstWhere('url', 'https://acme.example/contact')['cited_by']);
        $this->assertSame(2, $response->json('phones.0.citation'));
    }

    public function test_html_report_renders_the_dossier(): void
    {
        $user = $this->contestant();
        $target = $this->targetOf($user);
        $this->populate($user);

        $this->actingAs($user)->get(route('dossier', $target->id))
            ->assertOk()
            ->assertSee('Acme Inc')
            ->assertSee('Mail is hosted on Microsoft 365.')
            ->assertSee('+15145550100')
            ->assertSee('Jane Doe')
            ->assertSee('IT callback')
            ->assertSee('https://dns.example/acme.example');
    }

    public function test_report_is_denied_without_access(): void
    {
        $owner = $this->contestant();
        $other = $this->contestant('Other Corp');
        $target = $this->targetOf($owner);

        $this->actingAs($other)->getJson(route('app.targets.dossier', $target->id))->assertForbidden();
        $this->actingAs($other)->get(route('dossier', $target->id))->assertForbidden();
    }

    public function test_viewers_can_export_a_shared_target(): void
    {
        $owner = $this->contestant();
        $viewer = User::factory()->create();
        $target = $this->targetOf($owner);
        app(TargetContext::class)->share($target, $viewer);

        $this->actingAs($viewer)->get(route('dossier', $target->id))->assertOk()->assertSee('Acme Inc');
    }

    public function test_french_dossier_translates_the_template_and_catalogue_but_not_filed_content(): void
    {
        $user = $this->contestant();
        $target = $this->targetOf($user);
        $this->populate($user);

        $response = $this->actingAs($user)->getJson(route('app.targets.dossier', [$target->id, 'lang' => 'fr']))
            ->assertOk()
            ->assertJsonPath('locale', 'fr')
            ->assertJsonPath('pretexts.0.flags.0.question', 'Quel logiciel sert à ouvrir les PDF?');

        $category = collect($response->json('categories'))->firstWhere('id', '05');
        $this->assertSame("Technologies à l'échelle de l'entreprise", $category['name']);
        $this->assertSame("Technologies à l'échelle de l'entreprise", collect($response->json('summary.by_category'))->firstWhere('found', 1)['category']);

        $this->actingAs($user)->get(route('dossier', [$target->id, 'lang' => 'fr']))
            ->assertOk()
            ->assertSee('<html lang="fr">', false)
            ->assertSee('Quel logiciel sert à ouvrir les PDF?')
            ->assertSee('Mail is hosted on Microsoft 365.');

        $this->actingAs($user)->get(route('dossier', [$target->id, 'lang' => 'de']))->assertStatus(422);
    }
}
