export interface LectureFile {
    id: number;
    lectureId: number;
    filename: string;
    mimeType: string;
    size: number;
    uploadedByUserId: number | null;
    uploadedByUserName: string | null;
    uploadedByAgent: boolean;
    createdAt: string;
}
