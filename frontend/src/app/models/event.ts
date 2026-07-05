export type EventType =
    | 'ProjectCreated' | 'ProjectUpdated' | 'ProjectDeleted'
    | 'WorkflowUpdated'
    | 'StatusCreated' | 'StatusUpdated' | 'StatusDeleted' | 'StatusMoved'
    | 'TaskCreated' | 'TaskUpdated' | 'TaskDeleted' | 'TaskMoved' | 'TaskArchived' | 'TaskUnarchived'
    | 'MemberRoleChanged' | 'OwnershipTransferred'
    | 'AdminDeletedWorkspace' | 'AdminDeletedUser' | 'AdminChangedSystemRole'
    | 'FieldCreated' | 'FieldUpdated' | 'FieldDeleted' | 'ProjectFieldsUpdated'
    | 'UserSelfDeleted';

export type ActorType = 'Human' | 'Agent';

export interface AuditEvent {
    id: number;
    authorName: string | null;
    taskId: number | null;
    taskCode: string | null;
    type: EventType;
    metadata: Record<string, unknown>;
    actorType: ActorType;
    mcpClientId: string | null;
    mcpClientName: string | null;
    createdAt: string;
}

export interface WorkspaceAgentStats {
    eventsLast24h: number;
    tasksCreatedLast24h: number;
    tasksClosedLast24h: number;
    activeAgents: number;
    activeAgentNames: string[];
}

export interface WorkspaceMcpClient {
    clientId: string;
    clientName: string;
    firstSeenAt: string;
    lastUsedAt: string;
    activeTokens: number;
    totalAuthorizations: number;
}
