import {TaskFieldValue} from '@app/models/field';
import {Priority} from '@app/models/priority';
import {Status} from '@app/models/status';

export interface Task {
    id: number;
    code: string;
    projectId: number;
    statusId: number;
    assigneeId: number | null;
    name: string;
    description: string | null;
    priority: Priority;
    dueDate: string | null;
    startDate: string | null;
    position: number;
    sequenceNumber: number;
    createdByAgent: boolean;
    archivedAt: string | null;
    createdAt: string;
    updatedAt: string;
    fieldValues: TaskFieldValue[];
    tagIds: number[];
    subtasksTotal?: number;
    subtasksDone?: number;
    checklistTotal?: number;
    checklistDone?: number;
}

export type TaskOrderBy = 'created_at' | 'name' | 'status_id';
export type OrderDirection = 'ASC' | 'DESC';
export type SubtaskFilter = 'all' | 'hideSubtasks' | 'onlyParents';
export type ArchivedFilter = 'active' | 'archived' | 'all';

export interface TaskListItem {
    id: number;
    code: string;
    projectId: number;
    projectName: string;
    statusId: number;
    status: Status;
    assigneeId: number | null;
    name: string;
    description: string | null;
    priority: Priority;
    dueDate: string | null;
    startDate: string | null;
    position: number;
    sequenceNumber: number;
    createdByAgent: boolean;
    archivedAt: string | null;
    createdAt: string;
    updatedAt: string;
    tagIds: number[];
    subtasksTotal?: number;
    subtasksDone?: number;
    checklistTotal?: number;
    checklistDone?: number;
}

export interface TaskList {
    tasks: TaskListItem[];
    count: number;
}
