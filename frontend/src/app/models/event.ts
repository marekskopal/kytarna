export type EventType =
    | 'CourseCreated' | 'CourseUpdated' | 'CourseDeleted'
    | 'LectureCreated' | 'LectureUpdated' | 'LectureDeleted' | 'LectureMoved' | 'LectureArchived' | 'LectureUnarchived'
    | 'SongCreated' | 'SongUpdated' | 'SongDeleted' | 'SongMoved' | 'SongArchived' | 'SongUnarchived'
    | 'SongAddedToCourse' | 'SongRemovedFromCourse'
    | 'MemberRoleChanged' | 'OwnershipTransferred'
    | 'AdminDeletedWorkspace' | 'AdminDeletedUser' | 'AdminChangedSystemRole'
    | 'UserSelfDeleted';

export type ActorType = 'Human' | 'Agent';

export interface AuditEvent {
    id: number;
    authorName: string | null;
    lectureId: number | null;
    lectureCode: string | null;
    type: EventType;
    metadata: Record<string, unknown>;
    actorType: ActorType;
    mcpClientId: string | null;
    mcpClientName: string | null;
    createdAt: string;
}

export interface WorkspaceAgentStats {
    eventsLast24h: number;
    lecturesCreatedLast24h: number;
    lecturesClosedLast24h: number;
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
