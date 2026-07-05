export interface ChecklistItem {
    id: number;
    taskId: number;
    text: string;
    position: number;
    checked: boolean;
    checkedById: number | null;
    checkedByName: string | null;
    dueDate: string | null;
    assigneeId: number | null;
    assigneeName: string | null;
}

export interface ChecklistItemCreatePayload {
    text: string;
    dueDate?: string | null;
    assigneeId?: number | null;
}

export interface ChecklistItemUpdatePayload {
    text?: string;
    dueDate?: string | null;
    assigneeId?: number | null;
    checked?: boolean;
}
