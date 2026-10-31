<?php

namespace App\Http\Controllers\App;

use App\Models\Person;
use App\Models\PhoneNumber;
use App\Services\DossierService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DirectoryController extends AppController
{
    public function __construct(private DossierService $dossier) {}

    public function people(Request $request, int $target): JsonResponse
    {
        $target = $this->target($request, $target);

        return $this->payload($target, [
            'people' => $this->dossier->people($target)->map(fn (Person $p) => DossierService::presentPerson($p))->all(),
        ]);
    }

    public function phones(Request $request, int $target): JsonResponse
    {
        $target = $this->target($request, $target);

        return $this->payload($target, [
            'phones' => $this->dossier->phones($target)->map(fn (PhoneNumber $p) => DossierService::presentPhone($p))->all(),
        ]);
    }

    /**
     * A human confirmed the number rings. Claude never dials: recon stays passive.
     */
    public function verifyPhone(Request $request, int $target, int $phone): JsonResponse
    {
        $target = $this->target($request, $target, write: true);
        $phone = $this->dossier->phone($target, $phone);

        $phone = $this->dossier->markRingVerified($target, $this->user($request), $phone, 'web');

        return $this->payload($target, [
            'phone' => DossierService::presentPhone($phone->load(['source:id,url,title,retrieved_at', 'person:id,key,name'])),
        ]);
    }
}
