import {HttpErrorResponse} from '@angular/common/http';
import {HttpTestingController} from '@angular/common/http/testing';
import {TestBed} from '@angular/core/testing';
import {Tab, TabValidationErrorResponse} from '@app/models/tab';
import {commonTestProviders} from '@app/testing/test-providers';
import {beforeEach, describe, expect, it} from 'vitest';

import {TabService} from './tab.service';

const tab: Tab = {
    id: 1,
    lectureId: 7,
    name: 'Intro',
    alphatexContent: '\\title "x" . 3.3.4',
    sourceType: 'authored',
    originalFileId: null,
    tempo: 120,
    tuning: 'EADGBE',
    trackCount: 1,
    createdAt: '2026-01-01T00:00:00+00:00',
    updatedAt: '2026-01-01T00:00:00+00:00',
};

describe('TabService', () => {
    let service: TabService;
    let http: HttpTestingController;

    beforeEach(() => {
        TestBed.resetTestingModule();
        TestBed.configureTestingModule({providers: [...commonTestProviders()]});
        service = TestBed.inject(TabService);
        http = TestBed.inject(HttpTestingController);
    });

    it('lists tabs for a lecture', async () => {
        const promise = service.listTabs('MP-3');
        const req = http.expectOne((r) => r.url.endsWith('/lectures/MP-3/tabs'));
        expect(req.request.method).toBe('GET');
        req.flush([tab]);
        expect(await promise).toEqual([tab]);
        http.verify();
    });

    it('creates a tab with name + alphaTex', async () => {
        const promise = service.createTab('MP-3', {name: 'Intro', alphaTex: '. 3.3.4'});
        const req = http.expectOne((r) => r.url.endsWith('/lectures/MP-3/tabs'));
        expect(req.request.method).toBe('POST');
        expect(req.request.body).toEqual({name: 'Intro', alphaTex: '. 3.3.4'});
        req.flush(tab);
        expect(await promise).toEqual(tab);
        http.verify();
    });

    it('surfaces the 422 validation error body when alphaTex is invalid', async () => {
        const body: TabValidationErrorResponse = {
            message: 'alphaTex validation failed.',
            errors: [{message: 'Unexpected token', line: 2, col: 5, offset: 18}],
        };
        const promise = service.createTab('MP-3', {name: 'Bad', alphaTex: 'nonsense'});
        const req = http.expectOne((r) => r.url.endsWith('/lectures/MP-3/tabs'));
        req.flush(body, {status: 422, statusText: 'Unprocessable Entity'});

        await expect(promise).rejects.toSatisfy((err: unknown) => {
            const e = err as HttpErrorResponse;
            return e.status === 422 && (e.error as TabValidationErrorResponse).errors[0].line === 2;
        });
        http.verify();
    });

    it('imports a .gp file as multipart', async () => {
        const file = new File([new Uint8Array([1, 2, 3])], 'song.gp', {type: 'application/octet-stream'});
        const promise = service.importGpFile('MP-3', file);
        const req = http.expectOne((r) => r.url.endsWith('/lectures/MP-3/tabs/import'));
        expect(req.request.method).toBe('POST');
        expect(req.request.body instanceof FormData).toBe(true);
        req.flush({...tab, sourceType: 'imported_gp'});
        expect((await promise).sourceType).toBe('imported_gp');
        http.verify();
    });

    it('updates and deletes a tab by id', async () => {
        const updatePromise = service.updateTab(1, {name: 'x', alphaTex: '. 3.3.4'});
        const updateReq = http.expectOne((r) => r.url.endsWith('/tabs/1'));
        expect(updateReq.request.method).toBe('PUT');
        updateReq.flush(tab);
        await updatePromise;

        const deletePromise = service.deleteTab(1);
        const deleteReq = http.expectOne((r) => r.url.endsWith('/tabs/1'));
        expect(deleteReq.request.method).toBe('DELETE');
        deleteReq.flush(null);
        await deletePromise;
        http.verify();
    });
});
