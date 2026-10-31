import { ChevronsUpDown, LogOut, Settings } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

import type { SessionUser } from '@/lib/types';

function csrf(): string {
    return document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
}

export function UserMenu({ user }: { user: SessionUser }) {
    const [open, setOpen] = useState(false);
    const root = useRef<HTMLDivElement>(null);
    const { settings, logout } = window.__SECTF__.urls;

    useEffect(() => {
        if (!open) return;
        const onClick = (event: MouseEvent) => {
            if (!root.current?.contains(event.target as Node)) setOpen(false);
        };
        document.addEventListener('mousedown', onClick);

        return () => document.removeEventListener('mousedown', onClick);
    }, [open]);

    return (
        <div ref={root} className="relative">
            <button
                type="button"
                onClick={() => setOpen((v) => !v)}
                data-test="sidebar-menu-button"
                className="focus-ring flex w-full items-center gap-2 rounded-lg px-2 py-1.5 text-start hover:bg-zinc-100 dark:hover:bg-zinc-800"
            >
                <span className="flex size-7 items-center justify-center rounded-full bg-zinc-200 text-xs font-semibold text-zinc-700 dark:bg-zinc-700 dark:text-zinc-100">
                    {user.initials}
                </span>
                <span className="min-w-0 flex-1">
                    <span className="block truncate text-sm font-medium">{user.name}</span>
                    <span className="block truncate text-[11px] text-zinc-500">{user.email}</span>
                </span>
                <ChevronsUpDown className="size-4 text-zinc-400" />
            </button>

            {open && (
                <div className="absolute inset-x-0 bottom-full z-30 mb-1 animate-fade-in rounded-lg border border-zinc-200 bg-white p-1 shadow-lg dark:border-zinc-800 dark:bg-zinc-900">
                    <a href={settings} className="focus-ring flex items-center gap-2 rounded-md px-2 py-1.5 text-sm hover:bg-zinc-100 dark:hover:bg-zinc-800">
                        <Settings className="size-4 text-zinc-500" /> Settings
                    </a>
                    <form method="POST" action={logout}>
                        <input type="hidden" name="_token" value={csrf()} />
                        <button
                            type="submit"
                            data-test="logout-button"
                            className="focus-ring flex w-full items-center gap-2 rounded-md px-2 py-1.5 text-start text-sm hover:bg-zinc-100 dark:hover:bg-zinc-800"
                        >
                            <LogOut className="size-4 text-zinc-500" /> Log out
                        </button>
                    </form>
                </div>
            )}
        </div>
    );
}
