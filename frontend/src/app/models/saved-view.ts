import {ArchivedFilter, LectureOrderBy,OrderDirection} from '@app/models/lecture';
import {LearningStatus} from '@app/models/status';

export interface SavedViewFilters {
    q?: string;
    statuses?: LearningStatus[];
    tagIds?: number[];
    onlyActive?: boolean;
    archived?: ArchivedFilter;
    orderBy?: LectureOrderBy;
    orderDirection?: OrderDirection;
    pageSize?: number;
}

export interface SavedView {
    id: number;
    workspaceId: number;
    userId: number;
    name: string;
    filterConfig: string;
    createdAt: string;
    updatedAt: string;
}

export interface SavedViewWritePayload {
    name: string;
    filterConfig: string;
}
