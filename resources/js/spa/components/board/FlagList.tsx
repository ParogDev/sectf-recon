import clsx from 'clsx';
import { ChevronDown, FileText, Phone, Search, X } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';

import { Badge, EmptyState, Input, Kbd, ProgressBar, Tag } from '@/components/ui';
import { useHotkey } from '@/hooks/useHotkey';
import { points, STATUS, STATUS_ORDER } from '@/lib/format';
import type { Flag, FlagStatus, Summary } from '@/lib/types';

type Filter = FlagStatus | 'all';

export interface FlagGroup {
    id: string;
    name: string;
    flags: Flag[];
    banked: number;
    available: number;
    found: number;
    total: number;
}

export function groupFlags(flags: Flag[], summary: Summary): FlagGroup[] {
    const byName = new Map(summary.by_category.map((c) => [c.category, c]));
    const groups = new Map<string, FlagGroup>();

    for (const flag of flags) {
        let group = groups.get(flag.category_id);
        if (!group) {
            const stats = byName.get(flag.category);
            group = {
                id: flag.category_id,
                name: flag.category,
                flags: [],
                banked: stats?.banked ?? 0,
                available: stats?.available ?? 0,
                found: stats?.found ?? 0,
                total: stats?.total ?? 0,
            };
            groups.set(flag.category_id, group);
        }
        group.flags.push(flag);
    }

    return [...groups.values()];
}

export function FlagRow({ flag, active, onOpen }: { flag: Flag; active: boolean; onOpen: (id: string) => void }) {
    const meta = STATUS[flag.status];

    return (
        <li data-test={`flag-${flag.id}`}>
            <button
                type="button"
                onClick={() => onOpen(flag.id)}
                aria-current={active ? 'true' : undefined}
                className={clsx(
                    'focus-ring grid w-full grid-cols-[auto_1fr_auto] items-start gap-x-2.5 px-3 py-1.5 text-start text-sm transition-colors',
                    meta.row,
                    active && 'bg-sky-500/10 ring-1 ring-inset ring-sky-500/40',
                    flag.status === 'found' && 'text-zinc-500 dark:text-zinc-400',
                )}
            >
                <span className="flex items-center gap-1.5 pt-0.5">
                    <span className={clsx('size-1.5 rounded-full', meta.dot)} />
                    <code className="font-mono text-[11px] text-zinc-500">{flag.id}</code>
                </span>
                <span className="min-w-0 leading-snug">
                    {flag.question}
                    {flag.call_only && (
                        <Tag className="ms-1.5 bg-zinc-200 text-zinc-500 dark:bg-zinc-800" title="Only scored on the live call; no OSINT points">
                            <Phone className="me-0.5 inline size-2.5" />
                            call
                        </Tag>
                    )}
                    {flag.bonus && <Tag className="ms-1.5 bg-violet-500/15 text-violet-700 dark:text-violet-300">bonus</Tag>}
                    {flag.notes && <span className="mt-0.5 block truncate text-xs text-zinc-500">{flag.notes}</span>}
                </span>
                <span className="flex items-center gap-2 whitespace-nowrap pt-px">
                    {flag.findings > 0 && (
                        <span className="inline-flex items-center gap-0.5 text-[11px] tabular-nums text-zinc-500" title={`${flag.findings} finding(s)`}>
                            {flag.findings}
                            <FileText className="size-3" />
                        </span>
                    )}
                    <span className={clsx('w-7 text-end text-xs font-medium tabular-nums', flag.call_only ? 'text-zinc-400 line-through' : '')} title={`${flag.points} catalogue points`}>
                        {points(flag.osint_points)}
                    </span>
                    <Badge className={clsx('w-16 justify-center', meta.badge)}>{meta.short}</Badge>
                </span>
            </button>
        </li>
    );
}

export function CategorySection({ group, activeId, onOpen, collapsed, onToggle }: { group: FlagGroup; activeId: string | null; onOpen: (id: string) => void; collapsed: boolean; onToggle: () => void }) {
    return (
        <section className="surface overflow-hidden" data-test={`category-${group.id}`}>
            <button
                type="button"
                onClick={onToggle}
                aria-expanded={!collapsed}
                className="focus-ring flex w-full items-center gap-2 bg-zinc-50/70 px-3 py-2 text-start hover:bg-zinc-100 dark:bg-zinc-900/60 dark:hover:bg-zinc-800/60"
            >
                <ChevronDown className={clsx('size-4 text-zinc-400 transition-transform', collapsed && '-rotate-90')} />
                <h2 className="truncate text-sm font-semibold">{group.name}</h2>
                <span className="ms-auto flex items-center gap-3 text-xs tabular-nums text-zinc-500">
                    <span>
                        <span className="font-medium text-zinc-700 dark:text-zinc-300">{group.found}</span>/{group.total}
                    </span>
                    <span>
                        <span className="font-medium text-zinc-700 dark:text-zinc-300">{points(group.banked)}</span>/{points(group.available)} pts
                    </span>
                    <ProgressBar value={group.banked} max={group.available} className="hidden w-20 sm:block" />
                </span>
            </button>
            {!collapsed && (
                <ul className="divide-y divide-zinc-100 dark:divide-zinc-800/80">
                    {group.flags.map((flag) => (
                        <FlagRow key={flag.id} flag={flag} active={flag.id === activeId} onOpen={onOpen} />
                    ))}
                </ul>
            )}
        </section>
    );
}

