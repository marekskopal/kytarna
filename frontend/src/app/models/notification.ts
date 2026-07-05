export type NotificationType = 'TaskAssigned' | 'TaskComment' | 'TaskMention' | 'TaskMoved' | 'DueSoon' | 'DueToday';

export interface NotificationData {
    taskCode?: string;
    taskName?: string;
    statusName?: string;
    commentSnippet?: string;
    dueDate?: string;
}

export interface Notification {
    id: number;
    type: NotificationType;
    taskId: number | null;
    projectId: number | null;
    actorId: number | null;
    actorName: string | null;
    data: NotificationData;
    read: boolean;
    createdAt: string;
}

export interface NotificationList {
    notifications: Notification[];
    unreadCount: number;
}
