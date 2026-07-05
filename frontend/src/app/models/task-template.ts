import {TaskFieldValue} from '@app/models/field';

export interface TaskTemplatePayload {
    name: string;
    description: string | null;
    priorityId: number | null;
    fieldValues: TaskFieldValue[];
    tagIds: number[];
}

export interface TaskTemplate {
    id: number;
    workspaceId: number;
    name: string;
    payload: TaskTemplatePayload;
    createdAt: string;
    updatedAt: string;
}
