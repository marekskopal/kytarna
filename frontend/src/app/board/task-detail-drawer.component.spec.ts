import {provideZonelessChangeDetection} from '@angular/core';
import {ComponentFixture, TestBed} from '@angular/core/testing';
import {Status} from '@app/models/status';
import {Task} from '@app/models/task';
import {AlertService} from '@app/services/alert.service';
import {TaskService} from '@app/services/task.service';
import {TaskWatcherService} from '@app/services/task-watcher.service';
import {TranslateService} from '@ngx-translate/core';
import {afterEach, beforeEach, describe, expect, it, vi} from 'vitest';

import {TaskDetailDrawerComponent} from './task-detail-drawer.component';

interface DrawerInternals {
    onSubmit: () => Promise<void>;
    onDelete: () => Promise<void>;
    onCancel: () => void;
    onArchive: () => Promise<void>;
}

function internals(component: TaskDetailDrawerComponent): DrawerInternals {
    return component as unknown as DrawerInternals;
}

interface ServiceStubs {
    taskService: {
        updateTask: ReturnType<typeof vi.fn>;
        createTask: ReturnType<typeof vi.fn>;
        deleteTask: ReturnType<typeof vi.fn>;
        archiveTask: ReturnType<typeof vi.fn>;
        unarchiveTask: ReturnType<typeof vi.fn>;
        listTaskFiles: ReturnType<typeof vi.fn>;
    };
}

const STATUS_TODO: Status = {id: 10, workflowId: 1, name: 'To Do', color: '#888', position: 1, type: 'Start'};
const STATUS_DOING: Status = {id: 11, workflowId: 1, name: 'In Progress', color: '#369', position: 2, type: 'Normal'};

function makeTask(overrides: Partial<Task> = {}): Task {
    return {
        id: 42,
        code: 'U-42',
        projectId: 1,
        statusId: STATUS_TODO.id,
        name: 'Existing task',
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

function createFixture(options: {task: Task | null}): {
    fixture: ComponentFixture<TaskDetailDrawerComponent>;
    component: TaskDetailDrawerComponent;
    stubs: ServiceStubs;
} {
    const stubs: ServiceStubs = {
        taskService: {
            updateTask: vi.fn(),
            createTask: vi.fn(),
            deleteTask: vi.fn().mockResolvedValue(undefined),
            archiveTask: vi.fn(),
            unarchiveTask: vi.fn(),
            listTaskFiles: vi.fn().mockResolvedValue([]),
        },
    };

    TestBed.configureTestingModule({
        providers: [
            provideZonelessChangeDetection(),
            {provide: TaskService, useValue: stubs.taskService},
            {provide: TaskWatcherService, useValue: {
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

    const fixture = TestBed.createComponent(TaskDetailDrawerComponent);
    fixture.componentRef.setInput('task', options.task);
    fixture.componentRef.setInput('statuses', [STATUS_TODO, STATUS_DOING]);
    fixture.componentRef.setInput('projectId', 1);
    fixture.componentInstance.ngOnInit();
    return {fixture, component: fixture.componentInstance, stubs};
}

describe('TaskDetailDrawerComponent', () => {
    beforeEach(() => {
        TestBed.resetTestingModule();
    });

    afterEach(() => {
        vi.restoreAllMocks();
    });

    it('onCancel emits the cancelled output', () => {
        const {component} = createFixture({task: makeTask()});
        const cancelled = vi.fn();
        component.cancelled.subscribe(() => cancelled());

        internals(component).onCancel();

        expect(cancelled).toHaveBeenCalledTimes(1);
    });

    it('onSubmit on an existing task calls updateTask and emits saved with the result', async () => {
        const original = makeTask();
        const updated = {...original, name: 'Existing task (edited)'};
        const {component, stubs} = createFixture({task: original});
        stubs.taskService.updateTask.mockResolvedValue(updated);

        const saved: Task[] = [];
        component.saved.subscribe((task) => saved.push(task));

        await internals(component).onSubmit();

        expect(stubs.taskService.updateTask).toHaveBeenCalledTimes(1);
        expect(stubs.taskService.updateTask).toHaveBeenCalledWith(original.id, expect.objectContaining({
            name: original.name,
            statusId: original.statusId,
        }));
        expect(saved).toEqual([updated]);
    });

    it('onSubmit on a new task calls createTask and emits saved with the result', async () => {
        const created = makeTask({id: 99, name: 'Brand new'});
        const {component, stubs} = createFixture({task: null});
        stubs.taskService.createTask.mockResolvedValue(created);
        component.form.patchValue({name: 'Brand new'});

        const saved: Task[] = [];
        component.saved.subscribe((task) => saved.push(task));

        await internals(component).onSubmit();

        expect(stubs.taskService.createTask).toHaveBeenCalledTimes(1);
        expect(stubs.taskService.updateTask).not.toHaveBeenCalled();
        expect(saved).toEqual([created]);
    });

    it('onDelete asks for confirmation, deletes, and emits the deleted task id', async () => {
        const confirmSpy = vi.spyOn(window, 'confirm').mockReturnValue(true);
        const original = makeTask({id: 77});
        const {component, stubs} = createFixture({task: original});

        const deleted: number[] = [];
        component.deleted.subscribe((id) => deleted.push(id));

        await internals(component).onDelete();

        expect(confirmSpy).toHaveBeenCalledTimes(1);
        expect(stubs.taskService.deleteTask).toHaveBeenCalledWith(77);
        expect(deleted).toEqual([77]);
    });

    it('onDelete does not emit when the confirmation dialog is cancelled', async () => {
        vi.spyOn(window, 'confirm').mockReturnValue(false);
        const {component, stubs} = createFixture({task: makeTask()});

        const deleted: number[] = [];
        component.deleted.subscribe((id) => deleted.push(id));

        await internals(component).onDelete();

        expect(stubs.taskService.deleteTask).not.toHaveBeenCalled();
        expect(deleted).toEqual([]);
    });

    it('onArchive archives the task and emits saved with the result', async () => {
        const original = makeTask();
        const archived = {...original, archivedAt: '2026-02-01T00:00:00Z'};
        const {component, stubs} = createFixture({task: original});
        stubs.taskService.archiveTask.mockResolvedValue(archived);

        const saved: Task[] = [];
        component.saved.subscribe((task) => saved.push(task));

        await internals(component).onArchive();

        expect(stubs.taskService.archiveTask).toHaveBeenCalledWith(original.id);
        expect(saved).toEqual([archived]);
    });
});
