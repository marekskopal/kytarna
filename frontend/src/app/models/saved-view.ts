import {ArchivedFilter, OrderDirection, TaskOrderBy} from '@app/models/task';

export interface SavedViewFilters {
    q?: string;
    statusIds?: number[];
    tagIds?: number[];
    onlyActive?: boolean;
    archived?: ArchivedFilter;
    orderBy?: TaskOrderBy;
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
