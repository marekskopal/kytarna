import {Difficulty} from '@app/models/lecture';
import {LearningStatus} from '@app/models/status';

export interface Song {
    id: number;
    code: string | null;
    courseId: number | null;
    courseName: string | null;
    status: LearningStatus;
    name: string;
    description: string | null;
    tuning: string | null;
    capo: number | null;
    targetTempoBpm: number | null;
    difficulty: Difficulty | null;
    authorName: string | null;
    albumName: string | null;
    hasCover: boolean;
    tagIds: number[];
    position: number;
    sequenceNumber: number | null;
    createdByAgent: boolean;
    archivedAt: string | null;
    createdAt: string;
    updatedAt: string;
}

export interface SongList {
    songs: Song[];
    count: number;
}
