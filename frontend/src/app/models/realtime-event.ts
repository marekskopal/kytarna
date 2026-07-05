export type RealtimeEventType =
    | 'TaskCreated'
    | 'TaskUpdated'
    | 'TaskMoved'
    | 'TaskArchived'
    | 'TaskUnarchived'
    | 'TaskDeleted'
    | 'TaskTagsUpdated'
    | 'TaskCommentAdded'
    | 'TaskCommentEdited'
    | 'TaskCommentDeleted'
    | 'TaskFileAdded'
    | 'TaskFileDeleted'
    | 'TaskRelationCreated'
    | 'TaskRelationDeleted'
    | 'NotificationCreated'
    | 'RealtimeReconnected';

export interface RealtimeEvent {
    type: RealtimeEventType;
    workspaceId: number;
    projectId: number | null;
    taskId: number | null;
    commentId: number | null;
    fileId: number | null;
    relationId: number | null;
    userId: number | null;
    originClientId: string | null;
}

export const TASK_EVENT_TYPES: ReadonlySet<RealtimeEventType> = new Set<RealtimeEventType>([
    'TaskCreated',
    'TaskUpdated',
    'TaskMoved',
    'TaskArchived',
    'TaskUnarchived',
    'TaskDeleted',
    'TaskTagsUpdated',
    'TaskFileAdded',
    'TaskFileDeleted',
    'TaskRelationCreated',
    'TaskRelationDeleted',
]);

export const COMMENT_EVENT_TYPES: ReadonlySet<RealtimeEventType> = new Set<RealtimeEventType>([
    'TaskCommentAdded',
    'TaskCommentEdited',
    'TaskCommentDeleted',
]);

export const FILE_EVENT_TYPES: ReadonlySet<RealtimeEventType> = new Set<RealtimeEventType>([
    'TaskFileAdded',
    'TaskFileDeleted',
]);

export const RELATION_EVENT_TYPES: ReadonlySet<RealtimeEventType> = new Set<RealtimeEventType>([
    'TaskRelationCreated',
    'TaskRelationDeleted',
]);
