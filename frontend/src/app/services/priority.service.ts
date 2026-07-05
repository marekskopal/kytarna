import {HttpClient} from '@angular/common/http';
import {computed, inject, Injectable, signal} from '@angular/core';
import {Priority} from '@app/models/priority';
import {environment} from '@environments/environment';
import {firstValueFrom} from 'rxjs';

export interface PriorityWritePayload {
    name: string;
    color: string;
    isDefault: boolean;
}

@Injectable({providedIn: 'root'})
export class PriorityService {
    private readonly http = inject(HttpClient);
    private readonly cache = signal<{workspaceId: number; priorities: Priority[]} | null>(null);

    public readonly priorities = computed<Priority[]>(() => this.cache()?.priorities ?? []);

    public async loadWorkspacePriorities(workspaceId: number, force = false): Promise<Priority[]> {
        const current = this.cache();
        if (!force && current && current.workspaceId === workspaceId) {
            return current.priorities;
        }
        const priorities = await this.fetchWorkspacePriorities(workspaceId);
        this.cache.set({workspaceId, priorities});
        return priorities;
    }

    public fetchWorkspacePriorities(workspaceId: number): Promise<Priority[]> {
        return firstValueFrom(this.http.get<Priority[]>(`${environment.apiUrl}/workspaces/${workspaceId}/priorities`));
    }

    public async createPriority(workspaceId: number, payload: PriorityWritePayload): Promise<Priority> {
        const priority = await firstValueFrom(
            this.http.post<Priority>(`${environment.apiUrl}/workspaces/${workspaceId}/priorities`, payload),
        );
        this.upsertInCache(workspaceId, priority);
        return priority;
    }

    public async updatePriority(workspaceId: number, priorityId: number, payload: PriorityWritePayload): Promise<Priority> {
        const priority = await firstValueFrom(
            this.http.put<Priority>(`${environment.apiUrl}/workspaces/${workspaceId}/priorities/${priorityId}`, payload),
        );
        this.replaceInCache(workspaceId, priority);
        return priority;
    }

    public async movePriority(workspaceId: number, priorityId: number, position: number): Promise<Priority> {
        const priority = await firstValueFrom(
            this.http.put<Priority>(`${environment.apiUrl}/workspaces/${workspaceId}/priorities/${priorityId}/move`, {position}),
        );
        this.replaceInCache(priority.workspaceId, priority);
        return priority;
    }

    public async deletePriority(workspaceId: number, priorityId: number): Promise<void> {
        await firstValueFrom(
            this.http.delete<void>(`${environment.apiUrl}/workspaces/${workspaceId}/priorities/${priorityId}`),
        );
        this.removeFromCache(workspaceId, priorityId);
    }

    public clearCache(): void {
        this.cache.set(null);
    }

    private upsertInCache(workspaceId: number, priority: Priority): void {
        const current = this.cache();
        if (!current || current.workspaceId !== workspaceId) {
            return;
        }
        const without = current.priorities.filter((p) => p.id !== priority.id);
        const next = [...without, priority].sort((a, b) => a.position - b.position);
        this.cache.set({workspaceId, priorities: next});
    }

    private replaceInCache(workspaceId: number, _priority: Priority): void {
        const current = this.cache();
        if (!current || current.workspaceId !== workspaceId) {
            return;
        }
        // After update/move re-fetch on next access to ensure full re-sync (defaults, positions).
        this.cache.set(null);
    }

    private removeFromCache(workspaceId: number, priorityId: number): void {
        const current = this.cache();
        if (!current || current.workspaceId !== workspaceId) {
            return;
        }
        this.cache.set({workspaceId, priorities: current.priorities.filter((p) => p.id !== priorityId)});
    }
}
