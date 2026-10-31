import { useMemo, useState } from 'react';

import { ActivityItem } from '@/components/feed/ActivityItem';
import { EmptyState, ErrorBox, Loading, PageHeader } from '@/components/ui';
import { useNow } from '@/hooks/useNow';
import { kindTone } from '@/lib/format';
import { useActivity } from '@/lib/queries';

export function ActivityPage({ targetId }: { targetId: number }) {
    const now = useNow();
    const activity = useActivity(targetId, 200);
    const [kind, setKind] = useState<string | null>(null);

    const kinds = useMemo(() => {
        const counts = new Map<string, number>();
        for (const item of activity.data ?? []) counts.set(item.kind.split('.')[0], (counts.get(item.kind.split('.')[0]) ?? 0) + 1);

        return [...counts.entries()].sort((a, b) => b[1] - a[1]);
    }, [activity.data]);

    const visible = useMemo(() => (activity.data ?? []).filter((a) => kind === null || a.kind.startsWith(`${kind}.`)), [activity.data, kind]);

    return (
        <div className="flex flex-col gap-4">
            <PageHeader
                title="Activity"
                subtitle="Every write on this target, newest first. This is what get_updates returns to Claude."
                actions={
                    kinds.length > 1 && (
                        <div className="flex flex-wrap gap-1" role="radiogroup" aria-label="Kind filter">
                            <button
                                type="button"
                                role="radio"
                                aria-checked={kind === null}
                                onClick={() => setKind(null)}
                                className={`focus-ring rounded-full border px-2.5 py-0.5 text-xs font-medium ${kind === null ? 'border-zinc-900 bg-zinc-900 text-white dark:border-zinc-100 dark:bg-zinc-100 dark:text-zinc-900' : 'border-zinc-300 text-zinc-600 dark:border-zinc-700 dark:text-zinc-400'}`}
                            >
                                all
                            </button>
                            {kinds.map(([prefix, count]) => (
                                <button
                                    key={prefix}
                                    type="button"
                                    role="radio"
                                    aria-checked={kind === prefix}
                                    onClick={() => setKind(kind === prefix ? null : prefix)}
                                    className={`focus-ring rounded-full px-2.5 py-0.5 font-mono text-xs ring-1 ring-inset ring-transparent ${kindTone(prefix)} ${kind === prefix ? 'ring-current' : 'opacity-80 hover:opacity-100'}`}
                                >
                                    {prefix} <span className="opacity-60">{count}</span>
                                </button>
                            ))}
                        </div>
                    )
                }
            />

            {activity.isError ? (
                <ErrorBox message={activity.error.message} />
            ) : activity.isPending ? (
                <Loading />
            ) : visible.length === 0 ? (
                <EmptyState title="No activity yet">Everything Claude files through MCP, and every status change made here, is listed.</EmptyState>
            ) : (
                <ul className="surface divide-y divide-zinc-100 dark:divide-zinc-800/80" data-test="activity-feed">
                    {visible.map((item) => (
                        <ActivityItem key={item.id} activity={item} now={now} />
                    ))}
                </ul>
            )}
        </div>
    );
}
