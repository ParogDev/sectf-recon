import clsx from 'clsx';
import { Loader2, X } from 'lucide-react';
import { useEffect, type ButtonHTMLAttributes, type InputHTMLAttributes, type ReactNode, type Ref } from 'react';

import { useHotkey } from '@/hooks/useHotkey';

export function Badge({ className, children, title }: { className?: string; children: ReactNode; title?: string }) {
    return (
        <span
            title={title}
            className={clsx('inline-flex items-center gap-1 rounded-md px-1.5 py-0.5 text-[11px] font-medium leading-4 ring-1 ring-inset', className)}
        >
            {children}
        </span>
    );
}

export function Tag({ className, children, title }: { className?: string; children: ReactNode; title?: string }) {
    return (
        <span title={title} className={clsx('inline-flex items-center rounded px-1 text-[10px] font-semibold uppercase tracking-wide', className)}>
            {children}
        </span>
    );
}

type ButtonVariant = 'primary' | 'secondary' | 'ghost' | 'danger';

export function Button({
    variant = 'secondary',
    size = 'md',
    loading = false,
    className,
    children,
    ...props
}: ButtonHTMLAttributes<HTMLButtonElement> & { variant?: ButtonVariant; size?: 'sm' | 'md'; loading?: boolean }) {
    const variants: Record<ButtonVariant, string> = {
        primary: 'bg-zinc-900 text-white hover:bg-zinc-700 dark:bg-zinc-100 dark:text-zinc-900 dark:hover:bg-white',
        secondary: 'border border-zinc-300 bg-white text-zinc-800 hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100 dark:hover:bg-zinc-800',
        ghost: 'text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-zinc-100',
        danger: 'bg-rose-600 text-white hover:bg-rose-500',
    };

    return (
        <button
            type="button"
            {...props}
            disabled={props.disabled || loading}
            className={clsx(
                'focus-ring inline-flex items-center justify-center gap-1.5 rounded-lg font-medium transition-colors disabled:cursor-not-allowed disabled:opacity-50',
                size === 'sm' ? 'h-7 px-2 text-xs' : 'h-8 px-3 text-sm',
                variants[variant],
                className,
            )}
        >
            {loading && <Loader2 className="size-3.5 animate-spin" />}
            {children}
        </button>
    );
}

export function Input({ className, ref, ...props }: InputHTMLAttributes<HTMLInputElement> & { ref?: Ref<HTMLInputElement> }) {
    return (
        <input
            ref={ref}
            {...props}
            className={clsx(
                'focus-ring h-8 w-full rounded-lg border border-zinc-300 bg-white px-2.5 text-sm text-zinc-900 placeholder:text-zinc-400 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100 dark:placeholder:text-zinc-500',
                className,
            )}
        />
    );
}

export function Spinner({ className }: { className?: string }) {
    return <Loader2 className={clsx('size-5 animate-spin text-zinc-400', className)} aria-label="Loading" />;
}

export function Loading({ label = 'Loading…' }: { label?: string }) {
    return (
        <div className="flex items-center justify-center gap-2 py-12 text-sm text-zinc-500">
            <Spinner /> {label}
        </div>
    );
}

export function ErrorBox({ message }: { message: string }) {
    return <div className="rounded-xl border border-rose-500/40 bg-rose-500/5 px-4 py-3 text-sm text-rose-700 dark:text-rose-300">{message}</div>;
}

export function Kbd({ children }: { children: ReactNode }) {
    return <kbd className="kbd">{children}</kbd>;
}

export function ProgressBar({ value, max, tone = 'bg-emerald-500', className }: { value: number; max: number; tone?: string; className?: string }) {
    const pct = max > 0 ? Math.min(100, Math.round((value / max) * 100)) : 0;

    return (
        <div className={clsx('h-1.5 w-full overflow-hidden rounded-full bg-zinc-200 dark:bg-zinc-800', className)} role="progressbar" aria-valuenow={pct} aria-valuemin={0} aria-valuemax={100}>
            <div className={clsx('h-full rounded-full transition-[width] duration-500', tone)} style={{ width: `${pct}%` }} />
        </div>
    );
}

export function EmptyState({ title, children, icon }: { title: string; children?: ReactNode; icon?: ReactNode }) {
    return (
        <div className="flex flex-col items-center rounded-xl border border-dashed border-zinc-300 px-6 py-10 text-center dark:border-zinc-700">
            {icon && <div className="mb-3 text-zinc-400">{icon}</div>}
            <h3 className="text-sm font-semibold">{title}</h3>
            {children && <div className="mt-1 max-w-md text-sm text-zinc-500">{children}</div>}
        </div>
    );
}

