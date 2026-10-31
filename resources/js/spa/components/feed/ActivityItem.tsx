import clsx from 'clsx';

import { absolute, ago, kindTone } from '@/lib/format';
import type { Activity } from '@/lib/types';

export function ActivityItem({ activity, now, fresh = false }: { activity: Activity; now: number; fresh?: boolean }) {
    return (
        <li className={clsx('px-3 py-1.5 text-sm', fresh && 'animate-fade-in')} data-test={`activity-${activity.id}`}>
            <div className="flex items-center gap-2 text-xs text-zinc-500">
                <span className={clsx('shrink-0 rounded px-1 font-mono text-[10px] font-medium', kindTone(activity.kind))}>{activity.kind}</span>
                <span className="truncate">
                    {activity.by ?? 'system'} <span className="text-zinc-400">via {activity.via}</span>
                </span>
                <time className="ms-auto shrink-0 tabular-nums" dateTime={activity.at} title={absolute(activity.at)}>
                    {ago(activity.at, now)}
                </time>
            </div>
            <p className="mt-0.5 leading-snug">{activity.summary}</p>
        </li>
    );
}
