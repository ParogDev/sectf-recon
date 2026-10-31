<?php

namespace App\Http\Controllers\App;

use App\Enums\FlagStatus;
use App\Models\Flag;
use App\Services\FlagBoard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FlagController extends AppController
{
    public function __construct(private FlagBoard $flags) {}

    /**
     * Set a flag's status by hand (a human confirming or overruling what the
     * findings suggest). Unlike FlagBoard::promote this can lower a status.
     */
    public function update(Request $request, int $target, Flag $flag): JsonResponse
    {
        $target = $this->target($request, $target, write: true);

        $validated = $request->validate([
            'status' => ['required', Rule::enum(FlagStatus::class)],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $this->flags->setStatus($target, $this->user($request), $flag->id, FlagStatus::from($validated['status']), $validated['notes'] ?? null, 'web');

        return $this->payload($target, [
            'flag' => $this->flags->board($target)->firstWhere('id', $flag->id),
        ]);
    }
}