export function Segmented<T extends string>({
    value,
    onChange,
    options,
    size = 'sm',
}: {
    value: T;
    onChange: (value: T) => void;
    options: { value: T; label: ReactNode; title?: string }[];
    size?: 'sm' | 'md';
}) {
    return (
        <div className="inline-flex rounded-lg border border-zinc-300 bg-zinc-100 p-0.5 dark:border-zinc-700 dark:bg-zinc-800" role="radiogroup">
            {options.map((option) => (
                <button
                    key={option.value}
                    type="button"
                    role="radio"
                    aria-checked={option.value === value}
                    title={option.title}
                    onClick={() => onChange(option.value)}
                    className={clsx(
                        'focus-ring rounded-md px-2 font-medium transition-colors',
                        size === 'sm' ? 'h-6 text-xs' : 'h-7 text-sm',
                        option.value === value
                            ? 'bg-white text-zinc-900 shadow-xs dark:bg-zinc-900 dark:text-zinc-100'
                            : 'text-zinc-500 hover:text-zinc-900 dark:hover:text-zinc-100',
                    )}
                >
                    {option.label}
                </button>
            ))}
        </div>
    );
}

export function Panel({ title, aside, children, className, bodyClassName }: { title: ReactNode; aside?: ReactNode; children: ReactNode; className?: string; bodyClassName?: string }) {
    return (
        <section className={clsx('surface overflow-hidden', className)}>
            <header className="flex items-center gap-2 border-b border-zinc-200 bg-zinc-50/70 px-3 py-2 dark:border-zinc-800 dark:bg-zinc-900/60">
                <h2 className="truncate text-sm font-semibold">{title}</h2>
                <div className="ms-auto flex items-center gap-2 text-xs text-zinc-500">{aside}</div>
            </header>
            <div className={bodyClassName}>{children}</div>
        </section>
    );
}

/** A right-hand sheet. Esc closes it; the backdrop click too. */
export function Sheet({ open, onClose, title, children, width = 'max-w-xl' }: { open: boolean; onClose: () => void; title: ReactNode; children: ReactNode; width?: string }) {
    useHotkey('Escape', onClose, { enabled: open });

    useEffect(() => {
        if (!open) return;
        const previous = document.body.style.overflow;
        document.body.style.overflow = 'hidden';

        return () => {
            document.body.style.overflow = previous;
        };
    }, [open]);

    if (!open) return null;

    return (
        <div className="fixed inset-0 z-40 flex justify-end" role="dialog" aria-modal="true">
            <button type="button" aria-label="Close" onClick={onClose} className="absolute inset-0 bg-zinc-950/40 backdrop-blur-[1px]" />
            <aside className={clsx('relative flex h-full w-full animate-slide-in flex-col border-s border-zinc-200 bg-white shadow-2xl dark:border-zinc-800 dark:bg-zinc-950', width)}>
                <header className="flex items-start gap-3 border-b border-zinc-200 px-4 py-3 dark:border-zinc-800">
                    <div className="min-w-0 flex-1">{title}</div>
                    <button type="button" onClick={onClose} className="focus-ring rounded-md p-1 text-zinc-500 hover:bg-zinc-100 hover:text-zinc-900 dark:hover:bg-zinc-800 dark:hover:text-zinc-100" aria-label="Close">
                        <X className="size-4" />
                    </button>
                </header>
                <div className="min-h-0 flex-1 overflow-y-auto">{children}</div>
            </aside>
        </div>
    );
}

/** A centred modal. Esc closes it; the backdrop click too. */
export function Dialog({ open, onClose, title, children, width = 'max-w-lg', testId }: { open: boolean; onClose: () => void; title: ReactNode; children: ReactNode; width?: string; testId?: string }) {
    useHotkey('Escape', onClose, { enabled: open });

    useEffect(() => {
        if (!open) return;
        const previous = document.body.style.overflow;
        document.body.style.overflow = 'hidden';

        return () => {
            document.body.style.overflow = previous;
        };
    }, [open]);

    if (!open) return null;

    return (
        <div className="fixed inset-0 z-40 flex items-end justify-center p-0 sm:items-center sm:p-4" role="dialog" aria-modal="true" data-test={testId}>
            <button type="button" aria-label="Close" onClick={onClose} className="absolute inset-0 bg-zinc-950/40 backdrop-blur-[1px]" />
            <section className={clsx('relative flex max-h-[92vh] w-full animate-fade-in flex-col rounded-t-2xl border border-zinc-200 bg-white shadow-2xl sm:rounded-2xl dark:border-zinc-800 dark:bg-zinc-950', width)}>
                <header className="flex items-start gap-3 border-b border-zinc-200 px-4 py-3 dark:border-zinc-800">
                    <div className="min-w-0 flex-1 text-base font-semibold">{title}</div>
                    <button type="button" onClick={onClose} className="focus-ring rounded-md p-1 text-zinc-500 hover:bg-zinc-100 hover:text-zinc-900 dark:hover:bg-zinc-800 dark:hover:text-zinc-100" aria-label="Close">
                        <X className="size-4" />
                    </button>
                </header>
                <div className="min-h-0 flex-1 overflow-y-auto">{children}</div>
            </section>
        </div>
    );
}

export function PageHeader({ title, subtitle, actions }: { title: string; subtitle?: ReactNode; actions?: ReactNode }) {
    return (
        <div className="flex flex-wrap items-end gap-3">
            <div className="min-w-0">
                <h1 className="text-lg font-semibold tracking-tight">{title}</h1>
                {subtitle && <p className="text-sm text-zinc-500">{subtitle}</p>}
            </div>
            {actions && <div className="ms-auto flex flex-wrap items-center gap-2">{actions}</div>}
        </div>
    );
}
