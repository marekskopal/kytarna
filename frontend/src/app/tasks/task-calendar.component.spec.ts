import {ComponentRef} from '@angular/core';
import {ComponentFixture, TestBed} from '@angular/core/testing';
import {Status} from '@app/models/status';
import {TaskListItem} from '@app/models/task';
import {TaskService} from '@app/services/task.service';
import {commonTestProviders, provideTranslateStub} from '@app/testing/test-providers';
import {beforeEach, describe, expect, it, vi} from 'vitest';

import {CalendarTaskFilters, TaskCalendarComponent} from './task-calendar.component';

const FINISH_STATUS: Status = {id: 9, workflowId: 1, name: 'Done', color: '#16794a', position: 2, type: 'Finish'};
const ACTIVE_STATUS: Status = {id: 1, workflowId: 1, name: 'To Do', color: '#94a3a8', position: 0, type: 'Start'};

function makeTask(overrides: Partial<TaskListItem>): TaskListItem {
    return {
        id: 1,
        code: 'U-1',
        projectId: 1,
        projectName: 'Demo',
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
    search: undefined,
    statusIds: undefined,
    tagIds: undefined,
    assigneeIds: undefined,
    onlyActive: undefined,
    subtaskFilter: undefined,
    archived: undefined,
};

interface CalendarInternals {
    anchor: {(): string; set(v: string): void};
    view: {(): 'month' | 'week'; set(v: 'month' | 'week'): void};
    cells(): {iso: string; inMonth: boolean; isToday: boolean}[];
    tasksFor(iso: string): TaskListItem[];
    isOverdue(t: TaskListItem): boolean;
    isDone(t: TaskListItem): boolean;
}

describe('TaskCalendarComponent', () => {
    let fixture: ComponentFixture<TaskCalendarComponent>;
    let ref: ComponentRef<TaskCalendarComponent>;
    let internals: CalendarInternals;
    let taskService: {getTasks: ReturnType<typeof vi.fn>; getTask: ReturnType<typeof vi.fn>; updateTask: ReturnType<typeof vi.fn>};

    beforeEach(async () => {
        taskService = {
            getTasks: vi.fn().mockResolvedValue({tasks: [], count: 0}),
            getTask: vi.fn(),
            updateTask: vi.fn().mockResolvedValue(undefined),
        };

        await TestBed.configureTestingModule({
            imports: [TaskCalendarComponent],
            providers: [commonTestProviders(), provideTranslateStub(), {provide: TaskService, useValue: taskService}],
        }).compileComponents();

        fixture = TestBed.createComponent(TaskCalendarComponent);
        ref = fixture.componentRef;
        ref.setInput('filters', EMPTY_FILTERS);
        internals = fixture.componentInstance as unknown as CalendarInternals;
    });

    it('fetches tasks with the shared filters and visible date range', async () => {
        (internals as unknown as {anchor: {set(v: string): void}}).anchor.set('2026-05-01');
        ref.setInput('filters', {...EMPTY_FILTERS, onlyActive: true, statusIds: [1, 2]});
        fixture.detectChanges();
        await fixture.whenStable();

        expect(taskService.getTasks).toHaveBeenCalled();
        const params = taskService.getTasks.mock.calls.at(-1)?.[0];
        expect(params.onlyActive).toBe(true);
        expect(params.statusIds).toEqual([1, 2]);
        // May 2026 month grid backfills to Sun Apr 26 and runs 42 days through Jun 6.
        expect(params.dueFrom).toBe('2026-04-26');
        expect(params.dueTo).toBe('2026-06-06');
    });

    it('renders a 42-cell month grid anchored on the current month', () => {
        internals.anchor.set('2026-05-01');
        fixture.detectChanges();

        const cells = internals.cells();
        expect(cells.length).toBe(42);
        // May 2026 starts on a Friday, so the grid backfills to the prior Sunday (Apr 26).
        expect(cells[0].iso).toBe('2026-04-26');
        expect(cells.filter((c) => c.inMonth).length).toBe(31);
    });

    it('switches to a 7-cell week grid', () => {
        internals.anchor.set('2026-05-20');
        internals.view.set('week');
        fixture.detectChanges();

        expect(internals.cells().length).toBe(7);
    });

    it('buckets tasks by due date', async () => {
        taskService.getTasks.mockResolvedValue({
            tasks: [
                makeTask({id: 1, dueDate: '2026-05-12T00:00:00Z'}),
                makeTask({id: 2, dueDate: '2026-05-12T00:00:00Z'}),
                makeTask({id: 3, dueDate: '2026-05-20T00:00:00Z'}),
                makeTask({id: 4, dueDate: null}),
            ],
            count: 4,
        });
        ref.setInput('filters', {...EMPTY_FILTERS});
        fixture.detectChanges();
        await fixture.whenStable();

        expect(internals.tasksFor('2026-05-12').map((t) => t.id)).toEqual([1, 2]);
        expect(internals.tasksFor('2026-05-20').map((t) => t.id)).toEqual([3]);
    });

    it('flags overdue active tasks but not finished ones', () => {
        const overdue = makeTask({id: 1, dueDate: '2000-01-01T00:00:00Z', status: ACTIVE_STATUS});
        const doneOld = makeTask({id: 2, dueDate: '2000-01-01T00:00:00Z', status: FINISH_STATUS});
        expect(internals.isOverdue(overdue)).toBe(true);
        expect(internals.isDone(doneOld)).toBe(true);
        expect(internals.isOverdue(doneOld)).toBe(false);
    });

    it('reschedules via getTask + updateTask on drop, preserving fields', async () => {
        taskService.getTask.mockResolvedValue({
            id: 1,
            statusId: 1,
            name: 'Task',
            description: 'desc',
            priority: {id: 7},
            dueDate: '2026-05-10',
            startDate: '2026-05-08',
            assigneeId: 3,
            fieldValues: [{fieldId: 1, value: 'x'}],
            tagIds: [4],
        });
        const dropped = vi.fn();
        ref.setInput('filters', {...EMPTY_FILTERS});
        fixture.componentInstance.changed.subscribe(dropped);

        const task = makeTask({id: 1, dueDate: '2026-05-10T00:00:00Z'});
        await (fixture.componentInstance as unknown as {
            onDrop(e: {container: {data: string}; item: {data: TaskListItem}}): Promise<void>;
        }).onDrop({container: {data: '2026-05-15'}, item: {data: task}});

        expect(taskService.getTask).toHaveBeenCalledWith(1);
        const payload = taskService.updateTask.mock.calls.at(-1)?.[1];
        expect(payload.dueDate).toBe('2026-05-15');
        expect(payload.priorityId).toBe(7);
        expect(payload.assigneeId).toBe(3);
        expect(payload.tagIds).toEqual([4]);
        // The existing start date must be preserved, not silently cleared.
        expect(payload.startDate).toBe('2026-05-08');
        expect(dropped).toHaveBeenCalled();
    });
});
