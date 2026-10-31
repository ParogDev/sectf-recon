import clsx from 'clsx';
import { Activity as ActivityIcon, BookOpenText, Contact, ExternalLink, FileDown, FileSearch, LayoutGrid, Loader2, Menu, MessageSquareText, Search, X } from 'lucide-react';
import { useEffect, useState, type ReactNode } from 'react';
import { NavLink, useLocation } from 'react-router';

import { ExportDossierDialog } from '@/components/layout/ExportDossierDialog';
import { LiveIndicator } from '@/components/layout/LiveIndicator';
import { TargetSwitcher } from '@/components/layout/TargetSwitcher';
import { ThemeToggle } from '@/components/layout/ThemeToggle';
import { UserMenu } from '@/components/layout/UserMenu';
import { useExportDossier } from '@/lib/queries';
import type { LiveStatus, Session } from '@/lib/types';

const NAV = [
    { to: '/', label: 'Board', icon: LayoutGrid, end: true },
    { to: '/findings', label: 'Findings', icon: FileSearch },
    { to: '/directory', label: 'Directory', icon: Contact },
    { to: '/pretexts', label: 'Pretexts', icon: MessageSquareText },
    { to: '/searches', label: 'Searches', icon: Search },
    { to: '/activity', label: 'Activity', icon: ActivityIcon },
];

function Logo() {
    return (
        <span className="flex size-8 items-center justify-center rounded-md bg-zinc-900 text-white dark:bg-white dark:text-zinc-900">
            <svg viewBox="0 0 40 42" className="size-5 fill-current" aria-hidden="true">
                <path
                    fillRule="evenodd"
                    clipRule="evenodd"
                    d="M17.2 5.633 8.6.855 0 5.633v26.51l16.2 9 16.2-9v-8.442l7.6-4.223V9.856l-8.6-4.777-8.6 4.777V18.3l-5.6 3.111V5.633ZM38 18.301l-5.6 3.11v-6.157l5.6-3.11V18.3Zm-1.06-7.856-5.54 3.078-5.54-3.079 5.54-3.078 5.54 3.079ZM24.8 18.3v-6.157l5.6 3.111v6.158L24.8 18.3Zm-1 1.732 5.54 3.078-13.14 7.302-5.54-3.078 13.14-7.3v-.002Zm-16.2 7.89 7.6 4.222V38.3L2 30.966V7.92l5.6 3.111v16.892ZM8.6 9.3 3.06 6.222 8.6 3.143l5.54 3.08L8.6 9.3Zm21.8 15.51-13.2 7.334V38.3l13.2-7.334v-6.156ZM9.6 11.034l5.6-3.11v14.6l-5.6 3.11v-14.6Z"
                />
            </svg>
        </span>
    );
}

