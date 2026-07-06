export interface SongFile {
    id: number;
    songId: number;
    filename: string;
    mimeType: string;
    size: number;
    uploadedByUserId: number | null;
    uploadedByUserName: string | null;
    uploadedByAgent: boolean;
    createdAt: string;
}
