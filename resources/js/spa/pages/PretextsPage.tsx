import { ChevronDown, CornerDownRight, MessageSquareText, PhoneCall } from 'lucide-react';
import { useMemo, useState } from 'react';
import { useSearchParams } from 'react-router';

import { FlagDrawer } from '@/components/board/FlagDrawer';
import { Confidence } from '@/components/feed/FindingCard';
import { EmptyState, ErrorBox, Loading, PageHeader } from '@/components/ui';
import { useNow } from '@/hooks/useNow';
import { ago, formatPhone, points, STATUS } from '@/lib/format';
import { usePretexts, useTarget } from '@/lib/queries';
import type { Flag, Pretext } from '@/lib/types';

function FlagChip({ id, flag, onOpen }: { id: string; flag: Flag | undefined; onOpen: (id: string) => void }) {
    const meta = STATUS[flag?.status ?? 'open'];

    return (
        <button
            type="button"
            onClick={() => onOpen(id)}
            title={flag ? `${flag.question} · ${points(flag.osint_points)} OSINT pts · ${meta.label}` : id}
            className={`focus-ring inline-flex items-center gap-1 rounded-md px-1.5 py-0.5 font-mono text-[11px] ring-1 ring-inset ${meta.badge}`}
        >
            <span className={`size-1.5 rounded-full ${meta.dot}`} />
            {id}
        </button>
    );
}

function PretextCard({ pretext, flags, now, onOpenFlag }: { pretext: Pretext; flags: Map<string, Flag>; now: number; onOpenFlag: (id: string) => void }) {
    const [open, setOpen] = useState(false);
    const pivots = (pretext.pivots ?? []).filter((p) => !pretext.flag_ids.includes(p.flag_id));

    return (
        <article className="surface flex flex-col gap-2 p-4" data-test={`pretext-${pretext.key}`}>
            <div className="flex items-start gap-3">
                <div className="min-w-0 flex-1">
                    <h2 className="font-semibold">{pretext.title}</h2>
                    <p className="text-sm text-zinc-600 dark:text-zinc-400">
                        <span className="text-zinc-400">as</span> {pretext.persona}
                    </p>
                </div>
                <Confidence value={pretext.confidence} />
            </div>

            {pretext.flag_ids.length > 0 && (
                <div className="flex flex-wrap gap-1">
                    {pretext.flag_ids.map((id) => (
                        <FlagChip key={id} id={id} flag={flags.get(id)} onOpen={onOpenFlag} />
                    ))}
                </div>
            )}

            {pivots.length > 0 && (
                <div className="surface-muted px-3 py-2" data-test="pretext-pivots">
                    <div className="mb-1 flex items-center gap-1.5 text-[11px] font-semibold uppercase tracking-wide text-zinc-500">
                        <CornerDownRight className="size-3" /> Can naturally pivot to
                        <span className="rounded-full bg-zinc-200 px-1.5 text-[10px] tabular-nums text-zinc-600 dark:bg-zinc-700 dark:text-zinc-300">{pivots.length}</span>
                    </div>
                    <ul className="flex flex-col gap-1.5">
                        {pivots.map((pivot) => {
                            const flag = flags.get(pivot.flag_id);

                            return (
                                <li key={pivot.flag_id} className="flex flex-col gap-0.5 text-sm">
                                    <div className="flex flex-wrap items-center gap-x-2 gap-y-0.5">
                                        <FlagChip id={pivot.flag_id} flag={flag} onOpen={onOpenFlag} />
                                        {flag && (
                                            <>
                                                <span className="min-w-0 text-zinc-700 dark:text-zinc-300">{flag.question}</span>
                                                <span className="text-xs tabular-nums text-zinc-500">{points(flag.osint_points)} pts</span>
                                            </>
                                        )}
                                    </div>
                                    <p className="text-xs italic text-zinc-500">“{pivot.bridge}”</p>
                                </li>
                            );
                        })}
                    </ul>
                </div>
            )}

            {pretext.phone_numbers.length > 0 && (
                <ul className="flex flex-wrap gap-x-4 gap-y-1 text-xs">
                    {pretext.phone_numbers.map((phone) => (
                        <li key={phone.id} className="inline-flex items-center gap-1 text-zinc-600 dark:text-zinc-400">
                            <PhoneCall className="size-3" />
                            <span className="font-mono">{formatPhone(phone.number, phone.extension)}</span>
                            <span className="text-zinc-400">{phone.label}</span>
                        </li>
                    ))}
                </ul>
            )}

            {pretext.script && (
                <div>
                    <button type="button" onClick={() => setOpen((v) => !v)} className="focus-ring inline-flex items-center gap-1 rounded text-xs font-medium text-zinc-600 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-zinc-100">
                        <ChevronDown className={`size-3.5 transition-transform ${open ? 'rotate-180' : ''}`} />
                        {open ? 'Hide script' : 'Show script'}
                    </button>
                    {open && <pre className="surface-muted mt-2 overflow-x-auto px-3 py-2 font-sans text-sm leading-relaxed whitespace-pre-wrap">{pretext.script}</pre>}
                </div>
            )}

            <div className="flex items-center gap-2 text-[11px] text-zinc-500">
                <span>{pretext.by ?? 'unknown'}</span>
                <span>·</span>
                <span>{ago(pretext.updated_at, now)}</span>
                {pretext.untranslated && (
                    <span className="rounded border border-dotted border-zinc-400 px-1 text-[10px] text-zinc-500" title="No French rendering filed; the French dossier shows this pretext in English">
                        EN only
                    </span>
                )}
                <code className="ms-auto font-mono text-[10px] text-zinc-400">{pretext.key}</code>
            </div>
        </article>
    );
}

export function PretextsPage({ targetId }: { targetId: number }) {
    const now = useNow();
    const target = useTarget(targetId);
    const pretexts = usePretexts(targetId);
    const [params, setParams] = useSearchParams();
    const drawerId = params.get('open');

    const flags = useMemo(() => new Map((target.data?.flags ?? []).map((f) => [f.id, f])), [target.data]);
    const drawerFlag = drawerId ? (flags.get(drawerId) ?? null) : null;
    const setOpen = (id: string | null) =>
        setParams(
            (prev) => {
                const next = new URLSearchParams(prev);
                if (id) next.set('open', id);
                else next.delete('open');

                return next;
            },
            { replace: true },
        );

    return (
        <div className="flex flex-col gap-4">
            <PageHeader title="Pretexts" subtitle="Call plans for the live phase: who you are, what you want, which numbers you dial." />

            {pretexts.isError ? (
                <ErrorBox message={pretexts.error.message} />
            ) : pretexts.isPending ? (
                <Loading />
            ) : pretexts.data.length === 0 ? (
                <EmptyState title="No pretexts yet" icon={<MessageSquareText className="size-6" />}>
                    Plans written with add_pretext show up here with the flags they chase and the numbers they need.
                </EmptyState>
            ) : (
                <div className="grid gap-3 lg:grid-cols-2">
                    {pretexts.data.map((pretext) => (
                        <PretextCard key={pretext.id} pretext={pretext} flags={flags} now={now} onOpenFlag={setOpen} />
                    ))}
                </div>
            )}

            <FlagDrawer targetId={targetId} flag={drawerFlag} canWrite={(target.data?.target.role ?? 'viewer') !== 'viewer'} onClose={() => setOpen(null)} onOpenFlag={setOpen} />
        </div>
    );
}
