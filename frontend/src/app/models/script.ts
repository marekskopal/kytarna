export type ScriptTrigger = 'Manual' | 'Scheduled' | 'Event';

export type ScriptRunStatus = 'Running' | 'Success' | 'Error' | 'Timeout';

export interface Script {
    id: number;
    workspaceId: number;
    createdById: number;
    name: string;
    source: string;
    trigger: ScriptTrigger;
    triggerConfig: string | null;
    active: boolean;
    lastRunAt: string | null;
    lastStatus: ScriptRunStatus | null;
    runCount: number;
    createdAt: string;
    updatedAt: string;
}

export interface ScriptRun {
    id: number;
    scriptId: number;
    triggerType: ScriptTrigger;
    status: ScriptRunStatus;
    startedAt: string | null;
    finishedAt: string | null;
    durationMs: number | null;
    logs: string | null;
    error: string | null;
    httpCalls: number;
    taskApiCalls: number;
    createdAt: string;
}

export interface ScriptVariable {
    id: number;
    workspaceId: number;
    key: string;
    value: string | null;
    isSecret: boolean;
    updatedAt: string;
}

export interface ScriptWritePayload {
    name: string;
    source: string;
    trigger: ScriptTrigger;
    triggerConfig: string | null;
    active: boolean;
}

export interface ScriptVariablePayload {
    key: string;
    value: string;
    isSecret: boolean;
}
