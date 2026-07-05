import {provideZonelessChangeDetection} from '@angular/core';
import {signal} from '@angular/core';
import {TestBed} from '@angular/core/testing';
import {FormControl} from '@angular/forms';
import {provideRouter} from '@angular/router';
import {LectureListItem, LectureOrderBy,OrderDirection} from '@app/models/lecture';
import {BoardService} from '@app/services/board.service';
import {CurrentUserService} from '@app/services/current-user.service';
import {BulkResult, LectureService} from '@app/services/lecture.service';
import {SavedViewService} from '@app/services/saved-view.service';
import {TagService} from '@app/services/tag.service';
import {WorkflowService} from '@app/services/workflow.service';
import {WorkspaceService} from '@app/services/workspace.service';
import {provideTranslateStub} from '@app/testing/test-providers';
import {afterEach, beforeEach, describe, expect, it, vi} from 'vitest';

import {LecturesGridComponent} from './lectures-grid.component';

interface PageSignal {
    (): number;
    set(value: number): void;
}

interface SelectionSignal<T> {
    (): T[];
    set(value: T[]): void;
}

interface FlagSignal {
    (): boolean;
    set(value: boolean): void;
}

interface SortBySignal {
    (): LectureOrderBy;
    set(value: LectureOrderBy): void;
}

interface DirectionSignal {
    (): OrderDirection;
    set(value: OrderDirection): void;
}

interface SelectedIdsSignal {
    (): Set<number>;
    set(value: Set<number>): void;
}

interface LecturesSignal {
    (): LectureListItem[];
    set(value: LectureListItem[]): void;
}

interface LastBulkResultSignal {
    (): BulkResult | null;
    set(value: BulkResult | null): void;
}

interface GridInternals {
    page: PageSignal;
    pageSize: PageSignal;
    selectedStatusIds: SelectionSignal<number>;
    selectedTagIds: SelectionSignal<number>;
    onlyActive: FlagSignal;
    sortBy: SortBySignal;
    sortDirection: DirectionSignal;
    searchControl: FormControl<string>;
    selectedIds: SelectedIdsSignal;
    lectures: LecturesSignal;
    bulkBusy: FlagSignal;
    lastBulkResult: LastBulkResultSignal;
    selectionCount: () => number;
    allOnPageSelected: () => boolean;
    someOnPageSelected: () => boolean;
    toggleRow: (id: number, event: Event) => void;
    togglePage: (event: Event) => void;
    clearSelection: () => void;
    onBulkMove: (statusId: number) => Promise<void>;
    onBulkAddTag: (tagId: number) => Promise<void>;
    onSortClick: (column: LectureOrderBy) => void;
    onStatusToggle: (statusId: number, event: Event) => void;
    onTagToggle: (tagId: number, event: Event) => void;
    onOnlyActiveToggle: (event: Event) => void;
    onPageSizeChange: (size: number) => void;
    clearFilters: () => void;
}

function internals(component: LecturesGridComponent): GridInternals {
    return component as unknown as GridInternals;
}

function checkboxEvent(checked: boolean): Event {
    return {target: {checked}, stopPropagation: () => undefined} as unknown as Event;
}

interface CreateOptions {
    bulkUpdate?: ReturnType<typeof vi.fn>;
}

function createComponent(options: CreateOptions = {}): LecturesGridComponent {
    TestBed.configureTestingModule({
        providers: [
            provideZonelessChangeDetection(),
            provideRouter([]),
            provideTranslateStub(),
            {provide: LectureService, useValue: {
                getLectures: vi.fn().mockResolvedValue({lectures: [], count: 0}),
                getLecture: vi.fn().mockResolvedValue(null),
                bulkUpdate: options.bulkUpdate ?? vi.fn().mockResolvedValue({succeeded: [], skipped: []}),
            }},
            {provide: WorkflowService, useValue: {getWorkflows: vi.fn().mockResolvedValue([])}},
            {provide: BoardService, useValue: {getBoard: vi.fn().mockResolvedValue({statuses: []})}},
            {provide: TagService, useValue: {loadWorkspaceTags: vi.fn().mockResolvedValue([])}},
            {provide: WorkspaceService, useValue: {
                currentWorkspaceId: vi.fn(() => null),
                currentMembers: signal([]),
                loadCurrentMembers: vi.fn().mockResolvedValue([]),
            }},
            {provide: CurrentUserService, useValue: {
                load: vi.fn().mockResolvedValue({currentWorkspaceId: null}),
                currentUser: signal(null),
            }},
            {provide: SavedViewService, useValue: {
                views: signal([]),
                loadForWorkspace: vi.fn().mockResolvedValue([]),
                create: vi.fn(),
                update: vi.fn(),
                delete: vi.fn().mockResolvedValue(undefined),
                clearCache: vi.fn(),
            }},
        ],
    });
    return TestBed.createComponent(LecturesGridComponent).componentInstance;
}

function fakeLecture(id: number): LectureListItem {
    return {
        id,
        code: 'T-' + id,
        courseId: 1,
        courseName: 'P',
        statusId: 1,
        status: {id: 1, name: 'To Do', color: '#000', position: 0, type: 'Start'} as unknown as LectureListItem['status'],
        name: 'lecture ' + id,
        description: null,
        position: 0,
        sequenceNumber: id,
        createdByAgent: false,
        archivedAt: null,
        createdAt: '2026-05-26T00:00:00Z',
        updatedAt: '2026-05-26T00:00:00Z',
        tagIds: [],
    };
}

