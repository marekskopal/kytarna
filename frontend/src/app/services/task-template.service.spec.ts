import {HttpTestingController} from '@angular/common/http/testing';
import {TestBed} from '@angular/core/testing';
import {TaskTemplate} from '@app/models/task-template';
import {commonTestProviders} from '@app/testing/test-providers';
import {beforeEach, describe, expect, it} from 'vitest';

import {TaskTemplateService} from './task-template.service';

function makeTemplate(overrides: Partial<TaskTemplate> = {}): TaskTemplate {
    return {
        id: 1,
        workspaceId: 5,
        name: 'Release',
        payload: {
            name: 'Release checklist',
            description: 'Tag, build, deploy',
            priorityId: 2,
            fieldValues: [],
            tagIds: [3],
        },
        createdAt: '2026-01-01T00:00:00Z',
        updatedAt: '2026-01-01T00:00:00Z',
        ...overrides,
    };
}

describe('TaskTemplateService', () => {
    let service: TaskTemplateService;
    let http: HttpTestingController;

    beforeEach(() => {
        TestBed.resetTestingModule();
        TestBed.configureTestingModule({providers: [...commonTestProviders()]});
        service = TestBed.inject(TaskTemplateService);
        http = TestBed.inject(HttpTestingController);
    });

    it('loadWorkspaceTemplates fetches once and serves the cache afterwards', async () => {
        const templates = [makeTemplate()];

        const first = service.loadWorkspaceTemplates(5);
        http.expectOne((req) => req.url.endsWith('/workspaces/5/task-templates')).flush(templates);
        expect(await first).toEqual(templates);
        expect(service.templates()).toEqual(templates);

        // Second call for the same workspace must not hit the network.
        expect(await service.loadWorkspaceTemplates(5)).toEqual(templates);
        http.verify();
    });

    it('saveFromTask posts the name and adds the template to the cache', async () => {
        const initial = service.loadWorkspaceTemplates(5);
        http.expectOne((req) => req.url.endsWith('/workspaces/5/task-templates')).flush([]);
        await initial;

        const template = makeTemplate({id: 7, name: 'Kickoff'});
        const save = service.saveFromTask(42, 'Kickoff');
        const req = http.expectOne((r) => r.url.endsWith('/tasks/42/save-as-template'));
        expect(req.request.method).toBe('POST');
        expect(req.request.body).toEqual({name: 'Kickoff'});
        req.flush(template);

        expect(await save).toEqual(template);
        expect(service.templates()).toEqual([template]);
        http.verify();
    });

    it('deleteTemplate removes the template from the cache', async () => {
        const template = makeTemplate();
        const initial = service.loadWorkspaceTemplates(5);
        http.expectOne((req) => req.url.endsWith('/workspaces/5/task-templates')).flush([template]);
        await initial;

        const deletion = service.deleteTemplate(template);
        const req = http.expectOne((r) => r.url.endsWith('/task-templates/1'));
        expect(req.request.method).toBe('DELETE');
        req.flush(null);
        await deletion;

        expect(service.templates()).toEqual([]);
        http.verify();
    });
});
