import {HttpClient} from '@angular/common/http';
import {computed, inject, Injectable, signal} from '@angular/core';
import {TaskTemplate} from '@app/models/task-template';
import {environment} from '@environments/environment';
import {firstValueFrom} from 'rxjs';

@Injectable({providedIn: 'root'})
export class TaskTemplateService {
    private readonly http = inject(HttpClient);
    private readonly cache = signal<{workspaceId: number; templates: TaskTemplate[]} | null>(null);

    public readonly templates = computed<TaskTemplate[]>(() => this.cache()?.templates ?? []);

    public async loadWorkspaceTemplates(workspaceId: number, force = false): Promise<TaskTemplate[]> {
        const current = this.cache();
        if (!force && current && current.workspaceId === workspaceId) {
            return current.templates;
        }
        const templates = await firstValueFrom(
            this.http.get<TaskTemplate[]>(`${environment.apiUrl}/workspaces/${workspaceId}/task-templates`),
        );
        this.cache.set({workspaceId, templates});
        return templates;
    }

    public async saveFromTask(taskId: number, name: string): Promise<TaskTemplate> {
        const template = await firstValueFrom(
            this.http.post<TaskTemplate>(`${environment.apiUrl}/tasks/${taskId}/save-as-template`, {name}),
        );
        this.upsertInCache(template);
        return template;
    }

    public async deleteTemplate(template: TaskTemplate): Promise<void> {
        await firstValueFrom(this.http.delete<void>(`${environment.apiUrl}/task-templates/${template.id}`));
        this.removeFromCache(template);
    }

    public clearCache(): void {
        this.cache.set(null);
    }

    private upsertInCache(template: TaskTemplate): void {
        const current = this.cache();
        if (!current || current.workspaceId !== template.workspaceId) {
            return;
        }
        const without = current.templates.filter((t) => t.id !== template.id);
        const next = [...without, template].sort((a, b) => a.name.localeCompare(b.name));
        this.cache.set({workspaceId: current.workspaceId, templates: next});
    }

    private removeFromCache(template: TaskTemplate): void {
        const current = this.cache();
        if (!current || current.workspaceId !== template.workspaceId) {
            return;
        }
        this.cache.set({
            workspaceId: current.workspaceId,
            templates: current.templates.filter((t) => t.id !== template.id),
        });
    }
}
