import {HttpClient, HttpParams} from '@angular/common/http';
import {inject, Injectable, signal} from '@angular/core';
import {Notification, NotificationList} from '@app/models/notification';
import {environment} from '@environments/environment';
import {firstValueFrom} from 'rxjs';

@Injectable({providedIn: 'root'})
export class NotificationService {
    private readonly http = inject(HttpClient);

    /** Shared unread badge count — read by the topbar bell, kept current on load + realtime pings. */
    public readonly unreadCount = signal(0);

    public async list(unreadOnly = false, limit = 30, offset = 0): Promise<NotificationList> {
        let params = new HttpParams().set('limit', limit).set('offset', offset);
        if (unreadOnly) {
            params = params.set('unreadOnly', '1');
        }
        const result = await firstValueFrom(
            this.http.get<NotificationList>(`${environment.apiUrl}/notifications`, {params}),
        );
        this.unreadCount.set(result.unreadCount);
        return result;
    }

    public async refreshUnreadCount(): Promise<number> {
        const result = await firstValueFrom(
            this.http.get<{unreadCount: number}>(`${environment.apiUrl}/notifications/unread-count`),
        );
        this.unreadCount.set(result.unreadCount);
        return result.unreadCount;
    }

    public async markRead(id: number): Promise<Notification> {
        const notification = await firstValueFrom(
            this.http.post<Notification>(`${environment.apiUrl}/notifications/${id}/read`, {}),
        );
        this.unreadCount.update((count) => Math.max(0, count - 1));
        return notification;
    }

    public async markAllRead(): Promise<void> {
        await firstValueFrom(this.http.post(`${environment.apiUrl}/notifications/read-all`, {}));
        this.unreadCount.set(0);
    }

    public async remove(id: number): Promise<void> {
        await firstValueFrom(this.http.delete<void>(`${environment.apiUrl}/notifications/${id}`));
    }

    public clear(): void {
        this.unreadCount.set(0);
    }
}
