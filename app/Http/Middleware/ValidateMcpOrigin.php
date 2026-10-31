<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * DNS-rebinding defence for Streamable HTTP: a browser request whose Origin is
 * not this app (or listed in config/mcp.php) gets a 403 before auth runs.
 * Claude Code sends no Origin, so those requests go on to the bearer check.
 */
class ValidateMcpOrigin
{
    private const DEFAULT_PORTS = ['http' => 80, 'https' => 443];

    public function handle(Request $request, Closure $next): Response
    {
        $origin = (string) $request->headers->get('Origin');

        if ($origin === '' || $this->isAllowed($origin)) {
            return $next($request);
        }

        return response()->json([
            'jsonrpc' => '2.0',
            'id' => null,
            'error' => ['code' => -32600, 'message' => 'Origin not allowed.'],
        ], 403);
    }

    private function isAllowed(string $origin): bool
    {
        $canonical = $this->canonical($origin);

        if ($canonical === null) {
            return false;
        }

        $allowed = [config('app.url'), ...(array) config('mcp.allowed_origins', [])];

        return collect($allowed)
            ->map(fn (mixed $url): ?string => $this->canonical((string) $url))
            ->contains($canonical);
    }

    /**
     * Lowercase scheme://host:port, always with the port, so that an explicit
     * default port compares equal to none. Null for anything without a scheme
     * and host, such as the literal "null" Origin.
     */
    private function canonical(string $url): ?string
    {
        $scheme = strtolower((string) parse_url(trim($url), PHP_URL_SCHEME));
        $host = strtolower((string) parse_url(trim($url), PHP_URL_HOST));

        if ($scheme === '' || $host === '') {
            return null;
        }

        $port = parse_url(trim($url), PHP_URL_PORT) ?? self::DEFAULT_PORTS[$scheme] ?? 0;

        return "{$scheme}://{$host}:{$port}";
    }
}
