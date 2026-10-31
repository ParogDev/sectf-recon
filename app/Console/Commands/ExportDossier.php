<?php

namespace App\Console\Commands;

use App\Models\Target;
use App\Services\DossierExporter;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Writes a target's dossier (PDF + HTML, English and French) under
 * dossier-exports/<target>/ and prints exactly where the files are.
 * scripts/dossier.sh wraps this and offers to open the PDF or the folder.
 */
#[Signature('sectf:dossier {target? : Target id (default: the only target, or the most recently updated)} {--lang=* : en and/or fr (default both)} {--json : Print the result as JSON}')]
#[Description('Export a target\'s dossier as PDF and HTML and show where the files are')]
class ExportDossier extends Command
{
    public function handle(DossierExporter $exporter): int
    {
        $id = $this->argument('target');
        $target = $id !== null ? Target::query()->find($id) : Target::query()->latest('updated_at')->first();

        if ($target === null) {
            $this->error($id !== null ? "No target with id {$id}." : 'No targets yet. Create one first.');

            return self::FAILURE;
        }

        $langs = $this->option('lang') ?: DossierExporter::LANGS;
        $result = $exporter->export($target, $langs);

        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

            return $result['pdf_ok'] ? self::SUCCESS : self::FAILURE;
        }

        $this->newLine();
        $this->info("Dossier for {$target->name} saved.");
        $this->line('  Folder: '.($result['folder']['host_path'] ?? $result['folder']['path']));

        foreach ($result['files'] as $file) {
            $this->line(sprintf('  %-4s %-5s %s (%s KB)', strtoupper($file['type']), strtoupper($file['lang']), $file['name'], number_format($file['bytes'] / 1024, 0)));
        }

        foreach ($result['warnings'] as $warning) {
            $this->warn($warning);
        }

        if ($result['suggestions'] !== []) {
            $this->newLine();
            $this->line('Next best steps:');
            foreach ($result['suggestions'] as $i => $line) {
                $this->line('  '.($i + 1).'. '.$line);
            }
        }

        return $result['pdf_ok'] ? self::SUCCESS : self::FAILURE;
    }
}
