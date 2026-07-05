import {HttpClient} from '@angular/common/http';
import {inject, Injectable} from '@angular/core';
import {RecurrenceWritePayload, TaskRecurrence} from '@app/models/task-recurrence';
import {environment} from '@environments/environment';
import {firstValueFrom} from 'rxjs';

@Injectable({providedIn: 'root'})
export class TaskRecurrenceService {
    private readonly http = inject(HttpClient);

    public get(taskId: number): Promise<TaskRecurrence | null> {
        return firstValueFrom(this.http.get<TaskRecurrence | null>(`${environment.apiUrl}/tasks/${taskId}/recurrence`));
    }

    public set(taskId: number, payload: RecurrenceWritePayload): Promise<TaskRecurrence> {
        return firstValueFrom(this.http.put<TaskRecurrence>(`${environment.apiUrl}/tasks/${taskId}/recurrence`, payload));
    }

    public clear(taskId: number): Promise<void> {
        return firstValueFrom(this.http.delete<void>(`${environment.apiUrl}/tasks/${taskId}/recurrence`));
    }
}
