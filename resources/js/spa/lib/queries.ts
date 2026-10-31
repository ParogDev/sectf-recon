import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { api } from './api';
import type { Activity, Flag, FlagStatus, Phone, Session, TargetPayload } from './types';

export const keys = {
    session: ['session'] as const,
    target: (id: number) => ['target', id] as const,
    findings: (id: number, flagId?: string | null, search?: string | null) => ['target', id, 'findings', flagId ?? null, search ?? null] as const,
    activity: (id: number) => ['target', id, 'activity'] as const,
    people: (id: number) => ['target', id, 'people'] as const,
    phones: (id: number) => ['target', id, 'phones'] as const,
    pretexts: (id: number) => ['target', id, 'pretexts'] as const,
    searches: (id: number, contains?: string | null, flagId?: string | null) => ['target', id, 'searches', contains ?? null, flagId ?? null] as const,
};

export function useSession() {
    return useQuery({ queryKey: keys.session, queryFn: api.session, staleTime: 60_000 });
}

export function useSelectTarget() {
    const client = useQueryClient();

    return useMutation({
        mutationFn: api.selectTarget,
        onMutate: async (targetId) => {
            await client.cancelQueries({ queryKey: keys.session });
            const previous = client.getQueryData<Session>(keys.session);
            if (previous) client.setQueryData<Session>(keys.session, { ...previous, current_target_id: targetId });

            return { previous };
        },
        onError: (_error, _id, context) => {
            if (context?.previous) client.setQueryData(keys.session, context.previous);
        },
        onSuccess: (session) => client.setQueryData(keys.session, session),
    });
}

export function useTarget(id: number | null) {
    return useQuery({
        queryKey: keys.target(id ?? 0),
        queryFn: () => api.target(id as number),
        enabled: id !== null,
        staleTime: 15_000,
    });
}

export function useFindings(id: number | null, options: { flagId?: string | null; search?: string | null; limit?: number } = {}) {
    return useQuery({
        queryKey: keys.findings(id ?? 0, options.flagId, options.search),
        queryFn: () => api.findings(id as number, { flag_id: options.flagId, search: options.search, limit: options.limit ?? 100 }),
        enabled: id !== null,
        staleTime: 15_000,
        placeholderData: (previous) => previous,
    });
}

export function useActivity(id: number | null, limit = 60) {
    return useQuery({
        queryKey: keys.activity(id ?? 0),
        queryFn: () => api.activity(id as number, { limit }),
        enabled: id !== null,
        staleTime: 15_000,
    });
}

export function usePeople(id: number | null) {
    return useQuery({ queryKey: keys.people(id ?? 0), queryFn: () => api.people(id as number), enabled: id !== null, staleTime: 30_000 });
}

export function usePhones(id: number | null) {
    return useQuery({ queryKey: keys.phones(id ?? 0), queryFn: () => api.phones(id as number), enabled: id !== null, staleTime: 30_000 });
}

export function usePretexts(id: number | null) {
    return useQuery({ queryKey: keys.pretexts(id ?? 0), queryFn: () => api.pretexts(id as number), enabled: id !== null, staleTime: 30_000 });
}

export function useSearches(id: number | null, options: { contains?: string | null; flagId?: string | null; limit?: number } = {}) {
    return useQuery({
        queryKey: keys.searches(id ?? 0, options.contains, options.flagId),
        queryFn: () => api.searches(id as number, { contains: options.contains, flag_id: options.flagId, limit: options.limit ?? 100 }),
        enabled: id !== null,
        staleTime: 15_000,
        placeholderData: (previous) => previous,
    });
}

export function useVerifyPhone(targetId: number) {
    const client = useQueryClient();

    return useMutation({
        mutationFn: (phoneId: number) => api.verifyPhone(targetId, phoneId),
        onSuccess: (phone) => {
            client.setQueryData<Phone[]>(keys.phones(targetId), (list) => list?.map((p) => (p.id === phone.id ? phone : p)));
        },
    });
}

export function useUpdateFlag(targetId: number) {
    const client = useQueryClient();

    return useMutation({
        mutationFn: ({ flagId, status, notes }: { flagId: string; status: FlagStatus; notes?: string | null }) =>
            api.updateFlag(targetId, flagId, { status, notes }),
        onSuccess: (flag: Flag) => {
            client.setQueryData<TargetPayload>(keys.target(targetId), (payload) =>
                payload ? { ...payload, flags: payload.flags.map((f) => (f.id === flag.id ? flag : f)) } : payload,
            );
            // Summary and next-best depend on the status; refetch them.
            void client.invalidateQueries({ queryKey: keys.target(targetId), exact: true });
        },
    });
}

export function useExportDossier(targetId: number | null) {
    return useMutation({
        mutationKey: ['target', targetId ?? 0, 'dossier', 'export'],
        mutationFn: () => api.exportDossier(targetId as number),
    });
}

/** Prepend a live activity to the cached feed without a round-trip. */
export function pushActivity(client: ReturnType<typeof useQueryClient>, targetId: number, activity: Activity): void {
    client.setQueryData<Activity[]>(keys.activity(targetId), (list) => {
        if (!list) return list;
        if (list.some((a) => a.id === activity.id)) return list;

        return [activity, ...list].slice(0, 200);
    });
}
