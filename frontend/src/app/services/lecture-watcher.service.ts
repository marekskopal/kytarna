import {HttpClient} from '@angular/common/http';
import {inject, Injectable} from '@angular/core';
import {LectureWatchers} from '@app/models/lecture-watcher';
import {PracticeParent} from '@app/models/practice-parent';
import {environment} from '@environments/environment';
import {firstValueFrom} from 'rxjs';

@Injectable({providedIn: 'root'})
export class LectureWatcherService {
    private readonly http = inject(HttpClient);

    public list(id: number, parent: PracticeParent = 'lectures'): Promise<LectureWatchers> {
        return firstValueFrom(this.http.get<LectureWatchers>(`${environment.apiUrl}/${parent}/${id}/watchers`));
    }

    public watch(id: number, parent: PracticeParent = 'lectures'): Promise<LectureWatchers> {
        return firstValueFrom(this.http.post<LectureWatchers>(`${environment.apiUrl}/${parent}/${id}/watch`, {}));
    }

    public unwatch(id: number, parent: PracticeParent = 'lectures'): Promise<LectureWatchers> {
        return firstValueFrom(this.http.delete<LectureWatchers>(`${environment.apiUrl}/${parent}/${id}/watch`));
    }
}
