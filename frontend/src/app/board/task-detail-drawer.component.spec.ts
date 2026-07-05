import {provideZonelessChangeDetection} from '@angular/core';
import {ComponentFixture, TestBed} from '@angular/core/testing';
import {Priority} from '@app/models/priority';
import {Status} from '@app/models/status';
import {Subtask} from '@app/models/subtask';
import {Task} from '@app/models/task';
import {TaskComment} from '@app/models/task-comment';
import {AlertService} from '@app/services/alert.service';
import {CurrentUserService} from '@app/services/current-user.service';
import {FieldService} from '@app/services/field.service';
import {RealtimeService} from '@app/services/realtime.service';
import {TaskService} from '@app/services/task.service';
import {TaskCommentService} from '@app/services/task-comment.service';
import {TaskRelationService} from '@app/services/task-relation.service';
import {TaskTemplateService} from '@app/services/task-template.service';
import {TranslateService} from '@ngx-translate/core';
import {Subject} from 'rxjs';
import {afterEach, beforeEach, describe, expect, it, vi} from 'vitest';

import {TaskDetailDrawerComponent} from './task-detail-drawer.component';

interface DrawerInternals {
    onSubmit: () => Promise<void>;
    onDelete: () => Promise<void>;
    onCancel: () => void;
    onDuplicate: () => Promise<void>;
    onSaveAsTemplate: () => Promise<void>;
    onAddSubtask: () => Promise<void>;
    onToggleSubtask: (subtask: Subtask, event: Event) => Promise<void>;
    subtaskNameControl: {setValue: (v: string) => void};
    subtasks: {(): Subtask[]; set: (v: Subtask[]) => void};
    comments: {(): TaskComment[]; set: (v: TaskComment[]) => void};
    commentThreads: () => {root: TaskComment; replies: TaskComment[]}[];
    renderCommentBody: (body: string) => string;
}

function makeComment(overrides: Partial<TaskComment> = {}): TaskComment {
    return {
        id: 1,
        taskId: 1,
        authorId: 1,
        authorName: 'Ada',
        body: 'hello',
        createdByAgent: false,
        mcpClientId: null,
        mcpClientName: null,
        parentCommentId: null,
        edited: false,
        createdAt: '2026-06-21T10:00:00+00:00',
        ...overrides,
    };
}

function internals(component: TaskDetailDrawerComponent): DrawerInternals {
    return component as unknown as DrawerInternals;
}

interface ServiceStubs {
    taskService: {
        updateTask: ReturnType<typeof vi.fn>;
        createTask: ReturnType<typeof vi.fn>;
        deleteTask: ReturnType<typeof vi.fn>;
        duplicateTask: ReturnType<typeof vi.fn>;
        moveTask: ReturnType<typeof vi.fn>;
        listSubtasks: ReturnType<typeof vi.fn>;
        createSubtask: ReturnType<typeof vi.fn>;
        listTaskFiles: ReturnType<typeof vi.fn>;
        getTasks: ReturnType<typeof vi.fn>;
        getTask: ReturnType<typeof vi.fn>;
    };
    taskTemplateService: {
        loadWorkspaceTemplates: ReturnType<typeof vi.fn>;
        saveFromTask: ReturnType<typeof vi.fn>;
    };
}

const STATUS_TODO: Status = {id: 10, workflowId: 1, name: 'To Do', color: '#888', position: 1, type: 'Start'};
const STATUS_DOING: Status = {id: 11, workflowId: 1, name: 'In Progress', color: '#369', position: 2, type: 'Normal'};

const PRIORITY_HIGH: Priority = {
    id: 1,
    workspaceId: 1,
    name: 'High',
    color: '#fdecea',
    position: 0,
    isDefault: false,
    createdAt: '2026-01-01T00:00:00Z',
    updatedAt: '2026-01-01T00:00:00Z',
};
const PRIORITY_MEDIUM: Priority = {
    id: 2,
    workspaceId: 1,
    name: 'Medium',
    color: '#fbf2dd',
    position: 1,
    isDefault: true,
    createdAt: '2026-01-01T00:00:00Z',
    updatedAt: '2026-01-01T00:00:00Z',
};

