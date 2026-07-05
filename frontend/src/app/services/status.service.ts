import {HttpClient} from '@angular/common/http';
import {inject, Injectable} from '@angular/core';
import {Status, StatusType} from '@app/models/status';
import {environment} from '@environments/environment';
import {firstValueFrom} from 'rxjs';

export interface StatusWritePayload {
    name: string;
    color: string;
    type: StatusType;
    position?: number;
}

@Injectable({providedIn: 'root'})
export class StatusService {
    private readonly http = inject(HttpClient);

    public createStatus(workflowId: number, payload: StatusWritePayload): Promise<Status> {
        return firstValueFrom(this.http.post<Status>(`${environment.apiUrl}/workflows/${workflowId}/statuses`, payload));
    }

    public updateStatus(statusId: number, payload: StatusWritePayload): Promise<Status> {
        return firstValueFrom(this.http.put<Status>(`${environment.apiUrl}/statuses/${statusId}`, payload));
    }

    public moveStatus(statusId: number, position: number): Promise<Status> {
        return firstValueFrom(this.http.put<Status>(`${environment.apiUrl}/statuses/${statusId}/move`, {position}));
    }

    public deleteStatus(statusId: number): Promise<void> {
        return firstValueFrom(this.http.delete<void>(`${environment.apiUrl}/statuses/${statusId}`));
    }
}
