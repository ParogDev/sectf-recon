<?php

namespace Tests\Feature\Console;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CreateSectfUserTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_verified_user_with_the_given_password(): void
    {
        $this->artisan('sectf:user', ['name' => 'Ada', 'email' => 'Ada@Example.com', '--password' => 'correct-horse-battery'])
            ->assertSuccessful();

        $user = User::query()->where('email', 'ada@example.com')->sole();
        $this->assertSame('Ada', $user->name);
        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue(Hash::check('correct-horse-battery', $user->password));
    }

    public function test_it_generates_and_prints_a_password_when_none_is_given(): void
    {
        $this->artisan('sectf:user', ['name' => 'Ada', 'email' => 'ada@example.com'])
            ->expectsOutputToContain('Password: ')
            ->assertSuccessful();

        $this->assertDatabaseCount('users', 1);
    }

    public function test_it_refuses_an_existing_email_without_reset(): void
    {
        $user = User::factory()->create(['email' => 'ada@example.com']);

        $this->artisan('sectf:user', ['name' => 'Ada', 'email' => 'ada@example.com', '--password' => 'correct-horse-battery'])
            ->assertFailed();

        $this->assertFalse(Hash::check('correct-horse-battery', $user->fresh()->password));
    }

    public function test_reset_sets_a_new_password(): void
    {
        $user = User::factory()->create(['email' => 'ada@example.com']);

        $this->artisan('sectf:user', ['name' => 'Ada L.', 'email' => 'ada@example.com', '--password' => 'correct-horse-battery', '--reset' => true])
            ->assertSuccessful();

        $this->assertTrue(Hash::check('correct-horse-battery', $user->fresh()->password));
        $this->assertSame('Ada L.', $user->fresh()->name);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_it_rejects_a_short_password(): void
    {
        $this->artisan('sectf:user', ['name' => 'Ada', 'email' => 'ada@example.com', '--password' => 'short'])
            ->assertFailed();

        $this->assertDatabaseCount('users', 0);
    }
}
