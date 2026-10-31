import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

declare global {
    interface Window {
        Pusher: typeof Pusher;
        Echo?: Echo<'reverb'>;
        __SECTF__: {
            appName: string;
            user: { id: number; name: string; email: string; initials: string };
            urls: { settings: string; logout: string };
        };
    }
}

let instance: Echo<'reverb'> | null = null;

/**
 * One Reverb connection for the whole app, created on first use so a page
 * that never subscribes never opens a socket.
 */
export function echo(): Echo<'reverb'> {
    if (instance) return instance;

    window.Pusher = Pusher;

    const scheme = import.meta.env.VITE_REVERB_SCHEME ?? 'https';

    instance = new Echo({
        broadcaster: 'reverb',
        key: import.meta.env.VITE_REVERB_APP_KEY,
        wsHost: import.meta.env.VITE_REVERB_HOST,
        wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
        wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
        forceTLS: scheme === 'https',
        enabledTransports: ['ws', 'wss'],
    });

    window.Echo = instance;

    return instance;
}

export interface PusherConnection {
    state: string;
    bind: (event: string, handler: (payload?: unknown) => void) => void;
    unbind: (event: string, handler: (payload?: unknown) => void) => void;
}

export function connection(): PusherConnection | null {
    const connector = echo().connector as { pusher?: { connection: PusherConnection } };

    return connector.pusher?.connection ?? null;
}
