<?php

namespace Database\Factories;

use App\Models\Target;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A bare target row. Use TargetContext::create() when the test needs the
 * per-target flag rows and the owner's current target set.
 *
 * @extends Factory<Target>
 */
class TargetFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $domain = fake()->unique()->domainName();

        return [
            'owner_id' => User::factory(),
            'name' => fake()->company(),
            'url' => "https://{$domain}",
            'domains' => [$domain],
        ];
    }
}
