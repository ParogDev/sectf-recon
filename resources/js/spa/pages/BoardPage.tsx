import { ExternalLink } from 'lucide-react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { Link, useSearchParams } from 'react-router';

import { FlagDrawer } from '@/components/board/FlagDrawer';
import { FlagList } from '@/components/board/FlagList';
import { KpiStrip } from '@/components/board/KpiStrip';
import { ActivityItem } from '@/components/feed/ActivityItem';
import { FindingCard } from '@/components/feed/FindingCard';
import { Badge, ErrorBox, Loading, Panel } from '@/components/ui';
import { useNow } from '@/hooks/useNow';
import { roleTone } from '@/lib/format';
import { useActivity, useFindings, useTarget } from '@/lib/queries';

export function BoardPage({ targetId }: { targetId: number }) {
    const now = useNow();
    const target = useTarget(targetId);
    const findings = useFindings(targetId, { limit: 12 });
    const activity = useActivity(targetId, 40);
    const [params, setParams] = useSearchParams();
    const activeId = params.get('flag');

    const openFlag = useCallback(
        (id: string | null) =>
            setParams(
                (prev) => {
                    const next = new URLSearchParams(prev);
                    if (id) next.set('flag', id);
                    else next.delete('flag');

                    return next;
                },
                { replace: true },
            ),
        [setParams],
    );

    // Activities seen on first render are not "fresh"; later ones animate in.
    const seen = useRef<Set<number> | null>(null);
    const [, bump] = useState(0);
    useEffect(() => {
        if (!activity.data) return;
        if (seen.current === null) {
            seen.current = new Set(activity.data.map((a) => a.id));
        } else {
            const timer = window.setTimeout(() => {
                seen.current = new Set(activity.data?.map((a) => a.id));
                bump((n) => n + 1);
            }, 2000);

            return () => window.clearTimeout(timer);
        }
    }, [activity.data]);

    const activeFlag = useMemo(() => target.data?.flags.find((f) => f.id === activeId) ?? null, [target.data, activeId]);

    if (target.isPending) return <Loading label="Loading board…" />;
    if (target.isError) return <ErrorBox message={target.error.message} />;

    const { target: info, summary, flags, next_best } = target.data;
    const canWrite = info.role !== 'viewer';

    return (
        <div className="flex flex-col gap-4">
            <div className="flex flex-wrap items-center gap-2">
                <h1 className="text-lg font-semibold tracking-tight">{info.name}</h1>
                <Badge className={roleTone(info.role)}>{info.role}</Badge>
                {info.url && (
                    <a href={info.url} target="_blank" rel="noreferrer" className="inline-flex items-center gap-1 text-xs text-sky-600 hover:underline dark:text-sky-400">
                        {info.url.replace(/^https?:\/\/(www\.)?/, '').replace(/\/$/, '')} <ExternalLink className="size-3" />
                    </a>
                )}
                {info.domains && info.domains.length > 0 && (
                    <span className="hidden text-xs text-zinc-500 sm:inline">· {info.domains.join(', ')}</span>
                )}
            </div>

            <KpiStrip summary={summary} nextBest={next_best} flags={flags} onOpenFlag={openFlag} />

            <div className="grid gap-4 xl:grid-cols-[minmax(0,3fr)_minmax(0,2fr)]">
                <FlagList flags={flags} summary={summary} activeId={activeId} onOpen={openFlag} />

                <div className="flex flex-col gap-4 xl:sticky xl:top-4 xl:max-h-[calc(100vh-2rem)] xl:self-start">
                    <Panel
                        title="Latest findings"
                        aside={
                            <Link to="/findings" className="hover:underline">
                                all →
                            </Link>
                        }
                        className="xl:min-h-0 xl:flex-1"
                        bodyClassName="max-h-[50vh] overflow-y-auto xl:max-h-none xl:h-[calc(100%-2.4rem)]"
                        data-test="findings-feed"
                    >
                        {findings.isPending ? (
                            <Loading />
                        ) : findings.data && findings.data.length > 0 ? (
                            <div className="divide-y divide-zinc-100 dark:divide-zinc-800/80">
                                {findings.data.map((finding) => (
                                    <FindingCard key={finding.key} finding={finding} now={now} onOpenFlag={openFlag} compact />
                                ))}
                            </div>
                        ) : (
                            <p className="px-3 py-4 text-sm text-zinc-500">Nothing filed yet. Findings recorded with record_finding show up here, newest first.</p>
                        )}
                    </Panel>

                    <Panel
                        title="Activity"
                        aside={
                            <Link to="/activity" className="hover:underline">
                                all →
                            </Link>
                        }
                        className="xl:min-h-0 xl:flex-1"
                        bodyClassName="max-h-[40vh] overflow-y-auto xl:max-h-none xl:h-[calc(100%-2.4rem)]"
                    >
                        {activity.isPending ? (
                            <Loading />
                        ) : activity.data && activity.data.length > 0 ? (
                            <ul className="divide-y divide-zinc-100 dark:divide-zinc-800/80">
                                {activity.data.slice(0, 25).map((item) => (
                                    <ActivityItem key={item.id} activity={item} now={now} fresh={seen.current !== null && !seen.current.has(item.id)} />
                                ))}
                            </ul>
                        ) : (
                            <p className="px-3 py-4 text-sm text-zinc-500">No activity yet.</p>
                        )}
                    </Panel>
                </div>
            </div>

            <FlagDrawer targetId={targetId} flag={activeFlag} canWrite={canWrite} onClose={() => openFlag(null)} onOpenFlag={openFlag} />
        </div>
    );
}
