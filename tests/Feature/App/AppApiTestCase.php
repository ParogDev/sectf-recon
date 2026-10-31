<?php

namespace Tests\Feature\App;

use App\Models\Target;
use App\Models\User;
use App\Services\FindingService;
use App\Services\TargetContext;
use Database\Seeders\FlagSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

abstract class AppApiTestCase extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(FlagSeeder::class);
    }

    protected function contestant(string $company = 'Acme Inc'): User
    {
        $user = User::factory()->create();
        app(TargetContext::class)->create($user, ['name' => $company, 'url' => 'https://acme.example'], 'test');

        return $user->refresh();
    }

    protected function targetOf(User $user): Target
    {
        return Target::query()->findOrFail($user->current_target_id);
    }

    protected function fileFinding(User $user, string $key = 'email-provider', string $claim = 'Mail is hosted on Microsoft 365.', float $confidence = 0.9, string $flagId = '05-03'): void
    {
        app(FindingService::class)->record($this->targetOf($user), $user, [
            'key' => $key,
            'flag_id' => $flagId,
            'claim' => $claim,
            'confidence' => $confidence,
            'sources' => [['url' => 'https://dns.example/acme.example', 'title' => 'DNS records for acme.example']],
        ], 'test');
    }
}
