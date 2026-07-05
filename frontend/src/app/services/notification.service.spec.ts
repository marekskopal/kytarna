import {HttpTestingController} from '@angular/common/http/testing';
import {TestBed} from '@angular/core/testing';
import {Notification, NotificationList} from '@app/models/notification';
import {commonTestProviders} from '@app/testing/test-providers';
import {beforeEach, describe, expect, it} from 'vitest';

import {NotificationService} from './notification.service';

function makeNotification(overrides: Partial<Notification> = {}): Notification {
    return {
        id: 1,
        type: 'LectureMoved',
        lectureId: 42,
        courseId: 7,
        actorId: 9,
        actorName: 'Owner',
        data: {lectureCode: 'UK-1', lectureName: 'Lecture'},
        read: false,
        createdAt: '2026-06-22T10:00:00+00:00',
        ...overrides,
    };
}

describe('NotificationService', () => {
    let service: NotificationService;
    let http: HttpTestingController;

    beforeEach(() => {
        TestBed.resetTestingModule();
        TestBed.configureTestingModule({providers: [...commonTestProviders()]});
        service = TestBed.inject(NotificationService);
        http = TestBed.inject(HttpTestingController);
    });

    it('list fetches notifications and updates the unread signal', async () => {
        const payload: NotificationList = {notifications: [makeNotification()], unreadCount: 3};
        const promise = service.list();
        const req = http.expectOne((r) => r.url.endsWith('/notifications'));
        expect(req.request.method).toBe('GET');
        expect(req.request.params.get('limit')).toBe('30');
        req.flush(payload);
        expect(await promise).toEqual(payload);
        expect(service.unreadCount()).toBe(3);
        http.verify();
    });

    it('list passes unreadOnly when requested', async () => {
        const promise = service.list(true);
        const req = http.expectOne((r) => r.url.endsWith('/notifications'));
        expect(req.request.params.get('unreadOnly')).toBe('1');
        req.flush({notifications: [], unreadCount: 0});
        await promise;
        http.verify();
    });

    it('refreshUnreadCount sets the signal', async () => {
        const promise = service.refreshUnreadCount();
        const req = http.expectOne((r) => r.url.endsWith('/notifications/unread-count'));
        expect(req.request.method).toBe('GET');
        req.flush({unreadCount: 5});
        expect(await promise).toBe(5);
        expect(service.unreadCount()).toBe(5);
        http.verify();
    });

    it('markRead posts and decrements the unread count', async () => {
        service.unreadCount.set(2);
        const promise = service.markRead(1);
        const req = http.expectOne((r) => r.url.endsWith('/notifications/1/read'));
        expect(req.request.method).toBe('POST');
        req.flush(makeNotification({read: true}));
        await promise;
        expect(service.unreadCount()).toBe(1);
        http.verify();
    });

    it('markAllRead posts and zeroes the unread count', async () => {
        service.unreadCount.set(4);
        const promise = service.markAllRead();
        const req = http.expectOne((r) => r.url.endsWith('/notifications/read-all'));
        expect(req.request.method).toBe('POST');
        req.flush({marked: 4});
        await promise;
        expect(service.unreadCount()).toBe(0);
        http.verify();
    });

    it('remove deletes the notification', async () => {
        const promise = service.remove(1);
        const req = http.expectOne((r) => r.url.endsWith('/notifications/1'));
        expect(req.request.method).toBe('DELETE');
        req.flush(null);
        await promise;
        http.verify();
    });
});
