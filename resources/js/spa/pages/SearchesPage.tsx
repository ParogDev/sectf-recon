import { Search, X } from 'lucide-react';
import { useEffect, useState } from 'react';

import { EmptyState, ErrorBox, Input, Loading, PageHeader } from '@/components/ui';
import { useNow } from '@/hooks/useNow';
import { absolute, ago } from '@/lib/format';
import { useSearches } from '@/lib/queries';

export function SearchesPage({ targetId }: { targetId: number }) {
    const now = useNow();
    const [text, setText] = useState('');
    const [debounced, setDebounced] = useState('');
    useEffect(() => {
        const timer = window.setTimeout(() => setDebounced(text.trim()), 250);

        return () => window.clearTimeout(timer);
    }, [text]);

    const searches = useSearches(targetId, { contains: debounced || null, limit: 200 });

    return (
        <div className="flex flex-col gap-4">
            <PageHeader
                title="Searches"
                subtitle="Every search anyone has already run on this target, so nobody repeats it."
                actions={
                    <div className="relative w-full sm:w-72">
                        <Search className="pointer-events-none absolute start-2.5 top-1/2 size-3.5 -translate-y-1/2 text-zinc-400" />
                        <Input value={text} onChange={(e) => setText(e.target.value)} placeholder="Search query or notes" className="ps-8 pe-8" aria-label="Filter searches" />
                        {text && (
                            <button type="button" onClick={() => setText('')} className="absolute end-2 top-1/2 -translate-y-1/2 rounded p-0.5 text-zinc-400 hover:text-zinc-700" aria-label="Clear">
                                <X className="size-3.5" />
                            </button>
                        )}
                    </div>
                }
            />

            {searches.isError ? (
                <ErrorBox message={searches.error.message} />
            ) : searches.isPending ? (
                <Loading />
            ) : searches.data.length === 0 ? (
                <EmptyState title={debounced ? 'No searches match' : 'No searches logged'}>
                    {debounced ? 'Try other words.' : 'Claude logs each query with log_search, including the ones that found nothing.'}
                </EmptyState>
            ) : (
                <div className="surface overflow-x-auto">
                    <table className="w-full min-w-[44rem] text-sm">
                        <thead className="text-[11px] uppercase tracking-wide text-zinc-500">
                            <tr>
                                <th className="px-3 py-1.5 text-start font-medium">When</th>
                                <th className="px-3 py-1.5 text-start font-medium">By</th>
                                <th className="px-3 py-1.5 text-start font-medium">Channel</th>
                                <th className="px-3 py-1.5 text-start font-medium">Query</th>
                                <th className="px-3 py-1.5 text-start font-medium">Flag</th>
                                <th className="px-3 py-1.5 text-end font-medium">Results</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-zinc-100 dark:divide-zinc-800/80">
                            {searches.data.map((s) => (
                                <tr key={s.id} className="align-top" data-test={`search-${s.id}`}>
                                    <td className="px-3 py-1.5 text-xs whitespace-nowrap text-zinc-500" title={absolute(s.at)}>
                                        {ago(s.at, now)}
                                    </td>
                                    <td className="px-3 py-1.5 text-xs whitespace-nowrap">{s.by ?? '—'}</td>
                                    <td className="px-3 py-1.5">
                                        <span className="rounded bg-zinc-100 px-1 font-mono text-[11px] text-zinc-600 dark:bg-zinc-800 dark:text-zinc-400">{s.channel}</span>
                                    </td>
                                    <td className="px-3 py-1.5">
                                        <div className="break-words">{s.query}</div>
                                        {s.notes && <div className="text-xs text-zinc-500">{s.notes}</div>}
                                    </td>
                                    <td className="px-3 py-1.5 font-mono text-xs text-zinc-500">{s.flag_id ?? ''}</td>
                                    <td className={`px-3 py-1.5 text-end tabular-nums ${s.result_count === 0 ? 'text-zinc-400' : ''}`}>{s.result_count ?? '?'}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </div>
    );
}
