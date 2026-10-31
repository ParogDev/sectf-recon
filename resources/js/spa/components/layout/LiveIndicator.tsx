import clsx from 'clsx';

import type { LiveStatus } from '@/lib/types';

const META: Record<LiveStatus, { label: string; dot: string; title: string }> = {
    live: { label: 'live', dot: 'bg-emerald-500 animate-pulse-ring', title: 'Connected to Reverb; updates arrive as they are filed' },
    connecting: { label: 'connecting', dot: 'bg-amber-500 animate-pulse', title: 'Connecting to Reverb…' },
    offline: { label: 'offline', dot: 'bg-zinc-400 dark:bg-zinc-600', title: 'No live connection; data refreshes when you navigate' },
};

export function LiveIndicator({ status, className }: { status: LiveStatus; className?: string }) {
    const meta = META[status];

    return (
        <span className={clsx('inline-flex items-center gap-1.5 text-xs text-zinc-500', className)} title={meta.title} data-live={status}>
            <span className={clsx('size-2 rounded-full', meta.dot)} />
            {meta.label}
        </span>
    );
}