export function FlagList({ flags, summary, activeId, onOpen }: { flags: Flag[]; summary: Summary; activeId: string | null; onOpen: (id: string) => void }) {
    const [filter, setFilter] = useState<Filter>('all');
    const [text, setText] = useState('');
    const [hideCallOnly, setHideCallOnly] = useState(false);
    const [collapsed, setCollapsed] = useState<Set<string>>(() => new Set());
    const input = useRef<HTMLInputElement>(null);

    useHotkey('/', (event) => {
        event.preventDefault();
        input.current?.focus();
    });

    useEffect(() => {
        if (activeId) setCollapsed((set) => {
            const flag = flags.find((f) => f.id === activeId);
            if (!flag || !set.has(flag.category_id)) return set;
            const next = new Set(set);
            next.delete(flag.category_id);

            return next;
        });
    }, [activeId, flags]);

    const counts = useMemo(() => {
        const out: Record<Filter, number> = { all: 0, open: 0, partial: 0, found: 0, unverifiable: 0 };
        for (const flag of flags) {
            out.all += 1;
            out[flag.status] += 1;
        }

        return out;
    }, [flags]);

    const visible = useMemo(() => {
        const needle = text.trim().toLowerCase();

        return flags.filter((flag) => {
            if (filter !== 'all' && flag.status !== filter) return false;
            if (hideCallOnly && flag.call_only) return false;
            if (!needle) return true;

            return flag.id.includes(needle) || flag.question.toLowerCase().includes(needle) || flag.category.toLowerCase().includes(needle) || (flag.notes ?? '').toLowerCase().includes(needle);
        });
    }, [flags, filter, text, hideCallOnly]);

    const groups = useMemo(() => groupFlags(visible, summary), [visible, summary]);
    const allCollapsed = groups.length > 0 && groups.every((g) => collapsed.has(g.id));

    return (
        <div className="flex flex-col gap-3" data-test="flag-board">
            <div className="flex flex-wrap items-center gap-2">
                <div className="flex flex-wrap gap-1" role="radiogroup" aria-label="Status filter">
                    {(['all', ...STATUS_ORDER] as Filter[]).map((option) => {
                        const selected = option === filter;
                        const meta = option === 'all' ? null : STATUS[option];

                        return (
                            <button
                                key={option}
                                type="button"
                                role="radio"
                                aria-checked={selected}
                                onClick={() => setFilter(option)}
                                className={clsx(
                                    'focus-ring inline-flex h-7 items-center gap-1.5 rounded-full border px-2.5 text-xs font-medium transition-colors',
                                    selected
                                        ? 'border-zinc-900 bg-zinc-900 text-white dark:border-zinc-100 dark:bg-zinc-100 dark:text-zinc-900'
                                        : 'border-zinc-300 text-zinc-600 hover:bg-zinc-100 dark:border-zinc-700 dark:text-zinc-400 dark:hover:bg-zinc-800',
                                )}
                            >
                                {meta && <span className={clsx('size-1.5 rounded-full', meta.dot)} />}
                                {option === 'all' ? 'All' : meta?.label}
                                <span className={clsx('tabular-nums', selected ? 'opacity-70' : 'text-zinc-400')}>{counts[option]}</span>
                            </button>
                        );
                    })}
                </div>

                <div className="relative ms-auto w-full sm:w-64">
                    <Search className="pointer-events-none absolute start-2.5 top-1/2 size-3.5 -translate-y-1/2 text-zinc-400" />
                    <Input ref={input} value={text} onChange={(e) => setText(e.target.value)} placeholder="Filter flags" className="ps-8 pe-14" aria-label="Filter flags" />
                    <span className="pointer-events-none absolute end-2 top-1/2 -translate-y-1/2">
                        {text ? (
                            <button type="button" onClick={() => setText('')} className="pointer-events-auto rounded p-0.5 text-zinc-400 hover:text-zinc-700" aria-label="Clear">
                                <X className="size-3.5" />
                            </button>
                        ) : (
                            <Kbd>/</Kbd>
                        )}
                    </span>
                </div>

                <label className="flex cursor-pointer items-center gap-1.5 text-xs text-zinc-500">
                    <input type="checkbox" checked={hideCallOnly} onChange={(e) => setHideCallOnly(e.target.checked)} className="size-3.5 rounded border-zinc-300 accent-zinc-900 dark:accent-zinc-100" />
                    hide call-only
                </label>

                <button
                    type="button"
                    onClick={() => setCollapsed(allCollapsed ? new Set() : new Set(groups.map((g) => g.id)))}
                    className="focus-ring rounded-md px-1.5 py-1 text-xs text-zinc-500 hover:text-zinc-900 dark:hover:text-zinc-100"
                >
                    {allCollapsed ? 'Expand all' : 'Collapse all'}
                </button>
            </div>

            {groups.length === 0 ? (
                <EmptyState title="No flags match">Try another status or clear the filter.</EmptyState>
            ) : (
                groups.map((group) => (
                    <CategorySection
                        key={group.id}
                        group={group}
                        activeId={activeId}
                        onOpen={onOpen}
                        collapsed={collapsed.has(group.id)}
                        onToggle={() =>
                            setCollapsed((set) => {
                                const next = new Set(set);
                                if (next.has(group.id)) next.delete(group.id);
                                else next.add(group.id);

                                return next;
                            })
                        }
                    />
                ))
            )}
        </div>
    );
}
