import {provideZonelessChangeDetection} from '@angular/core';
import {ComponentFixture, TestBed} from '@angular/core/testing';
import {Lecture} from '@app/models/lecture';
import {Status} from '@app/models/status';
import {AlertService} from '@app/services/alert.service';
import {LectureService} from '@app/services/lecture.service';
import {LectureWatcherService} from '@app/services/lecture-watcher.service';
import {TranslateService} from '@ngx-translate/core';
import {afterEach, beforeEach, describe, expect, it, vi} from 'vitest';

import {LectureDetailDrawerComponent} from './lecture-detail-drawer.component';

interface DrawerInternals {
    onSubmit: () => Promise<void>;
    onDelete: () => Promise<void>;
    onCancel: () => void;
    onArchive: () => Promise<void>;
}

function internals(component: LectureDetailDrawerComponent): DrawerInternals {
    return component as unknown as DrawerInternals;
}

interface ServiceStubs {
    lectureService: {
        updateLecture: ReturnType<typeof vi.fn>;
        createLecture: ReturnType<typeof vi.fn>;
        deleteLecture: ReturnType<typeof vi.fn>;
        archiveLecture: ReturnType<typeof vi.fn>;
        unarchiveLecture: ReturnType<typeof vi.fn>;
        listLectureFiles: ReturnType<typeof vi.fn>;
    };
}

const STATUS_TODO: Status = {id: 10, workflowId: 1, name: 'To Do', color: '#888', position: 1, type: 'Start'};
const STATUS_DOING: Status = {id: 11, workflowId: 1, name: 'In Progress', color: '#369', position: 2, type: 'Normal'};

function makeLecture(overrides: Partial<Lecture> = {}): Lecture {
    return {
        id: 42,
        code: 'U-42',
        courseId: 1,
        statusId: STATUS_TODO.id,
        name: 'Existing lecture',
        description: 'A description',
        position: 1,
        sequenceNumber: 42,
        createdByAgent: false,
        archivedAt: null,
        createdAt: '2026-01-01T00:00:00Z',
        updatedAt: '2026-01-01T00:00:00Z',
        tagIds: [],
        ...overrides,
    };
}

function createFixture(options: {lecture: Lecture | null}): {
    fixture: ComponentFixture<LectureDetailDrawerComponent>;
    component: LectureDetailDrawerComponent;
    stubs: ServiceStubs;
} {
    const stubs: ServiceStubs = {
        lectureService: {
            updateLecture: vi.fn(),
            createLecture: vi.fn(),
            deleteLecture: vi.fn().mockResolvedValue(undefined),
            archiveLecture: vi.fn(),
            unarchiveLecture: vi.fn(),
            listLectureFiles: vi.fn().mockResolvedValue([]),
        },
    };

    TestBed.configureTestingModule({
        providers: [
            provideZonelessChangeDetection(),
            {provide: LectureService, useValue: stubs.lectureService},
            {provide: LectureWatcherService, useValue: {
                list: vi.fn().mockResolvedValue({watchers: [], watching: false}),
                watch: vi.fn().mockResolvedValue({watchers: [], watching: true}),
                unwatch: vi.fn().mockResolvedValue({watchers: [], watching: false}),
            }},
            {provide: AlertService, useValue: {
                success: vi.fn(),
                error: vi.fn(),
                info: vi.fn(),
            }},
            {provide: TranslateService, useValue: {
                instant: vi.fn((key: string) => key),
                get: vi.fn((key: string) => key),
            }},
        ],
    });

    const fixture = TestBed.createComponent(LectureDetailDrawerComponent);
    fixture.componentRef.setInput('lecture', options.lecture);
    fixture.componentRef.setInput('statuses', [STATUS_TODO, STATUS_DOING]);
    fixture.componentRef.setInput('courseId', 1);
    fixture.componentInstance.ngOnInit();
    return {fixture, component: fixture.componentInstance, stubs};
}

describe('LectureDetailDrawerComponent', () => {
    beforeEach(() => {
        TestBed.resetTestingModule();
    });

    afterEach(() => {
        vi.restoreAllMocks();
    });

    it('onCancel emits the cancelled output', () => {
        const {component} = createFixture({lecture: makeLecture()});
        const cancelled = vi.fn();
        component.cancelled.subscribe(() => cancelled());

        internals(component).onCancel();

        expect(cancelled).toHaveBeenCalledTimes(1);
    });

    it('onSubmit on an existing lecture calls updateLecture and emits saved with the result', async () => {
        const original = makeLecture();
        const updated = {...original, name: 'Existing lecture (edited)'};
        const {component, stubs} = createFixture({lecture: original});
        stubs.lectureService.updateLecture.mockResolvedValue(updated);

        const saved: Lecture[] = [];
        component.saved.subscribe((lecture) => saved.push(lecture));

        await internals(component).onSubmit();

        expect(stubs.lectureService.updateLecture).toHaveBeenCalledTimes(1);
        expect(stubs.lectureService.updateLecture).toHaveBeenCalledWith(original.id, expect.objectContaining({
            name: original.name,
            statusId: original.statusId,
        }));
        expect(saved).toEqual([updated]);
    });

    it('onSubmit on a new lecture calls createLecture and emits saved with the result', async () => {
        const created = makeLecture({id: 99, name: 'Brand new'});
        const {component, stubs} = createFixture({lecture: null});
        stubs.lectureService.createLecture.mockResolvedValue(created);
        component.form.patchValue({name: 'Brand new'});

        const saved: Lecture[] = [];
        component.saved.subscribe((lecture) => saved.push(lecture));

        await internals(component).onSubmit();

        expect(stubs.lectureService.createLecture).toHaveBeenCalledTimes(1);
        expect(stubs.lectureService.updateLecture).not.toHaveBeenCalled();
        expect(saved).toEqual([created]);
    });

    it('onDelete asks for confirmation, deletes, and emits the deleted lecture id', async () => {
        const confirmSpy = vi.spyOn(window, 'confirm').mockReturnValue(true);
        const original = makeLecture({id: 77});
        const {component, stubs} = createFixture({lecture: original});

        const deleted: number[] = [];
        component.deleted.subscribe((id) => deleted.push(id));

        await internals(component).onDelete();

        expect(confirmSpy).toHaveBeenCalledTimes(1);
        expect(stubs.lectureService.deleteLecture).toHaveBeenCalledWith(77);
        expect(deleted).toEqual([77]);
    });

    it('onDelete does not emit when the confirmation dialog is cancelled', async () => {
        vi.spyOn(window, 'confirm').mockReturnValue(false);
        const {component, stubs} = createFixture({lecture: makeLecture()});

        const deleted: number[] = [];
        component.deleted.subscribe((id) => deleted.push(id));

        await internals(component).onDelete();

        expect(stubs.lectureService.deleteLecture).not.toHaveBeenCalled();
        expect(deleted).toEqual([]);
    });

    it('onArchive archives the lecture and emits saved with the result', async () => {
        const original = makeLecture();
        const archived = {...original, archivedAt: '2026-02-01T00:00:00Z'};
        const {component, stubs} = createFixture({lecture: original});
        stubs.lectureService.archiveLecture.mockResolvedValue(archived);

        const saved: Lecture[] = [];
        component.saved.subscribe((lecture) => saved.push(lecture));

        await internals(component).onArchive();

        expect(stubs.lectureService.archiveLecture).toHaveBeenCalledWith(original.id);
        expect(saved).toEqual([archived]);
    });
});
