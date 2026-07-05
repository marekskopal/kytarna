import {HttpClient} from '@angular/common/http';
import {inject, Injectable} from '@angular/core';
import {Project} from '@app/models/project';
import {environment} from '@environments/environment';
import {firstValueFrom} from 'rxjs';

@Injectable({providedIn: 'root'})
export class ProjectService {
    private readonly http = inject(HttpClient);

    public getProjects(): Promise<Project[]> {
        return firstValueFrom(this.http.get<Project[]>(`${environment.apiUrl}/projects`));
    }

    public getProject(id: number): Promise<Project> {
        return firstValueFrom(this.http.get<Project>(`${environment.apiUrl}/projects/${id}`));
    }

    public createProject(name: string, description: string | null): Promise<Project> {
        return firstValueFrom(this.http.post<Project>(`${environment.apiUrl}/projects`, {name, description}));
    }

    public updateProject(id: number, name: string, description: string | null): Promise<Project> {
        return firstValueFrom(this.http.put<Project>(`${environment.apiUrl}/projects/${id}`, {name, description}));
    }

    public deleteProject(id: number): Promise<void> {
        return firstValueFrom(this.http.delete<void>(`${environment.apiUrl}/projects/${id}`));
    }
}
