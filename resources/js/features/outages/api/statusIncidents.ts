import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { apiClient } from '../../../lib/apiClient';

export type IncidentUpdate = { id: number; message: string; created_at: string; author?: string | null };
export type StatusIncident = { id: number; state: string; severity: 'outage' | 'degraded'; status: 'investigating' | 'monitoring' | 'resolved'; summary: string | null; started_at: string; resolved_at: string | null; acknowledged: boolean; acknowledged_at: string | null; acknowledged_by: string | null };

export function useIncidentUpdates(incidentId: number, enabled = true) {
    return useQuery({
        queryKey: ['status-incident-updates', incidentId],
        queryFn: async (): Promise<IncidentUpdate[]> => (await apiClient.get<{ data: IncidentUpdate[] }>(`/status-incidents/${incidentId}/updates`)).data.data,
        enabled,
    });
}

export function useAddIncidentUpdate(incidentId: number) {
    const qc = useQueryClient();
    return useMutation({
        mutationFn: async (message: string): Promise<IncidentUpdate> => (await apiClient.post<{ data: IncidentUpdate }>(`/status-incidents/${incidentId}/updates`, { message })).data.data,
        onSuccess: () => qc.invalidateQueries({ queryKey: ['status-incident-updates', incidentId] }),
    });
}

export function useAcknowledgeIncident(incidentId: number) {
    const qc = useQueryClient();
    return useMutation({
        mutationFn: async (): Promise<StatusIncident> => (await apiClient.post<{ data: StatusIncident }>(`/status-incidents/${incidentId}/acknowledge`)).data.data,
        onSuccess: () => qc.invalidateQueries({ queryKey: ['outages'] }),
    });
}

export function useUpdateIncident(incidentId: number) {
    const qc = useQueryClient();
    return useMutation({
        mutationFn: async (input: { status?: StatusIncident['status']; summary?: string | null }): Promise<StatusIncident> =>
            (await apiClient.patch<{ data: StatusIncident }>(`/status-incidents/${incidentId}`, input)).data.data,
        onSuccess: () => qc.invalidateQueries({ queryKey: ['outages'] }),
    });
}
