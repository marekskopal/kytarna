import {StatusType} from '@app/models/status';

export interface Subtask {
    taskId: number;
    relationId: number;
    code: string;
    name: string;
    projectId: number;
    statusId: number;
    statusName: string;
    statusColor: string;
    statusType: StatusType;
    priorityId: number;
    priorityName: string;
    priorityPosition: number;
    dueDate: string | null;
    assigneeId: number | null;
    startStatusId: number | null;
    finishStatusId: number | null;
}
