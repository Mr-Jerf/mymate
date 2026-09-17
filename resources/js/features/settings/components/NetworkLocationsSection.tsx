import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import { MapPin, Plus, X } from '@phosphor-icons/react';
import { apiClient } from '../../../lib/apiClient';
import { pushToast } from '../../../lib/toast';

const STATES = [
    ['AL', 'Alabama'], ['AK', 'Alaska'], ['AZ', 'Arizona'], ['AR', 'Arkansas'], ['CA', 'California'],
    ['CO', 'Colorado'], ['CT', 'Connecticut'], ['DE', 'Delaware'], ['DC', 'District of Columbia'],
    ['FL', 'Florida'], ['GA', 'Georgia'], ['HI', 'Hawaii'], ['ID', 'Idaho'], ['IL', 'Illinois'],
    ['IN', 'Indiana'], ['IA', 'Iowa'], ['KS', 'Kansas'], ['KY', 'Kentucky'], ['LA', 'Louisiana'],
    ['ME', 'Maine'], ['MD', 'Maryland'], ['MA', 'Massachusetts'], ['MI', 'Michigan'], ['MN', 'Minnesota'],
    ['MS', 'Mississippi'], ['MO', 'Missouri'], ['MT', 'Montana'], ['NE', 'Nebraska'], ['NV', 'Nevada'],
    ['NH', 'New Hampshire'], ['NJ', 'New Jersey'], ['NM', 'New Mexico'], ['NY', 'New York'],
    ['NC', 'North Carolina'], ['ND', 'North Dakota'], ['OH', 'Ohio'], ['OK', 'Oklahoma'], ['OR', 'Oregon'],
    ['PA', 'Pennsylvania'], ['RI', 'Rhode Island'], ['SC', 'South Carolina'], ['SD', 'South Dakota'],
    ['TN', 'Tennessee'], ['TX', 'Texas'], ['UT', 'Utah'], ['VT', 'Vermont'], ['VA', 'Virginia'],
    ['WA', 'Washington'], ['WV', 'West Virginia'], ['WI', 'Wisconsin'], ['WY', 'Wyoming'],
] as const;

type Site = { id: number; name: string; state_code: string | null; kind?: string };

type NewSite = { name: string; kind: 'tower' | 'cabinet' | 'pop' | 'other'; state_code: string; address: string };

function useSites() {
    return useQuery({
        queryKey: ['sites'],
        queryFn: async (): Promise<Site[]> => (await apiClient.get<{ data: Site[] }>('/sites')).data.data,
    });
}

