import {HttpClient} from '@angular/common/http';
import {inject, Injectable} from '@angular/core';
import {Board} from '@app/models/board';
import {environment} from '@environments/environment';
import {firstValueFrom} from 'rxjs';

@Injectable({providedIn: 'root'})
export class BoardService {
    private readonly http = inject(HttpClient);

    public getBoard(courseId: number): Promise<Board> {
        return firstValueFrom(this.http.get<Board>(`${environment.apiUrl}/courses/${courseId}/board`));
    }
}
