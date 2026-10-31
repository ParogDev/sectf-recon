<?php

namespace App\Services;

use App\Models\Target;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

/**
 * Writes the dossier to disk as a PDF and a standalone HTML file per language,
 * and says where they are. The PDF is printed by headless Chromium from the
 * same HTML the browser shows, so the two always match.
 */
class DossierExporter
{
    public const LANGS = ['en', 'fr'];

    public function __construct(
        private DossierReport $report,
        private FlagBoard $flags,
    ) {}

    /**
     * @param  array<int, string>  $langs
     * @return array<string, mixed>
     */
    public function export(Target $target, array $langs = self::LANGS): array
    {
        $slug = Str::slug($target->name) ?: 'target-'.$target->id;
        $dir = rtrim((string) config('sectf.dossier_dir'), '/').'/'.$slug;
        File::ensureDirectoryExists($dir, 0775);

        $original = app()->getLocale();
        $files = [];
        $warnings = [];
        $pdfOk = true;
        $reports = [];

        try {
            foreach ($langs as $lang) {
                app()->setLocale($lang);
                $reports[$lang] = $this->report->build($target);
                $html = view('dossier', ['report' => $reports[$lang]])->render();
                $base = "{$slug}-{$lang}";

                file_put_contents("{$dir}/{$base}.html", $html);
                $files[] = $this->describe($dir, "{$base}.html", $lang, 'html', $target);

                $error = $this->printPdf("{$dir}/{$base}.html", "{$dir}/{$base}.pdf");
                if ($error === null) {
                    $files[] = $this->describe($dir, "{$base}.pdf", $lang, 'pdf', $target);
                } else {
                    $pdfOk = false;
                    $warnings[] = $error;
                }
            }
        } finally {
            app()->setLocale($original);
        }

        $primary = $reports[$langs[0]] ?? [];
        $hostDir = config('sectf.host_dir');

        return [
            'generated_at' => now()->toIso8601String(),
            'pdf_ok' => $pdfOk,
            'folder' => [
                'path' => 'dossier-exports/'.$slug,
                'host_path' => $hostDir ? $this->hostPath((string) $hostDir, $slug) : null,
            ],
            'files' => $files,
            'readiness' => $primary['readiness'] ?? null,
            'warnings' => array_values(array_unique($warnings)),
            'suggestions' => $primary === [] ? [] : $this->suggestions($target, $primary),
        ];
    }

    /** Absolute path of an exported file, or null if the name is not one of this target's exports. */
    public function path(Target $target, string $name): ?string
    {
        $slug = Str::slug($target->name) ?: 'target-'.$target->id;

        if (! preg_match('/^'.preg_quote($slug, '/').'-(en|fr)\.(pdf|html)$/', $name)) {
            return null;
        }

        $path = rtrim((string) config('sectf.dossier_dir'), '/').'/'.$slug.'/'.$name;

        return is_file($path) ? $path : null;
    }

    /**
     * Short, concrete next steps drawn from what the dossier lacks.
     *
     * @param  array<string, mixed>  $report
     * @return array<int, string>
     */
    public function suggestions(Target $target, array $report): array
    {
        $out = [];

        foreach ($report['readiness']['gaps'] as $gap) {
            $out[] = match ($gap['code']) {
                'pretexts_min' => "Write {$this->more($gap['need'] - $gap['have'])} more pretext(s): {$gap['have']} of {$gap['need']} minimum, each with a different persona and number.",
                'pretexts_no_pivots' => 'Add pivot flags and bridge lines to '.implode(', ', $gap['refs']).'.',
                'phone_kinds' => "Numbers cover {$gap['have']} kind(s); add full stores, a distribution centre, customer service or repair lines (aim for {$gap['need']}+).",
                'small_format_only' => 'Only small-format stores are filed; add full-size store numbers, where staff have time to talk.',
                'fr_missing' => "Add French text on {$gap['count']} item(s) so the French dossier is entirely French.",
                'research_incomplete' => 'Research did not finish on '.implode(', ', $gap['flags']).' (blocked or failed); these need a person to look into them.',
                default => $gap['code'],
            };
        }

        $uncovered = $report['pretext_coverage']['uncovered'] ?? [];
        if ($uncovered !== []) {
            $top = collect($uncovered)->sortByDesc('points')->take(4)->pluck('id')->implode(', ');
            $out[] = count($uncovered).' unresolved flag(s) have no pretext that reaches them (highest value: '.$top.').';
        }

        $next = $this->flags->nextBest($target, 3)->pluck('id')->implode(', ');
        if ($next !== '') {
            $out[] = "Best next research targets: {$next}.";
        }

        return $out;
    }

    /** The export folder as the user sees it: a Windows host path keeps backslashes, a Unix one slashes. */
    private function hostPath(string $hostDir, string $slug): string
    {
        $sep = str_contains($hostDir, '\\') ? '\\' : '/';

        return rtrim($hostDir, '/\\').$sep.'dossier-exports'.$sep.$slug;
    }

    private function more(int $n): int
    {
        return max(1, $n);
    }

    /**
     * @return array<string, mixed>
     */
    private function describe(string $dir, string $name, string $lang, string $type, Target $target): array
    {
        return [
            'lang' => $lang,
            'type' => $type,
            'name' => $name,
            'bytes' => filesize("{$dir}/{$name}"),
            'url' => "/api/app/targets/{$target->id}/dossier/files/{$name}",
        ];
    }

    /** Null on success, otherwise a one-line reason. */
    private function printPdf(string $html, string $pdf): ?string
    {
        $chromium = (string) config('sectf.chromium');

        if (! is_executable($chromium)) {
            return 'PDF engine not installed (chromium missing). HTML was saved; open it and use Print, Save as PDF, or rebuild the app image.';
        }

        if (is_file($pdf)) {
            unlink($pdf);
        }
        $profile = sys_get_temp_dir().'/sectf-chromium-'.getmypid();

        $process = new Process([
            $chromium, '--headless=new', '--no-sandbox', '--disable-gpu', '--disable-dev-shm-usage',
            '--no-pdf-header-footer', "--user-data-dir={$profile}", '--hide-scrollbars',
            "--print-to-pdf={$pdf}", 'file://'.$html,
        ]);
        $process->setTimeout(90);

        try {
            $process->run();
        } catch (\Throwable $e) {
            return 'PDF engine failed: '.Str::limit($e->getMessage(), 160);
        } finally {
            File::deleteDirectory($profile);
        }

        if (! is_file($pdf) || filesize($pdf) === 0) {
            return 'PDF engine produced no file: '.Str::limit(trim($process->getErrorOutput()), 160);
        }

        return null;
    }
}
