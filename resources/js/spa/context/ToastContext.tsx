import { X } from 'lucide-react';
import { createContext, useCallback, useContext, useMemo, useRef, useState, type ReactNode } from 'react';

export interface Toast {
    id: number;
    title: string;
    body?: string;
    tone?: 'neutral' | 'success' | 'warning' | 'danger';
    /** Where a click on the toast should go. */
    href?: string;
}

interface ToastApi {
    push: (toast: Omit<Toast, 'id'>) => void;
    dismiss: (id: number) => void;
}

const ToastContext = createContext<ToastApi | null>(null);

const TONE: Record<NonNullable<Toast['tone']>, string> = {
    neutral: 'border-zinc-300 dark:border-zinc-700',
    success: 'border-emerald-500/50',
    warning: 'border-amber-500/50',
    danger: 'border-rose-500/50',
};

export function ToastProvider({ children, onNavigate }: { children: ReactNode; onNavigate?: (href: string) => void }) {
    const [toasts, setToasts] = useState<Toast[]>([]);
    const counter = useRef(0);

    const dismiss = useCallback((id: number) => setToasts((list) => list.filter((t) => t.id !== id)), []);

    const push = useCallback(
        (toast: Omit<Toast, 'id'>) => {
            const id = ++counter.current;
            setToasts((list) => [...list.slice(-4), { ...toast, id }]);
            window.setTimeout(() => dismiss(id), 6000);
        },
        [dismiss],
    );

    const value = useMemo(() => ({ push, dismiss }), [push, dismiss]);

    return (
        <ToastContext.Provider value={value}>
            {children}
            <div className="pointer-events-none fixed inset-x-0 bottom-4 z-50 flex flex-col items-center gap-2 px-4 sm:items-end sm:px-6" aria-live="polite">
                {toasts.map((toast) => (
                    <div
                        key={toast.id}
                        className={`pointer-events-auto flex w-full max-w-sm animate-slide-in items-start gap-3 rounded-xl border bg-white/95 p-3 text-sm shadow-lg backdrop-blur dark:bg-zinc-900/95 ${TONE[toast.tone ?? 'neutral']}`}
                    >
                        <button
                            type="button"
                            className="min-w-0 flex-1 text-start"
                            onClick={() => {
                                if (toast.href) onNavigate?.(toast.href);
                                dismiss(toast.id);
                            }}
                        >
                            <div className="font-medium text-zinc-900 dark:text-zinc-100">{toast.title}</div>
                            {toast.body && <div className="mt-0.5 line-clamp-2 text-xs text-zinc-600 dark:text-zinc-400">{toast.body}</div>}
                        </button>
                        <button
                            type="button"
                            onClick={() => dismiss(toast.id)}
                            className="focus-ring rounded p-0.5 text-zinc-400 hover:text-zinc-700 dark:hover:text-zinc-200"
                            aria-label="Dismiss"
                        >
                            <X className="size-4" />
                        </button>
                    </div>
                ))}
            </div>
        </ToastContext.Provider>
    );
}

export function useToast(): ToastApi {
    const api = useContext(ToastContext);
    if (!api) throw new Error('useToast must be used inside ToastProvider');

    return api;
}
