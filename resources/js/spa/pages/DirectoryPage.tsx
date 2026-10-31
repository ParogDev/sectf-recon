import clsx from 'clsx';
import { BadgeCheck, Building2, ExternalLink, Link as LinkIcon, PhoneCall, User } from 'lucide-react';

import { Button, EmptyState, ErrorBox, Loading, PageHeader, Panel, Tag } from '@/components/ui';
import { useToast } from '@/context/ToastContext';
import { useNow } from '@/hooks/useNow';
import { absolute, ago, formatPhone, host, phoneKindLabel } from '@/lib/format';
import { usePeople, usePhones, useTarget, useVerifyPhone } from '@/lib/queries';
import type { Phone } from '@/lib/types';

function SourceInline({ source }: { source: { url: string; title: string | null; retrieved_at: string } | null }) {
    if (!source) return <span className="text-zinc-400">no source</span>;

    return (
        <a href={source.url} target="_blank" rel="noreferrer" title={`${source.url} · retrieved ${absolute(source.retrieved_at)}`} className="inline-flex min-w-0 items-center gap-1 text-sky-600 hover:underline dark:text-sky-400">
            <LinkIcon className="size-3 shrink-0" />
            <span className="truncate">{source.title || host(source.url)}</span>
        </a>
    );
}

function PhoneRow({ phone, canWrite, targetId, now }: { phone: Phone; canWrite: boolean; targetId: number; now: number }) {
    const verify = useVerifyPhone(targetId);
    const toast = useToast();

    return (
        <tr className="align-top text-sm" data-test={`phone-${phone.id}`}>
            <td className="px-3 py-2 font-mono text-xs whitespace-nowrap">
                <a href={`tel:${phone.number}${phone.extension ? `;ext=${phone.extension}` : ''}`} className="hover:underline">
                    {formatPhone(phone.number, phone.extension)}
                </a>
            </td>
            <td className="px-3 py-2">
                <div className="flex flex-wrap items-center gap-1.5">
                    <span className="font-medium">{phone.label}</span>
                    {phone.kind && (
                        <Tag className="bg-violet-500/10 text-violet-700 dark:text-violet-300" title="What kind of place this number reaches">
                            <span data-test="phone-kind">{phoneKindLabel(phone.kind)}</span>
                        </Tag>
                    )}
                </div>
                {phone.department && <div className="text-xs text-zinc-500">{phone.department}</div>}
                {phone.notes && <div className="mt-0.5 text-xs text-zinc-500">{phone.notes}</div>}
            </td>
            <td className="px-3 py-2 text-xs text-zinc-600 dark:text-zinc-400">{phone.person?.name ?? <span className="text-zinc-400">—</span>}</td>
            <td className="max-w-48 px-3 py-2 text-xs">
                <SourceInline source={phone.source} />
            </td>
            <td className="px-3 py-2 text-xs whitespace-nowrap">
                {phone.ring_verified_at ? (
                    <span className="inline-flex items-center gap-1 text-emerald-600 dark:text-emerald-400" title={absolute(phone.ring_verified_at)}>
                        <BadgeCheck className="size-3.5" /> rings · {ago(phone.ring_verified_at, now)}
                    </span>
                ) : canWrite ? (
                    <Button
                        size="sm"
                        loading={verify.isPending}
                        onClick={() =>
                            verify.mutate(phone.id, {
                                onError: (error) => toast.push({ title: 'Could not mark as verified', body: error.message, tone: 'danger' }),
                            })
                        }
                        title="Mark after you heard it ring (the only contact the rules allow)"
                    >
                        <PhoneCall className="size-3" /> It rings
                    </Button>
                ) : (
                    <span className="text-zinc-400">unverified</span>
                )}
            </td>
        </tr>
    );
}

