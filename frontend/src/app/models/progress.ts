export interface ProgressEntry {
    id: number;
    lectureId: number;
    /** YYYY-MM-DD */
    practicedAt: string;
    note: string | null;
    tempoBpm: number | null;
    durationMinutes: number | null;
    createdAt: string;
    updatedAt: string;
}

export interface BpmTrendPoint {
    /** YYYY-MM-DD */
    practicedAt: string;
    tempoBpm: number;
}

export interface PracticeSummary {
    totalEntries: number;
    totalMinutes: number;
    /** ISO week keys such as "2026-W23" mapped to entry counts. */
    entriesPerWeek: Record<string, number>;
    bpmTrend: BpmTrendPoint[];
}

export interface ProgressEntryWritePayload {
    /** YYYY-MM-DD; omitted defaults to today on the backend. */
    practicedAt?: string | null;
    note?: string | null;
    tempoBpm?: number | null;
    durationMinutes?: number | null;
}