describe('LecturesGridComponent', () => {
    beforeEach(() => {
        TestBed.resetTestingModule();
    });

    afterEach(() => {
        vi.useRealTimers();
    });

    it('onPageSizeChange resets the page to 1', () => {
        const inner = internals(createComponent());
        inner.page.set(4);

        inner.onPageSizeChange(100);

        expect(inner.page()).toBe(1);
        expect(inner.pageSize()).toBe(100);
    });

    it('onSortClick resets the page to 1 when switching columns', () => {
        const inner = internals(createComponent());
        inner.page.set(4);

        inner.onSortClick('name');

        expect(inner.page()).toBe(1);
        expect(inner.sortBy()).toBe('name');
    });

    it('onSortClick resets the page to 1 when toggling direction on the same column', () => {
        const inner = internals(createComponent());
        inner.page.set(3);
        const initial = inner.sortDirection();

        inner.onSortClick(inner.sortBy());

        expect(inner.page()).toBe(1);
        expect(inner.sortDirection()).not.toBe(initial);
    });

    it('onStatusToggle resets the page to 1', () => {
        const inner = internals(createComponent());
        inner.page.set(5);

        inner.onStatusToggle(7, checkboxEvent(true));

        expect(inner.page()).toBe(1);
        expect(inner.selectedStatusIds()).toContain(7);
    });

    it('onTagToggle resets the page to 1', () => {
        const inner = internals(createComponent());
        inner.page.set(5);

        inner.onTagToggle(9, checkboxEvent(true));

        expect(inner.page()).toBe(1);
        expect(inner.selectedTagIds()).toContain(9);
    });

    it('onOnlyActiveToggle resets the page to 1', () => {
        const inner = internals(createComponent());
        inner.page.set(2);

        inner.onOnlyActiveToggle(checkboxEvent(true));

        expect(inner.page()).toBe(1);
        expect(inner.onlyActive()).toBe(true);
    });

    it('clearFilters resets the page to 1 and clears every active filter', () => {
        const inner = internals(createComponent());
        inner.page.set(6);
        inner.selectedStatusIds.set([1, 2]);
        inner.selectedTagIds.set([3]);
        inner.onlyActive.set(true);
        inner.searchControl.setValue('something');

        inner.clearFilters();

        expect(inner.page()).toBe(1);
        expect(inner.selectedStatusIds()).toEqual([]);
        expect(inner.selectedTagIds()).toEqual([]);
        expect(inner.onlyActive()).toBe(false);
        expect(inner.searchControl.value).toBe('');
    });

    it('search input resets the page to 1 after the 300 ms debounce settles', () => {
        vi.useFakeTimers();
        const inner = internals(createComponent());
        inner.page.set(4);

        inner.searchControl.setValue('alpha');
        expect(inner.page()).toBe(4);

        vi.advanceTimersByTime(300);

        expect(inner.page()).toBe(1);
    });

    describe('bulk selection', () => {
        it('toggleRow adds and removes ids from selection', () => {
            const inner = internals(createComponent());
            inner.lectures.set([fakeLecture(1), fakeLecture(2)]);

            inner.toggleRow(1, checkboxEvent(true));
            expect(inner.selectionCount()).toBe(1);
            expect(inner.selectedIds().has(1)).toBe(true);

            inner.toggleRow(2, checkboxEvent(true));
            expect(inner.selectionCount()).toBe(2);

            inner.toggleRow(1, checkboxEvent(false));
            expect(inner.selectionCount()).toBe(1);
            expect(inner.selectedIds().has(1)).toBe(false);
        });

        it('togglePage selects every visible lecture id', () => {
            const inner = internals(createComponent());
            inner.lectures.set([fakeLecture(1), fakeLecture(2), fakeLecture(3)]);

            inner.togglePage(checkboxEvent(true));

            expect(inner.allOnPageSelected()).toBe(true);
            expect(inner.someOnPageSelected()).toBe(false);
            expect(inner.selectionCount()).toBe(3);
        });

        it('clearSelection empties the set', () => {
            const inner = internals(createComponent());
            inner.lectures.set([fakeLecture(1), fakeLecture(2)]);
            inner.toggleRow(1, checkboxEvent(true));
            inner.toggleRow(2, checkboxEvent(true));

            inner.clearSelection();

            expect(inner.selectionCount()).toBe(0);
        });

        it('someOnPageSelected reflects a partial selection', () => {
            const inner = internals(createComponent());
            inner.lectures.set([fakeLecture(1), fakeLecture(2)]);

            inner.toggleRow(1, checkboxEvent(true));

            expect(inner.someOnPageSelected()).toBe(true);
            expect(inner.allOnPageSelected()).toBe(false);
        });

        it('onBulkMove calls lectureService.bulkUpdate with the selected ids', async () => {
            const bulkUpdate = vi.fn().mockResolvedValue({succeeded: [1, 2], skipped: []});
            const inner = internals(createComponent({bulkUpdate}));
            inner.lectures.set([fakeLecture(1), fakeLecture(2)]);
            inner.toggleRow(1, checkboxEvent(true));
            inner.toggleRow(2, checkboxEvent(true));

            await inner.onBulkMove(7);

            expect(bulkUpdate).toHaveBeenCalledWith([1, 2], 'move', {statusId: 7});
        });

        it('exposes lastBulkResult after a partial-skip response', async () => {
            const bulkUpdate = vi.fn().mockResolvedValue({succeeded: [1], skipped: [{id: 2, reason: 'not_found'}]});
            const inner = internals(createComponent({bulkUpdate}));
            inner.lectures.set([fakeLecture(1), fakeLecture(2)]);
            inner.toggleRow(1, checkboxEvent(true));
            inner.toggleRow(2, checkboxEvent(true));

            await inner.onBulkAddTag(99);

            expect(inner.lastBulkResult()?.skipped).toEqual([{id: 2, reason: 'not_found'}]);
        });
    });
});
