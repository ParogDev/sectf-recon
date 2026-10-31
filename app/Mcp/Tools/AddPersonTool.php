<?php

namespace App\Mcp\Tools;

use App\Services\DossierService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;

#[Title('Add person')]
#[IsIdempotent]
#[IsDestructive(false)]
#[IsOpenWorld(false)]
#[Description('Add or update an employee of the current target, with the source that names them. Job-relevant facts only: name, title, department, tenure, public professional profile URL. Never record home addresses, family, personal social accounts, age, health or anything off-job (event rules and Québec Law 25); leave such things out of notes too. Same key updates the person. Use the key as person_key in add_phone.')]
class AddPersonTool extends SectfTool
{
    protected string $name = 'add_person';

    public function __construct(private DossierService $dossier) {}

    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'key' => self::KEY_RULE,
            'name' => ['required', 'string', 'max:255'],
            'title' => ['nullable', 'string', 'max:255'],
            'department' => ['nullable', 'string', 'max:255'],
            'tenure' => ['nullable', 'string', 'max:255'],
            'profile_url' => ['nullable', 'url:http,https', 'max:2048'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'fr' => ['nullable', 'array'],
            'fr.title' => ['nullable', 'string', 'max:255'],
            'fr.department' => ['nullable', 'string', 'max:255'],
            'fr.notes' => ['nullable', 'string', 'max:2000'],
            'source_url' => ['required', 'url:http,https', 'max:2048'],
        ]);

        $target = $this->currentTarget($request, write: true);
        $result = $this->dossier->upsertPerson($target, $this->user($request), $validated);
        $person = $result['person'];

        return $this->payload($target, [
            'created' => $result['created'],
            'person' => [
                'key' => $person->key,
                'name' => $person->name,
                'title' => $person->title,
                'department' => $person->department,
                'tenure' => $person->tenure,
                'profile_url' => $person->profile_url,
                'source_url' => $validated['source_url'],
            ],
        ]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'key' => $schema->string()->max(191)->pattern('^[a-z0-9][a-z0-9-]*$')->required()->description(self::KEY_DESCRIPTION.' e.g. "jane-tremblay".'),
            'name' => $schema->string()->max(255)->required(),
            'title' => $schema->string()->max(255)->description('Job title.'),
            'department' => $schema->string()->max(255),
            'tenure' => $schema->string()->max(255)->description('e.g. "since 2019", "4 years".'),
            'profile_url' => $schema->string()->max(2048)->description('Public professional profile (company bio, LinkedIn).'),
            'notes' => $schema->string()->max(2000)->description('Job-relevant context only.'),
            'fr' => $schema->object([
                'title' => $schema->string(),
                'department' => $schema->string(),
                'notes' => $schema->string(),
            ])->description('French version of title, department and notes.'),
            'source_url' => $schema->string()->max(2048)->required()->description('Page that names this person in this role.'),
        ];
    }
}