export function DirectoryPage({ targetId }: { targetId: number }) {
    const now = useNow();
    const target = useTarget(targetId);
    const people = usePeople(targetId);
    const phones = usePhones(targetId);
    const canWrite = (target.data?.target.role ?? 'viewer') !== 'viewer';

    const phonesByPerson = new Map<number, Phone[]>();
    for (const phone of phones.data ?? []) {
        if (phone.person) phonesByPerson.set(phone.person.id, [...(phonesByPerson.get(phone.person.id) ?? []), phone]);
    }

    return (
        <div className="flex flex-col gap-4">
            <PageHeader title="Directory" subtitle="Who works there (job-relevant facts only) and every number that may be dialled on stage." />

            <div className="grid gap-4 xl:grid-cols-[minmax(0,2fr)_minmax(0,3fr)]">
                <Panel title="People" aside={people.data ? `${people.data.length}` : undefined} bodyClassName="p-2">
                    {people.isError ? (
                        <ErrorBox message={people.error.message} />
                    ) : people.isPending ? (
                        <Loading />
                    ) : people.data.length === 0 ? (
                        <EmptyState title="No people yet" icon={<User className="size-6" />}>
                            Staff added with add_person appear here with their public role and source.
                        </EmptyState>
                    ) : (
                        <ul className="grid gap-2 sm:grid-cols-2 xl:grid-cols-1 2xl:grid-cols-2">
                            {people.data.map((person) => (
                                <li key={person.id} className="surface-muted flex flex-col gap-1 px-3 py-2.5 text-sm" data-test={`person-${person.key}`}>
                                    <div className="flex items-start gap-2">
                                        <span className="flex size-8 shrink-0 items-center justify-center rounded-full bg-zinc-200 text-xs font-semibold text-zinc-700 dark:bg-zinc-800 dark:text-zinc-200">
                                            {person.name
                                                .split(/\s+/)
                                                .map((p) => p[0])
                                                .join('')
                                                .slice(0, 2)
                                                .toUpperCase()}
                                        </span>
                                        <div className="min-w-0 flex-1">
                                            <div className="flex items-center gap-1.5">
                                                <span className="truncate font-semibold">{person.name}</span>
                                                {person.profile_url && (
                                                    <a href={person.profile_url} target="_blank" rel="noreferrer" title={person.profile_url} className="text-zinc-400 hover:text-sky-600">
                                                        <ExternalLink className="size-3.5" />
                                                    </a>
                                                )}
                                            </div>
                                            <div className="text-xs text-zinc-600 dark:text-zinc-400">{person.title ?? <span className="text-zinc-400">title unknown</span>}</div>
                                            {(person.department || person.tenure) && (
                                                <div className="mt-0.5 flex flex-wrap items-center gap-x-2 text-[11px] text-zinc-500">
                                                    {person.department && (
                                                        <span className="inline-flex items-center gap-1">
                                                            <Building2 className="size-3" /> {person.department}
                                                        </span>
                                                    )}
                                                    {person.tenure && <span>{person.tenure}</span>}
                                                </div>
                                            )}
                                        </div>
                                    </div>
                                    {person.notes && <p className="text-xs text-zinc-500">{person.notes}</p>}
                                    {(phonesByPerson.get(person.id) ?? []).map((phone) => (
                                        <div key={phone.id} className="flex items-center gap-1.5 font-mono text-[11px] text-zinc-600 dark:text-zinc-400">
                                            <PhoneCall className="size-3" /> {formatPhone(phone.number, phone.extension)}
                                            <span className="font-sans text-zinc-400">{phone.label}</span>
                                        </div>
                                    ))}
                                    <div className="mt-0.5 flex items-center gap-2 text-[11px]">
                                        <SourceInline source={person.source} />
                                        <span className="ms-auto text-zinc-400">{ago(person.updated_at, now)}</span>
                                    </div>
                                </li>
                            ))}
                        </ul>
                    )}
                </Panel>

                <Panel
                    title="Phone numbers"
                    aside={
                        phones.data
                            ? `${phones.data.filter((p) => p.ring_verified_at).length} of ${phones.data.length} verified · ${new Set(phones.data.map((p) => p.kind).filter(Boolean)).size} kinds`
                            : undefined
                    }
                >
                    {phones.isError ? (
                        <ErrorBox message={phones.error.message} />
                    ) : phones.isPending ? (
                        <Loading />
                    ) : phones.data.length === 0 ? (
                        <div className="p-2">
                            <EmptyState title="No numbers yet" icon={<PhoneCall className="size-6" />}>
                                Every number you plan to dial on stage must be here with a source. Add them with add_phone.
                            </EmptyState>
                        </div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[40rem]">
                                <thead className="text-start text-[11px] uppercase tracking-wide text-zinc-500">
                                    <tr>
                                        <th className="px-3 py-1.5 text-start font-medium">Number</th>
                                        <th className="px-3 py-1.5 text-start font-medium">Label</th>
                                        <th className="px-3 py-1.5 text-start font-medium">Person</th>
                                        <th className="px-3 py-1.5 text-start font-medium">Source</th>
                                        <th className="px-3 py-1.5 text-start font-medium">Verified</th>
                                    </tr>
                                </thead>
                                <tbody className={clsx('divide-y divide-zinc-100 dark:divide-zinc-800/80')}>
                                    {phones.data.map((phone) => (
                                        <PhoneRow key={phone.id} phone={phone} canWrite={canWrite} targetId={targetId} now={now} />
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </Panel>
            </div>
        </div>
    );
}
