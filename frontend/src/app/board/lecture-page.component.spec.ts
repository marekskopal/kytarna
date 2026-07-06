import {provideZonelessChangeDetection} from '@angular/core';
import {ComponentFixture, TestBed} from '@angular/core/testing';
import {ActivatedRoute, Router} from '@angular/router';
import {Lecture} from '@app/models/lecture';
import {AlertService} from '@app/services/alert.service';
import {BoardService} from '@app/services/board.service';
import {CurrentUserService} from '@app/services/current-user.service';
import {LectureService} from '@app/services/lecture.service';
import {LectureWatcherService} from '@app/services/lecture-watcher.service';
import {TagService} from '@app/services/tag.service';
import {WorkspaceService} from '@app/services/workspace.service';
import {TranslateService} from '@ngx-translate/core';
import {afterEach, beforeEach, describe, expect, it, vi} from 'vitest';

import {LecturePageComponent} from './lecture-page.component';

interface PageInternals {
    onSubmit: () => Promise<void>;
    onDelete: () => Promise<void>;
    onCancel: () => void;
    onArchive: () => Promise<void>;
    form: {patchValue: (v: Record<string, unknown>) => void; getRawValue: () => {name: string}};
    isArchived: () => boolean;
}

function internals(component: LecturePageComponent): PageInternals {
    return component as unknown as PageInternals;
}

interface ServiceStubs {
    lectureService: {
        getLecture: ReturnType<typeof vi.fn>;
        updateLecture: ReturnType<typeof vi.fn>;
        createLecture: ReturnType<typeof vi.fn>;
        deleteLecture: ReturnType<typeof vi.fn>;
        archiveLecture: ReturnType<typeof vi.fn>;
        unarchiveLecture: ReturnType<typeof vi.fn>;
        listLectureFiles: ReturnType<typeof vi.fn>;
    };
    router: {navigate: ReturnType<typeof vi.fn>};
}

