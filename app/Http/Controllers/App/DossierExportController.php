<?php

namespace App\Http\Controllers\App;

use App\Services\DossierExporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Writes the dossier files (PDF + HTML, EN + FR) and serves them back, so the
 * dashboard can say where they went and open them.
 */
class DossierExportController extends AppController
{
    public function __construct(private DossierExporter $exporter) {}

    public function store(Request $request, int $target): JsonResponse
    {
        $target = $this->target($request, $target);

        return $this->payload($target, $this->exporter->export($target));
    }

    public function file(Request $request, int $target, string $name): BinaryFileResponse
    {
        $target = $this->target($request, $target);
        $path = $this->exporter->path($target, $name);

        abort_if($path === null, 404, 'No such dossier file. Export the dossier first.');

        return response()->file($path, [
            'Content-Type' => str_ends_with($name, '.pdf') ? 'application/pdf' : 'text/html; charset=utf-8',
            'Content-Disposition' => 'inline; filename="'.$name.'"',
        ]);
    }
}
