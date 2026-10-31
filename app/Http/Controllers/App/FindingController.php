<?php

namespace App\Http\Controllers\App;

use App\Services\FindingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FindingController extends AppController
{
    public function __construct(private FindingService $findings) {}

    public function index(Request $request, int $target): JsonResponse
    {
        $validated = $request->validate([
            'flag_id' => ['nullable', 'string', 'regex:/^\d{2}-\d{2}$/'],
            'search' => ['nullable', 'string', 'max:200'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $target = $this->target($request, $target);
        $list = $this->findings->list($target, $validated['flag_id'] ?? null, $validated['search'] ?? null, (int) ($validated['limit'] ?? 50));

        return $this->payload($target, [
            'findings' => $list->map(FindingService::present(...))->all(),
        ]);
    }
}