function makeLecture(overrides: Partial<Lecture> = {}): Lecture {
    return {
        id: 42,
        code: 'U-42',
        courseId: 1,
        status: 'ToLearn',
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

/**
 * Builds the component with a mock ActivatedRoute. When `lecture` is provided the route
 * exposes a `:lectureId` param (VIEW/EDIT); otherwise it is the CREATE route (`.../new`).
 */
async function createFixture(options: {lecture: Lecture | null}): Promise<{
    fixture: ComponentFixture<LecturePageComponent>;
    component: LecturePageComponent;
    stubs: ServiceStubs;
}> {
    const lecture = options.lecture;
    const stubs: ServiceStubs = {
        lectureService: {
            getLecture: vi.fn().mockResolvedValue(lecture),
            updateLecture: vi.fn(),
            createLecture: vi.fn(),
            deleteLecture: vi.fn().mockResolvedValue(undefined),
            archiveLecture: vi.fn(),
            unarchiveLecture: vi.fn(),
            listLectureFiles: vi.fn().mockResolvedValue([]),
        },
        router: {navigate: vi.fn().mockResolvedValue(true)},
    };

    const paramMap = new Map<string, string>([['id', '1']]);
    if (lecture !== null) {
        paramMap.set('lectureId', String(lecture.id));
    }
    const queryParamMap = new Map<string, string>();

    const activatedRoute = {
        snapshot: {
            paramMap: {get: (k: string): string | null => paramMap.get(k) ?? null},
            queryParamMap: {get: (k: string): string | null => queryParamMap.get(k) ?? null},
        },
    };

    TestBed.configureTestingModule({
        providers: [
            provideZonelessChangeDetection(),
            {provide: ActivatedRoute, useValue: activatedRoute},
            {provide: Router, useValue: stubs.router},
            {provide: LectureService, useValue: stubs.lectureService},
            {provide: BoardService, useValue: {
                getBoard: vi.fn().mockResolvedValue({
                    course: {id: 1, name: 'Course One'},
                    statuses: ['ToLearn', 'Learning', 'Mastered'],
                    lectures: [],
                    songs: [],
                }),
            }},
            {provide: TagService, useValue: {loadWorkspaceTags: vi.fn().mockResolvedValue([])}},
            {provide: WorkspaceService, useValue: {currentWorkspaceId: (): number => 1}},
            {provide: CurrentUserService, useValue: {load: vi.fn().mockResolvedValue({currentWorkspaceId: 1})}},
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

    const fixture = TestBed.createComponent(LecturePageComponent);
    await fixture.componentInstance.ngOnInit();
    return {fixture, component: fixture.componentInstance, stubs};
}

describe('LecturePageComponent', () => {
    beforeEach(() => {
        TestBed.resetTestingModule();
    });

    afterEach(() => {
        vi.restoreAllMocks();
    });

    it('onCancel navigates back to the course board', async () => {
        const {component, stubs} = await createFixture({lecture: makeLecture()});

        internals(component).onCancel();

        expect(stubs.router.navigate).toHaveBeenCalledWith(['courses', 1, 'board']);
    });

    it('onSubmit on an existing lecture calls updateLecture and applies the result', async () => {
        const original = makeLecture();
        const updated = {...original, name: 'Existing lecture (edited)'};
        const {component, stubs} = await createFixture({lecture: original});
        stubs.lectureService.updateLecture.mockResolvedValue(updated);

        await internals(component).onSubmit();

        expect(stubs.lectureService.updateLecture).toHaveBeenCalledTimes(1);
        expect(stubs.lectureService.updateLecture).toHaveBeenCalledWith(original.id, expect.objectContaining({
            name: original.name,
            status: original.status,
        }));
        expect(internals(component).form.getRawValue().name).toBe(updated.name);
    });

    it('onSubmit on a new lecture calls createLecture and navigates to its page', async () => {
        const created = makeLecture({id: 99, name: 'Brand new'});
        const {component, stubs} = await createFixture({lecture: null});
        stubs.lectureService.createLecture.mockResolvedValue(created);
        internals(component).form.patchValue({name: 'Brand new'});

        await internals(component).onSubmit();

        expect(stubs.lectureService.createLecture).toHaveBeenCalledTimes(1);
        expect(stubs.lectureService.updateLecture).not.toHaveBeenCalled();
        expect(stubs.router.navigate).toHaveBeenCalledWith(['courses', 1, 'lectures', created.id]);
    });

    it('onDelete asks for confirmation, deletes, and navigates back to the board', async () => {
        const confirmSpy = vi.spyOn(window, 'confirm').mockReturnValue(true);
        const original = makeLecture({id: 77});
        const {component, stubs} = await createFixture({lecture: original});
        stubs.router.navigate.mockClear();

        await internals(component).onDelete();

        expect(confirmSpy).toHaveBeenCalledTimes(1);
        expect(stubs.lectureService.deleteLecture).toHaveBeenCalledWith(77);
        expect(stubs.router.navigate).toHaveBeenCalledWith(['courses', 1, 'board']);
    });

    it('onDelete does not delete when the confirmation dialog is cancelled', async () => {
        vi.spyOn(window, 'confirm').mockReturnValue(false);
        const {component, stubs} = await createFixture({lecture: makeLecture()});
        stubs.router.navigate.mockClear();

        await internals(component).onDelete();

        expect(stubs.lectureService.deleteLecture).not.toHaveBeenCalled();
        expect(stubs.router.navigate).not.toHaveBeenCalled();
    });

    it('onArchive archives the lecture and reflects the result', async () => {
        const original = makeLecture();
        const archived = {...original, archivedAt: '2026-02-01T00:00:00Z'};
        const {component, stubs} = await createFixture({lecture: original});
        stubs.lectureService.archiveLecture.mockResolvedValue(archived);

        await internals(component).onArchive();

        expect(stubs.lectureService.archiveLecture).toHaveBeenCalledWith(original.id);
        expect(internals(component).isArchived()).toBe(true);
    });
});
