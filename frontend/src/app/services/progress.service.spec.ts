import {HttpTestingController} from '@angular/common/http/testing';
import {TestBed} from '@angular/core/testing';
import {PracticeSummary, ProgressEntry} from '@app/models/progress';
import {commonTestProviders} from '@app/testing/test-providers';
import {beforeEach, describe, expect, it} from 'vitest';

import {ProgressService} from './progress.service';

const entry: ProgressEntry = {
    id: 5,
    lectureId: 7,
    practicedAt: '2026-06-01',
    note: 'clean run',
    tempoBpm: 90,
    durationMinutes: 30,
    createdAt: '2026-06-01T10:00:00+00:00',
    updatedAt: '2026-06-01T10:00:00+00:00',
};

const summary: PracticeSummary = {
    totalEntries: 1,
    totalMinutes: 30,
    entriesPerWeek: {'2026-W22': 1},
    bpmTrend: [{practicedAt: '2026-06-01', tempoBpm: 90}],
};

describe('ProgressService', () => {
    let service: ProgressService;
    let http: HttpTestingController;

    beforeEach(() => {
        TestBed.resetTestingModule();
        TestBed.configureTestingModule({providers: [...commonTestProviders()]});
        service = TestBed.inject(ProgressService);
        http = TestBed.inject(HttpTestingController);
    });

    it('lists entries for a lecture', async () => {
        const promise = service.listEntries('MP-3');
        const req = http.expectOne((r) => r.url.endsWith('/lectures/MP-3/progress'));
        expect(req.request.method).toBe('GET');
        req.flush([entry]);
        expect(await promise).toEqual([entry]);
        http.verify();
    });

    it('creates an entry', async () => {
        const promise = service.createEntry('MP-3', {practicedAt: '2026-06-01', tempoBpm: 90, durationMinutes: 30, note: 'clean run'});
        const req = http.expectOne((r) => r.url.endsWith('/lectures/MP-3/progress'));
        expect(req.request.method).toBe('POST');
        expect(req.request.body.tempoBpm).toBe(90);
        req.flush(entry);
        expect(await promise).toEqual(entry);
        http.verify();
    });

    it('updates and deletes an entry by id', async () => {
        const updatePromise = service.updateEntry(5, {tempoBpm: 100});
        const updateReq = http.expectOne((r) => r.url.endsWith('/progress/5'));
        expect(updateReq.request.method).toBe('PUT');
        updateReq.flush({...entry, tempoBpm: 100});
        expect((await updatePromise).tempoBpm).toBe(100);

        const deletePromise = service.deleteEntry(5);
        const deleteReq = http.expectOne((r) => r.url.endsWith('/progress/5'));
        expect(deleteReq.request.method).toBe('DELETE');
        deleteReq.flush(null);
        await deletePromise;
        http.verify();
    });

    it('fetches the lecture practice summary', async () => {
        const promise = service.getLectureSummary('MP-3');
        const req = http.expectOne((r) => r.url.endsWith('/lectures/MP-3/practice-summary'));
        expect(req.request.method).toBe('GET');
        req.flush(summary);
        expect(await promise).toEqual(summary);
        http.verify();
    });

    it('fetches the course practice summary', async () => {
        const promise = service.getCourseSummary(2);
        const req = http.expectOne((r) => r.url.endsWith('/courses/2/practice-summary'));
        expect(req.request.method).toBe('GET');
        req.flush(summary);
        expect(await promise).toEqual(summary);
        http.verify();
    });
});
