import { Check, Copy, Download, ExternalLink, FileText, FolderOpen, Loader2, TriangleAlert } from 'lucide-react';
import { useEffect, useState } from 'react';

import { Button, Dialog, ErrorBox } from '@/components/ui';
import { bytes, readinessGapText, uiLanguage } from '@/lib/format';
import type { DossierExport, DossierFile } from '@/lib/types';

async function copyText(text: string): Promise<boolean> {
    try {
        if (navigator.clipboard && window.isSecureContext) {
            await navigator.clipboard.writeText(text);

            return true;
        }
        const area = document.createElement('textarea');
        area.value = text;
        area.style.position = 'fixed';
        area.style.opacity = '0';
        document.body.appendChild(area);
        area.select();
        const ok = document.execCommand('copy');
        area.remove();

        return ok;
    } catch {
        return false;
    }
}

function useCopied(): [boolean, (text: string) => void] {
    const [copied, setCopied] = useState(false);

    useEffect(() => {
        if (!copied) return;
        const timer = window.setTimeout(() => setCopied(false), 1500);

        return () => window.clearTimeout(timer);
    }, [copied]);

    return [copied, (text) => void copyText(text).then((ok) => setCopied(ok))];
}

const ROWS: { lang: 'en' | 'fr'; type: 'pdf' | 'html'; label: string }[] = [
    { lang: 'en', type: 'pdf', label: 'PDF English' },
    { lang: 'fr', type: 'pdf', label: 'PDF French' },
    { lang: 'en', type: 'html', label: 'HTML English' },
    { lang: 'fr', type: 'html', label: 'HTML French' },
];

function FileRow({ label, file }: { label: string; file: DossierFile | undefined }) {
    return (
        <li className="flex items-center gap-2 px-3 py-2 text-sm" data-test={file ? `dossier-file-${file.lang}-${file.type}` : undefined}>
            <FileText className="size-4 shrink-0 text-zinc-400" />
            <span className="min-w-0 flex-1">
                <span className="font-medium">{label}</span>
                {file ? (
                    <span className="ms-2 text-xs tabular-nums text-zinc-500" title={file.name}>
                        {bytes(file.bytes)}
                    </span>
                ) : (
                    <span className="ms-2 text-xs text-zinc-400">not generated</span>
                )}
            </span>
            {file && (
                <>
                    <a
                        href={file.url}
                        target="_blank"
                        rel="noreferrer"
                        className="focus-ring inline-flex h-7 items-center gap-1 rounded-lg border border-zinc-300 bg-white px-2 text-xs font-medium text-zinc-800 hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100 dark:hover:bg-zinc-800"
                    >
                        <ExternalLink className="size-3" /> Open
                    </a>
                    <a href={file.url} download={file.name} className="focus-ring inline-flex h-7 items-center gap-1 rounded-lg px-2 text-xs font-medium text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-zinc-100">
                        <Download className="size-3" /> Download
                    </a>
                </>
            )}
        </li>
    );
}

