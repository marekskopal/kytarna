export type WorkspaceRole = 'Teacher' | 'Student';

export interface Workspace {
    id: number;
    name: string;
    ownerId: number;
    isPublic: boolean;
    description: string | null;
    /** Present only for the workspace's own Teacher (owner). */
    joinCode: string | null;
    createdAt: string;
}

/** A workspace as shown in the public teacher directory. */
export interface PublicWorkspace {
    id: number;
    name: string;
    description: string | null;
    teacherName: string;
    memberCount: number;
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