export function AppShell({
    session,
    live,
    onSelectTarget,
    switching,
    counts,
    children,
}: {
    session: Session;
    live: LiveStatus;
    onSelectTarget: (id: number) => void;
    switching: boolean;
    counts: Partial<Record<string, number>>;
    children: ReactNode;
}) {
    const [drawer, setDrawer] = useState(false);
    const location = useLocation();

    useEffect(() => setDrawer(false), [location.pathname]);

    // The dossier is a standalone Blade document served outside the SPA basename.
    const dossierHref = session.current_target_id !== null ? `/dossier/${session.current_target_id}` : null;
    const dossierFrHref = dossierHref !== null ? `${dossierHref}?lang=fr` : null;

    // "Export dossier" renders EN + FR, PDF + HTML into the export folder and reports where they went.
    const exporter = useExportDossier(session.current_target_id);
    const [exportOpen, setExportOpen] = useState(false);
    const runExport = () => {
        setExportOpen(true);
        exporter.mutate();
    };
    const closeExport = () => {
        setExportOpen(false);
        if (!exporter.isPending) exporter.reset();
    };
    const exportButtonClass =
        'focus-ring flex w-full items-center gap-2.5 rounded-lg border border-dashed border-zinc-300 px-2.5 py-1.5 text-start text-sm font-medium text-zinc-700 hover:border-zinc-400 hover:bg-zinc-100 hover:text-zinc-900 disabled:cursor-wait disabled:opacity-70 dark:border-zinc-700 dark:text-zinc-300 dark:hover:border-zinc-600 dark:hover:bg-zinc-800/60 dark:hover:text-zinc-100';

    const sidebar = (
        <div className="flex h-full flex-col gap-4 p-3">
            <div className="flex items-center gap-2 px-1">
                <Logo />
                <span className="text-sm font-semibold tracking-tight">{window.__SECTF__.appName}</span>
                <button type="button" onClick={() => setDrawer(false)} className="focus-ring ms-auto rounded-md p-1 text-zinc-500 lg:hidden" aria-label="Close menu">
                    <X className="size-4" />
                </button>
            </div>

            <TargetSwitcher targets={session.targets} currentId={session.current_target_id} onSelect={onSelectTarget} busy={switching} />

            <nav className="flex flex-col gap-0.5" aria-label="Sections">
                {NAV.map(({ to, label, icon: Icon, end }) => (
                    <NavLink
                        key={to}
                        to={to}
                        end={end}
                        className={({ isActive }) =>
                            clsx(
                                'focus-ring flex items-center gap-2.5 rounded-lg px-2.5 py-1.5 text-sm font-medium transition-colors',
                                isActive
                                    ? 'bg-zinc-200/70 text-zinc-900 dark:bg-zinc-800 dark:text-zinc-100'
                                    : 'text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-800/60 dark:hover:text-zinc-100',
                            )
                        }
                    >
                        <Icon className="size-4 shrink-0" />
                        <span className="flex-1">{label}</span>
                        {counts[to] !== undefined && <span className="rounded-full bg-zinc-200 px-1.5 text-[10px] font-semibold tabular-nums text-zinc-600 dark:bg-zinc-700 dark:text-zinc-300">{counts[to]}</span>}
                    </NavLink>
                ))}
            </nav>

            {dossierHref && dossierFrHref && (
                <div className="flex flex-col gap-1">
                    <button
                        type="button"
                        onClick={runExport}
                        disabled={exporter.isPending}
                        data-test="export-dossier"
                        title="Save the Phase 1 dossier as PDF and HTML, in English and French, to the export folder"
                        className={exportButtonClass}
                    >
                        {exporter.isPending ? <Loader2 className="size-4 shrink-0 animate-spin" /> : <FileDown className="size-4 shrink-0" />}
                        <span className="flex-1">Export dossier</span>
                        <span className="text-[10px] font-normal text-zinc-500">PDF · HTML</span>
                    </button>
                    <div className="flex items-center gap-1 px-2.5 text-[11px] text-zinc-500">
                        <ExternalLink className="size-3 shrink-0" />
                        <span>Live dossier:</span>
                        <a href={dossierHref} target="_blank" rel="noreferrer" data-test="dossier-live-en" title="Open the live Phase 1 dossier in English (new tab)" className="focus-ring rounded px-0.5 font-medium hover:text-zinc-900 hover:underline dark:hover:text-zinc-100">
                            EN
                        </a>
                        <span aria-hidden="true">·</span>
                        <a href={dossierFrHref} target="_blank" rel="noreferrer" data-test="export-dossier-fr" title="Open the live Phase 1 dossier in French (new tab)" className="focus-ring rounded px-0.5 font-medium hover:text-zinc-900 hover:underline dark:hover:text-zinc-100">
                            FR
                        </a>
                    </div>
                </div>
            )}

            <div className="mt-auto flex flex-col gap-2">
                <a
                    href="https://hackfest.ca"
                    target="_blank"
                    rel="noreferrer"
                    className="focus-ring flex items-center gap-2.5 rounded-lg px-2.5 py-1.5 text-sm text-zinc-500 hover:bg-zinc-100 hover:text-zinc-900 dark:hover:bg-zinc-800/60 dark:hover:text-zinc-100"
                >
                    <BookOpenText className="size-4" /> HackFest SECTF
                </a>
                <div className="flex items-center justify-between px-2.5">
                    <LiveIndicator status={live} />
                    <ThemeToggle />
                </div>
                <UserMenu user={session.user} />
            </div>
        </div>
    );

    return (
        <div className="flex min-h-screen">
            <aside className="sticky top-0 hidden h-screen w-60 shrink-0 border-e border-zinc-200 bg-zinc-50/80 lg:block dark:border-zinc-800 dark:bg-zinc-900/60">{sidebar}</aside>

            {drawer && (
                <div className="fixed inset-0 z-40 lg:hidden">
                    <button type="button" aria-label="Close menu" onClick={() => setDrawer(false)} className="absolute inset-0 bg-zinc-950/40" />
                    <aside className="relative h-full w-72 max-w-[85vw] animate-fade-in border-e border-zinc-200 bg-zinc-50 dark:border-zinc-800 dark:bg-zinc-900">{sidebar}</aside>
                </div>
            )}

            <div className="flex min-w-0 flex-1 flex-col">
                <header className="sticky top-0 z-20 flex items-center gap-2 border-b border-zinc-200 bg-white/80 px-4 py-2 backdrop-blur lg:hidden dark:border-zinc-800 dark:bg-zinc-950/80">
                    <button type="button" onClick={() => setDrawer(true)} className="focus-ring rounded-md p-1 text-zinc-600 dark:text-zinc-300" aria-label="Open menu">
                        <Menu className="size-5" />
                    </button>
                    <Logo />
                    <span className="truncate text-sm font-semibold">{session.targets.find((t) => t.id === session.current_target_id)?.name ?? window.__SECTF__.appName}</span>
                    <LiveIndicator status={live} className="ms-auto" />
                    {dossierHref && (
                        <button
                            type="button"
                            onClick={runExport}
                            disabled={exporter.isPending}
                            aria-label="Export dossier"
                            title="Save the dossier as PDF and HTML (EN + FR)"
                            data-test="export-dossier-mobile"
                            className="focus-ring rounded-md p-1 text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900 disabled:opacity-60 dark:text-zinc-300 dark:hover:bg-zinc-800 dark:hover:text-zinc-100"
                        >
                            {exporter.isPending ? <Loader2 className="size-5 animate-spin" /> : <FileDown className="size-5" />}
                        </button>
                    )}
                    {dossierFrHref && (
                        <a
                            href={dossierFrHref}
                            target="_blank"
                            rel="noreferrer"
                            aria-label="Live dossier (FR)"
                            title="Open the live dossier in French (new tab)"
                            data-test="export-dossier-fr-mobile"
                            className="focus-ring flex items-center gap-0.5 rounded-md p-1 text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-300 dark:hover:bg-zinc-800 dark:hover:text-zinc-100"
                        >
                            <ExternalLink className="size-5" />
                            <span className="text-[10px] font-semibold leading-none">FR</span>
                        </a>
                    )}
                </header>

                <main className="flex-1 px-4 py-4 sm:px-6">{children}</main>
            </div>

            <ExportDossierDialog open={exportOpen} onClose={closeExport} status={exporter.status} result={exporter.data} error={exporter.error} onRetry={() => exporter.mutate()} />
        </div>
    );
}
