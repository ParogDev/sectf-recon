import clsx from 'clsx';
import { Crosshair } from 'lucide-react';

import { ProgressBar } from '@/components/ui';
import { percent, points, STATUS } from '@/lib/format';
import type { Flag, Summary } from '@/lib/types';

function Stat({ label, value, tone, hint }: { label: string; value: string; tone?: string; hint?: string }) {
    return (
        <div className="surface flex flex-col px-3 py-2.5" title={hint}>
            <span className="text-[11px] font-medium uppercase tracking-wide text-zinc-500">{label}</span>
            <span className={clsx('text-2xl font-semibold tabular-nums leading-tight', tone)}>{value}</span>
        </div>
    );
}

export function KpiStrip({ summary, nextBest, flags, onOpenFlag }: { summary: Summary; nextBest: string[]; flags: Flag[]; onOpenFlag: (id: string) => void }) {
    const pct = percent(summary.osint_points_banked, summary.osint_points_available);
    const byId = new Map(flags.map((f) => [f.id, f]));
    const unverifiable = flags.filter((f) => !f.call_only && f.status === 'unverifiable').length;

    return (
        <div className="grid grid-cols-2 gap-2 sm:grid-cols-6 xl:grid-cols-8" data-test="summary">
            <div className="surface col-span-2 flex flex-col justify-between px-3 py-2.5 sm:col-span-3 xl:col-span-3">
                <div className="flex items-baseline justify-between">
                    <span className="text-[11px] font-medium uppercase tracking-wide text-zinc-500">OSINT points</span>
                    <span className="text-[11px] tabular-nums text-zinc-500">{pct}%</span>
                </div>
                <div className="flex items-baseline gap-1.5">
                    <span className="text-3xl font-semibold tabular-nums leading-tight text-emerald-600 dark:text-emerald-400">{points(summary.osint_points_banked)}</span>
                    <span className="text-sm text-zinc-500">/ {points(summary.osint_points_available)} banked</span>
                </div>
                <ProgressBar value={summary.osint_points_banked} max={summary.osint_points_available} className="mt-1.5" />
            </div>

            <Stat label="Found" value={String(summary.flags_found)} tone={STATUS.found.text} hint="Flags with a finding at or above 70% confidence" />
            <Stat label="Partial" value={String(summary.flags_partial)} tone={STATUS.partial.text} hint="Flags with weaker evidence" />
            <Stat label="Open" value={String(summary.flags_open)} hint="Flags with nothing filed yet" />
            {unverifiable > 0 ? (
                <Stat label="Unverif." value={String(unverifiable)} tone={STATUS.unverifiable.text} hint="Flags marked unverifiable" />
            ) : (
                <div className="hidden xl:block" />
            )}

            <div className="surface col-span-2 flex flex-col px-3 py-2.5 sm:col-span-6 xl:col-span-2">
                <span className="flex items-center gap-1 text-[11px] font-medium uppercase tracking-wide text-zinc-500">
                    <Crosshair className="size-3" /> Hunt next
                </span>
                <div className="mt-1 flex flex-wrap gap-1">
                    {nextBest.length === 0 && <span className="text-xs text-zinc-500">Nothing left to hunt</span>}
                    {nextBest.map((id) => {
                        const flag = byId.get(id);

                        return (
                            <button
                                key={id}
                                type="button"
                                onClick={() => onOpenFlag(id)}
                                title={flag?.question}
                                className={clsx(
                                    'focus-ring inline-flex items-center gap-1 rounded-md border px-1.5 py-0.5 font-mono text-[11px] transition-colors',
                                    flag?.status === 'partial'
                                        ? 'border-amber-500/40 bg-amber-500/10 text-amber-700 hover:bg-amber-500/20 dark:text-amber-300'
                                        : 'border-zinc-300 hover:bg-zinc-100 dark:border-zinc-700 dark:hover:bg-zinc-800',
                                )}
                            >
                                {id}
                                <span className="font-sans text-[10px] text-zinc-500">{flag ? points(flag.osint_points) : ''}</span>
                            </button>
                        );
                    })}
                </div>
            </div>
        </div>
    );
}
