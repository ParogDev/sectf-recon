<?php

namespace App\Enums;

enum FlagStatus: string
{
    case Open = 'open';
    case Partial = 'partial';
    case Found = 'found';
    case Unverifiable = 'unverifiable';

    /**
     * Whether this status earns the flag's OSINT points in the report.
     */
    public function scores(): bool
    {
        return $this === self::Found;
    }
}
