<?php

namespace App\Http\Controllers\App;

use App\Services\DossierService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PretextController extends AppController
{
    public function __construct(private DossierService $dossier) {}

    public function index(Request $request, int $target): JsonResponse
    {
        $target = $this->target($request, $target);

        return $this->payload($target, [
            'pretexts' => $this->dossier->pretexts($target),
        ]);
    }
}
