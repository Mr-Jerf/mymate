import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useEffect, useState } from 'react';
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

type StatusPageSettings = { brand_name: string; subtitle: string; poll_ms: number; show_site_names: boolean; show_device_counts: boolean; allow_subscriptions: boolean; public_enabled: boolean; logo_url: string; favicon_url: string; color_operational: string; color_degraded: string; color_outage: string; color_unknown: string; color_maintenance_scheduled: string; color_maintenance_active: string; color_maintenance_completed: string };

function useStatusPageSettings() {
    return useQuery({ queryKey: ['status-page-settings'], queryFn: async (): Promise<StatusPageSettings> => (await apiClient.get<{ data: StatusPageSettings }>('/settings/status-page')).data.data });
}

function useSites() {
    return useQuery({
        queryKey: ['sites'],
        queryFn: async (): Promise<Site[]> => (await apiClient.get<{ data: Site[] }>('/sites')).data.data,
    });
}

export function NetworkLocationsSection() {
    const queryClient = useQueryClient();
    const { data: sites, isLoading } = useSites();
    const { data: statusSettings } = useStatusPageSettings();
    const [statusDraft, setStatusDraft] = useState<StatusPageSettings | null>(null);
    useEffect(() => { if (statusSettings) setStatusDraft(statusSettings); }, [statusSettings]);
    const saveStatus = useMutation({
        mutationFn: async (input: StatusPageSettings) => (await apiClient.put('/settings/status-page', input)).data,
        onSuccess: () => { void queryClient.invalidateQueries({ queryKey: ['status-page-settings'] }); pushToast({ title: 'Status page settings saved', tone: 'up' }); },
        onError: () => pushToast({ title: "Couldn't save status page settings", tone: 'down' }),
    });
    const uploadBranding = useMutation({
        mutationFn: async ({ kind, file }: { kind: 'logo' | 'favicon'; file: File }) => { const data = new FormData(); data.append('kind', kind); data.append('asset', file); return (await apiClient.post('/settings/status-page/branding', data, { headers: { 'Content-Type': 'multipart/form-data' } })).data; },
        onSuccess: () => { void queryClient.invalidateQueries({ queryKey: ['status-page-settings'] }); pushToast({ title: 'Branding uploaded', tone: 'up' }); },
        onError: () => pushToast({ title: "Couldn't upload branding", tone: 'down' }),
    });
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
        <section className="space-y-6">
            {statusDraft && <section className="min-w-0 rounded-2xl bg-white/[0.02] p-5 ring-1 ring-white/[0.06]">
                <div className="mb-4"><h2 className="text-sm font-bold text-white">Public status page</h2><p className="text-xs text-white/40">Configure safe presentation settings. The private API token remains on the deployment host.</p></div>
                <div className="grid gap-2.5 md:grid-cols-2">
                    <input className="rounded-lg bg-white/[0.04] px-3 py-2 text-sm text-white ring-1 ring-white/10 outline-none" value={statusDraft.brand_name} onChange={(e) => setStatusDraft({ ...statusDraft, brand_name: e.target.value })} placeholder="Business name" />
                    <input className="rounded-lg bg-white/[0.04] px-3 py-2 text-sm text-white ring-1 ring-white/10 outline-none" value={statusDraft.subtitle} onChange={(e) => setStatusDraft({ ...statusDraft, subtitle: e.target.value })} placeholder="Subtitle" />
                    <label className="flex items-center gap-2 text-xs text-white/70"><span>Refresh interval (ms)</span><input className="w-32 rounded-lg bg-white/[0.04] px-3 py-2 text-sm text-white ring-1 ring-white/10" type="number" min={10000} max={300000} value={statusDraft.poll_ms} onChange={(e) => setStatusDraft({ ...statusDraft, poll_ms: Number(e.target.value) })} /></label>
                    {([['public_enabled', 'Public page enabled'], ['show_site_names', 'Show site names'], ['show_device_counts', 'Show aggregate device counts'], ['allow_subscriptions', 'Allow customer subscriptions']] as const).map(([key, label]) => <label key={key} className="flex items-center gap-2 text-xs text-white/70"><input type="checkbox" checked={statusDraft[key]} onChange={(e) => setStatusDraft({ ...statusDraft, [key]: e.target.checked })} />{label}</label>)}
                    <div className="col-span-full mt-2 border-t border-white/10 pt-3"><p className="mb-2 text-xs font-semibold text-white/70">Branding</p><div className="grid gap-2 md:grid-cols-2"><label className="text-xs text-white/70">Logo<input type="file" accept="image/png,image/jpeg,image/webp" onChange={(e) => { const file = e.target.files?.[0]; if (file) uploadBranding.mutate({ kind: 'logo', file }); }} className="mt-1 block w-full text-xs text-white/50 file:mr-2 file:rounded-full file:border-0 file:bg-white/10 file:px-3 file:py-1 file:text-white" /><span className="mt-1 block text-[10px] text-white/30">PNG, JPEG, or WebP up to 256 KB. Default: Z4 logo.</span></label><label className="text-xs text-white/70">Favicon<input type="file" accept="image/png,image/webp,image/x-icon,.ico" onChange={(e) => { const file = e.target.files?.[0]; if (file) uploadBranding.mutate({ kind: 'favicon', file }); }} className="mt-1 block w-full text-xs text-white/50 file:mr-2 file:rounded-full file:border-0 file:bg-white/10 file:px-3 file:py-1 file:text-white" /><span className="mt-1 block text-[10px] text-white/30">PNG, WebP, or ICO up to 64 KB. Default: Z4 favicon.</span></label></div><p className="mt-2 text-[10px] text-white/30">Current logo: {statusDraft.logo_url.startsWith('data:') ? 'Custom upload' : 'Default Z4 logo'} · Current favicon: {statusDraft.favicon_url.startsWith('data:') ? 'Custom upload' : 'Default Z4 favicon'}</p></div>
                    <div className="col-span-full mt-2 border-t border-white/10 pt-3"><p className="mb-2 text-xs font-semibold text-white/70">Public colors</p><div className="grid gap-2 md:grid-cols-3">{([['color_operational', 'Operational'], ['color_degraded', 'Degraded'], ['color_outage', 'Outage'], ['color_unknown', 'Unknown'], ['color_maintenance_scheduled', 'Maintenance scheduled'], ['color_maintenance_active', 'Maintenance active'], ['color_maintenance_completed', 'Maintenance completed']] as const).map(([key, label]) => <label key={key} className="flex items-center gap-2 text-xs text-white/70"><input aria-label={label} type="color" value={statusDraft[key]} onChange={(e) => setStatusDraft({ ...statusDraft, [key]: e.target.value })} className="h-8 w-10 rounded bg-transparent" /><span>{label}</span><code className="text-[10px] text-white/40">{statusDraft[key]}</code></label>)}</div></div>
                </div>
                <div className="mt-4 flex justify-end"><button type="button" disabled={saveStatus.isPending} onClick={() => saveStatus.mutate(statusDraft)} className="rounded-full bg-emerald-500 px-4 py-1.5 text-xs font-semibold text-emerald-950 disabled:opacity-40">{saveStatus.isPending ? 'Saving...' : 'Save status page settings'}</button></div>
            </section>}
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
        </section>
        );
}
