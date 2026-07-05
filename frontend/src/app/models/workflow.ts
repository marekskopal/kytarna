import {Status} from '@app/models/status';

export interface Workflow {
    id: number;
    projectId: number;
    name: string;
}

export interface WorkflowWithStatuses {
    id: number;
    projectId: number;
    projectName: string;
    name: string;
    statuses: Status[];
}
