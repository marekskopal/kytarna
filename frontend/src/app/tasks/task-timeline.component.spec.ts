import {ComponentRef} from '@angular/core';
import {ComponentFixture, TestBed} from '@angular/core/testing';
import {Status} from '@app/models/status';
import {TaskListItem} from '@app/models/task';
import {TaskService} from '@app/services/task.service';
import {commonTestProviders, provideTranslateStub} from '@app/testing/test-providers';
import {beforeEach, describe, expect, it, vi} from 'vitest';

import {CalendarTaskFilters} from './task-calendar.component';
import {TaskTimelineComponent} from './task-timeline.component';

const ACTIVE_STATUS: Status = {id: 1, workflowId: 1, name: 'To Do', color: '#94a3a8', position: 0, type: 'Start'};
const FINISH_STATUS: Status = {id: 9, workflowId: 1, name: 'Done', color: '#16794a', position: 2, type: 'Finish'};

function makeTask(overrides: Partial<TaskListItem>): TaskListItem {
    return {
        id: 1,
        code: 'U-1',
        projectId: 1,
        projectName: 'Alpha',
        statusId: ACTIVE_STATUS.id,
        status: ACTIVE_STATUS,
        assigneeId: null,
        name: 'Task',
        description: null,
        priority: {
            id: 1, workspaceId: 1, name: 'Medium', color: '#a35c00', position: 1, isDefault: true,
            createdAt: '2026-05-01T00:00:00Z', updatedAt: '2026-05-01T00:00:00Z',
        },
        dueDate: null,
        startDate: null,
        position: 0,
        sequenceNumber: 1,
        createdByAgent: false,
        archivedAt: null,
        createdAt: '2026-05-01T00:00:00Z',
        updatedAt: '2026-05-01T00:00:00Z',
        tagIds: [],
        ...overrides,
    } as TaskListItem;
}

const EMPTY_FILTERS: CalendarTaskFilters = {
    search: undefined, statusIds: undefined, tagIds: undefined, assigneeIds: undefined,
    onlyActive: undefined, subtaskFilter: undefined, archived: undefined,
};

interface Bar {
    task: TaskListItem;
    leftPct: number;
    widthPct: number;
    overdue: boolean;
    clippedStart: boolean;
    clippedEnd: boolean;
}
interface Group {project: string; rows: Bar[]}
interface Internals {
    anchor: {set(v: string): void};
    zoom: {(): 'week' | 'month'; set(v: 'week' | 'month'): void};
    spanDays(): number;
    rangeStart(): string;
    rangeEnd(): string;
    weeks(): {iso: string}[];
    groups(): Group[];
    prev(): void;
    next(): void;
}

