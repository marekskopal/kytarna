import {HttpClient} from '@angular/common/http';
import {inject, Injectable} from '@angular/core';
import {PracticeSummary, ProgressEntry, ProgressEntryWritePayload} from '@app/models/progress';
import {environment} from '@environments/environment';
import {firstValueFrom} from 'rxjs';

@Injectable({providedIn: 'root'})
export class ProgressService {
    private readonly http = inject(HttpClient);

    public listEntries(lectureId: number | string): Promise<ProgressEntry[]> {
        return firstValueFrom(this.http.get<ProgressEntry[]>(`${environment.apiUrl}/lectures/${lectureId}/progress`));
    }

    public createEntry(lectureId: number | string, payload: ProgressEntryWritePayload): Promise<ProgressEntry> {
        return firstValueFrom(this.http.post<ProgressEntry>(`${environment.apiUrl}/lectures/${lectureId}/progress`, payload));
    }

    public updateEntry(progressEntryId: number, payload: ProgressEntryWritePayload): Promise<ProgressEntry> {
        return firstValueFrom(this.http.put<ProgressEntry>(`${environment.apiUrl}/progress/${progressEntryId}`, payload));
    }

    public deleteEntry(progressEntryId: number): Promise<void> {
        return firstValueFrom(this.http.delete<void>(`${environment.apiUrl}/progress/${progressEntryId}`));
    }

    public getLectureSummary(lectureId: number | string): Promise<PracticeSummary> {
        return firstValueFrom(this.http.get<PracticeSummary>(`${environment.apiUrl}/lectures/${lectureId}/practice-summary`));
    }

    public getCourseSummary(courseId: number): Promise<PracticeSummary> {
        return firstValueFrom(this.http.get<PracticeSummary>(`${environment.apiUrl}/courses/${courseId}/practice-summary`));
    }
}
