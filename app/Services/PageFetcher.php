<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Fetches one public web page and reduces it to readable text plus the dates
 * a citation needs. Refuses private, loopback and link-local addresses so a
 * URL found on the internet can never make the app read its own network.
 */
class PageFetcher
{
    public const MAX_TEXT = 20_000;

    /**
     * @return array{url: string, final_url: string, status: int, title: ?string, last_modified: ?string, published: ?string, text: string, truncated: bool}
     */
    public function fetch(string $url): array
    {
        $this->assertPublic($url);

        $response = Http::withHeaders([
            'User-Agent' => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0 Safari/537.36',
            'Accept-Language' => 'en-CA,fr-CA;q=0.8',
        ])
            ->timeout(20)
            ->withOptions(['allow_redirects' => ['max' => 5, 'track_redirects' => true]])
            ->get($url);

        $redirects = $response->header('X-Guzzle-Redirect-History');
        $finalUrl = $redirects !== '' ? Str::afterLast($redirects, ', ') : $url;
        $this->assertPublic($finalUrl);

        $body = (string) $response->body();
        $isHtml = str_contains(strtolower($response->header('Content-Type')), 'html');

        [$title, $published, $text] = $isHtml ? $this->readHtml($body) : [null, null, $body];

        $text = trim(preg_replace("/\n{3,}/", "\n\n", preg_replace('/[ \t]+/', ' ', $text) ?? $text) ?? $text);

        return [
            'url' => $url,
            'final_url' => $finalUrl,
            'status' => $response->status(),
            'title' => $title,
            'last_modified' => $response->header('Last-Modified') ?: null,
            'published' => $published,
            'text' => mb_substr($text, 0, self::MAX_TEXT),
            'truncated' => mb_strlen($text) > self::MAX_TEXT,
        ];
    }

    /**
     * @return array{0: ?string, 1: ?string, 2: string}
     */
    private function readHtml(string $html): array
    {
        $crawler = new Crawler($html);

        $title = $crawler->filter('title')->count() ? trim($crawler->filter('title')->text()) : null;

        $published = null;
        foreach ([
            'meta[property="article:published_time"]',
            'meta[property="og:updated_time"]',
            'meta[name="date"]',
            'meta[itemprop="datePublished"]',
            'time[datetime]',
        ] as $selector) {
            $node = $crawler->filter($selector);
            if ($node->count()) {
                $published = $node->first()->attr('content') ?? $node->first()->attr('datetime');
                break;
            }
        }

        // JSON-LD often carries datePublished / JobPosting datePosted.
        if ($published === null && preg_match('/"(datePublished|datePosted|dateModified)"\s*:\s*"([^"]+)"/', $html, $m)) {
            $published = $m[2];
        }

        $crawler->filter('script, style, noscript, svg, iframe')->each(function (Crawler $node) {
            $dom = $node->getNode(0);
            $dom?->parentNode?->removeChild($dom);
        });

        $body = $crawler->filter('body');
        $text = $body->count() ? $body->text(null, false) : strip_tags($html);

        return [$title, $published, $text];
    }

    /**
     * @throws InvalidArgumentException
     */
    public function assertPublic(string $url): void
    {
        $parts = parse_url($url);
        $scheme = strtolower($parts['scheme'] ?? '');
        $host = $parts['host'] ?? null;

        if (! in_array($scheme, ['http', 'https'], true) || $host === null) {
            throw new InvalidArgumentException("Only http(s) URLs can be fetched: [{$url}].");
        }

        $host = trim($host, '[]');
        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : (gethostbynamel($host) ?: []);

        if ($ips === []) {
            throw new InvalidArgumentException("Could not resolve [{$host}].");
        }

        foreach ($ips as $ip) {
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                throw new InvalidArgumentException("Refusing to fetch [{$host}]: it resolves to a private or reserved address.");
            }
        }
    }
}
