<?php

namespace App\Http\Controllers\App;

use App\Services\DossierReport;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The Phase 1 dossier: the same report as JSON for the SPA and as a
 * standalone, print-ready HTML document (File → Print → Save as PDF is the
 * submission path). `?lang=fr` renders the template and the flag catalogue
 * in French; filed content stays as written.
 */
class DossierController extends AppController
{
    public function __construct(private DossierReport $report) {}

    public function show(Request $request, int $target): JsonResponse
    {
        $target = $this->target($request, $target);
        $this->setLocale($request);

        return response()->json($this->report->build($target));
    }

    public function html(Request $request, int $target): View
    {
        $target = $this->target($request, $target);
        $this->setLocale($request);

        return view('dossier', ['report' => $this->report->build($target)]);
    }

    private function setLocale(Request $request): void
    {
        $lang = $request->query('lang', 'en');

        abort_unless(in_array($lang, ['en', 'fr'], true), 422, 'lang must be en or fr.');

        app()->setLocale($lang);
    }
}
