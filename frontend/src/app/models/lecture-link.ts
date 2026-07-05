export type LectureLinkKind = 'youtube' | 'other';

export interface LectureLink {
    id: number;
    lectureId: number;
    url: string;
    label: string | null;
    kind: LectureLinkKind;
    timestampSeconds: number | null;
    createdAt: string;
}

export interface LectureLinkWritePayload {
    url: string;
    label?: string | null;
    kind?: LectureLinkKind | null;
    timestampSeconds?: number | null;
}
