import {HttpClient, HttpParams} from '@angular/common/http';
import {inject, Injectable} from '@angular/core';
import {ArchivedFilter, OrderDirection, Task, TaskList, TaskOrderBy} from '@app/models/task';
import {TaskFile} from '@app/models/task-file';
import {environment} from '@environments/environment';
import {firstValueFrom} from 'rxjs';

export interface TaskWritePayload {
    statusId: number;
    name: string;
    description: string | null;
    tagIds?: number[];
}

export interface TaskListParams {
    limit: number;
    offset: number;
    orderBy: TaskOrderBy;
    orderDirection: OrderDirection;
    search?: string;
    statusIds?: number[];
    tagIds?: number[];
    onlyActive?: boolean;
    archived?: ArchivedFilter;
}

export type BulkOp = 'move' | 'tag' | 'untag' | 'delete';

export interface BulkSkipped {
    id: number;
    reason: string;
}

export interface BulkResult {
    succeeded: number[];
    skipped: BulkSkipped[];
}

@Injectable({providedIn: 'root'})
export class TaskService {
    private readonly http = inject(HttpClient);

    public getTasks(params: TaskListParams): Promise<TaskList> {
        let httpParams = new HttpParams()
            .set('limit', params.limit)
            .set('offset', params.offset)
            .set('orderBy', params.orderBy)
            .set('orderDirection', params.orderDirection);
        if (params.search) {
            httpParams = httpParams.set('search', params.search);
        }
        if (params.statusIds && params.statusIds.length > 0) {
            httpParams = httpParams.set('statusIds', params.statusIds.join('|'));
        }
        if (params.tagIds && params.tagIds.length > 0) {
            httpParams = httpParams.set('tagIds', params.tagIds.join('|'));
        }
        if (params.onlyActive) {
            httpParams = httpParams.set('onlyActive', '1');
        }
        if (params.archived && params.archived !== 'active') {
            httpParams = httpParams.set('archived', params.archived);
        }
        return firstValueFrom(this.http.get<TaskList>(`${environment.apiUrl}/tasks`, {params: httpParams}));
    }

    public getTask(taskId: number): Promise<Task> {
        return firstValueFrom(this.http.get<Task>(`${environment.apiUrl}/tasks/${taskId}`));
    }

    public createTask(projectId: number, payload: TaskWritePayload): Promise<Task> {
        return firstValueFrom(this.http.post<Task>(`${environment.apiUrl}/projects/${projectId}/tasks`, payload));
    }

    public updateTask(taskId: number, payload: TaskWritePayload): Promise<Task> {
        return firstValueFrom(this.http.put<Task>(`${environment.apiUrl}/tasks/${taskId}`, payload));
    }

    public moveTask(taskId: number, statusId: number, position: number): Promise<Task> {
        return firstValueFrom(this.http.put<Task>(`${environment.apiUrl}/tasks/${taskId}/move`, {statusId, position}));
    }

    public archiveTask(taskId: number): Promise<Task> {
        return firstValueFrom(this.http.post<Task>(`${environment.apiUrl}/tasks/${taskId}/archive`, {}));
    }

    public unarchiveTask(taskId: number): Promise<Task> {
        return firstValueFrom(this.http.post<Task>(`${environment.apiUrl}/tasks/${taskId}/unarchive`, {}));
    }

    public deleteTask(taskId: number): Promise<void> {
        return firstValueFrom(this.http.delete<void>(`${environment.apiUrl}/tasks/${taskId}`));
    }

    public bulkUpdate(ids: number[], op: BulkOp, payload?: Record<string, unknown>): Promise<BulkResult> {
        return firstValueFrom(
            this.http.post<BulkResult>(`${environment.apiUrl}/tasks/bulk`, {ids, op, payload: payload ?? {}}),
        );
    }

    public listTaskFiles(taskId: number): Promise<TaskFile[]> {
        return firstValueFrom(this.http.get<TaskFile[]>(`${environment.apiUrl}/tasks/${taskId}/files`));
    }

    public uploadTaskFile(taskId: number, file: File): Promise<TaskFile> {
        const data = new FormData();
        data.append('file', file, file.name);
        return firstValueFrom(this.http.post<TaskFile>(`${environment.apiUrl}/tasks/${taskId}/files`, data));
    }

    public deleteTaskFile(taskId: number, fileId: number): Promise<void> {
        return firstValueFrom(this.http.delete<void>(`${environment.apiUrl}/tasks/${taskId}/files/${fileId}`));
    }

    public downloadTaskFile(taskId: number, fileId: number): Promise<Blob> {
        return firstValueFrom(this.http.get(`${environment.apiUrl}/tasks/${taskId}/files/${fileId}/content`, {
            responseType: 'blob',
        }));
    }
}
