import {HttpTestingController} from '@angular/common/http/testing';
import {TestBed} from '@angular/core/testing';
import {ChecklistItem} from '@app/models/checklist-item';
import {commonTestProviders} from '@app/testing/test-providers';
import {beforeEach, describe, expect, it} from 'vitest';

import {TaskChecklistService} from './task-checklist.service';

function makeItem(overrides: Partial<ChecklistItem> = {}): ChecklistItem {
    return {
        id: 1,
        taskId: 42,
        text: 'Write the spec',
        position: 0,
        checked: false,
        checkedById: null,
        checkedByName: null,
        dueDate: null,
        assigneeId: null,
        assigneeName: null,
        ...overrides,
    };
}

describe('TaskChecklistService', () => {
    let service: TaskChecklistService;
    let http: HttpTestingController;

    beforeEach(() => {
        TestBed.resetTestingModule();
        TestBed.configureTestingModule({providers: [...commonTestProviders()]});
        service = TestBed.inject(TaskChecklistService);
        http = TestBed.inject(HttpTestingController);
    });

    it('list fetches the task checklist', async () => {
        const items = [makeItem()];
        const promise = service.list(42);
        const req = http.expectOne((r) => r.url.endsWith('/tasks/42/checklist'));
        expect(req.request.method).toBe('GET');
        req.flush(items);
        expect(await promise).toEqual(items);
        http.verify();
    });

    it('create posts the payload', async () => {
        const created = makeItem({id: 9, text: 'New'});
        const promise = service.create(42, {text: 'New'});
        const req = http.expectOne((r) => r.url.endsWith('/tasks/42/checklist'));
        expect(req.request.method).toBe('POST');
        expect(req.request.body).toEqual({text: 'New'});
        req.flush(created);
        expect(await promise).toEqual(created);
        http.verify();
    });

    it('update PUTs partial fields to the item endpoint', async () => {
        const updated = makeItem({checked: true});
        const promise = service.update(1, {checked: true});
        const req = http.expectOne((r) => r.url.endsWith('/checklist-items/1'));
        expect(req.request.method).toBe('PUT');
        expect(req.request.body).toEqual({checked: true});
        req.flush(updated);
        expect(await promise).toEqual(updated);
        http.verify();
    });

    it('move PUTs the new position', async () => {
        const promise = service.move(1, 2);
        const req = http.expectOne((r) => r.url.endsWith('/checklist-items/1/move'));
        expect(req.request.method).toBe('PUT');
        expect(req.request.body).toEqual({position: 2});
        req.flush(makeItem({position: 2}));
        await promise;
        http.verify();
    });

    it('delete removes the item', async () => {
        const promise = service.delete(1);
        const req = http.expectOne((r) => r.url.endsWith('/checklist-items/1'));
        expect(req.request.method).toBe('DELETE');
        req.flush(null);
        await promise;
        http.verify();
    });
});
