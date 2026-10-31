import clsx from 'clsx';
import { ArrowUpRight, Link2, Phone, Save, Search } from 'lucide-react';
import { useEffect, useState } from 'react';
import { Link } from 'react-router';

import { FindingCard } from '@/components/feed/FindingCard';
import { Badge, Button, Loading, Segmented, Sheet, Tag } from '@/components/ui';
import { useToast } from '@/context/ToastContext';
import { useNow } from '@/hooks/useNow';
import { ago, points, STATUS, STATUS_ORDER } from '@/lib/format';
import { useFindings, useSearches, useUpdateFlag } from '@/lib/queries';
import type { Flag, FlagStatus } from '@/lib/types';

export function FlagDrawer({
    targetId,
    flag,
    canWrite,
    onClose,
    onOpenFlag,
}: {
    targetId: number;
    flag: Flag | null;
    canWrite: boolean;
    onClose: () => void;
    onOpenFlag: (id: string) => void;
}) {
    return (
        <Sheet
            open={flag !== null}
            onClose={onClose}
            title={
                flag && (
                    <div className="flex flex-col gap-1">
                        <div className="flex flex-wrap items-center gap-1.5 text-xs text-zinc-500">
                            <code className="font-mono">{flag.id}</code>
                            <span>·</span>
                            <span>{flag.category}</span>
                            {flag.bonus && <Tag className="bg-violet-500/15 text-violet-700 dark:text-violet-300">bonus</Tag>}
                            {flag.call_only && (
                                <Tag className="bg-zinc-200 text-zinc-500 dark:bg-zinc-800">
                                    <Phone className="me-0.5 inline size-2.5" /> call only
                                </Tag>
                            )}
                        </div>
                        <h2 className="text-base font-semibold leading-snug">{flag.question}</h2>
                    </div>
                )
            }
        >
            {flag && <FlagBody key={flag.id} targetId={targetId} flag={flag} canWrite={canWrite} onOpenFlag={onOpenFlag} />}
        </Sheet>
    );
}

