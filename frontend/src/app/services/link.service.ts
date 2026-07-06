import {HttpClient} from '@angular/common/http';
import {inject, Injectable} from '@angular/core';
import {LectureLink, LectureLinkWritePayload} from '@app/models/lecture-link';
import {PracticeParent} from '@app/models/practice-parent';
import {environment} from '@environments/environment';
import {firstValueFrom} from 'rxjs';

@Injectable({providedIn: 'root'})
export class LinkService {
    private readonly http = inject(HttpClient);

    public listLinks(parentId: number | string, parent: PracticeParent = 'lectures'): Promise<LectureLink[]> {
        return firstValueFrom(this.http.get<LectureLink[]>(`${environment.apiUrl}/${parent}/${parentId}/links`));
    }

    public addLink(
        parentId: number | string,
        payload: LectureLinkWritePayload,
        parent: PracticeParent = 'lectures',
    ): Promise<LectureLink> {
        return firstValueFrom(this.http.post<LectureLink>(`${environment.apiUrl}/${parent}/${parentId}/links`, payload));
    }

    public deleteLink(parentId: number | string, linkId: number, parent: PracticeParent = 'lectures'): Promise<void> {
        return firstValueFrom(this.http.delete<void>(`${environment.apiUrl}/${parent}/${parentId}/links/${linkId}`));
    }
}
