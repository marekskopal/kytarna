import {Status} from '@app/models/status';

export interface Workflow {
    id: number;
    courseId: number;
    name: string;
}

export interface WorkflowWithStatuses {
    id: number;
    courseId: number;
    courseName: string;
    name: string;
    statuses: Status[];
}
