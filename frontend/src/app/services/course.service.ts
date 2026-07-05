import {HttpClient} from '@angular/common/http';
import {inject, Injectable} from '@angular/core';
import {Course} from '@app/models/course';
import {environment} from '@environments/environment';
import {firstValueFrom} from 'rxjs';

@Injectable({providedIn: 'root'})
export class CourseService {
    private readonly http = inject(HttpClient);

    public getCourses(): Promise<Course[]> {
        return firstValueFrom(this.http.get<Course[]>(`${environment.apiUrl}/courses`));
    }

    public getCourse(id: number): Promise<Course> {
        return firstValueFrom(this.http.get<Course>(`${environment.apiUrl}/courses/${id}`));
    }

    public createCourse(name: string, description: string | null): Promise<Course> {
        return firstValueFrom(this.http.post<Course>(`${environment.apiUrl}/courses`, {name, description}));
    }

    public updateCourse(id: number, name: string, description: string | null): Promise<Course> {
        return firstValueFrom(this.http.put<Course>(`${environment.apiUrl}/courses/${id}`, {name, description}));
    }

    public deleteCourse(id: number): Promise<void> {
        return firstValueFrom(this.http.delete<void>(`${environment.apiUrl}/courses/${id}`));
    }
}
