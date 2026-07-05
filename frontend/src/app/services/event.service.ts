import {HttpClient, HttpParams} from '@angular/common/http';
import {inject, Injectable} from '@angular/core';
import {ActorType, AuditEvent, WorkspaceAgentStats, WorkspaceMcpClient} from '@app/models/event';
import {environment} from '@environments/environment';
import {firstValueFrom} from 'rxjs';

@Injectable({providedIn: 'root'})
export class EventService {
    private readonly http = inject(HttpClient);

    public getEvents(courseId: number, limit = 100, offset = 0): Promise<AuditEvent[]> {
        return firstValueFrom(
            this.http.get<AuditEvent[]>(`${environment.apiUrl}/courses/${courseId}/events`, {params: {limit, offset}}),
        );
    }

    public getWorkspaceEvents(
        workspaceId: number,
        actorType: ActorType | null = null,
        limit = 100,
        offset = 0,
    ): Promise<AuditEvent[]> {
        let params = new HttpParams().set('limit', limit).set('offset', offset);
        if (actorType !== null) {
            params = params.set('actorType', actorType);
        }
        return firstValueFrom(
            this.http.get<AuditEvent[]>(`${environment.apiUrl}/workspaces/${workspaceId}/events`, {params}),
        );
    }

    public getWorkspaceAgentStats(workspaceId: number): Promise<WorkspaceAgentStats> {
        return firstValueFrom(
            this.http.get<WorkspaceAgentStats>(`${environment.apiUrl}/workspaces/${workspaceId}/agent-stats`),
        );
    }

    public getWorkspaceMcpClients(workspaceId: number): Promise<WorkspaceMcpClient[]> {
        return firstValueFrom(
            this.http.get<WorkspaceMcpClient[]>(`${environment.apiUrl}/workspaces/${workspaceId}/mcp-clients`),
        );
    }

    public revokeWorkspaceMcpClient(workspaceId: number, clientId: string): Promise<{revokedTokens: number}> {
        return firstValueFrom(
            this.http.post<{revokedTokens: number}>(
                `${environment.apiUrl}/workspaces/${workspaceId}/mcp-clients/${clientId}/revoke`,
                {},
            ),
        );
    }
}
