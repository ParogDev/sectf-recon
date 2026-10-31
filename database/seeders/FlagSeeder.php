<?php

namespace Database\Seeders;

use App\Models\Flag;
use App\Models\Target;
use Illuminate\Database\Seeder;

/**
 * Loads the organiser's catalogue from database/data/flags.json. Safe to
 * re-run: flags are upserted, and any target missing a flag row gets one.
 */
class FlagSeeder extends Seeder
{
    public function run(): void
    {
        $catalogue = json_decode((string) file_get_contents(database_path('data/flags.json')), true, flags: JSON_THROW_ON_ERROR);

        $sort = 0;
        foreach ($catalogue['categories'] as $category) {
            foreach ($category['flags'] as $flag) {
                Flag::query()->updateOrCreate(['id' => $flag['id']], [
                    'category_id' => $category['id'],
                    'category_en' => $category['name_en'],
                    'category_fr' => $category['name_fr'],
                    'points' => $flag['pts'],
                    'question_en' => $flag['en'],
                    'question_fr' => $flag['fr'],
                    'bonus' => $flag['bonus'] ?? false,
                    'call_only' => $flag['call_only'] ?? false,
                    'related_flag_id' => $flag['duplicate_of'] ?? $flag['overlaps'] ?? null,
                    'sort' => ++$sort,
                ]);
            }
        }

        $flagIds = Flag::query()->pluck('id');
        Target::query()->each(function (Target $target) use ($flagIds) {
            $existing = $target->flagStatuses()->pluck('flag_id');
            foreach ($flagIds->diff($existing) as $flagId) {
                $target->flagStatuses()->create(['flag_id' => $flagId, 'status' => 'open']);
            }
        });
    }
}