function FlagBody({ targetId, flag, canWrite, onOpenFlag }: { targetId: number; flag: Flag; canWrite: boolean; onOpenFlag: (id: string) => void }) {
    const now = useNow();
    const toast = useToast();
    const findings = useFindings(targetId, { flagId: flag.id, limit: 50 });
    const searches = useSearches(targetId, { flagId: flag.id, limit: 20 });
    const update = useUpdateFlag(targetId);

    const [status, setStatus] = useState<FlagStatus>(flag.status);
    const [notes, setNotes] = useState(flag.notes ?? '');

    useEffect(() => {
        setStatus(flag.status);
        setNotes(flag.notes ?? '');
    }, [flag.status, flag.notes]);

    const dirty = status !== flag.status || notes !== (flag.notes ?? '');
    const meta = STATUS[flag.status];

    const save = () => {
        update.mutate(
            { flagId: flag.id, status, notes: notes.trim() || null },
            {
                onSuccess: () => toast.push({ title: `${flag.id} → ${status}`, tone: 'success' }),
                onError: (error) => toast.push({ title: 'Could not update flag', body: error.message, tone: 'danger' }),
            },
        );
    };

    return (
        <div className="flex flex-col gap-5 p-4">
            <dl className="grid grid-cols-3 gap-2 text-sm">
                <div className="surface-muted px-3 py-2">
                    <dt className="text-[11px] uppercase tracking-wide text-zinc-500">Status</dt>
                    <dd className="mt-0.5">
                        <Badge className={meta.badge}>{meta.label}</Badge>
                    </dd>
                </div>
                <div className="surface-muted px-3 py-2">
                    <dt className="text-[11px] uppercase tracking-wide text-zinc-500">OSINT pts</dt>
                    <dd className={clsx('mt-0.5 font-semibold tabular-nums', flag.call_only && 'text-zinc-400 line-through')}>
                        {points(flag.osint_points)} <span className="text-xs font-normal text-zinc-500">of {flag.points} on call</span>
                    </dd>
                </div>
                <div className="surface-muted px-3 py-2">
                    <dt className="text-[11px] uppercase tracking-wide text-zinc-500">Findings</dt>
                    <dd className="mt-0.5 font-semibold tabular-nums">{flag.findings}</dd>
                </div>
            </dl>

            {flag.related_flag_id && (
                <p className="text-xs text-zinc-500">
                    Related to{' '}
                    <button type="button" onClick={() => onOpenFlag(flag.related_flag_id as string)} className="focus-ring rounded font-mono text-sky-600 hover:underline dark:text-sky-400">
                        {flag.related_flag_id}
                    </button>
                    ; finding one usually answers the other.
                </p>
            )}

            <section className="flex flex-col gap-2">
                <h3 className="text-xs font-semibold uppercase tracking-wide text-zinc-500">{canWrite ? 'Set status' : 'Notes'}</h3>
                {canWrite ? (
                    <>
                        <Segmented
                            value={status}
                            onChange={setStatus}
                            size="md"
                            options={STATUS_ORDER.map((s) => ({
                                value: s,
                                label: (
                                    <span className="inline-flex items-center gap-1.5">
                                        <span className={clsx('size-1.5 rounded-full', STATUS[s].dot)} />
                                        {STATUS[s].label}
                                    </span>
                                ),
                            }))}
                        />
                        <textarea
                            value={notes}
                            onChange={(e) => setNotes(e.target.value)}
                            rows={2}
                            maxLength={500}
                            placeholder="Why this status? What to try next? (shown on the board)"
                            className="focus-ring w-full resize-y rounded-lg border border-zinc-300 bg-white px-2.5 py-1.5 text-sm placeholder:text-zinc-400 dark:border-zinc-700 dark:bg-zinc-900 dark:placeholder:text-zinc-500"
                        />
                        <div className="flex items-center gap-2">
                            <Button variant="primary" size="sm" onClick={save} disabled={!dirty} loading={update.isPending}>
                                <Save className="size-3.5" /> Save
                            </Button>
                            {dirty && (
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    onClick={() => {
                                        setStatus(flag.status);
                                        setNotes(flag.notes ?? '');
                                    }}
                                >
                                    Reset
                                </Button>
                            )}
                            <span className="ms-auto text-[11px] text-zinc-500">Findings filed later can raise the status, never lower it.</span>
                        </div>
                    </>
                ) : (
                    <p className="text-sm text-zinc-600 dark:text-zinc-400">{flag.notes || <span className="text-zinc-400">No notes. You have read-only access to this target.</span>}</p>
                )}
            </section>

            <section className="flex flex-col gap-2">
                <div className="flex items-center gap-2">
                    <h3 className="text-xs font-semibold uppercase tracking-wide text-zinc-500">Findings</h3>
                    <Link to={`/findings?flag=${flag.id}`} className="focus-ring ms-auto inline-flex items-center gap-0.5 rounded text-xs text-sky-600 hover:underline dark:text-sky-400">
                        Open in Findings <ArrowUpRight className="size-3" />
                    </Link>
                </div>
                {findings.isPending ? (
                    <Loading />
                ) : findings.data && findings.data.length > 0 ? (
                    <div className="surface divide-y divide-zinc-100 dark:divide-zinc-800/80">
                        {findings.data.map((finding) => (
                            <FindingCard key={finding.key} finding={finding} now={now} defaultOpen />
                        ))}
                    </div>
                ) : (
                    <p className="surface-muted px-3 py-3 text-sm text-zinc-500">
                        Nothing filed for this flag yet. In Claude Code, ask for <code className="font-mono text-xs">record_finding</code> with <code className="font-mono text-xs">flag_id: "{flag.id}"</code> and at least one source URL.
                    </p>
                )}
            </section>

            <section className="flex flex-col gap-2">
                <h3 className="flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-zinc-500">
                    <Search className="size-3" /> Searches already run
                </h3>
                {searches.isPending ? (
                    <Loading />
                ) : searches.data && searches.data.length > 0 ? (
                    <ul className="surface divide-y divide-zinc-100 text-xs dark:divide-zinc-800/80">
                        {searches.data.map((s) => (
                            <li key={s.id} className="flex items-start gap-2 px-3 py-1.5">
                                <span className="shrink-0 rounded bg-zinc-100 px-1 font-mono text-[10px] text-zinc-600 dark:bg-zinc-800 dark:text-zinc-400">{s.channel}</span>
                                <span className="min-w-0 flex-1 break-words">
                                    {s.query}
                                    {s.notes && <span className="block text-zinc-500">{s.notes}</span>}
                                </span>
                                <span className="shrink-0 tabular-nums text-zinc-500" title={`${s.result_count ?? '?'} results`}>
                                    {s.result_count ?? '?'} <Link2 className="inline size-3" />
                                </span>
                                <span className="shrink-0 text-zinc-400">{ago(s.at, now)}</span>
                            </li>
                        ))}
                    </ul>
                ) : (
                    <p className="text-xs text-zinc-500">No search logged against this flag.</p>
                )}
            </section>
        </div>
    );
}
