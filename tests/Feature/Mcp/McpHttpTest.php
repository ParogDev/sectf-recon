<?php

namespace Tests\Feature\Mcp;

use App\Models\User;
use App\Services\TargetContext;
use Database\Seeders\FlagSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

/**
 * The real /mcp route: spec 2026-07-28 over HTTP, bearer auth, Origin check.
 */
class McpHttpTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A 2026-07-28 request: protocol version in params._meta, method (and
     * name, for a call) mirrored in MCP headers.
     *
     * @param  array<string, mixed>  $params
     * @return array{0: array<string, mixed>, 1: array<string, string>}
     */
    private function modernEraRequest(string $method, array $params = [], ?string $name = null): array
    {
        $message = [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => $method,
            'params' => [
                ...$params,
                '_meta' => [
                    'io.modelcontextprotocol/protocolVersion' => '2026-07-28',
                    'io.modelcontextprotocol/clientCapabilities' => [],
                ],
            ],
        ];

        $headers = ['MCP-Protocol-Version' => '2026-07-28', 'Mcp-Method' => $method];

        if ($name !== null) {
            $headers['Mcp-Name'] = $name;
        }

        return [$message, $headers];
    }

    public function test_an_unauthenticated_call_gets_401_pointing_at_the_resource_metadata(): void
    {
        [$message, $headers] = $this->modernEraRequest('server/discover');

        $this->postJson('/mcp', $message, $headers)
            ->assertUnauthorized()
            ->assertHeader('WWW-Authenticate');
    }

    public function test_it_advertises_2026_07_28_through_server_discover(): void
    {
        Passport::actingAs(User::factory()->create(), ['mcp:use']);

        [$message, $headers] = $this->modernEraRequest('server/discover');

        $this->postJson('/mcp', $message, $headers)
            ->assertSuccessful()
            ->assertJsonPath('result.supportedVersions', ['2026-07-28'])
            ->assertJsonStructure(['result' => ['capabilities' => ['tools']]]);
    }

    public function test_tools_list_returns_every_tool_on_one_page(): void
    {
        Passport::actingAs(User::factory()->create(), ['mcp:use']);

        [$message, $headers] = $this->modernEraRequest('tools/list');

        $this->postJson('/mcp', $message, $headers)
            ->assertSuccessful()
            ->assertJsonCount(13, 'result.tools')
            ->assertJsonMissingPath('result.nextCursor');
    }

    public function test_a_tool_call_over_http_records_a_finding(): void
    {
        $this->seed(FlagSeeder::class);
        $user = User::factory()->create();
        app(TargetContext::class)->create($user, ['name' => 'Acme Inc'], 'test');
        Passport::actingAs($user->refresh(), ['mcp:use']);

        [$message, $headers] = $this->modernEraRequest('tools/call', [
            'name' => 'record_finding',
            'arguments' => [
                'key' => 'switchboard-hours',
                'claim' => 'Switchboard open 8:30-17:00 weekdays.',
                'sources' => [['url' => 'https://acme.example/contact']],
            ],
        ], 'record_finding');

        $this->postJson('/mcp', $message, $headers)
            ->assertSuccessful()
            ->assertJsonPath('result.isError', false);

        $this->assertDatabaseHas('findings', ['key' => 'switchboard-hours', 'target_id' => $user->current_target_id]);
    }

    public function test_a_browser_request_from_a_foreign_origin_is_refused(): void
    {
        Passport::actingAs(User::factory()->create(), ['mcp:use']);

        [$message, $headers] = $this->modernEraRequest('server/discover');

        $this->postJson('/mcp', $message, [...$headers, 'Origin' => 'https://evil.example'])
            ->assertForbidden();
    }
}
