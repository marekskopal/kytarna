export type NotificationType = 'LectureMoved';

export interface NotificationData {
    lectureCode?: string;
    lectureName?: string;
    statusName?: string;
}

export interface Notification {
    id: number;
    type: NotificationType;
    lectureId: number | null;
    courseId: number | null;
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
