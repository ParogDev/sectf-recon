import clsx from 'clsx';
import { CalendarDays, ChevronDown, Link as LinkIcon, Quote } from 'lucide-react';
import { useState } from 'react';

import { absolute, ago, confidenceBar, confidenceTone, host } from '@/lib/format';
import type { Finding, Source } from '@/lib/types';

export function SourceLink({ source, compact = false }: { source: Source | { url: string; title: string | null; retrieved_at: string; content_date?: string | null; quote?: string | null }; compact?: boolean }) {
    return (
        <li className="flex min-w-0 flex-col gap-0.5 text-xs">
            <span className="flex min-w-0 items-center gap-1">
                <LinkIcon className="size-3 shrink-0 text-zinc-400" />
                <a
                    href={source.url}
                    target="_blank"
                    rel="noreferrer"
                    title={source.url}
                    className="min-w-0 truncate text-sky-600 hover:underline dark:text-sky-400"
                >
                    {source.title || source.url}
                </a>
                <span className="shrink-0 text-zinc-400">{host(source.url)}</span>
                {!compact && (
                    <span className="ms-auto shrink-0 text-[11px] text-zinc-400" title={`Retrieved ${absolute(source.retrieved_at)}`}>
                        seen {ago(source.retrieved_at)}
                        {source.content_date ? ` · dated ${source.content_date}` : ''}
                    </span>
                )}
            </span>
            {!compact && source.quote && (
                <span className="ms-4 flex gap-1 text-zinc-500 italic">
                    <Quote className="mt-0.5 size-3 shrink-0" />
                    <span className="line-clamp-3">{source.quote}</span>
                </span>
            )}
        </li>
    );
}

export function Confidence({ value, className }: { value: number; className?: string }) {
    const pct = Math.round(value * 100);

    return (
        <span className={clsx('inline-flex items-center gap-1.5', className)} title={`confidence ${pct}%`}>
            <span className="h-1 w-10 overflow-hidden rounded-full bg-zinc-200 dark:bg-zinc-800">
                <span className={clsx('block h-full rounded-full', confidenceBar(value))} style={{ width: `${pct}%` }} />
            </span>
            <span className={clsx('text-xs font-medium tabular-nums', confidenceTone(value))}>{pct}%</span>
        </span>
    );
}

export function FindingCard({ finding, now, onOpenFlag, compact = false, defaultOpen = false }: { finding: Finding; now: number; onOpenFlag?: (id: string) => void; compact?: boolean; defaultOpen?: boolean }) {
    const [open, setOpen] = useState(defaultOpen);
    const expandable = compact && (finding.detail || finding.sources.length > 1);

    return (
        <article className={clsx('flex flex-col gap-1.5 px-3 text-sm', compact ? 'py-2' : 'py-3')} data-test={`finding-${finding.key}`}>
            <div className="flex items-start gap-2">
                {finding.flag_id &&
                    (onOpenFlag ? (
                        <button
                            type="button"
                            onClick={() => onOpenFlag(finding.flag_id as string)}
                            className="focus-ring shrink-0 rounded bg-zinc-100 px-1 font-mono text-xs text-zinc-700 hover:bg-zinc-200 dark:bg-zinc-800 dark:text-zinc-300 dark:hover:bg-zinc-700"
                            title="Open flag"
                        >
                            {finding.flag_id}
                        </button>
                    ) : (
                        <code className="shrink-0 rounded bg-zinc-100 px-1 font-mono text-xs dark:bg-zinc-800">{finding.flag_id}</code>
                    ))}
                <p className={clsx('min-w-0 flex-1 leading-snug', compact && !open && 'line-clamp-3')}>{finding.claim}</p>
                <Confidence value={finding.confidence} className="shrink-0" />
            </div>

            {finding.detail && (!compact || open) && <p className="text-xs leading-relaxed text-zinc-600 dark:text-zinc-400">{finding.detail}</p>}

            <div className="flex flex-wrap items-center gap-x-2 gap-y-0.5 text-[11px] text-zinc-500">
                <span className="font-medium text-zinc-600 dark:text-zinc-400">{finding.by ?? 'unknown'}</span>
                <span>·</span>
                <time dateTime={finding.updated_at} title={absolute(finding.updated_at)}>
                    {ago(finding.updated_at, now)}
                </time>
                {finding.content_date && (
                    <>
                        <span>·</span>
                        <span className="inline-flex items-center gap-1" title="Date of the content">
                            <CalendarDays className="size-3" /> dated {finding.content_date}
                        </span>
                    </>
                )}
                <code className="ms-auto font-mono text-[10px] text-zinc-400" title="finding key">
                    {finding.key}
                </code>
            </div>

            <ul className="flex flex-col gap-0.5">
                {(compact && !open ? finding.sources.slice(0, 1) : finding.sources).map((source) => (
                    <SourceLink key={source.url} source={source} compact={compact && !open} />
                ))}
            </ul>

            {expandable && (
                <button type="button" onClick={() => setOpen((v) => !v)} className="focus-ring -mt-0.5 inline-flex items-center gap-0.5 self-start rounded text-[11px] text-zinc-500 hover:text-zinc-900 dark:hover:text-zinc-100">
                    <ChevronDown className={clsx('size-3 transition-transform', open && 'rotate-180')} />
                    {open ? 'less' : `more${finding.sources.length > 1 ? ` · ${finding.sources.length} sources` : ''}`}
                </button>
            )}
        </article>
    );
}
