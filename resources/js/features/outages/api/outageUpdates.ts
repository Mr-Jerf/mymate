import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { apiClient } from '../../../lib/apiClient';

export type OutageUpdate = { id: number; message: string; created_at: string; author?: string | null };

export function useOutageUpdates(outageId: number, enabled = true) {
    return useQuery({
        queryKey: ['outage-updates', outageId],
        queryFn: async (): Promise<OutageUpdate[]> => (await apiClient.get<{ data: OutageUpdate[] }>(`/outages/${outageId}/updates`)).data.data,
        enabled,
    });
}

export function useAddOutageUpdate(outageId: number) {
    const qc = useQueryClient();
    return useMutation({
        mutationFn: async (message: string): Promise<OutageUpdate> =>
            (await apiClient.post<{ data: OutageUpdate }>(`/outages/${outageId}/updates`, { message })).data.data,
        onSuccess: () => qc.invalidateQueries({ queryKey: ['outage-updates', outageId] }),
    });
}