describe('TaskTimelineComponent', () => {
    let fixture: ComponentFixture<TaskTimelineComponent>;
    let ref: ComponentRef<TaskTimelineComponent>;
    let internals: Internals;
    let taskService: {getTasks: ReturnType<typeof vi.fn>; getTask: ReturnType<typeof vi.fn>; updateTask: ReturnType<typeof vi.fn>};

    beforeEach(async () => {
        taskService = {
            getTasks: vi.fn().mockResolvedValue({tasks: [], count: 0}),
            getTask: vi.fn(),
            updateTask: vi.fn().mockResolvedValue(undefined),
        };
        await TestBed.configureTestingModule({
            imports: [TaskTimelineComponent],
            providers: [commonTestProviders(), provideTranslateStub(), {provide: TaskService, useValue: taskService}],
        }).compileComponents();

        fixture = TestBed.createComponent(TaskTimelineComponent);
        ref = fixture.componentRef;
        ref.setInput('filters', EMPTY_FILTERS);
        internals = fixture.componentInstance as unknown as Internals;
        // Anchor on Monday May 4 2026 so the visible range is deterministic.
        internals.anchor.set('2026-05-04');
    });

    it('fetches with the shared filters (no due-date range — overlap is computed client-side)', async () => {
        ref.setInput('filters', {...EMPTY_FILTERS, onlyActive: true});
        fixture.detectChanges();
        await fixture.whenStable();

        const params = taskService.getTasks.mock.calls.at(-1)?.[0];
        expect(params.onlyActive).toBe(true);
        expect(params.limit).toBe(200);
        expect(params.dueFrom).toBeUndefined();
        expect(params.dueTo).toBeUndefined();
    });

    it('week zoom spans 35 days / 5 week columns; month zoom spans 70 / 10', () => {
        expect(internals.spanDays()).toBe(35);
        expect(internals.rangeStart()).toBe('2026-05-04');
        expect(internals.rangeEnd()).toBe('2026-06-07');
        expect(internals.weeks().length).toBe(5);

        internals.zoom.set('month');
        expect(internals.spanDays()).toBe(70);
        expect(internals.weeks().length).toBe(10);
    });

    it('prev/next shift the range by a week and back', () => {
        internals.prev();
        expect(internals.rangeStart()).toBe('2026-04-27');
        internals.next();
        expect(internals.rangeStart()).toBe('2026-05-04');
    });

    it('groups overlapping tasks by project with correct bar geometry', async () => {
        taskService.getTasks.mockResolvedValue({
            tasks: [
                makeTask({id: 1, projectName: 'Alpha', startDate: '2026-05-04', dueDate: '2026-05-08'}),
                makeTask({id: 2, projectName: 'Alpha', startDate: null, dueDate: '2026-05-11'}), // single-day
                makeTask({id: 3, projectName: 'Beta', startDate: '2026-04-20', dueDate: '2026-05-06'}), // clipped start
                makeTask({id: 4, projectName: 'Beta', startDate: '2026-09-01', dueDate: '2026-09-05'}), // out of range
            ],
            count: 4,
        });
        ref.setInput('filters', {...EMPTY_FILTERS});
        fixture.detectChanges();
        await fixture.whenStable();

        const groups = internals.groups();
        expect(groups.map((g) => g.project)).toEqual(['Alpha', 'Beta']); // sorted, out-of-range Beta task dropped
        const alpha = groups[0].rows;
        expect(alpha.map((b) => b.task.id)).toEqual([1, 2]);
        // Task 1: starts at range start (left 0), 5 days wide of 35.
        expect(alpha[0].leftPct).toBeCloseTo(0, 5);
        expect(alpha[0].widthPct).toBeCloseTo(5 / 35 * 100, 4);
        // Task 2: single-day bar 7 days in.
        expect(alpha[1].leftPct).toBeCloseTo(7 / 35 * 100, 4);
        expect(alpha[1].widthPct).toBeCloseTo(1 / 35 * 100, 4);

        const beta = groups[1].rows;
        expect(beta).toHaveLength(1);
        expect(beta[0].clippedStart).toBe(true);
        expect(beta[0].leftPct).toBeCloseTo(0, 5); // clamped to range start
    });

    it('flags overdue for unfinished tasks whose effective end is before today', async () => {
        internals.anchor.set('2000-01-03'); // Monday; range around Jan 2000 so the bar end is "today"-relative past
        taskService.getTasks.mockResolvedValue({
            tasks: [
                makeTask({id: 1, status: ACTIVE_STATUS, startDate: '2000-01-03', dueDate: '2000-01-05'}),
                makeTask({id: 2, status: FINISH_STATUS, startDate: '2000-01-03', dueDate: '2000-01-05'}),
            ],
            count: 2,
        });
        ref.setInput('filters', {...EMPTY_FILTERS});
        fixture.detectChanges();
        await fixture.whenStable();

        const rows = internals.groups()[0].rows;
        expect(rows.find((b) => b.task.id === 1)?.overdue).toBe(true);
        expect(rows.find((b) => b.task.id === 2)?.overdue).toBe(false);
    });
});
