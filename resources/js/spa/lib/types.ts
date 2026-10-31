export type Role = 'owner' | 'editor' | 'viewer';

export type FlagStatus = 'open' | 'partial' | 'found' | 'unverifiable';

export interface SessionUser {
    id: number;
    name: string;
    email: string;
    initials: string;
}

export interface TargetSummaryItem {
    id: number;
    name: string;
    url: string | null;
    role: Role;
    owner: string | null;
}

export interface Session {
    user: SessionUser;
    current_target_id: number | null;
    targets: TargetSummaryItem[];
}

export interface TargetInfo {
    id: number;
    name: string;
    url: string | null;
    domains: string[] | null;
    registry_id: string | null;
    notes: string | null;
    role: Role;
    owner: string | null;
}

export interface CategorySummary {
    category: string;
    banked: number;
    available: number;
    found: number;
    total: number;
}

export interface Summary {
    osint_points_banked: number;
    osint_points_available: number;
    flags_found: number;
    flags_partial: number;
    flags_open: number;
    by_category: CategorySummary[];
}

export interface Flag {
    id: string;
    category_id: string;
    category: string;
    question: string;
    points: number;
    osint_points: number;
    status: FlagStatus;
    findings: number;
    bonus: boolean;
    call_only: boolean;
    related_flag_id: string | null;
    notes: string | null;
}

export interface TargetPayload {
    target_id: number;
    target: TargetInfo;
    summary: Summary;
    flags: Flag[];
    next_best: string[];
}

export interface Source {
    url: string;
    title: string | null;
    retrieved_at: string;
    content_date: string | null;
    quote: string | null;
}

export interface Finding {
    id: number;
    key: string;
    flag_id: string | null;
    claim: string;
    detail: string | null;
    confidence: number;
    content_date: string | null;
    by: string | null;
    created_at: string;
    updated_at: string;
    sources: Source[];
}

export interface Activity {
    id: number;
    kind: string;
    summary: string;
    subject: { type: string; id: number } | null;
    by: string | null;
    via: string;
    at: string;
}

export interface SourceRef {
    url: string;
    title: string | null;
    retrieved_at: string;
}

export interface Person {
    id: number;
    key: string;
    name: string;
    title: string | null;
    department: string | null;
    tenure: string | null;
    profile_url: string | null;
    notes: string | null;
    source: SourceRef | null;
    phone_ids: number[];
    updated_at: string;
}

/** What kind of place a number reaches (PhoneKind enum on the backend). */
export type PhoneKind = 'corporate' | 'customer_service' | 'store' | 'small_format_store' | 'distribution_centre' | 'repair_service' | 'regional_office' | 'department' | 'other';

export interface Phone {
    id: number;
    number: string;
    extension: string;
    label: string;
    department: string | null;
    person: { id: number; key: string; name: string } | null;
    kind: PhoneKind | null;
    ring_verified_at: string | null;
    notes: string | null;
    source: SourceRef | null;
    updated_at: string;
}

/** A flag the call can naturally steer to, and the line that gets there. */
export interface PretextPivot {
    flag_id: string;
    bridge: string;
}

export interface Pretext {
    id: number;
    key: string;
    title: string;
    persona: string;
    script: string | null;
    untranslated?: boolean;
    flag_ids: string[];
    pivots: PretextPivot[];
    phone_numbers: { id: number; number: string; extension: string; label: string }[];
    confidence: number;
    by: string | null;
    updated_at: string;
}

export type ReadinessGap =
    | { code: 'pretexts_min'; have: number; need: number }
    | { code: 'pretexts_no_pivots'; refs: string[] }
    | { code: 'phone_kinds'; have: number; need: number }
    | { code: 'small_format_only' }
    | { code: 'fr_missing'; count: number }
    | { code: 'research_incomplete'; flags: string[] }
    | { code: string; [key: string]: unknown };

export interface Readiness {
    ok: boolean;
    pretexts: { have: number; need: number };
    phone_kinds: { have: Record<string, number>; distinct: number; need: number };
    french_missing: { total: number; [key: string]: number };
    gaps: ReadinessGap[];
}

export interface DossierFile {
    lang: 'en' | 'fr';
    type: 'pdf' | 'html';
    name: string;
    bytes: number;
    url: string;
}

export interface DossierExport {
    target_id: number;
    generated_at: string;
    pdf_ok: boolean;
    folder: { host_path: string | null; path: string };
    files: DossierFile[];
    readiness: Readiness;
    warnings: string[];
}

export interface SearchEntry {
    id: number;
    channel: string;
    query: string;
    flag_id: string | null;
    result_count: number | null;
    notes: string | null;
    by: string | null;
    at: string;
}

export type LiveStatus = 'offline' | 'connecting' | 'live';
