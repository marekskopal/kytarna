export type TabSourceType = 'authored' | 'imported_gp';

export interface Tab {
    id: number;
    lectureId: number;
    name: string;
    alphatexContent: string;
    sourceType: TabSourceType;
    originalFileId: number | null;
    tempo: number | null;
    tuning: string | null;
    trackCount: number | null;
    createdAt: string;
    updatedAt: string;
}

/** One alphaTex validation error as returned by the backend on HTTP 422. */
export interface TabValidationError {
    message: string;
    line: number | null;
    col: number | null;
    offset: number | null;
}

/** Body of a 422 response from tab create/update. */
export interface TabValidationErrorResponse {
    message: string;
    errors: TabValidationError[];
}
