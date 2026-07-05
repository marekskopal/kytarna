import {HttpClient} from '@angular/common/http';
import {inject, Injectable} from '@angular/core';
import {ChecklistItem, ChecklistItemCreatePayload, ChecklistItemUpdatePayload} from '@app/models/checklist-item';
import {environment} from '@environments/environment';
import {firstValueFrom} from 'rxjs';

@Injectable({providedIn: 'root'})
export class TaskChecklistService {
    private readonly http = inject(HttpClient);

    public list(taskId: number): Promise<ChecklistItem[]> {
        return firstValueFrom(this.http.get<ChecklistItem[]>(`${environment.apiUrl}/tasks/${taskId}/checklist`));
    }

    public create(taskId: number, payload: ChecklistItemCreatePayload): Promise<ChecklistItem> {
        return firstValueFrom(this.http.post<ChecklistItem>(`${environment.apiUrl}/tasks/${taskId}/checklist`, payload));
    }

    public update(itemId: number, payload: ChecklistItemUpdatePayload): Promise<ChecklistItem> {
        return firstValueFrom(this.http.put<ChecklistItem>(`${environment.apiUrl}/checklist-items/${itemId}`, payload));
    }

    public move(itemId: number, position: number): Promise<ChecklistItem> {
        return firstValueFrom(this.http.put<ChecklistItem>(`${environment.apiUrl}/checklist-items/${itemId}/move`, {position}));
    }

    public delete(itemId: number): Promise<void> {
        return firstValueFrom(this.http.delete<void>(`${environment.apiUrl}/checklist-items/${itemId}`));
    }
}
