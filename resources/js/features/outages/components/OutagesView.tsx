import { Fragment, useState } from 'react';
import { Warning, ChatText } from '@phosphor-icons/react';
import { useOutages } from '../api/getOutages';
import { StatusDot } from '../../../components/StatusDot';
import { relativeTime } from '../../../lib/relativeTime';
import { selectDevice, setView } from '../../../lib/shellStore';
import { useIncidentUpdates, useAddIncidentUpdate, useUpdateIncident } from '../api/statusIncidents';
import { useIsAdmin } from '../../auth/api/auth';

type Filter = 'all' | 'open' | 'closed';

function fmtDuration(s: number | null): string {
    if (s === null) return '-';
    if (s < 60) return `${s}s`;
    const m = Math.floor(s / 60);
    if (m < 60) return `${m}m ${s % 60}s`;
    const h = Math.floor(m / 60);
    return `${h}h ${m % 60}m`;
}

const pill = (active: boolean) =>
    `rounded-full px-3 py-1 text-xs font-medium transition-colors duration-200 ${
        active ? 'bg-white/10 text-white/90 ring-1 ring-white/15' : 'text-white/45 hover:text-white/75'
    }`;

function OutageUpdates({ incidentId, incidentStatus, severity }: { incidentId: number; incidentStatus: 'investigating' | 'monitoring' | 'resolved' | null; severity: 'outage' | 'degraded' | null }) {
    const isAdmin = useIsAdmin();
    const [message, setMessage] = useState('');
    const { data: updates, isLoading } = useIncidentUpdates(incidentId);
    const add = useAddIncidentUpdate(incidentId);
    const updateIncident = useUpdateIncident(incidentId);

    return (
        <div className="space-y-3 bg-white/[0.02] px-4 py-3">
            {isLoading ? <p className="text-xs text-white/35">Loading status feed...</p> : (
                <div className="space-y-2">
                    {(updates ?? []).map((update) => (
                        <div key={update.id} className="border-l-2 border-amber-400/30 pl-3">
                            <p className="text-sm text-white/70">{update.message}</p>
                            <p className="mt-1 text-[10px] text-white/35">{relativeTime(update.created_at)}{update.author ? ` · ${update.author}` : ''}</p>
                        </div>
                    ))}
                    {(updates ?? []).length === 0 && <p className="text-xs text-white/35">No public updates yet.</p>}
                </div>
            )}
            {isAdmin && <div className="flex items-center justify-between rounded-lg bg-white/[0.03] px-3 py-2 ring-1 ring-white/10">
                <span className="text-xs text-white/55">{severity === 'outage' ? 'Outage' : 'Degraded'} incident status</span>
                <select value={incidentStatus ?? 'investigating'} onChange={(event) => updateIncident.mutate({ status: event.target.value as 'investigating' | 'monitoring' | 'resolved' })} disabled={updateIncident.isPending} className="rounded-md bg-white/[0.05] px-2 py-1 text-xs text-white ring-1 ring-white/10 outline-none [color-scheme:dark]">
                    <option value="investigating">Investigating</option><option value="monitoring">Monitoring</option><option value="resolved">Resolved</option>
                </select>
            </div>}
            {isAdmin && <form className="flex gap-2" onSubmit={(event) => { event.preventDefault(); if (message.trim()) add.mutate(message.trim(), { onSuccess: () => setMessage('') }); }}>
                <input value={message} onChange={(event) => setMessage(event.target.value)} placeholder="Post an outage update for the status page..." className="min-w-0 flex-1 rounded-lg bg-white/[0.04] px-3 py-2 text-xs text-white ring-1 ring-white/10 outline-none placeholder:text-white/30" maxLength={4000} />
                <button type="submit" disabled={add.isPending || !message.trim()} className="rounded-lg bg-amber-500/15 px-3 py-2 text-xs font-medium text-amber-200 ring-1 ring-amber-400/25 disabled:opacity-40">{add.isPending ? 'Posting...' : 'Post update'}</button>
            </form>}
        </div>
    );
}
export function OutagesView() {
    const [filter, setFilter] = useState<Filter>('all');
    const [expanded, setExpanded] = useState<number | null>(null);
    const { data: outages, isLoading } = useOutages(filter === 'all' ? undefined : filter);

    function open(deviceId: number) {
        selectDevice(deviceId);
        setView('map');
    }

    return (
        <div className="h-full overflow-y-auto p-6 lg:p-8">
            <div className="animate-rise">
                <header className="mb-6 flex items-center justify-between gap-3">
                    <div className="flex items-center gap-3">
                        <span className="grid h-9 w-9 place-items-center rounded-xl bg-amber-500/15 text-amber-300 ring-1 ring-amber-400/20">
                            <Warning weight="light" className="h-5 w-5" />
                        </span>
                        <div>
                            <h1 className="text-base font-bold tracking-tight text-white">Outages</h1>
                            <p className="text-xs text-white/40">Device down-events with durations, newest first</p>
                        </div>
                    </div>
                    <div className="flex items-center gap-0.5 rounded-full bg-white/5 p-0.5 ring-1 ring-white/10">
                        {(['all', 'open', 'closed'] as Filter[]).map((f) => (
                            <button key={f} onClick={() => setFilter(f)} className={pill(filter === f)}>
                                {f === 'all' ? 'All' : f === 'open' ? 'Ongoing' : 'Resolved'}
                            </button>
                        ))}
                    </div>
                </header>

                {isLoading ? (
                    <p className="px-1 text-sm text-white/40">Loading...</p>
                ) : !outages || outages.length === 0 ? (
                    <div className="grid place-items-center rounded-2xl bg-white/[0.02] py-16 text-center ring-1 ring-white/[0.06]">
                        <p className="text-sm text-white/45">No outages recorded{filter !== 'all' ? ` (${filter})` : ''}.</p>
                    </div>
                ) : (
                    <div className="overflow-x-auto rounded-2xl ring-1 ring-white/[0.06]">
                        <table className="w-full min-w-[34rem] text-left text-sm">
                            <thead className="bg-white/[0.03] text-[11px] uppercase tracking-wide text-white/40">
                                <tr>
                                    <th className="px-4 py-2.5 font-medium">Device</th>
                                    <th className="px-4 py-2.5 font-medium">Started</th>
                                    <th className="px-4 py-2.5 font-medium">Duration</th>
                                    <th className="px-4 py-2.5 font-medium">State</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-white/[0.04]">
                                {outages.map((o) => (
                                    <Fragment key={o.id}>
                                    <tr
                                        key={o.id}
                                        onClick={() => open(o.device_id)}
                                        className="cursor-pointer transition-colors duration-200 hover:bg-white/[0.03]"
                                    >
                                        <td className="px-4 py-2.5 font-medium text-white/85">{o.device_name ?? `device ${o.device_id}`}</td>
                                        <td className="px-4 py-2.5 tabular-nums text-white/55">{relativeTime(o.started_at)}</td>
                                        <td className="px-4 py-2.5 tabular-nums text-white/70">
                                            {o.ongoing ? 'ongoing' : fmtDuration(o.duration_s)}
                                        </td>
                                        <td className="px-4 py-2.5">
                                            <span className="flex items-center gap-1.5 text-white/60">
                                                <StatusDot status={o.ongoing ? 'down' : 'up'} />
                                                {o.ongoing ? 'Down' : 'Recovered'}
                                                <button type="button" onClick={(event) => { event.stopPropagation(); setExpanded(expanded === o.id ? null : o.id); }} className="ml-2 inline-flex items-center gap-1 rounded-md px-1.5 py-0.5 text-[10px] text-amber-300/80 ring-1 ring-amber-400/20 hover:bg-amber-500/10" title="View or post status updates">
                                                    <ChatText weight="bold" className="h-3 w-3" /> Feed
                                                </button>
                                            </span>
                                        </td>
                                    </tr>
                                    {expanded === o.id && o.incident_id !== null && <tr><td colSpan={4}><OutageUpdates incidentId={o.incident_id} incidentStatus={o.incident_status} severity={o.incident_severity} /></td></tr>}
                                    </Fragment>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>
        </div>
    );
}
