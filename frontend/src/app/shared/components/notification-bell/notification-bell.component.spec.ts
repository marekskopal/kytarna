import {HttpTestingController} from '@angular/common/http/testing';
import {signal} from '@angular/core';
import {ComponentFixture, TestBed} from '@angular/core/testing';
import {NotificationList} from '@app/models/notification';
import {RealtimeEvent} from '@app/models/realtime-event';
import {CurrentUserService} from '@app/services/current-user.service';
import {RealtimeService} from '@app/services/realtime.service';
import {commonTestProviders, provideTranslateStub} from '@app/testing/test-providers';
import {Subject} from 'rxjs';
import {beforeEach, describe, expect, it} from 'vitest';

import {NotificationBellComponent} from './notification-bell.component';

const list: NotificationList = {
    notifications: [
        {
            id: 1,
            type: 'TaskMention',
            taskId: 42,
            projectId: 7,
            actorId: 9,
            actorName: 'Carol',
            data: {taskCode: 'UK-1', taskName: 'Ship it', commentSnippet: 'please review'},
            read: false,
            createdAt: '2026-06-22T10:00:00+00:00',
        },
    ],
    unreadCount: 1,
};

describe('NotificationBellComponent', () => {
    let fixture: ComponentFixture<NotificationBellComponent>;
    let http: HttpTestingController;
    let events: Subject<RealtimeEvent>;

    beforeEach(() => {
        events = new Subject<RealtimeEvent>();
        TestBed.resetTestingModule();
        TestBed.configureTestingModule({
            imports: [NotificationBellComponent],
            providers: [
                ...commonTestProviders(),
                provideTranslateStub(),
                {provide: RealtimeService, useValue: {events$: events.asObservable(), connected: signal(false)}},
            ],
        });

        const currentUser = TestBed.inject(CurrentUserService);
        currentUser.currentUser.set({id: 9, email: 'me@example.com', name: 'Me'} as never);

        fixture = TestBed.createComponent(NotificationBellComponent);
        http = TestBed.inject(HttpTestingController);
    });

    it('loads the unread count on init and renders the badge', async () => {
        fixture.detectChanges();
        const req = http.expectOne((r) => r.url.endsWith('/notifications/unread-count'));
        req.flush({unreadCount: 4});
        await fixture.whenStable();
        fixture.detectChanges();

        const badge = fixture.nativeElement.querySelector('.bell-badge');
        expect(badge?.textContent?.trim()).toBe('4');
        http.verify();
    });

    it('opens the panel and lists notifications', async () => {
        fixture.detectChanges();
        http.expectOne((r) => r.url.endsWith('/notifications/unread-count')).flush({unreadCount: 1});
        await fixture.whenStable();

        const component = fixture.componentInstance as unknown as {toggle(): Promise<void>};
        const opening = component.toggle();
        const listReq = http.expectOne((r) => r.url.endsWith('/notifications'));
        listReq.flush(list);
        await opening;
        await fixture.whenStable();
        fixture.detectChanges();

        const items = fixture.nativeElement.querySelectorAll('.notif-item');
        expect(items.length).toBe(1);
        http.verify();
    });

    it('refreshes the unread count on a realtime ping addressed to the user', async () => {
        fixture.detectChanges();
        http.expectOne((r) => r.url.endsWith('/notifications/unread-count')).flush({unreadCount: 0});
        await fixture.whenStable();

        events.next({
            type: 'NotificationCreated',
            workspaceId: 1,
            projectId: 7,
            taskId: 42,
            commentId: null,
            fileId: null,
            relationId: null,
            userId: 9,
            originClientId: null,
        });
        await fixture.whenStable();

        const req = http.expectOne((r) => r.url.endsWith('/notifications/unread-count'));
        req.flush({unreadCount: 1});
        await fixture.whenStable();
        fixture.detectChanges();

        expect(fixture.nativeElement.querySelector('.bell-badge')?.textContent?.trim()).toBe('1');
        http.verify();
    });

    it('ignores realtime pings addressed to a different user', async () => {
        fixture.detectChanges();
        http.expectOne((r) => r.url.endsWith('/notifications/unread-count')).flush({unreadCount: 0});
        await fixture.whenStable();

        events.next({
            type: 'NotificationCreated',
            workspaceId: 1,
            projectId: 7,
            taskId: 42,
            commentId: null,
            fileId: null,
            relationId: null,
            userId: 999,
            originClientId: null,
        });
        await fixture.whenStable();

        http.verify(); // no extra unread-count request
    });
});
