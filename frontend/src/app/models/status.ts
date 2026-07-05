export type StatusType = 'Start' | 'Normal' | 'Finish';

export interface Status {
    id: number;
    workflowId: number;
    name: string;
    color: string;
    position: number;
    type: StatusType;
}
