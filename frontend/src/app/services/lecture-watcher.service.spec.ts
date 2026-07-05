import {HttpTestingController} from '@angular/common/http/testing';
import {TestBed} from '@angular/core/testing';
import {LectureWatchers} from '@app/models/lecture-watcher';
import {commonTestProviders} from '@app/testing/test-providers';
import {beforeEach, describe, expect, it} from 'vitest';

import {LectureWatcherService} from './lecture-watcher.service';

const watched: LectureWatchers = {watchers: [{userId: 9, userName: 'Owner'}], watching: true};
const empty: LectureWatchers = {watchers: [], watching: false};

describe('LectureWatcherService', () => {
    let service: LectureWatcherService;
    let http: HttpTestingController;

    beforeEach(() => {
        TestBed.resetTestingModule();
        TestBed.configureTestingModule({providers: [...commonTestProviders()]});
        service = TestBed.inject(LectureWatcherService);
        http = TestBed.inject(HttpTestingController);
    });

    it('list fetches watchers', async () => {
        const promise = service.list(42);
        const req = http.expectOne((r) => r.url.endsWith('/lectures/42/watchers'));
        expect(req.request.method).toBe('GET');
        req.flush(watched);
        expect(await promise).toEqual(watched);
        http.verify();
    });

    it('watch posts to the watch endpoint', async () => {
        const promise = service.watch(42);
        const req = http.expectOne((r) => r.url.endsWith('/lectures/42/watch'));
        expect(req.request.method).toBe('POST');
        req.flush(watched);
        expect((await promise).watching).toBe(true);
        http.verify();
    });

    it('unwatch deletes from the watch endpoint', async () => {
        const promise = service.unwatch(42);
        const req = http.expectOne((r) => r.url.endsWith('/lectures/42/watch'));
        expect(req.request.method).toBe('DELETE');
        req.flush(empty);
        expect((await promise).watching).toBe(false);
        http.verify();
    });
});
