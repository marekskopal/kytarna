export type RecurrenceCadence = 'Daily' | 'Weekly' | 'Monthly' | 'Cron';
export type RecurrenceEndType = 'Never' | 'OnDate' | 'AfterCount';

export interface TaskRecurrence {
    id: number;
    taskId: number;
    cadence: RecurrenceCadence;
    interval: number;
    weekday: number | null;
    dayOfMonth: number | null;
    cronExpression: string | null;
    anchorDate: string;
    endType: RecurrenceEndType;
    endDate: string | null;
    maxOccurrences: number | null;
    occurrenceCount: number;
    nextRunAt: string | null;
    lastSpawnedAt: string | null;
    active: boolean;
}

export interface RecurrenceWritePayload {
    cadence: RecurrenceCadence;
    interval: number;
    weekday?: number | null;
    dayOfMonth?: number | null;
    cronExpression?: string | null;
    endType: RecurrenceEndType;
    endDate?: string | null;
    maxOccurrences?: number | null;
    anchorDate?: string | null;
}
