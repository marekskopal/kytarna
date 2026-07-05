import {HttpClient} from '@angular/common/http';
import {computed, inject, Injectable, signal} from '@angular/core';
import {Tag} from '@app/models/tag';
import {environment} from '@environments/environment';
import {firstValueFrom} from 'rxjs';

export interface TagWritePayload {
    name: string;
    color: string;
}

@Injectable({providedIn: 'root'})
export class TagService {
    private readonly http = inject(HttpClient);
    private readonly cache = signal<{workspaceId: number; tags: Tag[]} | null>(null);

    public readonly tags = computed<Tag[]>(() => this.cache()?.tags ?? []);

    public async loadWorkspaceTags(workspaceId: number, force = false): Promise<Tag[]> {
        const current = this.cache();
        if (!force && current && current.workspaceId === workspaceId) {
            return current.tags;
        }
        const tags = await this.fetchWorkspaceTags(workspaceId);
        this.cache.set({workspaceId, tags});
        return tags;
    }

    public fetchWorkspaceTags(workspaceId: number): Promise<Tag[]> {
        return firstValueFrom(this.http.get<Tag[]>(`${environment.apiUrl}/workspaces/${workspaceId}/tags`));
    }

    public async createTag(workspaceId: number, payload: TagWritePayload): Promise<Tag> {
        const tag = await firstValueFrom(
            this.http.post<Tag>(`${environment.apiUrl}/workspaces/${workspaceId}/tags`, payload),
        );
        this.upsertInCache(workspaceId, tag);
        return tag;
    }

    public async updateTag(workspaceId: number, tagId: number, payload: TagWritePayload): Promise<Tag> {
        const tag = await firstValueFrom(
            this.http.put<Tag>(`${environment.apiUrl}/workspaces/${workspaceId}/tags/${tagId}`, payload),
        );
        this.upsertInCache(workspaceId, tag);
        return tag;
    }

    public async deleteTag(workspaceId: number, tagId: number): Promise<void> {
        await firstValueFrom(
            this.http.delete<void>(`${environment.apiUrl}/workspaces/${workspaceId}/tags/${tagId}`),
        );
        this.removeFromCache(workspaceId, tagId);
    }

    public clearCache(): void {
        this.cache.set(null);
    }

    private upsertInCache(workspaceId: number, tag: Tag): void {
        const current = this.cache();
        if (!current || current.workspaceId !== workspaceId) {
            return;
        }
        const without = current.tags.filter((t) => t.id !== tag.id);
        const next = [...without, tag].sort((a, b) => a.name.localeCompare(b.name));
        this.cache.set({workspaceId, tags: next});
    }

    private removeFromCache(workspaceId: number, tagId: number): void {
        const current = this.cache();
        if (!current || current.workspaceId !== workspaceId) {
            return;
        }
        this.cache.set({workspaceId, tags: current.tags.filter((t) => t.id !== tagId)});
    }
}
