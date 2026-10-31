import type { FlagStatus, PhoneKind, ReadinessGap } from './types';

/** "2.5", "5", "46" — never "5.0". */
export function points(value: number): string {
    return Number.isInteger(value) ? String(value) : value.toFixed(1).replace(/\.0$/, '');
}

export function percent(part: number, whole: number): number {
    return whole > 0 ? Math.round((part / whole) * 100) : 0;
}

/** Relative time, short form ("just now", "4m", "2h", "3d"). */
export function ago(iso: string, now: number = Date.now()): string {
    const seconds = Math.max(0, Math.round((now - Date.parse(iso)) / 1000));

    if (seconds < 45) return 'just now';
    const minutes = Math.round(seconds / 60);
    if (minutes < 60) return `${minutes}m`;
    const hours = Math.round(minutes / 60);
    if (hours < 24) return `${hours}h`;
    const days = Math.round(hours / 24);
    if (days < 30) return `${days}d`;
    const months = Math.round(days / 30);
    if (months < 12) return `${months}mo`;

    return `${Math.round(months / 12)}y`;
}

export function absolute(iso: string): string {
    const date = new Date(iso);

    return date.toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' });
}

export function host(url: string): string {
    try {
        return new URL(url).host.replace(/^www\./, '');
    } catch {
        return url;
    }
}

/** +15145551234 → +1 514 555 1234 */
export function formatPhone(number: string, extension = ''): string {
    const digits = number.replace(/\D/g, '');
    let out = number;

    if (digits.length === 11 && digits.startsWith('1')) {
        out = `+1 ${digits.slice(1, 4)} ${digits.slice(4, 7)} ${digits.slice(7)}`;
    }

    return extension ? `${out} x${extension}` : out;
}

export interface StatusMeta {
    label: string;
    short: string;
    badge: string;
    dot: string;
    text: string;
    bar: string;
    row: string;
}

export const STATUS: Record<FlagStatus, StatusMeta> = {
    found: {
        label: 'Found',
        short: 'found',
        badge: 'bg-emerald-500/15 text-emerald-700 ring-emerald-500/30 dark:text-emerald-300',
        dot: 'bg-emerald-500',
        text: 'text-emerald-600 dark:text-emerald-400',
        bar: 'bg-emerald-500',
        row: 'bg-emerald-500/5 hover:bg-emerald-500/10',
    },
    partial: {
        label: 'Partial',
        short: 'partial',
        badge: 'bg-amber-500/15 text-amber-700 ring-amber-500/30 dark:text-amber-300',
        dot: 'bg-amber-500',
        text: 'text-amber-600 dark:text-amber-400',
        bar: 'bg-amber-500',
        row: 'hover:bg-amber-500/10',
    },
    unverifiable: {
        label: 'Unverifiable',
        short: 'unverif.',
        badge: 'bg-rose-500/15 text-rose-700 ring-rose-500/30 dark:text-rose-300',
        dot: 'bg-rose-500',
        text: 'text-rose-600 dark:text-rose-400',
        bar: 'bg-rose-500',
        row: 'hover:bg-rose-500/10',
    },
    open: {
        label: 'Open',
        short: 'open',
        badge: 'bg-zinc-500/10 text-zinc-600 ring-zinc-500/20 dark:text-zinc-400',
        dot: 'bg-zinc-400 dark:bg-zinc-600',
        text: 'text-zinc-500',
        bar: 'bg-zinc-400',
        row: 'hover:bg-zinc-500/10',
    },
};

export const STATUS_ORDER: FlagStatus[] = ['open', 'partial', 'found', 'unverifiable'];

export const FOUND_CONFIDENCE = 0.7;

export function confidenceTone(confidence: number): string {
    if (confidence >= FOUND_CONFIDENCE) return 'text-emerald-600 dark:text-emerald-400';
    if (confidence >= 0.4) return 'text-amber-600 dark:text-amber-400';

    return 'text-rose-600 dark:text-rose-400';
}

export function confidenceBar(confidence: number): string {
    if (confidence >= FOUND_CONFIDENCE) return 'bg-emerald-500';
    if (confidence >= 0.4) return 'bg-amber-500';

    return 'bg-rose-500';
}

/** Colour family for an activity kind prefix ("finding.recorded" → finding). */
export function kindTone(kind: string): string {
    const prefix = kind.split('.')[0];

    switch (prefix) {
        case 'finding':
            return 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300';
        case 'flag':
            return 'bg-amber-500/15 text-amber-700 dark:text-amber-300';
        case 'target':
            return 'bg-sky-500/15 text-sky-700 dark:text-sky-300';
        case 'person':
        case 'phone':
            return 'bg-violet-500/15 text-violet-700 dark:text-violet-300';
        case 'pretext':
            return 'bg-fuchsia-500/15 text-fuchsia-700 dark:text-fuchsia-300';
        default:
            return 'bg-zinc-500/10 text-zinc-600 dark:text-zinc-400';
    }
}

export function roleTone(role: string): string {
    switch (role) {
        case 'owner':
            return 'bg-sky-500/15 text-sky-700 ring-sky-500/30 dark:text-sky-300';
        case 'editor':
            return 'bg-emerald-500/15 text-emerald-700 ring-emerald-500/30 dark:text-emerald-300';
        default:
            return 'bg-zinc-500/10 text-zinc-600 ring-zinc-500/20 dark:text-zinc-400';
    }
}

export function plural(count: number, singular: string, pluralForm = `${singular}s`): string {
    return `${count} ${count === 1 ? singular : pluralForm}`;
}

/** Short labels for what kind of place a number reaches. */
export const PHONE_KIND_LABEL: Record<PhoneKind, string> = {
    corporate: 'corporate',
    customer_service: 'customer service',
    store: 'store',
    small_format_store: 'small-format store',
    distribution_centre: 'distribution centre',
    repair_service: 'repair service',
    regional_office: 'regional office',
    department: 'department',
    other: 'other',
};

export function phoneKindLabel(kind: string | null | undefined): string | null {
    if (!kind) return null;

    return (PHONE_KIND_LABEL as Record<string, string>)[kind] ?? kind.replace(/_/g, ' ');
}

/** "12.3 KB", "1.4 MB" */
export function bytes(n: number): string {
    if (n < 1024) return `${n} B`;
    if (n < 1024 * 1024) return `${(n / 1024).toFixed(n < 10 * 1024 ? 1 : 0)} KB`;

    return `${(n / (1024 * 1024)).toFixed(1)} MB`;
}

/** The UI language, from the <html lang> the Blade shell sets from the app locale. */
export function uiLanguage(): 'en' | 'fr' {
    return document.documentElement.lang.toLowerCase().startsWith('fr') ? 'fr' : 'en';
}

/** One plain sentence per readiness gap; suggestions, not errors. */
export function readinessGapText(gap: ReadinessGap): string {
    switch (gap.code) {
        case 'pretexts_min':
            return `Only ${gap.have} of ${gap.need} pretexts`;
        case 'pretexts_no_pivots':
            return `No pivot flags on ${(gap.refs as string[]).join(', ')}`;
        case 'phone_kinds':
            return `Numbers reach only ${gap.have} of ${gap.need} kinds of place`;
        case 'small_format_only':
            return 'Every store number is a small-format store';
        case 'fr_missing':
            return `French missing on ${gap.count} items`;
        case 'research_incomplete':
            return `Research did not finish on ${(gap.flags as string[]).join(', ')}; needs a person`;
        default:
            return gap.code.replace(/_/g, ' ');
    }
}
