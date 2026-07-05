import {HttpClient} from '@angular/common/http';
import {inject, Injectable} from '@angular/core';
import {TaskWatchers} from '@app/models/task-watcher';
import {environment} from '@environments/environment';
import {firstValueFrom} from 'rxjs';

@Injectable({providedIn: 'root'})
export class TaskWatcherService {
    private readonly http = inject(HttpClient);

    public list(taskId: number): Promise<TaskWatchers> {
        return firstValueFrom(this.http.get<TaskWatchers>(`${environment.apiUrl}/tasks/${taskId}/watchers`));
    }

    public watch(taskId: number): Promise<TaskWatchers> {
        return firstValueFrom(this.http.post<TaskWatchers>(`${environment.apiUrl}/tasks/${taskId}/watch`, {}));
    }

    public unwatch(taskId: number): Promise<TaskWatchers> {
        return firstValueFrom(this.http.delete<TaskWatchers>(`${environment.apiUrl}/tasks/${taskId}/watch`));
    }
}