function Result({ result }: { result: DossierExport }) {
    const path = result.folder.host_path ?? result.folder.path;
    const [pathCopied, copyPath] = useCopied();
    const [folderCopied, copyFolder] = useCopied();
    const lang = uiLanguage();
    const primaryPdf = result.files.find((f) => f.type === 'pdf' && f.lang === lang) ?? result.files.find((f) => f.type === 'pdf');
    const primaryHtml = result.files.find((f) => f.type === 'html' && f.lang === lang) ?? result.files.find((f) => f.type === 'html');
    const primary = primaryPdf ?? primaryHtml;
    const find = (lang: 'en' | 'fr', type: 'pdf' | 'html') => result.files.find((f) => f.lang === lang && f.type === type);

    return (
        <div className="flex flex-col gap-4 p-4">
            {!result.pdf_ok && (
                <div className="flex items-start gap-2 rounded-xl border border-amber-500/40 bg-amber-500/5 px-3 py-2 text-sm text-amber-800 dark:text-amber-200" data-test="dossier-pdf-unavailable">
                    <TriangleAlert className="mt-0.5 size-4 shrink-0" />
                    <span>The PDF engine is unavailable, so only the HTML was saved. Open it and use Print → Save as PDF to produce the file to submit.</span>
                </div>
            )}

            <section>
                <h3 className="mb-1 text-[11px] font-semibold uppercase tracking-wide text-zinc-500">Saved to</h3>
                <div className="flex items-stretch gap-2">
                    <code className="surface-muted min-w-0 flex-1 overflow-x-auto px-3 py-2 font-mono text-xs leading-5 break-all whitespace-pre-wrap" data-test="dossier-folder">
                        {path}
                    </code>
                    <Button variant="secondary" onClick={() => copyPath(path)} title="Copy the folder path" aria-label="Copy the folder path" className="h-auto shrink-0">
                        {pathCopied ? <Check className="size-3.5 text-emerald-600" /> : <Copy className="size-3.5" />}
                        {pathCopied ? 'Copied' : 'Copy'}
                    </Button>
                </div>
                {result.folder.host_path === null && <p className="mt-1 text-xs text-zinc-500">Path inside the app container; no host mapping was reported.</p>}
            </section>

            <section>
                <h3 className="mb-1 text-[11px] font-semibold uppercase tracking-wide text-zinc-500">Files</h3>
                <ul className="surface divide-y divide-zinc-100 dark:divide-zinc-800/80">
                    {ROWS.map((row) => (
                        <FileRow key={`${row.lang}-${row.type}`} label={row.label} file={find(row.lang, row.type)} />
                    ))}
                </ul>
            </section>

            <div className="flex flex-wrap items-center gap-2">
                {primary && (
                    <a
                        href={primary.url}
                        target="_blank"
                        rel="noreferrer"
                        data-test="dossier-open-primary"
                        className="focus-ring inline-flex h-8 items-center gap-1.5 rounded-lg bg-zinc-900 px-3 text-sm font-medium text-white hover:bg-zinc-700 dark:bg-zinc-100 dark:text-zinc-900 dark:hover:bg-white"
                    >
                        <ExternalLink className="size-3.5" /> {primary.type === 'pdf' ? 'Open PDF' : 'Open HTML'}
                        <span className="text-[10px] font-semibold uppercase opacity-70">{primary.lang}</span>
                    </a>
                )}
                <Button variant="secondary" onClick={() => copyFolder(path)} data-test="dossier-show-folder">
                    {folderCopied ? <Check className="size-3.5 text-emerald-600" /> : <FolderOpen className="size-3.5" />}
                    {folderCopied ? 'Path copied' : 'Show folder'}
                </Button>
                <p className="basis-full text-xs text-zinc-500">A browser cannot open folders itself: paste the copied path into your file manager (Explorer, Finder) to see the files.</p>
            </div>

            {result.warnings.length > 0 && (
                <ul className="flex flex-col gap-1 text-xs text-amber-700 dark:text-amber-300" data-test="dossier-warnings">
                    {result.warnings.map((w) => (
                        <li key={w}>{w}</li>
                    ))}
                </ul>
            )}

            {result.readiness.gaps.length > 0 && (
                <section className="surface-muted px-3 py-2" data-test="dossier-readiness-gaps">
                    <h3 className="text-[11px] font-semibold uppercase tracking-wide text-zinc-500">Could still be stronger</h3>
                    <ul className="mt-1 list-disc space-y-0.5 ps-4 text-sm text-zinc-700 dark:text-zinc-300">
                        {result.readiness.gaps.map((gap) => (
                            <li key={gap.code}>{readinessGapText(gap)}</li>
                        ))}
                    </ul>
                    <p className="mt-1 text-xs text-zinc-500">Suggestions only; the files above are complete as they stand.</p>
                </section>
            )}
        </div>
    );
}

export function ExportDossierDialog({
    open,
    onClose,
    status,
    result,
    error,
    onRetry,
}: {
    open: boolean;
    onClose: () => void;
    status: 'idle' | 'pending' | 'error' | 'success';
    result: DossierExport | undefined;
    error: Error | null;
    onRetry: () => void;
}) {
    return (
        <Dialog open={open} onClose={onClose} title={status === 'success' ? 'Dossier saved' : 'Export dossier'} testId="export-dossier-dialog">
            {status === 'pending' || status === 'idle' ? (
                <div className="flex flex-col items-center gap-3 px-4 py-12 text-sm text-zinc-500" data-test="export-dossier-pending">
                    <Loader2 className="size-6 animate-spin text-zinc-400" />
                    <p>Rendering the dossier in English and French…</p>
                    <p className="text-xs text-zinc-400">PDF generation can take a few seconds.</p>
                </div>
            ) : status === 'error' ? (
                <div className="flex flex-col gap-3 p-4" data-test="export-dossier-error">
                    <ErrorBox message={error?.message ?? 'The export failed.'} />
                    <p className="text-xs text-zinc-500">Nothing was changed. You can still open the live dossier from the sidebar and use Print → Save as PDF.</p>
                    <div className="flex gap-2">
                        <Button variant="primary" onClick={onRetry}>
                            Try again
                        </Button>
                        <Button variant="secondary" onClick={onClose}>
                            Close
                        </Button>
                    </div>
                </div>
            ) : result ? (
                <Result result={result} />
            ) : null}
        </Dialog>
    );
}
