<?php

namespace App\Mcp\Tools;

use App\Enums\PhoneKind;
use App\Services\DossierService;
use Closure;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;

#[Title('Add phone number')]
#[IsIdempotent]
#[IsDestructive(false)]
#[IsOpenWorld(false)]
#[Description('Add or update a phone number of the current target, with the public page it appears on. Only numbers in the dossier may be dialled on stage, so file every business number that might be called: switchboard, departments, help desk, a named employee\'s work line. Business numbers only, never personal ones. Number plus extension is the key: the same pair again updates it. 10-digit numbers are taken as North American (+1). person_key links it to someone filed with add_person. Set `kind` and file a mix (head office, customer service, regular stores, distribution centre, repair service): calls go better to staff who have time to talk, so prefer full stores over small commission-based formats. Add `fr` for the French label, department and notes.')]
class AddPhoneTool extends SectfTool
{
    protected string $name = 'add_phone';

    public function __construct(private DossierService $dossier) {}

    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'number' => ['required', 'string', 'max:32', function (string $attribute, mixed $value, Closure $fail) {
                $digits = strlen(preg_replace('/\D+/', '', (string) $value) ?? '');
                if ($digits < 10 || $digits > 15) {
                    $fail('The number must have 10 to 15 digits, e.g. "+1 514 555 0100". Put an extension in `extension`.');
                }
            }],
            'extension' => ['nullable', 'string', 'max:16', 'regex:/^\d+$/'],
            'label' => ['required', 'string', 'max:255'],
            'department' => ['nullable', 'string', 'max:255'],
            'person_key' => ['nullable', 'string', 'max:191'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'kind' => ['nullable', 'string', Rule::in(PhoneKind::values())],
            'fr' => ['nullable', 'array'],
            'fr.label' => ['nullable', 'string', 'max:255'],
            'fr.department' => ['nullable', 'string', 'max:255'],
            'fr.notes' => ['nullable', 'string', 'max:2000'],
            'source_url' => ['required', 'url:http,https', 'max:2048'],
        ]);

        $target = $this->currentTarget($request, write: true);
        $result = $this->dossier->upsertPhone($target, $this->user($request), $validated);
        $phone = $result['phone']->load('person:id,key,name');

        return $this->payload($target, [
            'created' => $result['created'],
            'phone' => [
                'number' => $phone->number,
                'extension' => $phone->extension,
                'label' => $phone->label,
                'department' => $phone->department,
                'person' => $phone->person === null ? null : ['key' => $phone->person->key, 'name' => $phone->person->name],
                'ring_verified_at' => $phone->ring_verified_at?->toIso8601String(),
                'source_url' => $validated['source_url'],
            ],
            'warning' => ! empty($validated['person_key']) && $phone->person === null
                ? "No person with key [{$validated['person_key']}] on this target, so the number is not linked. File them with add_person, then call add_phone again."
                : null,
        ]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'number' => $schema->string()->max(32)->required()->description('As published; it is normalised to +digits.'),
            'extension' => $schema->string()->max(16)->description('Digits only.'),
            'label' => $schema->string()->max(255)->required()->description('What this number reaches, e.g. "Main switchboard", "IT help desk".'),
            'department' => $schema->string()->max(255),
            'person_key' => $schema->string()->max(191)->description('Key of a person filed with add_person.'),
            'notes' => $schema->string()->max(2000)->description('Hours, IVR options, etc.'),
            'kind' => $schema->string()->enum(PhoneKind::values())->description('What this number reaches.'),
            'fr' => $schema->object([
                'label' => $schema->string(),
                'department' => $schema->string(),
                'notes' => $schema->string(),
            ])->description('French version of label, department and notes.'),
            'source_url' => $schema->string()->max(2048)->required()->description('Public page where this number appears.'),
        ];
    }
}
