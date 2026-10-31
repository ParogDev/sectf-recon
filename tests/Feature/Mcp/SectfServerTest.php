<?php

namespace Tests\Feature\Mcp;

use App\Enums\TargetRole;
use App\Mcp\Servers\SectfServer;
use App\Mcp\Tools\AddPersonTool;
use App\Mcp\Tools\AddPhoneTool;
use App\Mcp\Tools\AddPretextTool;
use App\Mcp\Tools\CreateTargetTool;
use App\Mcp\Tools\GetUpdatesTool;
use App\Mcp\Tools\ListFindingsTool;
use App\Mcp\Tools\ListFlagsTool;
use App\Mcp\Tools\ListSearchesTool;
use App\Mcp\Tools\ListTargetsTool;
use App\Mcp\Tools\LogSearchTool;
use App\Mcp\Tools\RecordFindingTool;
use App\Mcp\Tools\SelectTargetTool;
use App\Models\Finding;
use App\Models\Target;
use App\Models\User;
use App\Services\TargetContext;
use Database\Seeders\FlagSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Mcp\Server\Attributes\Instructions;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionClass;
use Tests\TestCase;

class SectfServerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(FlagSeeder::class);
    }

    private function contestant(string $company = 'Acme Inc'): User
    {
        $user = User::factory()->create();
        app(TargetContext::class)->create($user, ['name' => $company, 'url' => 'https://acme.example'], 'test');

        return $user->refresh();
    }

    /**
     * @return array<string, mixed>
     */
    private function finding(string $key = 'email-provider', array $overrides = []): array
    {
        return [
            'key' => $key,
            'flag_id' => '05-03',
            'claim' => 'Mail is hosted on Microsoft 365.',
            'confidence' => 0.9,
            'answers_flag' => 'The MX record shows which mail service staff use.',
            'sources' => [['url' => 'https://dns.example/acme.example', 'quote' => 'MX acme-example.mail.protection.outlook.com']],
            ...$overrides,
        ];
    }

    public function test_recording_the_same_key_twice_leaves_one_finding_and_merges_sources(): void
    {
        $user = $this->contestant();

        SectfServer::actingAs($user)->tool(RecordFindingTool::class, $this->finding())
            ->assertOk()
            ->assertSee(['"created":true', '"flag_status":"found"']);

        SectfServer::actingAs($user)->tool(RecordFindingTool::class, $this->finding(overrides: [
            'claim' => 'Mail is hosted on Microsoft 365 (Exchange Online).',
            'sources' => [['url' => 'https://other.example/page']],
        ]))->assertOk()->assertSee('"created":false');

        $this->assertSame(1, Finding::query()->count());
        $finding = Finding::query()->sole();
        $this->assertSame('Mail is hosted on Microsoft 365 (Exchange Online).', $finding->claim);
        $this->assertCount(2, $finding->sources);
    }

    public function test_a_finding_without_a_source_is_refused(): void
    {
        SectfServer::actingAs($this->contestant())
            ->tool(RecordFindingTool::class, $this->finding(overrides: ['sources' => []]))
            ->assertHasErrors(['at least one source URL']);

        $this->assertSame(0, Finding::query()->count());
    }

    public function test_a_found_level_finding_must_say_how_it_answers_the_flag(): void
    {
        $user = $this->contestant();

        SectfServer::actingAs($user)
            ->tool(RecordFindingTool::class, $this->finding(overrides: ['answers_flag' => null]))
            ->assertHasErrors(['answers_flag']);
        $this->assertSame(0, Finding::query()->count());

        SectfServer::actingAs($user)
            ->tool(RecordFindingTool::class, $this->finding(overrides: ['answers_flag' => null, 'confidence' => 0.5]))
            ->assertOk()->assertSee('"flag_status":"partial"');
    }

    public function test_a_weak_finding_never_downgrades_a_found_flag(): void
    {
        $user = $this->contestant();

        SectfServer::actingAs($user)->tool(RecordFindingTool::class, $this->finding())->assertOk();
        SectfServer::actingAs($user)->tool(RecordFindingTool::class, $this->finding('weak-guess', ['confidence' => 0.2]))->assertOk();

        $this->assertDatabaseHas('target_flag', ['target_id' => $user->current_target_id, 'flag_id' => '05-03', 'status' => 'found']);
    }

    public function test_tools_explain_what_to_do_when_no_target_is_selected(): void
    {
        SectfServer::actingAs(User::factory()->create())
            ->tool(RecordFindingTool::class, $this->finding())
            ->assertHasErrors(['No target selected. Call list_targets']);
    }

    public function test_a_user_cannot_select_or_read_another_contestants_target(): void
    {
        $alice = $this->contestant('Alice Corp');
        $bob = $this->contestant('Bob Ltd');

        SectfServer::actingAs($alice)->tool(RecordFindingTool::class, $this->finding('alice-secret', ['claim' => 'Alice-only fact']))->assertOk();

        SectfServer::actingAs($bob)
            ->tool(SelectTargetTool::class, ['target_id' => $alice->current_target_id])
            ->assertHasErrors(['is not shared with you']);

        SectfServer::actingAs($bob)->tool(ListTargetsTool::class)->assertOk()->assertDontSee('Alice Corp');
        SectfServer::actingAs($bob)->tool(ListFindingsTool::class)->assertOk()->assertDontSee('Alice-only fact');
        SectfServer::actingAs($bob)->tool(GetUpdatesTool::class)->assertOk()->assertDontSee('Alice-only fact');

        $this->assertSame(Target::query()->where('name', 'Bob Ltd')->value('id'), $bob->refresh()->current_target_id);
    }

    public function test_a_viewer_can_read_a_shared_target_but_not_write_to_it(): void
    {
        $owner = $this->contestant('Shared Co');
        $viewer = User::factory()->create();
        $target = Target::query()->findOrFail($owner->current_target_id);
        app(TargetContext::class)->share($target, $viewer, TargetRole::Viewer);

        SectfServer::actingAs($viewer)->tool(SelectTargetTool::class, ['target_id' => $target->id])
            ->assertOk()->assertSee('"role":"viewer"');
        SectfServer::actingAs($viewer->refresh())->tool(ListFlagsTool::class)->assertOk();
        SectfServer::actingAs($viewer)->tool(RecordFindingTool::class, $this->finding())
            ->assertHasErrors(['read-only access to [Shared Co]']);

        $this->assertSame(0, Finding::query()->count());
    }

    public function test_create_target_is_idempotent_on_the_name(): void
    {
        $user = User::factory()->create();

        SectfServer::actingAs($user)->tool(CreateTargetTool::class, ['name' => 'Acme Inc', 'domains' => ['Acme.example']])
            ->assertOk()->assertSee(['"created":true', '"domains":["acme.example"]']);
        SectfServer::actingAs($user->refresh())->tool(CreateTargetTool::class, ['name' => ' acme inc '])
            ->assertOk()->assertSee('"created":false');

        $this->assertSame(1, Target::query()->count());
        $this->assertDatabaseCount('target_flag', 67);
    }

    public function test_list_flags_returns_the_board_and_next_best(): void
    {
        // next_best ranks across every category; the filter applies to the board.
        SectfServer::actingAs($this->contestant())->tool(ListFlagsTool::class, ['category_id' => '05', 'next_best' => 0])
            ->assertOk()
            ->assertSee(['"next_best":[]', '"osint_points_available"', '"id":"05-03"'])
            ->assertDontSee('"id":"04-13"');
    }

    public function test_get_updates_pages_forward_with_a_cursor(): void
    {
        $user = $this->contestant();
        SectfServer::actingAs($user)->tool(RecordFindingTool::class, $this->finding())->assertOk();

        // target.created, flag.status, finding.recorded
        SectfServer::actingAs($user)->tool(GetUpdatesTool::class, ['limit' => 2])
            ->assertOk()->assertSee(['"has_more":true', 'target.created'])->assertDontSee('finding.recorded');

        $cursor = $user->currentTarget->activities()->orderBy('id')->skip(1)->value('id');

        SectfServer::actingAs($user)->tool(GetUpdatesTool::class, ['since_id' => $cursor])
            ->assertOk()->assertSee(['finding.recorded', '"has_more":false'])->assertDontSee('target.created');
    }

    public function test_dossier_tools_upsert_and_link_people_phones_and_pretexts(): void
    {
        $user = $this->contestant();

        SectfServer::actingAs($user)->tool(AddPersonTool::class, [
            'key' => 'jane-tremblay', 'name' => 'Jane Tremblay', 'title' => 'IT Manager',
            'source_url' => 'https://acme.example/team',
        ])->assertOk()->assertSee('"created":true');

        foreach ([1, 2] as $attempt) {
            SectfServer::actingAs($user)->tool(AddPhoneTool::class, [
                'number' => '(514) 555-0100', 'extension' => '204', 'label' => 'IT help desk',
                'person_key' => 'jane-tremblay', 'source_url' => 'https://acme.example/contact',
            ])->assertOk()->assertSee(['"number":"+15145550100"', '"name":"Jane Tremblay"']);
        }
        $this->assertDatabaseCount('phone_numbers', 1);

        SectfServer::actingAs($user)->tool(AddPretextTool::class, [
            'key' => 'it-survey', 'title' => 'Vendor survey', 'persona' => 'Survey caller from a university study',
            'flag_ids' => ['05-03'], 'phone_numbers' => ['+1 514 555 0100', '514 555 9999'],
        ])->assertOk()->assertSee(['"phone_numbers":["+15145550100"]', '"unmatched_phone_numbers":["+15145559999"]']);
    }

    public function test_add_phone_rejects_a_number_that_is_too_short(): void
    {
        SectfServer::actingAs($this->contestant())->tool(AddPhoneTool::class, [
            'number' => '555-0100', 'label' => 'Front desk', 'source_url' => 'https://acme.example/contact',
        ])->assertHasErrors(['10 to 15 digits']);
    }

    public function test_a_repeated_search_is_logged_once_and_can_be_listed(): void
    {
        $user = $this->contestant();
        $search = ['channel' => 'Google', 'query' => 'site:acme.example filetype:pdf', 'result_count' => 0];

        SectfServer::actingAs($user)->tool(LogSearchTool::class, $search)->assertOk();
        SectfServer::actingAs($user)->tool(LogSearchTool::class, [...$search, 'result_count' => 3])->assertOk();

        $this->assertDatabaseCount('search_logs', 1);
        $this->assertDatabaseHas('search_logs', ['channel' => 'google', 'result_count' => 3]);

        SectfServer::actingAs($user)->tool(ListSearchesTool::class, ['contains' => 'filetype'])
            ->assertOk()->assertSee(['"count":1', 'site:acme.example filetype:pdf']);
    }

    /**
     * @return array<string, array{class-string}>
     */
    public static function tools(): array
    {
        return collect([
            ListTargetsTool::class, SelectTargetTool::class, CreateTargetTool::class, GetUpdatesTool::class,
            ListFlagsTool::class, ListFindingsTool::class, RecordFindingTool::class, AddPersonTool::class,
            AddPhoneTool::class, AddPretextTool::class, ListSearchesTool::class, LogSearchTool::class,
        ])->mapWithKeys(fn (string $class) => [class_basename($class) => [$class]])->all();
    }

    #[DataProvider('tools')]
    public function test_tool_descriptions_fit_the_client_limit(string $toolClass): void
    {
        $description = app($toolClass)->description();

        $this->assertNotSame('', $description);
        $this->assertLessThan(2048, mb_strlen($description));
    }

    public function test_server_instructions_fit_the_client_limit(): void
    {
        $instructions = (new ReflectionClass(SectfServer::class))->getAttributes(Instructions::class)[0]->getArguments()[0];

        $this->assertLessThan(2048, mb_strlen($instructions));
    }

    public function test_guests_see_no_target_data(): void
    {
        SectfServer::tool(ListTargetsTool::class)->assertHasErrors(['Not signed in']);
    }
}
