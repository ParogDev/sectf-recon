<?php

namespace App\Exceptions;

use Illuminate\Auth\Access\AuthorizationException;

/**
 * The caller has no usable current target: none selected, no access, or
 * read-only access to a write. Messages say what to do next, because MCP
 * tools hand them straight back to Claude.
 *
 * An AuthorizationException so laravel/mcp returns its message as a tool
 * error even with APP_DEBUG off (other throwables become a generic error).
 */
class TargetContextException extends AuthorizationException
{
    public static function noneSelected(): self
    {
        return new self('No target selected. Call list_targets, then select_target with one of the ids (or create_target if you have none).');
    }

    public static function notAccessible(int $targetId): self
    {
        return new self("Target [{$targetId}] does not exist or is not shared with you. Call list_targets to see the ones you can use.");
    }

    public static function readOnly(string $targetName): self
    {
        return new self("You have read-only access to [{$targetName}]. Only its owner (or an editor) can write to it. Select one of your own targets to record findings.");
    }
}
