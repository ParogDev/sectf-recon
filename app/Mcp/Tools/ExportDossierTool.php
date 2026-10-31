<?php

namespace App\Mcp\Tools;

use App\Services\DossierExporter;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;

#[Title('Export dossier')]
#[IsIdempotent]
#[IsDestructive(false)]
#[IsOpenWorld(false)]
#[Description('Write the current target\'s dossier to disk as a PDF and an HTML file in English and in French, and return where the files are (`folder.host_path`), their names, and `suggestions` for making the dossier stronger. Running it again regenerates the same files. After calling it, tell the user the folder path and file names in one or two lines, ask whether to open the PDF or the folder (open on their machine, e.g. explorer.exe on Windows/WSL, open on macOS, xdg-open on Linux), and give the top suggestions briefly. If `pdf_ok` is false, say so and point to the HTML.')]
class ExportDossierTool extends SectfTool
{
    protected string $name = 'export_dossier';

    public function __construct(private DossierExporter $exporter) {}

    public function handle(Request $request): Response
    {
        $request->validate(['lang' => ['nullable', 'in:en,fr,both']]);

        $target = $this->currentTarget($request);
        $lang = $request->get('lang', 'both');
        $result = $this->exporter->export($target, $lang === 'both' ? DossierExporter::LANGS : [$lang]);

        return $this->payload($target, [
            ...$result,
            'files' => array_map(fn (array $f) => ['lang' => $f['lang'], 'type' => $f['type'], 'name' => $f['name'], 'kb' => (int) round($f['bytes'] / 1024)], $result['files']),
        ]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'lang' => $schema->string()->enum(['en', 'fr', 'both'])->description('Which language(s) to write. Default both.'),
        ];
    }
}
