import clsx from 'clsx';
import { Check, ChevronsUpDown, ExternalLink } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

import { Badge } from '@/components/ui';
import { roleTone } from '@/lib/format';
import type { TargetSummaryItem } from '@/lib/types';

export function TargetSwitcher({
    targets,
    currentId,
    onSelect,
    busy,
}: {
    targets: TargetSummaryItem[];
    currentId: number | null;
    onSelect: (id: number) => void;
    busy: boolean;
}) {
    const [open, setOpen] = useState(false);
    const root = useRef<HTMLDivElement>(null);
    const current = targets.find((t) => t.id === currentId) ?? null;

    useEffect(() => {
        if (!open) return;
        const onClick = (event: MouseEvent) => {
            if (!root.current?.contains(event.target as Node)) setOpen(false);
        };
        const onKey = (event: KeyboardEvent) => {
            if (event.key === 'Escape') setOpen(false);
        };
        document.addEventListener('mousedown', onClick);
        document.addEventListener('keydown', onKey);

        return () => {
            document.removeEventListener('mousedown', onClick);
            document.removeEventListener('keydown', onKey);
        };
    }, [open]);

    if (targets.length === 0) {
        return <div className="px-3 py-2 text-xs text-zinc-500">No target yet</div>;
    }

    return (
        <div ref={root} className="relative">
            <button
                type="button"
                onClick={() => setOpen((v) => !v)}
                aria-haspopup="listbox"
                aria-expanded={open}
                data-test="target-switcher"
                className={clsx(
                    'focus-ring flex w-full items-center gap-2 rounded-lg border border-zinc-200 bg-white px-2.5 py-2 text-start shadow-xs transition-colors hover:bg-zinc-50 dark:border-zinc-800 dark:bg-zinc-900 dark:hover:bg-zinc-800',
                    busy && 'opacity-60',
                )}
            >
                <span className="flex size-7 shrink-0 items-center justify-center rounded-md bg-gradient-to-br from-sky-500 to-violet-600 text-xs font-bold text-white">
                    {(current?.name ?? '?').slice(0, 1).toUpperCase()}
                </span>
                <span className="min-w-0 flex-1">
                    <span className="block truncate text-sm font-semibold">{current?.name ?? 'Pick a target'}</span>
                    <span className="block truncate text-[11px] text-zinc-500">
                        {current ? (current.role === 'owner' ? 'your target' : `${current.role} · ${current.owner ?? 'shared'}`) : `${targets.length} available`}
                    </span>
                </span>
                <ChevronsUpDown className="size-4 shrink-0 text-zinc-400" />
            </button>

            {open && (
                <ul
                    role="listbox"
                    className="absolute inset-x-0 top-full z-30 mt-1 max-h-72 animate-fade-in overflow-y-auto rounded-lg border border-zinc-200 bg-white p-1 shadow-lg dark:border-zinc-800 dark:bg-zinc-900"
                >
                    {targets.map((target) => (
                        <li key={target.id}>
                            <button
                                type="button"
                                role="option"
                                aria-selected={target.id === currentId}
                                onClick={() => {
                                    setOpen(false);
                                    if (target.id !== currentId) onSelect(target.id);
                                }}
                                className="focus-ring flex w-full items-center gap-2 rounded-md px-2 py-1.5 text-start text-sm hover:bg-zinc-100 dark:hover:bg-zinc-800"
                            >
                                <Check className={clsx('size-4 shrink-0', target.id === currentId ? 'text-emerald-500' : 'invisible')} />
                                <span className="min-w-0 flex-1">
                                    <span className="block truncate font-medium">{target.name}</span>
                                    {target.url && (
                                        <span className="flex items-center gap-1 text-[11px] text-zinc-500">
                                            <ExternalLink className="size-3" /> {target.url.replace(/^https?:\/\/(www\.)?/, '')}
                                        </span>
                                    )}
                                </span>
                                <Badge className={roleTone(target.role)}>{target.role}</Badge>
                            </button>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}
