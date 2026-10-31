import type {
    Activity,
    DossierExport,
    Finding,
    Flag,
    FlagStatus,
    Person,
    Phone,
    Pretext,
    SearchEntry,
    Session,
    TargetPayload,
} from './types';

export class ApiError extends Error {
    constructor(
        public status: number,
        message: string,
        public body: unknown = null,
    ) {
        super(message);
    }
}

function cookie(name: string): string | null {
    const match = document.cookie.match(new RegExp('(?:^|; )' + name.replace(/[.$?*|{}()[\]\\/+^]/g, '\\$&') + '=([^;]*)'));

    return match ? decodeURIComponent(match[1]) : null;
}

function csrfToken(): string {
    return cookie('XSRF-TOKEN') ?? document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
}

type Query = Record<string, string | number | null | undefined>;

function withQuery(path: string, query?: Query): string {
    if (!query) return path;

    const params = new URLSearchParams();
    for (const [key, value] of Object.entries(query)) {
        if (value !== undefined && value !== null && value !== '') params.set(key, String(value));
    }
    const qs = params.toString();

    return qs ? `${path}?${qs}` : path;
}

async function request<T>(method: 'GET' | 'POST' | 'PATCH', path: string, body?: unknown, query?: Query): Promise<T> {
    const headers: Record<string, string> = {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    };

    if (method !== 'GET') {
        headers['Content-Type'] = 'application/json';
        headers['X-XSRF-TOKEN'] = csrfToken();
    }

    const response = await fetch(withQuery(`/api/app/${path}`, query), {
        method,
        headers,
        credentials: 'same-origin',
        body: body === undefined ? undefined : JSON.stringify(body),
    });

    if (response.status === 401 || response.status === 419) {
        window.location.assign('/login');
        throw new ApiError(response.status, 'Signed out');
    }

    const text = await response.text();
    const json = text ? (JSON.parse(text) as unknown) : null;

    if (!response.ok) {
        const message =
            json && typeof json === 'object' && 'message' in json && typeof (json as { message: unknown }).message === 'string'
                ? (json as { message: string }).message
                : `${response.status} ${response.statusText}`;

        throw new ApiError(response.status, message, json);
    }

    return json as T;
}

export const api = {
    session: () => request<Session>('GET', 'session'),

    selectTarget: (targetId: number) => request<Session>('POST', `targets/${targetId}/select`),

    target: (targetId: number) => request<TargetPayload>('GET', `targets/${targetId}`),

    findings: (targetId: number, query?: { flag_id?: string | null; search?: string | null; limit?: number }) =>
        request<{ findings: Finding[] }>('GET', `targets/${targetId}/findings`, undefined, query).then((r) => r.findings),

    activity: (targetId: number, query?: { limit?: number; after?: number }) =>
        request<{ activity: Activity[] }>('GET', `targets/${targetId}/activity`, undefined, query).then((r) => r.activity),

    people: (targetId: number) => request<{ people: Person[] }>('GET', `targets/${targetId}/people`).then((r) => r.people),

    phones: (targetId: number) => request<{ phones: Phone[] }>('GET', `targets/${targetId}/phones`).then((r) => r.phones),

    verifyPhone: (targetId: number, phoneId: number) =>
        request<{ phone: Phone }>('POST', `targets/${targetId}/phones/${phoneId}/ring-verified`).then((r) => r.phone),

    pretexts: (targetId: number) => request<{ pretexts: Pretext[] }>('GET', `targets/${targetId}/pretexts`).then((r) => r.pretexts),

    searches: (targetId: number, query?: { contains?: string | null; flag_id?: string | null; limit?: number }) =>
        request<{ searches: SearchEntry[] }>('GET', `targets/${targetId}/searches`, undefined, query).then((r) => r.searches),

    updateFlag: (targetId: number, flagId: string, body: { status: FlagStatus; notes?: string | null }) =>
        request<{ flag: Flag }>('PATCH', `targets/${targetId}/flags/${flagId}`, body).then((r) => r.flag),

    /** Render the dossier (EN + FR, PDF + HTML) to the export folder on the server. */
    exportDossier: (targetId: number) => request<DossierExport>('POST', `targets/${targetId}/dossier/export`),
};
