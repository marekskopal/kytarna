import {HttpClient} from '@angular/common/http';
import {inject, Injectable} from '@angular/core';
import {TaskComment} from '@app/models/task-comment';
import {environment} from '@environments/environment';
import {firstValueFrom} from 'rxjs';

export interface CreateTaskCommentPayload {
    body: string;
    parentCommentId?: number | null;
}

export interface UpdateTaskCommentPayload {
    body: string;
}

@Injectable({providedIn: 'root'})
export class TaskCommentService {
    private readonly http = inject(HttpClient);

    public list(taskId: number): Promise<TaskComment[]> {
        return firstValueFrom(this.http.get<TaskComment[]>(`${environment.apiUrl}/tasks/${taskId}/comments`));
    }

    public create(taskId: number, payload: CreateTaskCommentPayload): Promise<TaskComment> {
        return firstValueFrom(this.http.post<TaskComment>(`${environment.apiUrl}/tasks/${taskId}/comments`, payload));
    }

    public update(commentId: number, payload: UpdateTaskCommentPayload): Promise<TaskComment> {
        return firstValueFrom(this.http.put<TaskComment>(`${environment.apiUrl}/task-comments/${commentId}`, payload));
    }

    public delete(commentId: number): Promise<void> {
        return firstValueFrom(this.http.delete<void>(`${environment.apiUrl}/task-comments/${commentId}`));
    }
}
