import {HttpClient} from '@angular/common/http';
import {inject, Injectable} from '@angular/core';
import {PracticeParent} from '@app/models/practice-parent';
import {PracticeSummary, ProgressEntry, ProgressEntryWritePayload} from '@app/models/progress';
import {environment} from '@environments/environment';
import {firstValueFrom} from 'rxjs';

@Injectable({providedIn: 'root'})
export class ProgressService {
    private readonly http = inject(HttpClient);

    public listEntries(parentId: number | string, parent: PracticeParent = 'lectures'): Promise<ProgressEntry[]> {
        return firstValueFrom(this.http.get<ProgressEntry[]>(`${environment.apiUrl}/${parent}/${parentId}/progress`));
    }

    public createEntry(
        parentId: number | string,
        payload: ProgressEntryWritePayload,
        parent: PracticeParent = 'lectures',
    ): Promise<ProgressEntry> {
        return firstValueFrom(this.http.post<ProgressEntry>(`${environment.apiUrl}/${parent}/${parentId}/progress`, payload));
    }

    public updateEntry(
        progressEntryId: number,
        payload: ProgressEntryWritePayload,
        parent: PracticeParent = 'lectures',
    ): Promise<ProgressEntry> {
        return firstValueFrom(this.http.put<ProgressEntry>(`${environment.apiUrl}/${this.itemBase(parent)}/${progressEntryId}`, payload));
    }

    public deleteEntry(progressEntryId: number, parent: PracticeParent = 'lectures'): Promise<void> {
        return firstValueFrom(this.http.delete<void>(`${environment.apiUrl}/${this.itemBase(parent)}/${progressEntryId}`));
    }

    public getLectureSummary(parentId: number | string, parent: PracticeParent = 'lectures'): Promise<PracticeSummary> {
        return firstValueFrom(this.http.get<PracticeSummary>(`${environment.apiUrl}/${parent}/${parentId}/practice-summary`));
    }

    public getCourseSummary(courseId: number): Promise<PracticeSummary> {
        return firstValueFrom(this.http.get<PracticeSummary>(`${environment.apiUrl}/courses/${courseId}/practice-summary`));
    }

    /** Item routes diverge: lectures use `/progress/{id}`, songs use `/song-progress/{id}`. */
    private itemBase(parent: PracticeParent): string {
        return parent === 'songs' ? 'song-progress' : 'progress';
    }
}
