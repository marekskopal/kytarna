import {CdkDrag, CdkDragDrop, CdkDropList, CdkDropListGroup, moveItemInArray, transferArrayItem} from '@angular/cdk/drag-drop';
import {ChangeDetectionStrategy, Component, computed, DestroyRef, inject, OnInit, signal} from '@angular/core';
import {takeUntilDestroyed} from '@angular/core/rxjs-interop';
import {ActivatedRoute, Router, RouterLink} from '@angular/router';
import {TaskCardComponent} from '@app/board/task-card.component';
import {TaskDetailDrawerComponent} from '@app/board/task-detail-drawer.component';
import {Board} from '@app/models/board';
import {ProjectField} from '@app/models/field';
import {Priority} from '@app/models/priority';
import {RealtimeEvent, TASK_EVENT_TYPES} from '@app/models/realtime-event';
import {Status} from '@app/models/status';
import {Tag} from '@app/models/tag';
import {Task, TaskListItem} from '@app/models/task';
import {BoardService} from '@app/services/board.service';
import {CurrentUserService} from '@app/services/current-user.service';
import {FieldService} from '@app/services/field.service';
import {PriorityService} from '@app/services/priority.service';
import {RealtimeService} from '@app/services/realtime.service';
import {TagService} from '@app/services/tag.service';
import {TaskService} from '@app/services/task.service';
import {WorkspaceService} from '@app/services/workspace.service';
import {TranslatePipe} from '@ngx-translate/core';

interface Column {
    status: Status;
    tasks: Task[];
}

@Component({
    selector: 'uk-board',
    standalone: true,
    imports: [CdkDropListGroup, CdkDropList, CdkDrag, RouterLink, TaskCardComponent, TaskDetailDrawerComponent, TranslatePipe],
    changeDetection: ChangeDetectionStrategy.OnPush,
    templateUrl: './board.component.html',
    styleUrl: './board.component.scss',
})
export class BoardComponent implements OnInit {
    private readonly route = inject(ActivatedRoute);
    private readonly router = inject(Router);
    private readonly boardService = inject(BoardService);
    private readonly taskService = inject(TaskService);
    private readonly fieldService = inject(FieldService);
    private readonly tagService = inject(TagService);
    private readonly priorityService = inject(PriorityService);
    private readonly workspaceService = inject(WorkspaceService);
    private readonly currentUserService = inject(CurrentUserService);
    private readonly realtimeService = inject(RealtimeService);
    private readonly destroyRef = inject(DestroyRef);

    private refreshTimer: ReturnType<typeof setTimeout> | null = null;

    protected readonly loading = signal(true);
    protected readonly board = signal<Board | null>(null);
    protected readonly projectId = signal<number | null>(null);
    protected readonly projectFields = signal<ProjectField[]>([]);
    protected readonly workspaceTags = signal<Tag[]>([]);
    protected readonly workspacePriorities = signal<Priority[]>([]);
    protected readonly members = this.workspaceService.currentMembers;

    protected readonly drawerOpen = signal(false);
    protected readonly editingTask = signal<Task | null>(null);
    protected readonly defaultStatusId = signal<number | null>(null);

    protected readonly columns = computed<Column[]>(() => {
        const board = this.board();
        if (!board) {
            return [];
        }
        return [...board.statuses]
            .sort((a, b) => a.position - b.position)
            .map((status) => ({
                status,
                tasks: board.tasks
                    .filter((t) => t.statusId === status.id)
                    .sort((a, b) => a.priority.position - b.priority.position || a.position - b.position),
            }));
    });

    public async ngOnInit(): Promise<void> {
        const id = Number(this.route.snapshot.paramMap.get('id'));
        this.projectId.set(id);
        await Promise.all([
            this.loadBoard(),
            this.loadProjectFields(),
            this.loadWorkspaceTags(),
            this.loadWorkspacePriorities(),
            this.workspaceService.currentMembers().length === 0
                ? this.workspaceService.loadCurrentMembers()
                : Promise.resolve(),
        ]);

        this.realtimeService.events$
            .pipe(takeUntilDestroyed(this.destroyRef))
            .subscribe((event) => this.onRealtimeEvent(event));

        const openTaskParam = this.route.snapshot.queryParamMap.get('openTask');
        if (openTaskParam !== null) {
            const openId = Number(openTaskParam);
            if (Number.isFinite(openId) && openId > 0) {
                try {
                    const task = await this.taskService.getTask(openId);
                    this.editingTask.set(task);
                    this.defaultStatusId.set(null);
                    this.drawerOpen.set(true);
                } catch {
                    // task may have been deleted; ignore
                }
            }
        }
    }