function makeTask(overrides: Partial<Task> = {}): Task {
    return {
        id: 42,
        code: 'U-42',
        projectId: 1,
        statusId: STATUS_TODO.id,
        name: 'Existing task',
        description: 'A description',
        priority: PRIORITY_MEDIUM,
        dueDate: null,
        startDate: null,
        position: 1,
        sequenceNumber: 42,
        createdByAgent: false,
        createdAt: '2026-01-01T00:00:00Z',
        updatedAt: '2026-01-01T00:00:00Z',
        fieldValues: [],
        tagIds: [],
        ...overrides,
    };
}


function makeSubtask(overrides: Partial<Subtask> = {}): Subtask {
    return {
        taskId: 100,
        relationId: 1,
        code: 'U-100',
        name: 'Subtask',
        projectId: 1,
        statusId: 10,
        statusName: 'To Do',
        statusColor: '#888',
        statusType: 'Start',
        priorityId: 2,
        priorityName: 'Medium',
        priorityPosition: 1,
        dueDate: null,
        assigneeId: null,
        startStatusId: 10,
        finishStatusId: 12,
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
            duplicateTask: vi.fn(),
            moveTask: vi.fn().mockResolvedValue({}),
            listSubtasks: vi.fn().mockResolvedValue([]),
            createSubtask: vi.fn(),
            listTaskFiles: vi.fn().mockResolvedValue([]),
            getTasks: vi.fn().mockResolvedValue({tasks: [], count: 0}),
            getTask: vi.fn().mockResolvedValue(null),
        },
        taskTemplateService: {
            loadWorkspaceTemplates: vi.fn().mockResolvedValue([]),
            saveFromTask: vi.fn().mockResolvedValue({}),
        },
    };

    TestBed.configureTestingModule({
        providers: [
            provideZonelessChangeDetection(),
            {provide: TaskService, useValue: stubs.taskService},
            {provide: TaskTemplateService, useValue: stubs.taskTemplateService},
            {provide: FieldService, useValue: {sortVersionsDescending: (xs: string[]): string[] => xs}},
            {provide: TaskRelationService, useValue: {
                list: vi.fn().mockResolvedValue({outgoing: [], incoming: []}),
                create: vi.fn().mockResolvedValue({}),
                delete: vi.fn().mockResolvedValue(undefined),
            }},
            {provide: TaskCommentService, useValue: {
                list: vi.fn().mockResolvedValue([]),
                create: vi.fn().mockResolvedValue({}),
                delete: vi.fn().mockResolvedValue(undefined),
            }},
            {provide: CurrentUserService, useValue: {currentUser: vi.fn(() => null)}},
            {provide: AlertService, useValue: {
                success: vi.fn(),
                error: vi.fn(),
                info: vi.fn(),
            }},
            {provide: TranslateService, useValue: {
                instant: vi.fn((key: string) => key),
                get: vi.fn((key: string) => key),
            }},
            {provide: RealtimeService, useValue: {events$: new Subject()}},
        ],
    });

    const fixture = TestBed.createComponent(TaskDetailDrawerComponent);
    fixture.componentRef.setInput('task', options.task);
    fixture.componentRef.setInput('statuses', [STATUS_TODO, STATUS_DOING]);
    fixture.componentRef.setInput('workspacePriorities', [PRIORITY_HIGH, PRIORITY_MEDIUM]);
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
            priorityId: original.priority.id,
        }));
        expect(saved).toEqual([updated]);
    });

    it('onSubmit sends startDate in the payload', async () => {
        const original = makeTask();
        const {component, stubs} = createFixture({task: original});
        stubs.taskService.updateTask.mockResolvedValue(original);
        component.form.patchValue({startDate: '2026-05-10', dueDate: '2026-05-20'});

        await internals(component).onSubmit();

        expect(stubs.taskService.updateTask).toHaveBeenCalledWith(original.id, expect.objectContaining({
            startDate: '2026-05-10',
            dueDate: '2026-05-20',
        }));
    });

    it('onSubmit blocks the save when start date is after due date', async () => {
        const original = makeTask();
        const {component, stubs} = createFixture({task: original});
        component.form.patchValue({startDate: '2026-05-25', dueDate: '2026-05-20'});

        await internals(component).onSubmit();

        expect(stubs.taskService.updateTask).not.toHaveBeenCalled();
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

    it('onDuplicate calls duplicateTask and emits saved with the copy', async () => {
        const original = makeTask();
        const copy = makeTask({id: 43, name: 'Existing task (copy)'});
        const {component, stubs} = createFixture({task: original});
        stubs.taskService.duplicateTask.mockResolvedValue(copy);

        const saved: Task[] = [];
        component.saved.subscribe((task) => saved.push(task));

        await internals(component).onDuplicate();

        expect(stubs.taskService.duplicateTask).toHaveBeenCalledWith(original.id);
        expect(saved).toEqual([copy]);
    });

    it('onSaveAsTemplate prompts for a name and saves the template', async () => {
        vi.spyOn(window, 'prompt').mockReturnValue('My template');
        const original = makeTask();
        const {component, stubs} = createFixture({task: original});

        await internals(component).onSaveAsTemplate();

        expect(stubs.taskTemplateService.saveFromTask).toHaveBeenCalledWith(original.id, 'My template');
    });

    it('onSaveAsTemplate does nothing when the prompt is cancelled', async () => {
        vi.spyOn(window, 'prompt').mockReturnValue(null);
        const {component, stubs} = createFixture({task: makeTask()});

        await internals(component).onSaveAsTemplate();

        expect(stubs.taskTemplateService.saveFromTask).not.toHaveBeenCalled();
    });

    it('onAddSubtask creates the subtask and appends it to the list', async () => {
        const parent = makeTask();
        const {component, stubs} = createFixture({task: parent});
        const created = makeSubtask({taskId: 101, name: 'Child'});
        stubs.taskService.createSubtask.mockResolvedValue(created);

        internals(component).subtaskNameControl.setValue('Child');
        await internals(component).onAddSubtask();

        expect(stubs.taskService.createSubtask).toHaveBeenCalledWith(parent.id, 'Child');
        expect(internals(component).subtasks()).toEqual([created]);
    });

    it('onToggleSubtask moves the child to its finish status when checked', async () => {
        const {component, stubs} = createFixture({task: makeTask()});
        const subtask = makeSubtask({taskId: 7, startStatusId: 10, finishStatusId: 12});
        stubs.taskService.listSubtasks.mockResolvedValue([{...subtask, statusType: 'Finish'}]);

        const event = {target: {checked: true}} as unknown as Event;
        await internals(component).onToggleSubtask(subtask, event);

        expect(stubs.taskService.moveTask).toHaveBeenCalledWith(7, 12, 0);
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

    it('commentThreads groups replies under their top-level comment', () => {
        const {component} = createFixture({task: makeTask()});
        internals(component).comments.set([
            makeComment({id: 1, body: 'root'}),
            makeComment({id: 2, body: 'reply', parentCommentId: 1}),
            makeComment({id: 3, body: 'other root'}),
        ]);

        const threads = internals(component).commentThreads();

        expect(threads.map((t) => t.root.id)).toEqual([1, 3]);
        expect(threads[0].replies.map((r) => r.id)).toEqual([2]);
        expect(threads[1].replies).toEqual([]);
    });

    it('renderCommentBody turns mention tokens into a styled span and escapes the name', () => {
        const {component} = createFixture({task: makeTask()});

        expect(internals(component).renderCommentBody('hi @[Ada Lovelace](user:7)!'))
            .toBe('hi <span class="mention">@Ada Lovelace</span>!');
        expect(internals(component).renderCommentBody('@[<b>x</b>](user:1)'))
            .toBe('<span class="mention">@&lt;b&gt;x&lt;/b&gt;</span>');
    });
});
