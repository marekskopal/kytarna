import {Status} from '@app/models/status';

export interface Task {
    id: number;
    code: string;
    projectId: number;
    statusId: number;
    name: string;
    description: string | null;
    position: number;
    sequenceNumber: number;
    createdByAgent: boolean;
    archivedAt: string | null;
    createdAt: string;
    updatedAt: string;
    tagIds: number[];
}

export type TaskOrderBy = 'created_at' | 'name' | 'status_id';
export type OrderDirection = 'ASC' | 'DESC';
export type ArchivedFilter = 'active' | 'archived' | 'all';

export interface TaskListItem {
    id: number;
    code: string;
    projectId: number;
    projectName: string;
    statusId: number;
    status: Status;
    name: string;
    description: string | null;
    position: number;
    sequenceNumber: number;
    createdByAgent: boolean;
    archivedAt: string | null;
    createdAt: string;
    updatedAt: string;
    tagIds: number[];
}

export interface TaskList {
    tasks: TaskListItem[];
    count: number;
}
