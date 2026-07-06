import {LearningStatus} from '@app/models/status';

export type Difficulty = 'Beginner' | 'Intermediate' | 'Advanced';

export interface Lecture {
    id: number;
    code: string;
    courseId: number;
    status: LearningStatus;
    name: string;
    description: string | null;
    tuning: string | null;
    capo: number | null;
    targetTempoBpm: number | null;
    difficulty: Difficulty | null;
    position: number;
    sequenceNumber: number;
    createdByAgent: boolean;
    archivedAt: string | null;
    createdAt: string;
    updatedAt: string;
    tagIds: number[];
}

export type LectureOrderBy = 'created_at' | 'name' | 'status';
export type OrderDirection = 'ASC' | 'DESC';
export type ArchivedFilter = 'active' | 'archived' | 'all';

export interface LectureListItem {
    id: number;
    code: string;
    courseId: number;
    courseName: string;
    status: LearningStatus;
    name: string;
    description: string | null;
    tuning: string | null;
    capo: number | null;
    targetTempoBpm: number | null;
    difficulty: Difficulty | null;
    position: number;
    sequenceNumber: number;
    createdByAgent: boolean;
    archivedAt: string | null;
    createdAt: string;
    updatedAt: string;
    tagIds: number[];
}

export interface LectureList {
    lectures: LectureListItem[];
    count: number;
}
