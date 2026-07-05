import {HttpClient} from '@angular/common/http';
import {inject, Injectable} from '@angular/core';
import {Workflow, WorkflowWithStatuses} from '@app/models/workflow';
import {environment} from '@environments/environment';
import {firstValueFrom} from 'rxjs';

@Injectable({providedIn: 'root'})
export class WorkflowService {
    private readonly http = inject(HttpClient);

    public getWorkflow(projectId: number): Promise<Workflow> {
        return firstValueFrom(this.http.get<Workflow>(`${environment.apiUrl}/projects/${projectId}/workflow`));
    }

    public getWorkflows(): Promise<WorkflowWithStatuses[]> {
        return firstValueFrom(this.http.get<WorkflowWithStatuses[]>(`${environment.apiUrl}/workflows`));
    }
}
