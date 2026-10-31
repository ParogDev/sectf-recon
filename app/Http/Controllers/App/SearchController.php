<?php

namespace App\Http\Controllers\App;

use App\Models\SearchLog;
use App\Services\DossierService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends AppController
{
    public function __construct(private DossierService $dossier) {}

    public function index(Request $request, int $target): JsonResponse
    {
        $validated = $request->validate([
            'contains' => ['nullable', 'string', 'max:200'],
            'flag_id' => ['nullable', 'string', 'regex:/^\d{2}-\d{2}$/'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $target = $this->target($request, $target);
        $list = $this->dossier->searches($target, $validated['contains'] ?? null, $validated['flag_id'] ?? null, (int) ($validated['limit'] ?? 50));

        return $this->payload($target, [
            'searches' => $list->map(fn (SearchLog $s) => [
                'id' => $s->id,
                'channel' => $s->channel,
                'query' => $s->query,
                'flag_id' => $s->flag_id,
                'result_count' => $s->result_count,
                'notes' => $s->notes,
                'by' => $s->user?->name,
                'at' => $s->created_at->toIso8601String(),
            ])->all(),
        ]);
    }
}
