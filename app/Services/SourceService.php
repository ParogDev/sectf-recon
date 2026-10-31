<?php

namespace App\Services;

use App\Models\Source;
use App\Models\Target;
use App\Models\User;

class SourceService
{
    /**
     * Find-or-create a source by URL on this target. A later call may fill in
     * a title or content date the first one lacked; it never blanks them.
     *
     * @param  array{url: string, title?: ?string, content_date?: ?string, content_date_basis?: ?string}  $data
     */
    public function upsert(Target $target, User $user, array $data): Source
    {
        $url = self::normalise($data['url']);

        $source = Source::query()->firstOrNew([
            'target_id' => $target->id,
            'url_hash' => Source::hashUrl($url),
        ], [
            'url' => $url,
            'retrieved_at' => now(),
            'added_by' => $user->id,
        ]);

        foreach (['title', 'content_date', 'content_date_basis'] as $field) {
            if (! empty($data[$field])) {
                $source->{$field} = $data[$field];
            }
        }

        $source->save();

        return $source;
    }

    /**
     * Trim and drop the fragment, so the same page cited twice is one source.
     * Query strings are kept: on many sites they are the page.
     */
    public static function normalise(string $url): string
    {
        $url = trim($url);

        return preg_replace('/#.*$/', '', $url) ?? $url;
    }
}
