import { Monitor, Moon, Sun } from 'lucide-react';
import { useEffect, useState } from 'react';

type Appearance = 'light' | 'dark' | 'system';

const KEY = 'flux.appearance'; // shared with the Blade settings page

function read(): Appearance {
    try {
        const value = localStorage.getItem(KEY);

        return value === 'light' || value === 'dark' ? value : 'system';
    } catch {
        return 'system';
    }
}

function apply(appearance: Appearance): void {
    const dark = appearance === 'dark' || (appearance === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
    document.documentElement.classList.toggle('dark', dark);
}

const NEXT: Record<Appearance, Appearance> = { system: 'light', light: 'dark', dark: 'system' };

export function ThemeToggle({ className }: { className?: string }) {
    const [appearance, setAppearance] = useState<Appearance>(read);

    useEffect(() => {
        apply(appearance);
        try {
            localStorage.setItem(KEY, appearance);
        } catch {
            /* private mode */
        }

        if (appearance !== 'system') return;
        const media = window.matchMedia('(prefers-color-scheme: dark)');
        const onChange = () => apply('system');
        media.addEventListener('change', onChange);

        return () => media.removeEventListener('change', onChange);
    }, [appearance]);

    const Icon = appearance === 'light' ? Sun : appearance === 'dark' ? Moon : Monitor;

    return (
        <button
            type="button"
            onClick={() => setAppearance(NEXT[appearance])}
            title={`Appearance: ${appearance} (click to change)`}
            className={`focus-ring inline-flex size-8 items-center justify-center rounded-lg text-zinc-500 hover:bg-zinc-100 hover:text-zinc-900 dark:hover:bg-zinc-800 dark:hover:text-zinc-100 ${className ?? ''}`}
        >
            <Icon className="size-4" />
        </button>
    );
}
