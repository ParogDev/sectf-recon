import { useMemo } from 'react';
import { Navigate, Route, Routes, useNavigate } from 'react-router';

import { AppShell } from '@/components/layout/AppShell';
import { ErrorBox, Loading } from '@/components/ui';
import { ToastProvider, useToast } from '@/context/ToastContext';
import { useLive } from '@/hooks/useLive';
import { useActivity, usePeople, usePhones, usePretexts, useSelectTarget, useSession, useTarget } from '@/lib/queries';
import { ActivityPage } from '@/pages/ActivityPage';
import { BoardPage } from '@/pages/BoardPage';
import { DirectoryPage } from '@/pages/DirectoryPage';
import { FindingsPage } from '@/pages/FindingsPage';
import { NoTargetPage } from '@/pages/NoTargetPage';
import { PretextsPage } from '@/pages/PretextsPage';
import { SearchesPage } from '@/pages/SearchesPage';

function Shell() {
    const session = useSession();
    const select = useSelectTarget();
    const toast = useToast();
    const targetId = session.data?.current_target_id ?? null;
    const live = useLive(targetId);

    // Sidebar counts: cheap, cached, and refreshed by the live feed.
    const target = useTarget(targetId);
    const people = usePeople(targetId);
    const phones = usePhones(targetId);
    const pretexts = usePretexts(targetId);
    const activity = useActivity(targetId);

    const counts = useMemo(() => {
        const out: Record<string, number> = {};
        if (target.data) out['/findings'] = target.data.flags.reduce((n, f) => n + f.findings, 0);
        if (people.data && phones.data) out['/directory'] = people.data.length + phones.data.length;
        if (pretexts.data) out['/pretexts'] = pretexts.data.length;
        if (activity.data) out['/activity'] = activity.data.length;

        return out;
    }, [target.data, people.data, phones.data, pretexts.data, activity.data]);

    if (session.isPending) return <Loading label="Signing in…" />;
    if (session.isError) {
        return (
            <div className="p-6">
                <ErrorBox message={session.error.message} />
            </div>
        );
    }

    const onSelectTarget = (id: number) =>
        select.mutate(id, {
            onError: (error) => toast.push({ title: 'Could not switch target', body: error.message, tone: 'danger' }),
        });

    return (
        <AppShell session={session.data} live={live} onSelectTarget={onSelectTarget} switching={select.isPending} counts={counts}>
            {targetId === null ? (
                <NoTargetPage />
            ) : (
                <Routes>
                    <Route index element={<BoardPage targetId={targetId} />} />
                    <Route path="findings" element={<FindingsPage targetId={targetId} />} />
                    <Route path="directory" element={<DirectoryPage targetId={targetId} />} />
                    <Route path="pretexts" element={<PretextsPage targetId={targetId} />} />
                    <Route path="searches" element={<SearchesPage targetId={targetId} />} />
                    <Route path="activity" element={<ActivityPage targetId={targetId} />} />
                    <Route path="*" element={<Navigate to="/" replace />} />
                </Routes>
            )}
        </AppShell>
    );
}

export function App() {
    const navigate = useNavigate();

    return (
        <ToastProvider onNavigate={(href) => navigate(href)}>
            <Shell />
        </ToastProvider>
    );
}
