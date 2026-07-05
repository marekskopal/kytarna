import {HttpTestingController} from '@angular/common/http/testing';
import {TestBed} from '@angular/core/testing';
import {RecurrenceWritePayload, TaskRecurrence} from '@app/models/task-recurrence';
import {commonTestProviders} from '@app/testing/test-providers';
import {beforeEach, describe, expect, it} from 'vitest';

import {TaskRecurrenceService} from './task-recurrence.service';

const recurrence: TaskRecurrence = {
    id: 1,
    taskId: 42,
    cadence: 'Weekly',
    interval: 2,
    weekday: 1,
    dayOfMonth: null,
    cronExpression: null,
    anchorDate: '2026-06-01',
    endType: 'Never',
    endDate: null,
    maxOccurrences: null,
    occurrenceCount: 0,
    nextRunAt: '2026-06-08 00:00:00',
    lastSpawnedAt: null,
    active: true,
};

describe('TaskRecurrenceService', () => {
    let service: TaskRecurrenceService;
    let http: HttpTestingController;

    beforeEach(() => {
        TestBed.resetTestingModule();
        TestBed.configureTestingModule({providers: [...commonTestProviders()]});
        service = TestBed.inject(TaskRecurrenceService);
        http = TestBed.inject(HttpTestingController);
    });

    it('get fetches the recurrence', async () => {
        const promise = service.get(42);
        const req = http.expectOne((r) => r.url.endsWith('/tasks/42/recurrence'));
        expect(req.request.method).toBe('GET');
        req.flush(recurrence);
        expect(await promise).toEqual(recurrence);
        http.verify();
    });

    it('get returns null when the task does not recur', async () => {
        const promise = service.get(42);
        http.expectOne((r) => r.url.endsWith('/tasks/42/recurrence')).flush(null);
        expect(await promise).toBeNull();
        http.verify();
    });

    it('set puts the payload to the recurrence endpoint', async () => {
        const payload: RecurrenceWritePayload = {cadence: 'Weekly', interval: 2, weekday: 1, endType: 'Never'};
        const promise = service.set(42, payload);
        const req = http.expectOne((r) => r.url.endsWith('/tasks/42/recurrence'));
        expect(req.request.method).toBe('PUT');
        expect(req.request.body).toEqual(payload);
        req.flush(recurrence);
        expect((await promise).cadence).toBe('Weekly');
        http.verify();
    });

    it('clear deletes the recurrence', async () => {
        const promise = service.clear(42);
        const req = http.expectOne((r) => r.url.endsWith('/tasks/42/recurrence'));
        expect(req.request.method).toBe('DELETE');
        req.flush(null);
        await promise;
        http.verify();
    });
});
