import {HttpClient} from '@angular/common/http';
import {inject, Injectable, signal} from '@angular/core';
import {User} from '@app/models/user';
import {environment} from '@environments/environment';
import {firstValueFrom} from 'rxjs';

@Injectable({providedIn: 'root'})
export class CurrentUserService {
    private readonly http = inject(HttpClient);
    public readonly currentUser = signal<User | null>(null);

    public async load(): Promise<User> {
        const user = await firstValueFrom(this.http.get<User>(`${environment.apiUrl}/current-user`));
        this.currentUser.set(user);
        return user;
    }

    public async update(changes: {
        name?: string;
        locale?: string;
        theme?: string;
        defaultSavedViewId?: number | null;
    }): Promise<User> {
        const user = await firstValueFrom(this.http.patch<User>(`${environment.apiUrl}/current-user`, changes));
        this.currentUser.set(user);
        return user;
    }

    public async exportData(): Promise<Blob> {
        return firstValueFrom(this.http.get(`${environment.apiUrl}/current-user/export`, {
            responseType: 'blob',
        }));
    }

    public async deleteAccount(): Promise<void> {
        await firstValueFrom(this.http.delete<void>(`${environment.apiUrl}/current-user`));
    }

    public clear(): void {
        this.currentUser.set(null);
    }
}
