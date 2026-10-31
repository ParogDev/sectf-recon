<?php

namespace App\Enums;

enum TargetRole: string
{
    case Owner = 'owner';
    case Editor = 'editor';
    case Viewer = 'viewer';

    public function canWrite(): bool
    {
        return $this !== self::Viewer;
    }
}
