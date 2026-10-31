import { useEffect } from 'react';

function inEditable(target: EventTarget | null): boolean {
    if (!(target instanceof HTMLElement)) return false;

    return target.isContentEditable || ['INPUT', 'TEXTAREA', 'SELECT'].includes(target.tagName);
}

/**
 * Run `handler` when `key` is pressed outside a text field (unless `always`).
 * Modifier-free keys only; "Escape" is allowed inside fields too.
 */
export function useHotkey(key: string, handler: (event: KeyboardEvent) => void, options: { always?: boolean; enabled?: boolean } = {}): void {
    const { always = false, enabled = true } = options;

    useEffect(() => {
        if (!enabled) return;

        const listener = (event: KeyboardEvent) => {
            if (event.key !== key || event.metaKey || event.ctrlKey || event.altKey) return;
            if (!always && key !== 'Escape' && inEditable(event.target)) return;

            handler(event);
        };

        window.addEventListener('keydown', listener);

        return () => window.removeEventListener('keydown', listener);
    }, [key, handler, always, enabled]);
}
