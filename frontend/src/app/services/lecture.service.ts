import {HttpClient, HttpParams} from '@angular/common/http';
import {inject, Injectable} from '@angular/core';
import {ArchivedFilter, Difficulty, Lecture, LectureList, LectureOrderBy,OrderDirection} from '@app/models/lecture';
import {LectureFile} from '@app/models/lecture-file';
import {LearningStatus} from '@app/models/status';
import {environment} from '@environments/environment';
import {firstValueFrom} from 'rxjs';

export interface LectureWritePayload {
    status: LearningStatus;
    name: string;
    description: string | null;
    tuning?: string | null;
    capo?: number | null;
    targetTempoBpm?: number | null;
    difficulty?: Difficulty | null;
    tagIds?: number[];
}

export interface LectureListParams {
    limit: number;
    offset: number;
    orderBy: LectureOrderBy;
    orderDirection: OrderDirection;
    search?: string;
    statuses?: LearningStatus[];
    tagIds?: number[];
    onlyActive?: boolean;
    archived?: ArchivedFilter;
}

export type BulkOp = 'move' | 'tag' | 'untag' | 'delete';

export interface BulkSkipped {
    id: number;
    reason: string;
}

export interface BulkResult {
    succeeded: number[];
    skipped: BulkSkipped[];
}

@Injectable({providedIn: 'root'})
export class LectureService {
    private readonly http = inject(HttpClient);

    public getLectures(params: LectureListParams): Promise<LectureList> {
        let httpParams = new HttpParams()
            .set('limit', params.limit)
            .set('offset', params.offset)
            .set('orderBy', params.orderBy)
            .set('orderDirection', params.orderDirection);
        if (params.search) {
            httpParams = httpParams.set('search', params.search);
        }
        if (params.statuses && params.statuses.length > 0) {
            httpParams = httpParams.set('statuses', params.statuses.join('|'));
        }
        if (params.tagIds && params.tagIds.length > 0) {
            httpParams = httpParams.set('tagIds', params.tagIds.join('|'));
        }
        if (params.onlyActive) {
            httpParams = httpParams.set('onlyActive', '1');
        }
        if (params.archived && params.archived !== 'active') {
            httpParams = httpParams.set('archived', params.archived);
        }
        return firstValueFrom(this.http.get<LectureList>(`${environment.apiUrl}/lectures`, {params: httpParams}));
    }

    public getLecture(lectureId: number | string): Promise<Lecture> {
        return firstValueFrom(this.http.get<Lecture>(`${environment.apiUrl}/lectures/${lectureId}`));
    }

    public createLecture(courseId: number, payload: LectureWritePayload): Promise<Lecture> {
        return firstValueFrom(this.http.post<Lecture>(`${environment.apiUrl}/courses/${courseId}/lectures`, payload));
    }

    public updateLecture(lectureId: number, payload: LectureWritePayload): Promise<Lecture> {
        return firstValueFrom(this.http.put<Lecture>(`${environment.apiUrl}/lectures/${lectureId}`, payload));
    }

    public moveLecture(lectureId: number, status: LearningStatus, position: number): Promise<Lecture> {
        return firstValueFrom(this.http.put<Lecture>(`${environment.apiUrl}/lectures/${lectureId}/move`, {status, position}));
    }

    public archiveLecture(lectureId: number): Promise<Lecture> {
        return firstValueFrom(this.http.post<Lecture>(`${environment.apiUrl}/lectures/${lectureId}/archive`, {}));
    }

    public unarchiveLecture(lectureId: number): Promise<Lecture> {
        return firstValueFrom(this.http.post<Lecture>(`${environment.apiUrl}/lectures/${lectureId}/unarchive`, {}));
    }

    public deleteLecture(lectureId: number): Promise<void> {
        return firstValueFrom(this.http.delete<void>(`${environment.apiUrl}/lectures/${lectureId}`));
    }

    public bulkUpdate(ids: number[], op: BulkOp, payload?: Record<string, unknown>): Promise<BulkResult> {
        return firstValueFrom(
            this.http.post<BulkResult>(`${environment.apiUrl}/lectures/bulk`, {ids, op, payload: payload ?? {}}),
        );
    }

    public listLectureFiles(lectureId: number): Promise<LectureFile[]> {
        return firstValueFrom(this.http.get<LectureFile[]>(`${environment.apiUrl}/lectures/${lectureId}/files`));
    }

    public uploadLectureFile(lectureId: number, file: File): Promise<LectureFile> {
        const data = new FormData();
        data.append('file', file, file.name);
        return firstValueFrom(this.http.post<LectureFile>(`${environment.apiUrl}/lectures/${lectureId}/files`, data));
    }

    public deleteLectureFile(lectureId: number, fileId: number): Promise<void> {
        return firstValueFrom(this.http.delete<void>(`${environment.apiUrl}/lectures/${lectureId}/files/${fileId}`));
    }

    public downloadLectureFile(lectureId: number, fileId: number): Promise<Blob> {
        return firstValueFrom(this.http.get(`${environment.apiUrl}/lectures/${lectureId}/files/${fileId}/content`, {
            responseType: 'blob',
        }));
    }
}
