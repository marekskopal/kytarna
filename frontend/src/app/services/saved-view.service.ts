import {HttpClient} from '@angular/common/http';
import {computed, inject, Injectable, signal} from '@angular/core';
import {SavedView, SavedViewWritePayload} from '@app/models/saved-view';
import {environment} from '@environments/environment';
import {firstValueFrom} from 'rxjs';

@Injectable({providedIn: 'root'})
export class SavedViewService {
    private readonly http = inject(HttpClient);
    private readonly cache = signal<{workspaceId: number; views: SavedView[]} | null>(null);

    public readonly views = computed<SavedView[]>(() => this.cache()?.views ?? []);

    public async loadForWorkspace(workspaceId: number, force = false): Promise<SavedView[]> {
        const current = this.cache();
        if (!force && current && current.workspaceId === workspaceId) {
            return current.views;
        }
        const views = await firstValueFrom(
            this.http.get<SavedView[]>(`${environment.apiUrl}/workspaces/${workspaceId}/saved-views`),
        );
        this.cache.set({workspaceId, views});
        return views;
    }

    public async create(workspaceId: number, payload: SavedViewWritePayload): Promise<SavedView> {
        const view = await firstValueFrom(
            this.http.post<SavedView>(`${environment.apiUrl}/workspaces/${workspaceId}/saved-views`, payload),
        );
        this.upsertInCache(workspaceId, view);
        return view;
    }

    public async update(viewId: number, payload: SavedViewWritePayload): Promise<SavedView> {
        const view = await firstValueFrom(
            this.http.put<SavedView>(`${environment.apiUrl}/saved-views/${viewId}`, payload),
        );
        this.upsertInCache(view.workspaceId, view);
        return view;
    }

    public async delete(viewId: number): Promise<void> {
        await firstValueFrom(this.http.delete<void>(`${environment.apiUrl}/saved-views/${viewId}`));
        const current = this.cache();
        if (current) {
            this.cache.set({workspaceId: current.workspaceId, views: current.views.filter((v) => v.id !== viewId)});
        }
    }

    public clearCache(): void {
        this.cache.set(null);
    }

    private upsertInCache(workspaceId: number, view: SavedView): void {
        const current = this.cache();
        if (!current || current.workspaceId !== workspaceId) {
            return;
        }
        const without = current.views.filter((v) => v.id !== view.id);
        const next = [...without, view].sort((a, b) => a.name.localeCompare(b.name));
        this.cache.set({workspaceId, views: next});
    }
}
