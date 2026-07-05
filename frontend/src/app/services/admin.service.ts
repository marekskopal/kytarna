import {HttpClient} from '@angular/common/http';
import {inject, Injectable} from '@angular/core';
import {SystemRole} from '@app/models/user';
import {Workspace, WorkspaceMember, WorkspaceRole} from '@app/models/workspace';
import {environment} from '@environments/environment';
import {firstValueFrom} from 'rxjs';

export interface AdminUser {
    id: number;
    email: string;
    name: string;
    locale: string;
    systemRole: SystemRole;
    workspaceCount: number;
    ownedWorkspaceCount: number;
}

export interface AdminWorkspace {
    id: number;
    name: string;
    ownerId: number;
    ownerEmail: string;
    ownerName: string;
    memberCount: number;
    createdAt: string;
}

export interface AdminWorkspaceDetail {
    workspace: AdminWorkspace;
    members: WorkspaceMember[];
}

@Injectable({providedIn: 'root'})
export class AdminService {
    private readonly http = inject(HttpClient);
    private readonly baseUrl = `${environment.apiUrl}/admin`;

    public listUsers(): Promise<AdminUser[]> {
        return firstValueFrom(this.http.get<AdminUser[]>(`${this.baseUrl}/users`));
    }

    public updateUser(id: number, changes: {name?: string; email?: string; systemRole?: SystemRole}): Promise<AdminUser> {
        return firstValueFrom(this.http.patch<AdminUser>(`${this.baseUrl}/users/${id}`, changes));
    }

    public deleteUser(id: number): Promise<void> {
        return firstValueFrom(this.http.delete<void>(`${this.baseUrl}/users/${id}`));
    }

    public listWorkspaces(): Promise<AdminWorkspace[]> {
        return firstValueFrom(this.http.get<AdminWorkspace[]>(`${this.baseUrl}/workspaces`));
    }

    public getWorkspace(id: number): Promise<AdminWorkspaceDetail> {
        return firstValueFrom(this.http.get<AdminWorkspaceDetail>(`${this.baseUrl}/workspaces/${id}`));
    }

    public renameWorkspace(id: number, name: string): Promise<Workspace> {
        return firstValueFrom(this.http.patch<Workspace>(`${this.baseUrl}/workspaces/${id}`, {name}));
    }

    public deleteWorkspace(id: number): Promise<void> {
        return firstValueFrom(this.http.delete<void>(`${this.baseUrl}/workspaces/${id}`));
    }

    public addMember(workspaceId: number, userId: number, role: WorkspaceRole = 'Member'): Promise<WorkspaceMember> {
        return firstValueFrom(
            this.http.post<WorkspaceMember>(`${this.baseUrl}/workspaces/${workspaceId}/members`, {userId, role}),
        );
    }

    public removeMember(workspaceId: number, userId: number): Promise<void> {
        return firstValueFrom(
            this.http.delete<void>(`${this.baseUrl}/workspaces/${workspaceId}/members/${userId}`),
        );
    }

    public transferOwnership(workspaceId: number, userId: number): Promise<Workspace> {
        return firstValueFrom(
            this.http.patch<Workspace>(`${this.baseUrl}/workspaces/${workspaceId}/transfer-ownership`, {userId}),
        );
    }
}
