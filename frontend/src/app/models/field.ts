export type FieldType = 'Text' | 'Textarea' | 'Select' | 'Version';

export interface Field {
    id: number;
    workspaceId: number;
    name: string;
    type: FieldType;
    required: boolean;
    defaultValue: string | null;
    options: string[] | null;
    createdAt: string;
    updatedAt: string;
}

export interface ProjectField {
    fieldId: number;
    position: number;
    field: Field;
}

export interface TaskFieldValue {
    fieldId: number;
    value: string | null;
}
