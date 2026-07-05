import {ArchivedFilter, LectureOrderBy,OrderDirection} from '@app/models/lecture';

export interface SavedViewFilters {
    q?: string;
    statusIds?: number[];
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
