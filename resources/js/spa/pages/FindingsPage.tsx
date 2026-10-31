import { Search, X } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { useSearchParams } from 'react-router';

import { FlagDrawer } from '@/components/board/FlagDrawer';
import { FindingCard } from '@/components/feed/FindingCard';
import { EmptyState, ErrorBox, Input, Loading, PageHeader } from '@/components/ui';
import { useNow } from '@/hooks/useNow';
import { useFindings, useTarget } from '@/lib/queries';

function useDebounced<T>(value: T, delay = 250): T {
    const [debounced, setDebounced] = useState(value);
    useEffect(() => {
        const timer = window.setTimeout(() => setDebounced(value), delay);

        return () => window.clearTimeout(timer);
    }, [value, delay]);

    return debounced;
}

export function FindingsPage({ targetId }: { targetId: number }) {
    const now = useNow();
    const [params, setParams] = useSearchParams();
    const flagFilter = params.get('flag');
    const drawerId = params.get('open');
    const [search, setSearch] = useState(params.get('q') ?? '');
    const debounced = useDebounced(search.trim());

    const target = useTarget(targetId);
    const findings = useFindings(targetId, { flagId: flagFilter, search: debounced || null, limit: 200 });

    const setParam = (key: string, value: string | null) =>
        setParams(
            (prev) => {
                const next = new URLSearchParams(prev);
                if (value) next.set(key, value);
                else next.delete(key);

                return next;
            },
            { replace: true },
        );

    const flagOptions = useMemo(() => target.data?.flags.filter((f) => f.findings > 0) ?? [], [target.data]);
    const drawerFlag = useMemo(() => target.data?.flags.find((f) => f.id === drawerId) ?? null, [target.data, drawerId]);

    return (
        <div className="flex flex-col gap-4">
            <PageHeader
                title="Findings"
                subtitle={findings.data ? `${findings.data.length} shown · newest update first` : undefined}
                actions={
                    <>
                        <select
                            value={flagFilter ?? ''}
                            onChange={(e) => setParam('flag', e.target.value || null)}
                            aria-label="Filter by flag"
                            className="focus-ring h-8 rounded-lg border border-zinc-300 bg-white px-2 text-sm dark:border-zinc-700 dark:bg-zinc-900"
                        >
                            <option value="">All flags</option>
                            {flagOptions.map((flag) => (
                                <option key={flag.id} value={flag.id}>
                                    {flag.id} · {flag.question.slice(0, 60)} ({flag.findings})
                                </option>
                            ))}
                        </select>
                        <div className="relative w-full sm:w-72">
                            <Search className="pointer-events-none absolute start-2.5 top-1/2 size-3.5 -translate-y-1/2 text-zinc-400" />
                            <Input
                                value={search}
                                onChange={(e) => {
                                    setSearch(e.target.value);
                                    setParam('q', e.target.value || null);
                                }}
                                placeholder="Search claim, detail or key"
                                className="ps-8 pe-8"
                                aria-label="Search findings"
                            />
                            {search && (
                                <button
                                    type="button"
                                    onClick={() => {
                                        setSearch('');
                                        setParam('q', null);
                                    }}
                                    className="absolute end-2 top-1/2 -translate-y-1/2 rounded p-0.5 text-zinc-400 hover:text-zinc-700"
                                    aria-label="Clear"
                                >
                                    <X className="size-3.5" />
                                </button>
                            )}
                        </div>
                    </>
                }
            />

            {findings.isError ? (
                <ErrorBox message={findings.error.message} />
            ) : findings.isPending ? (
                <Loading />
            ) : findings.data.length === 0 ? (
                <EmptyState title={flagFilter || debounced ? 'No findings match' : 'Nothing filed yet'}>
                    {flagFilter || debounced ? 'Clear the filters to see everything.' : 'Findings recorded from Claude Code with record_finding appear here with their sources.'}
                </EmptyState>
            ) : (
                <div className="surface divide-y divide-zinc-100 dark:divide-zinc-800/80" data-test="findings-list">
                    {findings.data.map((finding) => (
                        <FindingCard key={finding.key} finding={finding} now={now} onOpenFlag={(id) => setParam('open', id)} />
                    ))}
                </div>
            )}

            <FlagDrawer
                targetId={targetId}
                flag={drawerFlag}
                canWrite={(target.data?.target.role ?? 'viewer') !== 'viewer'}
                onClose={() => setParam('open', null)}
                onOpenFlag={(id) => setParam('open', id)}
            />
        </div>
    );
}
