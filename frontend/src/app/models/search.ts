export type SearchMatchedIn = 'name' | 'description' | 'comments' | 'fieldValues' | 'tags';

export interface SearchHit {
    id: number;
    code: string;
    projectId: number;
    statusId: number;
    name: string;
    snippet: string | null;
    matchedIn: SearchMatchedIn;
}

export interface SearchResult {
    hits: SearchHit[];
    estimatedTotalHits: number;
    processingTimeMs: number;
}

export interface SearchParams {
    q: string;
    limit?: number;
    offset?: number;
    projectId?: number;
    statusIds?: number[];
    onlyActive?: boolean;
}
