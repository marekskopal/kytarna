import {HttpClient} from '@angular/common/http';
import {inject, Injectable} from '@angular/core';
import {LectureWatchers} from '@app/models/lecture-watcher';
import {environment} from '@environments/environment';
import {firstValueFrom} from 'rxjs';

@Injectable({providedIn: 'root'})
export class LectureWatcherService {
    private readonly http = inject(HttpClient);

    public list(lectureId: number): Promise<LectureWatchers> {
        return firstValueFrom(this.http.get<LectureWatchers>(`${environment.apiUrl}/lectures/${lectureId}/watchers`));
    }

    public watch(lectureId: number): Promise<LectureWatchers> {
        return firstValueFrom(this.http.post<LectureWatchers>(`${environment.apiUrl}/lectures/${lectureId}/watch`, {}));
    }

    public unwatch(lectureId: number): Promise<LectureWatchers> {
        return firstValueFrom(this.http.delete<LectureWatchers>(`${environment.apiUrl}/lectures/${lectureId}/watch`));
    }
}
