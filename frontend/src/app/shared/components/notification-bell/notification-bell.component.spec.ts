import {HttpTestingController} from '@angular/common/http/testing';
import {ComponentFixture, TestBed} from '@angular/core/testing';
import {NotificationList} from '@app/models/notification';
import {commonTestProviders, provideTranslateStub} from '@app/testing/test-providers';
import {beforeEach, describe, expect, it} from 'vitest';

import {NotificationBellComponent} from './notification-bell.component';

const list: NotificationList = {
    notifications: [
        {
            id: 1,
            type: 'LectureMoved',
            lectureId: 42,
            courseId: 7,
            actorId: 9,
            actorName: 'Carol',
            data: {lectureCode: 'UK-1', lectureName: 'Ship it', status: 'Mastered'},
            read: false,
            createdAt: '2026-06-22T10:00:00+00:00',
        },
    ],
    unreadCount: 1,
};

describe('NotificationBellComponent', () => {
    let fixture: ComponentFixture<NotificationBellComponent>;
    let http: HttpTestingController;

    beforeEach(() => {
        TestBed.resetTestingModule();
        TestBed.configureTestingModule({
            imports: [NotificationBellComponent],
            providers: [
                ...commonTestProviders(),
                provideTranslateStub(),
            ],
        });

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
});
