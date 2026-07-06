import {HttpClient, HttpParams} from '@angular/common/http';
import {inject, Injectable} from '@angular/core';
import {ArchivedFilter, Difficulty, LectureOrderBy, OrderDirection} from '@app/models/lecture';
import {Song, SongList} from '@app/models/song';
import {SongFile} from '@app/models/song-file';
import {LearningStatus} from '@app/models/status';
import {environment} from '@environments/environment';
import {firstValueFrom} from 'rxjs';

export interface SongListParams {
    limit: number;
    offset: number;
    orderBy: LectureOrderBy;
    orderDirection: OrderDirection;
    search?: string;
    statuses?: LearningStatus[];
    onlyActive?: boolean;
    archived?: ArchivedFilter;
}

export interface SongWritePayload {
    name: string;
    status?: LearningStatus;
    description?: string | null;
    tuning?: string | null;
    capo?: number | null;
    targetTempoBpm?: number | null;
    difficulty?: Difficulty | null;
    authorName?: string | null;
    albumName?: string | null;
    courseId?: number | null;
    tagIds?: number[];
}

@Injectable({providedIn: 'root'})
export class SongService {
    private readonly http = inject(HttpClient);

    public getSongs(params: SongListParams): Promise<SongList> {
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
        if (params.onlyActive) {
            httpParams = httpParams.set('onlyActive', '1');
        }
        if (params.archived && params.archived !== 'active') {
            httpParams = httpParams.set('archived', params.archived);
        }
        return firstValueFrom(this.http.get<SongList>(`${environment.apiUrl}/songs`, {params: httpParams}));
    }

    public getSong(songId: number | string): Promise<Song> {
        return firstValueFrom(this.http.get<Song>(`${environment.apiUrl}/songs/${songId}`));
    }

    public createSong(payload: SongWritePayload): Promise<Song> {
        return firstValueFrom(this.http.post<Song>(`${environment.apiUrl}/songs`, payload));
    }

    public updateSong(songId: number, payload: SongWritePayload): Promise<Song> {
        return firstValueFrom(this.http.put<Song>(`${environment.apiUrl}/songs/${songId}`, payload));
    }

    public deleteSong(songId: number): Promise<void> {
        return firstValueFrom(this.http.delete<void>(`${environment.apiUrl}/songs/${songId}`));
    }

    public moveSong(songId: number, status: LearningStatus, position: number): Promise<Song> {
        return firstValueFrom(this.http.put<Song>(`${environment.apiUrl}/songs/${songId}/move`, {status, position}));
    }

    public archiveSong(songId: number): Promise<Song> {
        return firstValueFrom(this.http.post<Song>(`${environment.apiUrl}/songs/${songId}/archive`, {}));
    }

    public unarchiveSong(songId: number): Promise<Song> {
        return firstValueFrom(this.http.post<Song>(`${environment.apiUrl}/songs/${songId}/unarchive`, {}));
    }

    public setCourse(songId: number, courseId: number | null): Promise<Song> {
        return firstValueFrom(this.http.put<Song>(`${environment.apiUrl}/songs/${songId}/course`, {courseId}));
    }

    public uploadCover(songId: number, file: File): Promise<Song> {
        const data = new FormData();
        data.append('file', file, file.name);
        return firstValueFrom(this.http.put<Song>(`${environment.apiUrl}/songs/${songId}/cover`, data));
    }

    public deleteCover(songId: number): Promise<Song> {
        return firstValueFrom(this.http.delete<Song>(`${environment.apiUrl}/songs/${songId}/cover`));
    }

    /** Fetch the cover image bytes (auth header added by the JWT interceptor). Caller owns the blob URL. */
    public downloadCover(songId: number): Promise<Blob> {
        return firstValueFrom(this.http.get(`${environment.apiUrl}/songs/${songId}/cover`, {responseType: 'blob'}));
    }

    public listSongFiles(songId: number): Promise<SongFile[]> {
        return firstValueFrom(this.http.get<SongFile[]>(`${environment.apiUrl}/songs/${songId}/files`));
    }

    public uploadSongFile(songId: number, file: File): Promise<SongFile> {
        const data = new FormData();
        data.append('file', file, file.name);
        return firstValueFrom(this.http.post<SongFile>(`${environment.apiUrl}/songs/${songId}/files`, data));
    }

    public deleteSongFile(songId: number, fileId: number): Promise<void> {
        return firstValueFrom(this.http.delete<void>(`${environment.apiUrl}/songs/${songId}/files/${fileId}`));
    }

    public downloadSongFile(songId: number, fileId: number): Promise<Blob> {
        return firstValueFrom(this.http.get(`${environment.apiUrl}/songs/${songId}/files/${fileId}/content`, {
            responseType: 'blob',
        }));
    }
}
