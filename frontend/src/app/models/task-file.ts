export interface TaskFile {
    id: number;
    taskId: number;
    filename: string;
    mimeType: string;
    size: number;
    uploadedByUserId: number | null;
    uploadedByUserName: string | null;
    uploadedByAgent: boolean;
    createdAt: string;
}