export function NetworkLocationsSection() {
    const queryClient = useQueryClient();
    const { data: sites, isLoading } = useSites();
    const [showCreate, setShowCreate] = useState(false);
    const [form, setForm] = useState<NewSite>({ name: '', kind: 'other', state_code: '', address: '' });
    const create = useMutation({
        mutationFn: async (input: NewSite) => (await apiClient.post('/sites', {
            ...input,
            state_code: input.state_code || null,
            address: input.address || null,
        })).data,
        onSuccess: () => {
            void queryClient.invalidateQueries({ queryKey: ['sites'] });
            setForm({ name: '', kind: 'other', state_code: '', address: '' });
            setShowCreate(false);
            pushToast({ title: 'Site created', tone: 'up' });
        },
        onError: () => pushToast({ title: "Couldn't create site", tone: 'down' }),
    });
    const update = useMutation({
        mutationFn: async ({ id, state_code }: { id: number; state_code: string | null }) =>
            (await apiClient.patch(`/sites/${id}`, { state_code })).data,
        onSuccess: () => {
            void queryClient.invalidateQueries({ queryKey: ['sites'] });
            pushToast({ title: 'Network state saved', tone: 'up' });
        },
        onError: () => pushToast({ title: "Couldn't save network state", tone: 'down' }),
    });

    return (
        <section className="min-w-0 rounded-2xl bg-white/[0.02] p-5 ring-1 ring-white/[0.06]">
            <div className="mb-4 flex items-center justify-between gap-3">
                <div className="flex items-center gap-2">
                    <MapPin weight="light" className="h-4 w-4 text-white/40" />
                    <div>
                        <h2 className="text-sm font-bold text-white">Network status locations</h2>
                        <p className="text-xs text-white/40">Assign each monitored site to a state for the public status page.</p>
                    </div>
                </div>
                <button type="button" onClick={() => setShowCreate((open) => !open)} className="flex shrink-0 items-center gap-1 rounded-full bg-emerald-500/15 px-3 py-1.5 text-xs font-semibold text-emerald-200 ring-1 ring-emerald-400/25 transition hover:bg-emerald-500/25">
                    {showCreate ? <X weight="bold" className="h-3.5 w-3.5" /> : <Plus weight="bold" className="h-3.5 w-3.5" />}
                    {showCreate ? 'Cancel' : 'Add site'}
                </button>
            </div>
            {showCreate && (
                <form className="mb-4 space-y-2 rounded-xl bg-white/[0.03] p-3 ring-1 ring-white/10" onSubmit={(event) => { event.preventDefault(); if (form.name.trim()) create.mutate({ ...form, name: form.name.trim() }); }}>
                    <input required value={form.name} onChange={(event) => setForm((current) => ({ ...current, name: event.target.value }))} placeholder="Site name (e.g. Main office or Regional facility)" className="w-full rounded-lg bg-white/[0.04] px-3 py-2 text-sm text-white ring-1 ring-white/10 outline-none placeholder:text-white/30" />
                    <div className="grid grid-cols-2 gap-2">
                        <select value={form.kind} onChange={(event) => setForm((current) => ({ ...current, kind: event.target.value as NewSite['kind'] }))} className="rounded-lg bg-white/[0.04] px-3 py-2 text-xs text-white ring-1 ring-white/10 outline-none [color-scheme:dark]">
                            <option value="other">Other</option><option value="tower">Tower</option><option value="cabinet">Cabinet</option><option value="pop">Point of presence</option>
                        </select>
                        <select value={form.state_code} onChange={(event) => setForm((current) => ({ ...current, state_code: event.target.value }))} className="rounded-lg bg-white/[0.04] px-3 py-2 text-xs text-white ring-1 ring-white/10 outline-none [color-scheme:dark]">
                            <option value="">No state yet</option>
                            {STATES.map(([code, name]) => <option key={code} value={code}>{name}</option>)}
                        </select>
                    </div>
                    <input value={form.address} onChange={(event) => setForm((current) => ({ ...current, address: event.target.value }))} placeholder="Address (optional)" className="w-full rounded-lg bg-white/[0.04] px-3 py-2 text-xs text-white ring-1 ring-white/10 outline-none placeholder:text-white/30" />
                    <div className="flex justify-end"><button type="submit" disabled={create.isPending || !form.name.trim()} className="rounded-full bg-emerald-500 px-4 py-1.5 text-xs font-semibold text-emerald-950 disabled:opacity-40">{create.isPending ? 'Creating...' : 'Create site'}</button></div>
                </form>
            )}
            {isLoading ? <p className="text-sm text-white/40">Loading sites...</p> : (
                <div className="space-y-2">
                    {sites?.map((site) => (
                        <label key={site.id} className="flex items-center justify-between gap-3 rounded-xl bg-white/[0.02] px-3 py-2 ring-1 ring-white/[0.05]">
                            <span className="min-w-0 truncate text-sm text-white/75">{site.name}</span>
                            <select
                                aria-label={`State for ${site.name}`}
                                className="shrink-0 rounded-lg bg-white/[0.04] px-2 py-1.5 text-xs text-white ring-1 ring-white/10 outline-none [color-scheme:dark]"
                                value={site.state_code ?? ''}
                                disabled={update.isPending}
                                onChange={(event) => update.mutate({ id: site.id, state_code: event.target.value || null })}
                            >
                                <option value="">Unassigned</option>
                                {STATES.map(([code, name]) => <option key={code} value={code}>{name}</option>)}
                            </select>
                        </label>
                    ))}
                    {!sites?.length && <p className="text-sm text-white/40">No sites have been created yet.</p>}
                </div>
            )}
            <p className="mt-4 text-[11px] leading-relaxed text-white/30">Unassigned sites are excluded from the public state rollup until an administrator assigns them.</p>
        </section>
    );
}
