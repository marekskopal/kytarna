export type TaskRelationType = 'Related' | 'Duplicates' | 'Parent' | 'DependsOn';
export type TaskRelationDirection = 'outgoing' | 'incoming';

export interface TaskRelation {
    id: number;
    type: TaskRelationType;
    direction: TaskRelationDirection;
    labelKey: string;
    otherTaskId: number;
    otherTaskCode: string;
    otherTaskName: string;
    otherTaskProjectId: number;
    otherTaskProjectName: string;
    otherTaskStatusId: number;
    otherTaskStatusName: string;
    otherTaskStatusColor: string;
    createdAt: string;
    createdByUserId: number | null;
    createdByUserName: string | null;
}

export interface TaskRelationList {
    outgoing: TaskRelation[];
    incoming: TaskRelation[];
}
