<?php

namespace Tests\Feature\App;

use App\Mcp\Servers\SectfServer;
use App\Mcp\Tools\AddPretextTool;
use App\Mcp\Tools\ExportDossierTool;
use App\Mcp\Tools\LogSearchTool;
use App\Models\User;
use App\Services\DossierExporter;
use App\Services\DossierReport;
use App\Services\DossierService;
use App\Services\FindingService;
use Illuminate\Support\Facades\File;

class DossierBilingualAndExportTest extends AppApiTestCase
{
    private string $exportDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->exportDir = storage_path('framework/testing/dossier-exports-'.uniqid());
        config(['sectf.dossier_dir' => $this->exportDir, 'sectf.chromium' => '/nonexistent/chromium', 'sectf.host_dir' => '/host/project']);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->exportDir);

        parent::tearDown();
    }

    private function phone(User $user, string $number, string $kind = 'corporate', array $fr = []): void
    {
        app(DossierService::class)->upsertPhone($this->targetOf($user), $user, [
            'number' => $number, 'label' => 'Head office', 'department' => 'Corporate', 'kind' => $kind,
            'source_url' => 'https://acme.example/contact', 'fr' => $fr,
        ], 'test');
    }

    public function test_french_report_uses_french_text_and_flags_what_is_missing(): void
    {
        $user = $this->contestant();
        $target = $this->targetOf($user);

        $this->fileFinding($user, 'email-provider', 'Mail is hosted on Microsoft 365.', 0.9, '05-03');
        $this->fileFinding($user, 'pdf-reader', 'Staff open PDFs in Adobe Acrobat Reader.', 0.4, '05-04');
        $this->phone($user, '514-555-0100', fr: ['label' => 'Siège social', 'department' => 'Corporatif']);

        app(FindingService::class)->record($target, $user, [
            'key' => 'email-provider', 'claim' => 'Mail is hosted on Microsoft 365.', 'fr' => ['claim' => 'Le courriel est hébergé chez Microsoft 365.'],
            'sources' => [['url' => 'https://dns.example/acme.example']],
        ], 'test');

        $fr = $this->actingAs($user)->getJson(route('app.targets.dossier', $target->id).'?lang=fr')->assertOk();
        // The JSON API uses the app locale; render through the same builder the HTML uses.
        app()->setLocale('fr');
        $report = app(DossierReport::class)->build($target);
        app()->setLocale('en');

        $flag = collect(collect($report['categories'])->firstWhere('id', '05')['flags'])->firstWhere('id', '05-03');
        $this->assertSame('Le courriel est hébergé chez Microsoft 365.', $flag['findings'][0]['claim']);
        $this->assertFalse($flag['findings'][0]['untranslated']);

        $other = collect(collect($report['categories'])->firstWhere('id', '05')['flags'])->firstWhere('id', '05-04');
        $this->assertSame('Staff open PDFs in Adobe Acrobat Reader.', $other['findings'][0]['claim']);
        $this->assertTrue($other['findings'][0]['untranslated']);

        $this->assertSame('Siège social', $report['phones'][0]['label']);
        $this->assertSame('Corporatif', $report['phones'][0]['department']);
        $this->assertSame(1, $report['readiness']['french_missing']['findings']);
        $this->assertContains('fr_missing', array_column($report['readiness']['gaps'], 'code'));
        $fr->assertOk();
    }

    public function test_english_report_ignores_french_text(): void
    {
        $user = $this->contestant();
        $this->phone($user, '514-555-0100', fr: ['label' => 'Siège social']);

        $report = app(DossierReport::class)->build($this->targetOf($user));

        $this->assertSame('Head office', $report['phones'][0]['label']);
    }

    public function test_pretext_pivots_coverage_and_the_five_pretext_minimum(): void
    {
        $user = $this->contestant();
        $target = $this->targetOf($user);
        $this->phone($user, '514-555-0100', 'corporate');

        SectfServer::actingAs($user)->tool(AddPretextTool::class, [
            'key' => 'new-hire', 'title' => 'New hire', 'persona' => 'Starting Monday',
            'flag_ids' => ['02-04'], 'phone_numbers' => ['514-555-0100'],
            'pivots' => [
                ['flag_id' => '03-09', 'bridge' => 'While asking what to bring, ask if a badge is issued.'],
                ['flag_id' => '03-09', 'bridge' => 'duplicate is ignored'],
                ['flag_id' => '01-05', 'bridge' => 'Ask where people usually eat lunch.'],
            ],
            'fr' => ['title' => 'Nouvel employé', 'persona' => 'Débute lundi', 'pivots' => ['03-09' => 'En demandant quoi apporter, demander si un badge est remis.']],
        ])->assertOk()->assertSee('"has_french":true');

        $report = app(DossierReport::class)->build($target);
        $pretext = $report['pretexts'][0];

        $this->assertCount(2, $pretext['pivot_flags']);
        $this->assertSame('03-09', $pretext['pivot_flags'][0]['id']);
        $this->assertSame('While asking what to bring, ask if a badge is issued.', $pretext['pivot_flags'][0]['bridge']);
        $this->assertNotNull($pretext['pivot_flags'][0]['question']);
        $this->assertSame([['ref' => 'P1', 'role' => 'pivot']], $report['pretext_coverage']['by_flag']['03-09']);
        $this->assertSame([['ref' => 'P1', 'role' => 'primary']], $report['pretext_coverage']['by_flag']['02-04']);
        $this->assertNotContains('03-09', array_column($report['pretext_coverage']['uncovered'], 'id'));
        $this->assertContains('05-03', array_column($report['pretext_coverage']['uncovered'], 'id'));

        $gaps = collect($report['readiness']['gaps'])->keyBy('code');
        $this->assertSame(['code' => 'pretexts_min', 'have' => 1, 'need' => 5], $gaps['pretexts_min']);
        $this->assertSame(1, $report['readiness']['phone_kinds']['distinct']);
        $this->assertTrue($gaps->has('phone_kinds'));

        app()->setLocale('fr');
        $fr = app(DossierReport::class)->build($target)['pretexts'][0];
        app()->setLocale('en');
        $this->assertSame('Nouvel employé', $fr['title']);
        $this->assertSame('En demandant quoi apporter, demander si un badge est remis.', $fr['pivot_flags'][0]['bridge']);
        $this->assertSame('Ask where people usually eat lunch.', $fr['pivot_flags'][1]['bridge']); // no French bridge filed: falls back
    }

    public function test_only_small_format_stores_is_called_out(): void
    {
        $user = $this->contestant();
        $this->phone($user, '514-555-0101', 'small_format_store');

        $codes = array_column(app(DossierReport::class)->build($this->targetOf($user))['readiness']['gaps'], 'code');

        $this->assertContains('small_format_only', $codes);
    }

    public function test_blocked_research_is_listed_until_a_later_search_or_finding_clears_it(): void
    {
        $user = $this->contestant();
        $target = $this->targetOf($user);
        $tool = SectfServer::actingAs($user);
        $incomplete = function () use ($target) {
            $gap = collect(app(DossierReport::class)->build($target)['readiness']['gaps'])->firstWhere('code', 'research_incomplete');

            return $gap['flags'] ?? [];
        };

        $tool->tool(LogSearchTool::class, ['channel' => 'web', 'query' => 'no flag given', 'outcome' => 'blocked'])->assertHasErrors(['flag_id']);
        $tool->tool(LogSearchTool::class, ['channel' => 'web', 'query' => 'a', 'flag_id' => '04-13', 'outcome' => 'blocked'])->assertOk();
        $tool->tool(LogSearchTool::class, ['channel' => 'web', 'query' => 'b', 'flag_id' => '05-03', 'outcome' => 'failed'])->assertOk();
        $tool->tool(LogSearchTool::class, ['channel' => 'web', 'query' => 'c', 'flag_id' => '02-04', 'outcome' => 'blocked'])->assertOk();
        $this->assertSame(['02-04', '04-13', '05-03'], $incomplete());

        $tool->tool(LogSearchTool::class, ['channel' => 'web', 'query' => 'd', 'flag_id' => '04-13'])->assertOk();
        $this->fileFinding($user, 'pdf-app', 'Staff open PDFs in Acrobat.', 0.9, '05-03');
        $this->assertSame(['02-04'], $incomplete());

        $this->assertContains('Research did not finish on 02-04 (blocked or failed); these need a person to look into them.',
            app(DossierExporter::class)->suggestions($target, app(DossierReport::class)->build($target)));
    }

    public function test_export_writes_html_for_both_languages_and_reports_where(): void
    {
        $user = $this->contestant();
        $target = $this->targetOf($user);
        $this->phone($user, '514-555-0100', fr: ['label' => 'Siège social']);

        $result = $this->actingAs($user)->postJson(route('app.targets.dossier.export', $target->id))
            ->assertOk()
            ->assertJsonPath('target_id', $target->id)
            ->assertJsonPath('pdf_ok', false)
            ->assertJsonPath('folder.path', 'dossier-exports/acme-inc')
            ->assertJsonStructure(['files' => [['lang', 'type', 'name', 'bytes', 'url']], 'readiness' => ['gaps'], 'warnings', 'suggestions'])
            ->json();

        $this->assertStringContainsString('/host/project', $result['folder']['host_path']);
        $this->assertStringEndsWith('dossier-exports'.DIRECTORY_SEPARATOR.'acme-inc', $result['folder']['host_path']);
        $this->assertSame(['acme-inc-en.html', 'acme-inc-fr.html'], array_column($result['files'], 'name'));
        $this->assertStringContainsString('chromium', $result['warnings'][0]);
        $this->assertNotEmpty($result['suggestions']);

        $en = file_get_contents("{$this->exportDir}/acme-inc/acme-inc-en.html");
        $fr = file_get_contents("{$this->exportDir}/acme-inc/acme-inc-fr.html");
        $this->assertStringContainsString('Head office', $en);
        $this->assertStringContainsString('Siège social', $fr);
        $this->assertStringNotContainsString('Siège social', $en);

        $this->actingAs($user)->get($result['files'][0]['url'])->assertOk()->assertHeader('Content-Type', 'text/html; charset=utf-8');
    }

    public function test_windows_host_path_keeps_backslashes_throughout(): void
    {
        config(['sectf.host_dir' => '\\\\wsl.localhost\\Ubuntu\\home\\me\\sectf']);
        $user = $this->contestant();

        $path = $this->actingAs($user)->postJson(route('app.targets.dossier.export', $this->targetOf($user)->id))
            ->assertOk()->json('folder.host_path');

        $this->assertSame('\\\\wsl.localhost\\Ubuntu\\home\\me\\sectf\\dossier-exports\\acme-inc', $path);
    }

    public function test_export_files_are_whitelisted_and_scoped_to_the_target(): void
    {
        $user = $this->contestant();
        $target = $this->targetOf($user);
        $this->actingAs($user)->postJson(route('app.targets.dossier.export', $target->id))->assertOk();

        $base = '/api/app/targets/'.$target->id.'/dossier/files/';
        $this->actingAs($user)->get($base.'acme-inc-en.html')->assertOk();
        $this->actingAs($user)->get($base.'..%2F.env')->assertNotFound();
        $this->actingAs($user)->get($base.'other-en.html')->assertNotFound();
        $this->actingAs($user)->get($base.'acme-inc-en.pdf')->assertNotFound(); // no PDF engine in the test
        $this->assertNull(app(DossierExporter::class)->path($target, '../acme-inc-en.html'));

        $stranger = User::factory()->create();
        $this->actingAs($stranger)->postJson(route('app.targets.dossier.export', $target->id))->assertForbidden();
        $this->actingAs($stranger)->get($base.'acme-inc-en.html')->assertForbidden();
    }

    public function test_mcp_export_dossier_tool_returns_folder_files_and_suggestions(): void
    {
        $user = $this->contestant();
        $this->phone($user, '514-555-0100');

        SectfServer::actingAs($user)->tool(ExportDossierTool::class, ['lang' => 'en'])
            ->assertOk()
            ->assertSee('dossier-exports/acme-inc')
            ->assertSee('acme-inc-en.html')
            ->assertSee('suggestions')
            ->assertDontSee('acme-inc-fr.html');
    }
}
