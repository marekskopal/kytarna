import {HttpClient} from '@angular/common/http';
import {inject, Injectable} from '@angular/core';
import {TaskRelation, TaskRelationList, TaskRelationType} from '@app/models/task-relation';
import {environment} from '@environments/environment';
import {firstValueFrom} from 'rxjs';

export interface CreateTaskRelationPayload {
    targetTaskId: number;
    type: TaskRelationType;
}

@Injectable({providedIn: 'root'})
export class TaskRelationService {
    private readonly http = inject(HttpClient);

    public list(taskId: number): Promise<TaskRelationList> {
        return firstValueFrom(this.http.get<TaskRelationList>(`${environment.apiUrl}/tasks/${taskId}/relations`));
    }

    public create(taskId: number, payload: CreateTaskRelationPayload): Promise<TaskRelation> {
        return firstValueFrom(this.http.post<TaskRelation>(`${environment.apiUrl}/tasks/${taskId}/relations`, payload));
    }

    public delete(relationId: number): Promise<void> {
        return firstValueFrom(this.http.delete<void>(`${environment.apiUrl}/task-relations/${relationId}`));
    }
}
