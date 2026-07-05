import {ChangeDetectionStrategy, Component, ElementRef, HostListener, inject, OnInit, signal} from '@angular/core';
import {takeUntilDestroyed} from '@angular/core/rxjs-interop';
import {Router} from '@angular/router';
import {Notification} from '@app/models/notification';
import {RealtimeEvent} from '@app/models/realtime-event';
import {CurrentUserService} from '@app/services/current-user.service';
import {NotificationService} from '@app/services/notification.service';
import {RealtimeService} from '@app/services/realtime.service';
import {TranslatePipe, TranslateService} from '@ngx-translate/core';

@Component({
    selector: 'uk-notification-bell',
    standalone: true,
    imports: [TranslatePipe],
    changeDetection: ChangeDetectionStrategy.OnPush,
    templateUrl: './notification-bell.component.html',
    styleUrl: './notification-bell.component.scss',
})
export class NotificationBellComponent implements OnInit {
    private readonly notificationService = inject(NotificationService);
    private readonly realtimeService = inject(RealtimeService);
    private readonly currentUserService = inject(CurrentUserService);
    private readonly translate = inject(TranslateService);
    private readonly router = inject(Router);
    private readonly host = inject<ElementRef<HTMLElement>>(ElementRef);

    protected readonly unreadCount = this.notificationService.unreadCount;
    protected readonly open = signal(false);
    protected readonly loading = signal(false);
    protected readonly notifications = signal<Notification[]>([]);

    public constructor() {
        this.realtimeService.events$
            .pipe(takeUntilDestroyed())
            .subscribe((event) => void this.onRealtimeEvent(event));
    }

    public ngOnInit(): void {
        void this.notificationService.refreshUnreadCount();
    }

    @HostListener('document:click', ['$event.target'])
    protected onDocumentClick(target: EventTarget | null): void {
        if (!this.open() || !(target instanceof Node)) {
            return;
        }
        if (!this.host.nativeElement.contains(target)) {
            this.open.set(false);
        }
    }

    protected async toggle(): Promise<void> {
        const next = !this.open();
        this.open.set(next);
        if (next) {
            await this.load();
        }
    }

    protected async markAllRead(): Promise<void> {
        await this.notificationService.markAllRead();
        this.notifications.update((list) => list.map((n) => ({...n, read: true})));
    }

    protected async openNotification(notification: Notification): Promise<void> {
        if (!notification.read) {
            await this.notificationService.markRead(notification.id);
            this.notifications.update((list) => list.map((n) => (n.id === notification.id ? {...n, read: true} : n)));
        }
        this.open.set(false);

        const code = notification.data.taskCode;
        if (code !== undefined && code !== '') {
            await this.router.navigate(['/tasks'], {queryParams: {open: code}});
        }
    }

    protected async remove(notification: Notification, event: Event): Promise<void> {
        event.stopPropagation();
        await this.notificationService.remove(notification.id);
        this.notifications.update((list) => list.filter((n) => n.id !== notification.id));
        if (!notification.read) {
            await this.notificationService.refreshUnreadCount();
        }
    }

    protected messageKey(type: Notification['type']): string {
        return 'app.notifications.message.' + type;
    }

    protected formatRelative(iso: string): string {
        const then = new Date(iso).getTime();
        const diff = Math.max(0, Date.now() - then);
        const seconds = Math.round(diff / 1000);
        const t = (key: string, params?: Record<string, unknown>): string => this.translate.instant(key, params) as string;
        if (seconds < 60) {
            return t('app.events.timeAgo.seconds', {n: seconds});
        }
        const minutes = Math.round(seconds / 60);
        if (minutes < 60) {
            return t('app.events.timeAgo.minutes', {n: minutes});
        }
        const hours = Math.round(minutes / 60);
        if (hours < 24) {
            return t('app.events.timeAgo.hours', {n: hours});
        }
        return new Date(iso).toLocaleDateString();
    }

    private async load(): Promise<void> {
        this.loading.set(true);
        try {
            const result = await this.notificationService.list();
            this.notifications.set(result.notifications);
        } finally {
            this.loading.set(false);
        }
    }

    private async onRealtimeEvent(event: RealtimeEvent): Promise<void> {
        if (event.type === 'RealtimeReconnected') {
            await this.notificationService.refreshUnreadCount();
            return;
        }
        if (event.type !== 'NotificationCreated') {
            return;
        }
        // Notifications are broadcast on the workspace topic; act only on pings addressed to me.
        if (event.userId !== this.currentUserService.currentUser()?.id) {
            return;
        }
        await this.notificationService.refreshUnreadCount();
        if (this.open()) {
            await this.load();
        }
    }
}
