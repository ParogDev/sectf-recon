import { useQueryClient } from '@tanstack/react-query';
import { useEffect, useState } from 'react';

import { useToast } from '@/context/ToastContext';
import { connection, echo } from '@/lib/echo';
import { keys, pushActivity } from '@/lib/queries';
import type { Activity, LiveStatus } from '@/lib/types';

/**
 * Subscribe to the target's private channel. Every `.activity.recorded`
 * event lands in the activity cache immediately and refreshes the queries
 * its kind can have changed, so the board updates without a reload.
 */
export function useLive(targetId: number | null, options: { toasts?: boolean } = {}): LiveStatus {
    const { toasts = true } = options;
    const client = useQueryClient();
    const toast = useToast();
    const [status, setStatus] = useState<LiveStatus>('offline');

    useEffect(() => {
        if (targetId === null) {
            setStatus('offline');

            return;
        }

        const channelName = `targets.${targetId}`;
        let subscribed = false;
        setStatus('connecting');

        const onActivity = (activity: Activity) => {
            pushActivity(client, targetId, activity);

            const prefix = activity.kind.split('.')[0];
            const invalidate = (key: readonly unknown[]) => void client.invalidateQueries({ queryKey: key });

            switch (prefix) {
                case 'finding':
                    invalidate(keys.target(targetId));
                    break;
                case 'flag':
                    invalidate([...keys.target(targetId)]);
                    break;
                case 'person':
                    invalidate(keys.people(targetId));
                    break;
                case 'phone':
                    invalidate(keys.phones(targetId));
                    invalidate(keys.people(targetId));
                    break;
                case 'pretext':
                    invalidate(keys.pretexts(targetId));
                    break;
                case 'target':
                    invalidate(keys.session);
                    invalidate(keys.target(targetId));
                    break;
            }

            if (toasts && activity.by !== window.__SECTF__.user.name) {
                toast.push({
                    title: `${activity.by ?? 'Someone'} · ${activity.kind}`,
                    body: activity.summary,
                    tone: prefix === 'finding' ? 'success' : 'neutral',
                    href: prefix === 'finding' ? '/findings' : prefix === 'person' || prefix === 'phone' ? '/directory' : prefix === 'pretext' ? '/pretexts' : '/activity',
                });
            }
        };

        const channel = echo().private(channelName);
        channel.subscribed(() => {
            subscribed = true;
            setStatus('live');
        });
        channel.error(() => setStatus('offline'));
        channel.listen('.activity.recorded', onActivity);

        const conn = connection();
        const onState = (payload?: unknown) => {
            const current = (payload as { current?: string } | undefined)?.current ?? conn?.state;
            if (current === 'connected') setStatus(subscribed ? 'live' : 'connecting');
            else if (current === 'connecting' || current === 'initialized') setStatus('connecting');
            else setStatus('offline');
        };
        conn?.bind('state_change', onState);

        return () => {
            conn?.unbind('state_change', onState);
            echo().leave(channelName);
        };
    }, [targetId, client, toast, toasts]);

    return status;
}