    private async loadBoard(): Promise<void> {
        this.loading.set(true);
        try {
            this.board.set(await this.boardService.getBoard(this.projectId()!));
        } finally {
            this.loading.set(false);
        }
    }

    private async loadProjectFields(): Promise<void> {
        const id = this.projectId();
        if (id === null) return;
        try {
            this.projectFields.set(await this.fieldService.listProjectFields(id));
        } catch {
            this.projectFields.set([]);
        }
    }

    private async loadWorkspaceTags(): Promise<void> {
        let workspaceId = this.workspaceService.currentWorkspaceId();
        if (workspaceId === null) {
            try {
                workspaceId = (await this.currentUserService.load()).currentWorkspaceId;
            } catch {
                workspaceId = null;
            }
        }
        if (workspaceId === null) {
            this.workspaceTags.set([]);
            return;
        }
        try {
            this.workspaceTags.set(await this.tagService.loadWorkspaceTags(workspaceId));
        } catch {
            this.workspaceTags.set([]);
        }
    }

    private async loadWorkspacePriorities(): Promise<void> {
        const workspaceId = this.workspaceService.currentWorkspaceId();
        if (workspaceId === null) {
            this.workspacePriorities.set([]);
            return;
        }
        try {
            this.workspacePriorities.set(await this.priorityService.loadWorkspacePriorities(workspaceId));
        } catch {
            this.workspacePriorities.set([]);
        }
    }

    protected async onDrop(event: CdkDragDrop<Task[]>, targetStatus: Status): Promise<void> {
        const previousArr = event.previousContainer.data;
        const currentArr = event.container.data;
        let movedTask: Task;

        if (event.previousContainer === event.container) {
            if (event.previousIndex === event.currentIndex) {
                return;
            }
            movedTask = currentArr[event.previousIndex];
            moveItemInArray(currentArr, event.previousIndex, event.currentIndex);
        } else {
            movedTask = previousArr[event.previousIndex];
            transferArrayItem(previousArr, currentArr, event.previousIndex, event.currentIndex);
        }

        currentArr.forEach((t, i) => { t.position = i; t.statusId = targetStatus.id; });
        previousArr.forEach((t, i) => { t.position = i; });

        this.board.update((b) => b ? {...b, tasks: [...b.tasks]} : b);

        try {
            await this.taskService.moveTask(movedTask.id, targetStatus.id, event.currentIndex);
        } catch {
            await this.loadBoard();
        }
    }

    protected openCreate(status: Status): void {
        this.editingTask.set(null);
        this.defaultStatusId.set(status.id);
        this.drawerOpen.set(true);
    }

    protected openEdit(task: Task): void {
        this.editingTask.set(task);
        this.defaultStatusId.set(null);
        this.drawerOpen.set(true);
    }

    protected closeDrawer(): void {
        this.drawerOpen.set(false);
        this.editingTask.set(null);
    }

    protected onTaskSaved(_task: Task): void {
        this.closeDrawer();
        void this.loadBoard();
    }

    protected onTaskDeleted(_id: number): void {
        this.closeDrawer();
        void this.loadBoard();
    }

    private onRealtimeEvent(event: RealtimeEvent): void {
        const projectId = this.projectId();
        if (projectId === null) {
            return;
        }
        if (event.type === 'RealtimeReconnected') {
            this.scheduleBoardRefresh();
            return;
        }
        if (TASK_EVENT_TYPES.has(event.type) && event.projectId === projectId) {
            this.scheduleBoardRefresh();
        }
    }

    private scheduleBoardRefresh(): void {
        if (this.refreshTimer !== null) {
            clearTimeout(this.refreshTimer);
        }
        this.refreshTimer = setTimeout(() => {
            this.refreshTimer = null;
            void this.loadBoard();
        }, 150);
    }

    protected async onOpenRelatedTask(item: TaskListItem): Promise<void> {
        const currentProjectId = this.projectId();
        if (item.projectId !== currentProjectId) {
            this.closeDrawer();
            await this.router.navigate(['/projects', item.projectId, 'board'], {queryParams: {openTask: item.id}});
            return;
        }
        try {
            const task = await this.taskService.getTask(item.id);
            this.drawerOpen.set(false);
            this.editingTask.set(task);
            this.defaultStatusId.set(null);
            queueMicrotask(() => this.drawerOpen.set(true));
        } catch {
            // error interceptor
        }
    }
}
