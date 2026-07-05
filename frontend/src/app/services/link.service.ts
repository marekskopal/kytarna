import {HttpClient} from '@angular/common/http';
import {inject, Injectable} from '@angular/core';
import {LectureLink, LectureLinkWritePayload} from '@app/models/lecture-link';
import {environment} from '@environments/environment';
import {firstValueFrom} from 'rxjs';

@Injectable({providedIn: 'root'})
export class LinkService {
    private readonly http = inject(HttpClient);

    public listLinks(lectureId: number | string): Promise<LectureLink[]> {
        return firstValueFrom(this.http.get<LectureLink[]>(`${environment.apiUrl}/lectures/${lectureId}/links`));
    }

    public addLink(lectureId: number | string, payload: LectureLinkWritePayload): Promise<LectureLink> {
        return firstValueFrom(this.http.post<LectureLink>(`${environment.apiUrl}/lectures/${lectureId}/links`, payload));
    }

    public deleteLink(lectureId: number | string, linkId: number): Promise<void> {
        return firstValueFrom(this.http.delete<void>(`${environment.apiUrl}/lectures/${lectureId}/links/${linkId}`));
    }
}
