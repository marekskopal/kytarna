export type WorkspaceRole = 'Owner' | 'Admin' | 'Member';

export interface Workspace {
    id: number;
    name: string;
    ownerId: number;
    createdAt: string;
}

export interface WorkspaceMember {
    userId: number;
    name: string;
    email: string;
    role: WorkspaceRole;
}

export interface Invitation {
    id: number;
    workspaceId: number;
    workspaceName: string;
    email: string;
    inviterName: string;
    role: WorkspaceRole;
    expiresAt: string;
    acceptedAt: string | null;
}
