<?php

namespace Tests\Feature\App;

use PHPUnit\Framework\Attributes\DataProvider;

class GuestAccessTest extends AppApiTestCase
{
    /**
     * @return array<string, array{string, string}>
     */
    public static function routes(): array
    {
        return [
            'session' => ['get', '/api/app/session'],
            'select' => ['post', '/api/app/targets/1/select'],
            'show' => ['get', '/api/app/targets/1'],
            'findings' => ['get', '/api/app/targets/1/findings'],
            'activity' => ['get', '/api/app/targets/1/activity'],
            'people' => ['get', '/api/app/targets/1/people'],
            'phones' => ['get', '/api/app/targets/1/phones'],
            'ring-verified' => ['post', '/api/app/targets/1/phones/1/ring-verified'],
            'pretexts' => ['get', '/api/app/targets/1/pretexts'],
            'searches' => ['get', '/api/app/targets/1/searches'],
            'flag update' => ['patch', '/api/app/targets/1/flags/01-01'],
        ];
    }

    /**
     * api/* always renders errors as JSON (bootstrap/app.php), so a guest
     * gets a 401 rather than a login redirect, with or without Accept.
     */
    #[DataProvider('routes')]
    public function test_guests_get_a_401(string $method, string $uri): void
    {
        $this->json($method, $uri)->assertUnauthorized()->assertJsonPath('message', 'Unauthenticated.');
        $this->call(strtoupper($method), $uri)->assertUnauthorized();
    }

    public function test_guests_are_redirected_from_the_spa_shell(): void
    {
        $this->get('/dashboard')->assertRedirect(route('login'));
        $this->get('/dashboard/flags/05-03')->assertRedirect(route('login'));
    }

    public function test_the_spa_shell_serves_deep_links(): void
    {
        $user = $this->contestant();

        $this->actingAs($user)->get('/dashboard/flags/05-03')->assertOk()->assertViewIs('spa');
        $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertViewIs('spa');
    }
}
