import {CdkDrag, CdkDragDrop, CdkDragHandle, CdkDropList, moveItemInArray} from '@angular/cdk/drag-drop';
import {DatePipe, NgTemplateOutlet} from '@angular/common';
import {ChangeDetectionStrategy, Component, computed, ElementRef, inject, input, OnInit, output, signal, viewChild} from '@angular/core';
import {takeUntilDestroyed, toSignal} from '@angular/core/rxjs-interop';
import {FormBuilder, FormControl, FormGroup, ReactiveFormsModule, Validators} from '@angular/forms';
import {ChecklistItem} from '@app/models/checklist-item';
import {ProjectField} from '@app/models/field';
import {Priority} from '@app/models/priority';
import {COMMENT_EVENT_TYPES, FILE_EVENT_TYPES, RealtimeEvent, RELATION_EVENT_TYPES} from '@app/models/realtime-event';
import {Status} from '@app/models/status';
import {Subtask} from '@app/models/subtask';
import {Tag} from '@app/models/tag';
import {Task, TaskListItem} from '@app/models/task';
import {TaskComment} from '@app/models/task-comment';
import {TaskFile} from '@app/models/task-file';
import {RecurrenceCadence, RecurrenceEndType, RecurrenceWritePayload, TaskRecurrence} from '@app/models/task-recurrence';
import {TaskRelation, TaskRelationType} from '@app/models/task-relation';
import {TaskTemplate} from '@app/models/task-template';
import {TaskWatcher} from '@app/models/task-watcher';
import {WorkspaceMember} from '@app/models/workspace';
import {AlertService} from '@app/services/alert.service';
import {CurrentUserService} from '@app/services/current-user.service';
import {FieldService} from '@app/services/field.service';
import {RealtimeService} from '@app/services/realtime.service';
import {TaskService} from '@app/services/task.service';
import {TaskChecklistService} from '@app/services/task-checklist.service';
import {TaskCommentService} from '@app/services/task-comment.service';
import {TaskRecurrenceService} from '@app/services/task-recurrence.service';
import {TaskRelationService} from '@app/services/task-relation.service';
import {TaskTemplateService} from '@app/services/task-template.service';
import {TaskWatcherService} from '@app/services/task-watcher.service';
import {WorkspaceService} from '@app/services/workspace.service';
import {pickReadableForeground} from '@app/shared/color-contrast';
import {MarkdownEditorComponent} from '@app/shared/components/markdown-editor/markdown-editor.component';
import {TranslatePipe, TranslateService} from '@ngx-translate/core';
import {MarkdownComponent} from 'ngx-markdown';
import {debounceTime, distinctUntilChanged} from 'rxjs';

interface CustomControlDescriptor {
    controlName: string;
    pf: ProjectField;
    options: string[];
}

interface RelationGroup {
    key: string;
    headerKey: string;
    items: TaskRelation[];
}

interface FileTypeChip {
    tag: string;
    bg: string;
    fg: string;
}

const RELATION_TYPES: TaskRelationType[] = ['Related', 'Duplicates', 'Parent', 'DependsOn'];

const FILE_TYPE_MAP: Record<string, FileTypeChip> = {
    pdf: {tag: 'PDF', fg: '#b42318', bg: '#fdecea'},
    doc: {tag: 'DOC', fg: '#1e58b6', bg: '#e6efff'},
    docx: {tag: 'DOC', fg: '#1e58b6', bg: '#e6efff'},
    xls: {tag: 'XLS', fg: '#16794a', bg: '#e6f5ee'},
    xlsx: {tag: 'XLS', fg: '#16794a', bg: '#e6f5ee'},
    csv: {tag: 'CSV', fg: '#16794a', bg: '#e6f5ee'},
    png: {tag: 'IMG', fg: '#6f4ed3', bg: '#f0ebfb'},
    jpg: {tag: 'IMG', fg: '#6f4ed3', bg: '#f0ebfb'},
    jpeg: {tag: 'IMG', fg: '#6f4ed3', bg: '#f0ebfb'},
    svg: {tag: 'IMG', fg: '#6f4ed3', bg: '#f0ebfb'},
    gif: {tag: 'IMG', fg: '#6f4ed3', bg: '#f0ebfb'},
    md: {tag: 'MD', fg: '#18181b', bg: '#f4f4f5'},
    txt: {tag: 'TXT', fg: '#52525b', bg: '#f4f4f5'},
    log: {tag: 'LOG', fg: '#52525b', bg: '#f4f4f5'},
    json: {tag: 'JSON', fg: '#a35c00', bg: '#fbf2dd'},
    yaml: {tag: 'YML', fg: '#a35c00', bg: '#fbf2dd'},
    yml: {tag: 'YML', fg: '#a35c00', bg: '#fbf2dd'},
    sql: {tag: 'SQL', fg: '#0e7490', bg: '#e0f2fe'},
    zip: {tag: 'ZIP', fg: '#52525b', bg: '#ebebed'},
    mp4: {tag: 'MP4', fg: '#be185d', bg: '#fce7f3'},
    mov: {tag: 'MOV', fg: '#be185d', bg: '#fce7f3'},
};

const FILE_TYPE_FALLBACK: FileTypeChip = {tag: 'FILE', fg: '#52525b', bg: '#f4f4f5'};

@Component({
    selector: 'uk-task-detail-drawer',
    standalone: true,
    imports: [
        NgTemplateOutlet,
        ReactiveFormsModule,
        MarkdownComponent,
        MarkdownEditorComponent,
        TranslatePipe,
        DatePipe,
        CdkDropList,
        CdkDrag,
        CdkDragHandle,
    ],
    changeDetection: ChangeDetectionStrategy.OnPush,
    templateUrl: './task-detail-drawer.component.html',
    styleUrl: './task-detail-drawer.component.scss',
})
export class TaskDetailDrawerComponent implements OnInit {
    public readonly task = input<Task | null>(null);
    public readonly statuses = input.required<Status[]>();
    public readonly projectId = input.required<number>();
    public readonly defaultStatusId = input<number | null>(null);
    public readonly projectFields = input<ProjectField[]>([]);
    public readonly workspaceTags = input<Tag[]>([]);
    public readonly workspacePriorities = input<Priority[]>([]);

    public readonly saved = output<Task>();
    public readonly deleted = output<number>();
    public readonly cancelled = output<void>();
    public readonly openTask = output<TaskListItem>();

    private readonly fb = inject(FormBuilder);
    private readonly taskService = inject(TaskService);
    private readonly fieldService = inject(FieldService);
    private readonly taskRelationService = inject(TaskRelationService);
    private readonly taskCommentService = inject(TaskCommentService);
    private readonly taskChecklistService = inject(TaskChecklistService);
    private readonly taskRecurrenceService = inject(TaskRecurrenceService);
    private readonly taskTemplateService = inject(TaskTemplateService);
    private readonly taskWatcherService = inject(TaskWatcherService);
    private readonly currentUserService = inject(CurrentUserService);
    private readonly alertService = inject(AlertService);
    private readonly translate = inject(TranslateService);
    private readonly realtimeService = inject(RealtimeService);
    private readonly workspaceService = inject(WorkspaceService);

    protected readonly saving = signal(false);
    protected readonly duplicating = signal(false);
    protected readonly archiving = signal(false);
    protected readonly isArchived = computed<boolean>(() => this.task()?.archivedAt != null);
    protected readonly templates = signal<TaskTemplate[]>([]);
    protected readonly descriptionInitialTab = computed<'edit' | 'preview'>(() =>
        this.task() === null ? 'edit' : 'preview',
    );

    protected readonly selectedTagIds = signal<number[]>([]);
    protected readonly tagPickerOpen = signal(false);

    protected readonly selectedTags = computed<Tag[]>(() => {
        const ids = new Set(this.selectedTagIds());
        return this.workspaceTags().filter((t) => ids.has(t.id));
    });

    protected readonly availableTags = computed<Tag[]>(() => {
        const ids = new Set(this.selectedTagIds());
        return this.workspaceTags().filter((t) => !ids.has(t.id));
    });

    protected readonly selectedAssigneeId = signal<number | null>(null);
    protected readonly assigneePickerOpen = signal(false);

    protected readonly sortedMembers = computed<WorkspaceMember[]>(() => {
        const me = this.currentUserService.currentUser()?.id;
        return [...this.workspaceService.currentMembers()].sort((a, b) => {
            if (a.userId === me && b.userId !== me) return -1;
            if (b.userId === me && a.userId !== me) return 1;
            return a.name.localeCompare(b.name);
        });
    });

    protected readonly selectedAssignee = computed<WorkspaceMember | null>(() => {
        const id = this.selectedAssigneeId();
        if (id === null) return null;
        return this.workspaceService.currentMembers().find((m) => m.userId === id) ?? null;
    });

    protected readonly files = signal<TaskFile[]>([]);
    protected readonly uploading = signal(false);

    protected readonly outgoingRelations = signal<TaskRelation[]>([]);
    protected readonly incomingRelations = signal<TaskRelation[]>([]);
    protected readonly relationsLoaded = signal(false);
    protected readonly addRelationOpen = signal(false);
    protected readonly addRelationType = signal<TaskRelationType>('Related');
    protected readonly addRelationSaving = signal(false);
    protected readonly relationTypes = RELATION_TYPES;

    protected readonly subtasks = signal<Subtask[]>([]);
    protected readonly subtasksLoaded = signal(false);
    protected readonly addingSubtask = signal(false);
    protected readonly subtaskNameControl = new FormControl<string>('', {nonNullable: true});

    protected readonly subtasksDone = computed<number>(() =>
        this.subtasks().filter((s) => s.statusType === 'Finish').length,
    );

    protected readonly subtaskProgressPercent = computed<number>(() => {
        const total = this.subtasks().length;
        return total === 0 ? 0 : Math.round((this.subtasksDone() / total) * 100);
    });

    // Read-only rollup: earliest due date and highest priority (lowest position) across children.
    protected readonly subtaskEarliestDue = computed<string | null>(() => {
        const dues = this.subtasks().map((s) => s.dueDate).filter((d): d is string => d !== null).sort();
        return dues[0] ?? null;
    });

    protected readonly subtaskHighestPriority = computed<string | null>(() => {
        const subtasks = this.subtasks();
        if (subtasks.length === 0) return null;
        const highest = [...subtasks].sort((a, b) => a.priorityPosition - b.priorityPosition)[0];
        return highest.priorityName;
    });

    protected readonly checklist = signal<ChecklistItem[]>([]);
    protected readonly checklistLoaded = signal(false);
    protected readonly addingChecklistItem = signal(false);
    protected readonly checklistTextControl = new FormControl<string>('', {nonNullable: true});

    protected readonly checklistDone = computed<number>(() => this.checklist().filter((i) => i.checked).length);

    protected readonly checklistProgressPercent = computed<number>(() => {
        const total = this.checklist().length;
        return total === 0 ? 0 : Math.round((this.checklistDone() / total) * 100);
    });

    protected readonly recurrence = signal<TaskRecurrence | null>(null);
    protected readonly recurrenceLoaded = signal(false);
    protected readonly recurrenceEditing = signal(false);
    protected readonly savingRecurrence = signal(false);
    protected readonly weekdays = [0, 1, 2, 3, 4, 5, 6];

    protected readonly recurrenceForm = this.fb.nonNullable.group({
        cadence: 'Weekly' as RecurrenceCadence,
        interval: 1,
        weekday: 1,
        dayOfMonth: 1,
        cronExpression: '',
        endType: 'Never' as RecurrenceEndType,
        endDate: '',
        maxOccurrences: 10,
    });

    // Mirror the cadence/end-type controls into signals so per-mode field visibility re-renders
    // under zoneless change detection (reactive-form values are not signals).
    protected readonly recurrenceCadence = signal<RecurrenceCadence>('Weekly');
    protected readonly recurrenceEndType = signal<RecurrenceEndType>('Never');

    protected readonly recurrenceActive = computed<boolean>(() => this.recurrence()?.active === true);

    protected readonly watching = signal(false);
    protected readonly watchers = signal<TaskWatcher[]>([]);
    protected readonly watchToggling = signal(false);

    protected readonly comments = signal<TaskComment[]>([]);
    protected readonly commentsLoaded = signal(false);
    protected readonly commentForm = this.fb.nonNullable.group({
        body: ['', [Validators.required, Validators.minLength(1)]],
    });
    protected readonly postingComment = signal(false);

    // Threading is clamped to one level: every reply points at a top-level comment.
    protected readonly commentThreads = computed<{root: TaskComment; replies: TaskComment[]}[]>(() => {
        const all = this.comments();
        const repliesByParent = new Map<number, TaskComment[]>();
        for (const c of all) {
            if (c.parentCommentId !== null) {
                const bucket = repliesByParent.get(c.parentCommentId) ?? [];
                bucket.push(c);
                repliesByParent.set(c.parentCommentId, bucket);
            }
        }
        return all
            .filter((c) => c.parentCommentId === null)
            .map((root) => ({root, replies: repliesByParent.get(root.id) ?? []}));
    });

    protected readonly replyingTo = signal<TaskComment | null>(null);
    protected readonly editingCommentId = signal<number | null>(null);
    protected readonly editingBody = signal('');
    protected readonly savingEdit = signal(false);

    private readonly commentInputRef = viewChild<ElementRef<HTMLTextAreaElement>>('commentInput');
    private mentionAnchor = -1;
    protected readonly mentionActive = signal(false);
    protected readonly mentionQuery = signal('');
    protected readonly mentionResults = computed<WorkspaceMember[]>(() => {
        if (!this.mentionActive()) {
            return [];
        }
        const query = this.mentionQuery();
        const members = this.sortedMembers();
        const matched = query === ''
            ? members
            : members.filter((m) => m.name.toLowerCase().includes(query) || m.email.toLowerCase().includes(query));
        return matched.slice(0, 6);
    });

    protected readonly searchControl = new FormControl<string>('', {nonNullable: true});
    protected readonly searchResults = signal<TaskListItem[]>([]);
    private readonly searchTerm = toSignal(
        this.searchControl.valueChanges.pipe(debounceTime(250), distinctUntilChanged(), takeUntilDestroyed()),
        {initialValue: ''},
    );

    protected readonly form = this.fb.nonNullable.group({
        name: ['', Validators.required],
        description: [''],
        statusId: [0, Validators.required],
        priorityId: [0, Validators.required],
        dueDate: [''],
        startDate: [''],
    });

    protected readonly currentPriorityColor = computed<string>(() => {
        const id = Number(this.form.value.priorityId);
        const match = this.workspacePriorities().find((p) => p.id === id);
        return match?.color ?? '#f1f1f3';
    });

    protected priorityForeground(color: string): string {
        return pickReadableForeground(color);
    }

    private readonly statusId = signal<number>(0);

    protected readonly currentStatusColor = computed<string>(() => {
        const id = this.statusId();
        const match = this.statuses().find((s) => s.id === id);
        return match?.color ?? '#94a3a8';
    });

    protected readonly customControls = computed<CustomControlDescriptor[]>(() => {
        const sorted = [...this.projectFields()].sort((a, b) => a.position - b.position);
        return sorted.map((pf) => ({
            controlName: 'field_' + pf.fieldId,
            pf,
            options: pf.field.type === 'Version'
                ? this.fieldService.sortVersionsDescending(pf.field.options ?? [])
                : pf.field.options ?? [],
        }));
    });

    protected readonly groupedRelations = computed<RelationGroup[]>(() => {
        const outgoing = this.outgoingRelations();
        const incoming = this.incomingRelations();
        const groups: RelationGroup[] = [
            {key: 'Parent', headerKey: 'app.taskRelations.groupHeader.Parent', items: incoming.filter((r) => r.type === 'Parent')},
            {key: 'DependsOn', headerKey: 'app.taskRelations.groupHeader.DependsOn', items: outgoing.filter((r) => r.type === 'DependsOn')},
            {key: 'RequiredFor', headerKey: 'app.taskRelations.groupHeader.RequiredFor', items: incoming.filter((r) => r.type === 'DependsOn')},
            {
                key: 'Related',
                headerKey: 'app.taskRelations.groupHeader.Related',
                items: [...outgoing.filter((r) => r.type === 'Related'), ...incoming.filter((r) => r.type === 'Related')],
            },
            {
                key: 'Duplicates',
                headerKey: 'app.taskRelations.groupHeader.Duplicates',
                items: [...outgoing.filter((r) => r.type === 'Duplicates'), ...incoming.filter((r) => r.type === 'Duplicates')],
            },
        ];
        return groups;
    });

    protected readonly hasAnyRelation = computed<boolean>(() =>
        this.outgoingRelations().length + this.incomingRelations().length > 0,
    );

    public constructor() {
        const trigger = computed<string>(() => this.searchTerm() ?? '');
        this.searchControl.valueChanges
            .pipe(debounceTime(250), distinctUntilChanged(), takeUntilDestroyed())
            .subscribe(() => {
                void this.runSearch(trigger());
            });

        this.realtimeService.events$
            .pipe(takeUntilDestroyed())
            .subscribe((event) => this.onRealtimeEvent(event));
    }

    private onRealtimeEvent(event: RealtimeEvent): void {
        const current = this.task();
        if (current === null || event.taskId !== current.id) {
            return;
        }
        if (COMMENT_EVENT_TYPES.has(event.type)) {
            void this.loadComments(current.id);
            // Commenters auto-watch (U-83), so the watcher list may have changed.
            void this.loadWatchers(current.id);
        } else if (FILE_EVENT_TYPES.has(event.type)) {
            void this.loadFiles(current.id);
        } else if (RELATION_EVENT_TYPES.has(event.type)) {
            void this.loadRelations(current.id);
            void this.loadSubtasks(current.id);
        }
    }

    public ngOnInit(): void {
        if (this.workspaceService.currentMembers().length === 0) {
            void this.workspaceService.loadCurrentMembers();
        }

        const existing = this.task();
        if (existing) {
            this.form.patchValue({
                name: existing.name,
                description: existing.description ?? '',
                statusId: existing.statusId,
                priorityId: existing.priority.id,
                dueDate: existing.dueDate ?? '',
                startDate: existing.startDate ?? '',
            });
            this.statusId.set(existing.statusId);
            this.selectedTagIds.set([...(existing.tagIds ?? [])]);
            this.selectedAssigneeId.set(existing.assigneeId);
            void this.loadFiles(existing.id);
            void this.loadRelations(existing.id);
            void this.loadComments(existing.id);
            void this.loadSubtasks(existing.id);
            void this.loadChecklist(existing.id);
            void this.loadRecurrence(existing.id);
            void this.loadWatchers(existing.id);
        } else {
            const fallbackStatusId = this.defaultStatusId() ?? this.statuses()[0]?.id ?? 0;
            const defaultPriority = this.workspacePriorities().find((p) => p.isDefault) ?? this.workspacePriorities()[0];
            this.form.patchValue({statusId: fallbackStatusId, priorityId: defaultPriority?.id ?? 0});
            this.statusId.set(fallbackStatusId);
            this.selectedAssigneeId.set(this.currentUserService.currentUser()?.id ?? null);
            void this.loadTemplates();
        }

        this.form.controls.statusId.valueChanges.subscribe((value) => {
            this.statusId.set(Number(value));
        });

        this.recurrenceForm.controls.cadence.valueChanges.subscribe((value) => this.recurrenceCadence.set(value));
        this.recurrenceForm.controls.endType.valueChanges.subscribe((value) => this.recurrenceEndType.set(value));

        const existingValues = new Map(existing?.fieldValues.map((fv) => [fv.fieldId, fv.value ?? '']) ?? []);
        const dynamic = this.form as unknown as FormGroup;
        for (const desc of this.customControls()) {
            const initial = existingValues.get(desc.pf.fieldId) ?? desc.pf.field.defaultValue ?? '';
            const validators = desc.pf.field.required ? [Validators.required] : [];
            dynamic.addControl(desc.controlName, new FormControl<string>(initial, {nonNullable: true, validators}));
        }
    }

    protected async onSubmit(): Promise<void> {
        if (this.form.invalid) {
            const firstRequiredMissing = this.customControls().find((desc) => {
                const ctrl = this.form.get(desc.controlName);
                return desc.pf.field.required && (ctrl === null || ctrl.invalid);
            });
            if (firstRequiredMissing) {
                this.alertService.error(await this.translate.instant('app.taskFields.fieldRequired', {
                    name: firstRequiredMissing.pf.field.name,
                }) as string);
            }
            return;
        }
        const dueDate = this.form.value.dueDate ? this.form.value.dueDate : null;
        const startDate = this.form.value.startDate ? this.form.value.startDate : null;
        if (startDate !== null && dueDate !== null && startDate > dueDate) {
            this.alertService.error(await this.translate.instant('app.board.drawer.startAfterDue') as string);
            return;
        }
        this.saving.set(true);
        const fieldValues = this.customControls().map((desc) => ({
            fieldId: desc.pf.fieldId,
            value: (this.form.get(desc.controlName)?.value as string | null) ?? null,
        }));
        const payload = {
            statusId: Number(this.form.value.statusId),
            name: this.form.value.name!,
            description: (this.form.value.description ?? '').trim() === '' ? null : this.form.value.description!,
            priorityId: Number(this.form.value.priorityId),
            dueDate,
            startDate,
            assigneeId: this.selectedAssigneeId(),
            fieldValues,
            tagIds: this.selectedTagIds(),
        };
        try {
            const existing = this.task();
            const saved = existing
                ? await this.taskService.updateTask(existing.id, payload)
                : await this.taskService.createTask(this.projectId(), payload);
            this.alertService.success(
                await this.translate.instant(existing ? 'app.board.taskUpdated' : 'app.board.taskCreated') as string,
            );
            this.saved.emit(saved);
        } catch {
            // error interceptor
        } finally {
            this.saving.set(false);
        }
    }

    protected async onDelete(): Promise<void> {
        const existing = this.task();
        if (!existing) {
            return;
        }
        const subtaskCount = this.subtasks().length;
        const confirmMessage = subtaskCount > 0
            ? await this.translate.instant(
                'app.board.deleteTaskWithSubtasksConfirm',
                {name: existing.name, count: subtaskCount},
            ) as string
            : await this.translate.instant('app.board.deleteTaskConfirm', {name: existing.name}) as string;
        if (!confirm(confirmMessage)) {
            return;
        }
        try {
            await this.taskService.deleteTask(existing.id);
            this.alertService.success(await this.translate.instant('app.board.taskDeleted') as string);
            this.deleted.emit(existing.id);
        } catch {
            // error interceptor
        }
    }

    protected onCancel(): void {
        this.cancelled.emit();
    }

    protected async onDuplicate(): Promise<void> {
        const existing = this.task();
        if (!existing) {
            return;
        }
        this.duplicating.set(true);
        try {
            const copy = await this.taskService.duplicateTask(existing.id);
            this.alertService.success(await this.translate.instant('app.taskTemplates.duplicated') as string);
            this.saved.emit(copy);
        } catch {
            // error interceptor
        } finally {
            this.duplicating.set(false);
        }
    }

    protected async onArchive(): Promise<void> {
        const existing = this.task();
        if (!existing) {
            return;
        }
        this.archiving.set(true);
        try {
            const updated = await this.taskService.archiveTask(existing.id);
            this.alertService.success(await this.translate.instant('app.board.drawer.archived') as string);
            this.saved.emit(updated);
        } catch {
            // error interceptor
        } finally {
            this.archiving.set(false);
        }
    }

    protected async onUnarchive(): Promise<void> {
        const existing = this.task();
        if (!existing) {
            return;
        }
        this.archiving.set(true);
        try {
            const updated = await this.taskService.unarchiveTask(existing.id);
            this.alertService.success(await this.translate.instant('app.board.drawer.unarchived') as string);
            this.saved.emit(updated);
        } catch {
            // error interceptor
        } finally {
            this.archiving.set(false);
        }
    }

    protected async onSaveAsTemplate(): Promise<void> {
        const existing = this.task();
        if (!existing) {
            return;
        }
        const promptMessage = await this.translate.instant('app.taskTemplates.namePrompt') as string;
        const name = prompt(promptMessage, existing.name);
        if (name === null || name.trim() === '') {
            return;
        }
        try {
            await this.taskTemplateService.saveFromTask(existing.id, name.trim());
            this.alertService.success(await this.translate.instant('app.taskTemplates.saved') as string);
        } catch {
            // error interceptor
        }
    }

    protected onTemplateSelected(event: Event): void {
        const id = Number((event.target as HTMLSelectElement).value);
        const template = this.templates().find((t) => t.id === id);
        if (template) {
            this.applyTemplate(template);
        }
    }

    private applyTemplate(template: TaskTemplate): void {
        const payload = template.payload;
        this.form.patchValue({name: payload.name, description: payload.description ?? ''});
        if (payload.priorityId !== null && this.workspacePriorities().some((p) => p.id === payload.priorityId)) {
            this.form.patchValue({priorityId: payload.priorityId});
        }
        const workspaceTagIds = new Set(this.workspaceTags().map((t) => t.id));
        this.selectedTagIds.set(payload.tagIds.filter((tagId) => workspaceTagIds.has(tagId)));
        for (const fv of payload.fieldValues) {
            this.form.get('field_' + fv.fieldId)?.setValue(fv.value ?? '');
        }
    }

    private async loadTemplates(): Promise<void> {
        let workspaceId = this.workspaceService.currentWorkspaceId();
        if (workspaceId === null) {
            try {
                workspaceId = (await this.currentUserService.load()).currentWorkspaceId;
            } catch {
                workspaceId = null;
            }
        }
        if (workspaceId === null) {
            return;
        }
        try {
            this.templates.set(await this.taskTemplateService.loadWorkspaceTemplates(workspaceId));
        } catch {
            this.templates.set([]);
        }
    }

    protected toggleTagPicker(): void {
        this.tagPickerOpen.update((v) => !v);
    }

    protected closeTagPicker(): void {
        this.tagPickerOpen.set(false);
    }

    protected addTagToTask(tag: Tag): void {
        this.selectedTagIds.update((ids) => ids.includes(tag.id) ? ids : [...ids, tag.id]);
        this.tagPickerOpen.set(false);
    }

    protected removeTagFromTask(tag: Tag): void {
        this.selectedTagIds.update((ids) => ids.filter((id) => id !== tag.id));
    }

    protected tagForeground(color: string): string {
        return pickReadableForeground(color);
    }

    protected toggleAssigneePicker(): void {
        this.assigneePickerOpen.update((v) => !v);
    }

    protected assignMember(member: WorkspaceMember): void {
        this.selectedAssigneeId.set(member.userId);
        this.assigneePickerOpen.set(false);
    }

    protected clearAssignee(): void {
        this.selectedAssigneeId.set(null);
        this.assigneePickerOpen.set(false);
    }

    protected memberInitials(name: string): string {
        const parts = name.trim().split(/\s+/);
        if (parts.length === 0 || parts[0] === '') return '?';
        if (parts.length === 1) return parts[0].slice(0, 2).toUpperCase();
        return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
    }

    protected async onFileSelected(event: Event): Promise<void> {
        const target = event.target as HTMLInputElement;
        const file = target.files?.[0];
        target.value = '';
        if (!file) {
            return;
        }
        const existing = this.task();
        if (!existing) {
            return;
        }
        this.uploading.set(true);
        try {
            const uploaded = await this.taskService.uploadTaskFile(existing.id, file);
            this.files.update((current) => [...current, uploaded]);
            this.alertService.success(await this.translate.instant('app.board.drawer.files.uploaded') as string);
        } catch {
            // error interceptor
        } finally {
            this.uploading.set(false);
        }
    }

    protected async onDownloadFile(file: TaskFile): Promise<void> {
        const existing = this.task();
        if (!existing) {
            return;
        }
        try {
            const blob = await this.taskService.downloadTaskFile(existing.id, file.id);
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = file.filename;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
        } catch {
            // error interceptor
        }
    }

    protected async onDeleteFile(file: TaskFile): Promise<void> {
        const existing = this.task();
        if (!existing) {
            return;
        }
        const message = await this.translate.instant('app.board.drawer.files.deleteConfirm', {name: file.filename}) as string;
        if (!confirm(message)) {
            return;
        }
        try {
            await this.taskService.deleteTaskFile(existing.id, file.id);
            this.files.update((current) => current.filter((f) => f.id !== file.id));
        } catch {
            // error interceptor
        }
    }

    private async loadSubtasks(taskId: number): Promise<void> {
        try {
            this.subtasks.set(await this.taskService.listSubtasks(taskId));
        } catch {
            this.subtasks.set([]);
        } finally {
            this.subtasksLoaded.set(true);
        }
    }

    protected async onAddSubtask(): Promise<void> {
        const existing = this.task();
        const name = this.subtaskNameControl.value.trim();
        if (!existing || name === '' || this.addingSubtask()) {
            return;
        }
        this.addingSubtask.set(true);
        try {
            const created = await this.taskService.createSubtask(existing.id, name);
            this.subtasks.update((list) => [...list, created]);
            this.subtaskNameControl.setValue('');
        } catch {
            // error interceptor
        } finally {
            this.addingSubtask.set(false);
        }
    }

    protected async onToggleSubtask(subtask: Subtask, event: Event): Promise<void> {
        const checked = (event.target as HTMLInputElement).checked;
        const targetStatusId = checked ? subtask.finishStatusId : subtask.startStatusId;
        if (targetStatusId === null) {
            return;
        }
        try {
            await this.taskService.moveTask(subtask.taskId, targetStatusId, 0);
            const parent = this.task();
            if (parent) {
                await this.loadSubtasks(parent.id);
            }
        } catch {
            // error interceptor — restore the checkbox by re-rendering current state
            this.subtasks.update((list) => [...list]);
        }
    }

    protected async onRemoveSubtask(subtask: Subtask): Promise<void> {
        const message = await this.translate.instant('app.subtasks.removeConfirm', {name: subtask.name}) as string;
        if (!confirm(message)) {
            return;
        }
        try {
            await this.taskRelationService.delete(subtask.relationId);
            this.subtasks.update((list) => list.filter((s) => s.relationId !== subtask.relationId));
        } catch {
            // error interceptor
        }
    }

    protected async onOpenSubtask(subtask: Subtask): Promise<void> {
        try {
            const task = await this.taskService.getTask(subtask.taskId);
            const item: TaskListItem = {
                id: task.id,
                code: task.code,
                projectId: task.projectId,
                projectName: '',
                statusId: task.statusId,
                status: {
                    id: subtask.statusId,
                    workflowId: 0,
                    name: subtask.statusName,
                    color: subtask.statusColor,
                    position: 0,
                    type: subtask.statusType,
                },
                assigneeId: task.assigneeId,
                name: task.name,
                description: task.description,
                priority: task.priority,
                dueDate: task.dueDate,
                startDate: task.startDate,
                position: task.position,
                sequenceNumber: task.sequenceNumber,
                createdByAgent: task.createdByAgent,
                archivedAt: task.archivedAt,
                createdAt: task.createdAt,
                updatedAt: task.updatedAt,
                tagIds: task.tagIds,
            };
            this.openTask.emit(item);
        } catch {
            // error interceptor
        }
    }

    private async loadChecklist(taskId: number): Promise<void> {
        try {
            this.checklist.set(await this.taskChecklistService.list(taskId));
        } catch {
            this.checklist.set([]);
        } finally {
            this.checklistLoaded.set(true);
        }
    }

    protected async onAddChecklistItem(): Promise<void> {
        const existing = this.task();
        const text = this.checklistTextControl.value.trim();
        if (!existing || text === '' || this.addingChecklistItem()) {
            return;
        }
        this.addingChecklistItem.set(true);
        try {
            const created = await this.taskChecklistService.create(existing.id, {text});
            this.checklist.update((list) => [...list, created]);
            this.checklistTextControl.setValue('');
        } catch {
            // error interceptor
        } finally {
            this.addingChecklistItem.set(false);
        }
    }

    protected async onToggleChecklistItem(item: ChecklistItem, event: Event): Promise<void> {
        const checked = (event.target as HTMLInputElement).checked;
        try {
            const updated = await this.taskChecklistService.update(item.id, {checked});
            this.replaceChecklistItem(updated);
        } catch {
            // revert the checkbox to the stored state
            this.checklist.update((list) => [...list]);
        }
    }

    protected async onRenameChecklistItem(item: ChecklistItem, event: Event): Promise<void> {
        const text = (event.target as HTMLInputElement).value.trim();
        if (text === '' || text === item.text) {
            this.checklist.update((list) => [...list]);
            return;
        }
        try {
            const updated = await this.taskChecklistService.update(item.id, {text});
            this.replaceChecklistItem(updated);
        } catch {
            this.checklist.update((list) => [...list]);
        }
    }

    protected async onChecklistDueDateChange(item: ChecklistItem, event: Event): Promise<void> {
        const value = (event.target as HTMLInputElement).value;
        try {
            const updated = await this.taskChecklistService.update(item.id, {dueDate: value === '' ? null : value});
            this.replaceChecklistItem(updated);
        } catch {
            this.checklist.update((list) => [...list]);
        }
    }

    protected async onChecklistAssigneeChange(item: ChecklistItem, event: Event): Promise<void> {
        const raw = (event.target as HTMLSelectElement).value;
        try {
            const updated = await this.taskChecklistService.update(item.id, {assigneeId: raw === '' ? null : Number(raw)});
            this.replaceChecklistItem(updated);
        } catch {
            this.checklist.update((list) => [...list]);
        }
    }

    protected async onRemoveChecklistItem(item: ChecklistItem): Promise<void> {
        try {
            await this.taskChecklistService.delete(item.id);
            this.checklist.update((list) => list.filter((i) => i.id !== item.id));
        } catch {
            // error interceptor
        }
    }

    protected async onReorderChecklist(event: CdkDragDrop<ChecklistItem[]>): Promise<void> {
        if (event.previousIndex === event.currentIndex) {
            return;
        }
        const reordered = [...this.checklist()];
        const moved = reordered[event.previousIndex];
        moveItemInArray(reordered, event.previousIndex, event.currentIndex);
        this.checklist.set(reordered);
        try {
            await this.taskChecklistService.move(moved.id, event.currentIndex);
        } catch {
            const parent = this.task();
            if (parent) {
                await this.loadChecklist(parent.id);
            }
        }
    }

    private replaceChecklistItem(updated: ChecklistItem): void {
        this.checklist.update((list) => list.map((i) => (i.id === updated.id ? updated : i)));
    }

    private async loadRecurrence(taskId: number): Promise<void> {
        try {
            const recurrence = await this.taskRecurrenceService.get(taskId);
            this.recurrence.set(recurrence);
            if (recurrence) {
                this.recurrenceForm.patchValue({
                    cadence: recurrence.cadence,
                    interval: recurrence.interval,
                    weekday: recurrence.weekday ?? 1,
                    dayOfMonth: recurrence.dayOfMonth ?? 1,
                    cronExpression: recurrence.cronExpression ?? '',
                    endType: recurrence.endType,
                    endDate: recurrence.endDate ?? '',
                    maxOccurrences: recurrence.maxOccurrences ?? 10,
                });
                this.recurrenceCadence.set(recurrence.cadence);
                this.recurrenceEndType.set(recurrence.endType);
            }
        } catch {
            this.recurrence.set(null);
        } finally {
            this.recurrenceLoaded.set(true);
        }
    }

    protected onEditRecurrence(): void {
        this.recurrenceEditing.set(true);
    }

    protected onCancelRecurrence(): void {
        this.recurrenceEditing.set(false);
    }

    protected async onSaveRecurrence(): Promise<void> {
        const existing = this.task();
        if (!existing || this.savingRecurrence()) {
            return;
        }
        this.savingRecurrence.set(true);
        try {
            const saved = await this.taskRecurrenceService.set(existing.id, this.buildRecurrencePayload());
            this.recurrence.set(saved);
            this.recurrenceEditing.set(false);
        } catch {
            // error interceptor surfaces the message
        } finally {
            this.savingRecurrence.set(false);
        }
    }

    protected async onClearRecurrence(): Promise<void> {
        const existing = this.task();
        if (!existing || this.savingRecurrence()) {
            return;
        }
        this.savingRecurrence.set(true);
        try {
            await this.taskRecurrenceService.clear(existing.id);
            this.recurrence.set(null);
            this.recurrenceEditing.set(false);
        } catch {
            // error interceptor surfaces the message
        } finally {
            this.savingRecurrence.set(false);
        }
    }

    private buildRecurrencePayload(): RecurrenceWritePayload {
        const value = this.recurrenceForm.getRawValue();
        return {
            cadence: value.cadence,
            interval: value.cadence === 'Cron' ? 1 : Math.max(1, Number(value.interval) || 1),
            weekday: value.cadence === 'Weekly' ? Number(value.weekday) : null,
            dayOfMonth: value.cadence === 'Monthly' ? Number(value.dayOfMonth) : null,
            cronExpression: value.cadence === 'Cron' ? value.cronExpression.trim() : null,
            endType: value.endType,
            endDate: value.endType === 'OnDate' && value.endDate !== '' ? value.endDate : null,
            maxOccurrences: value.endType === 'AfterCount' ? Math.max(1, Number(value.maxOccurrences) || 1) : null,
        };
    }

    protected onOpenAddRelation(): void {
        this.addRelationOpen.set(true);
        this.searchControl.setValue('');
        this.searchResults.set([]);
    }

    protected onCloseAddRelation(): void {
        this.addRelationOpen.set(false);
        this.searchControl.setValue('');
        this.searchResults.set([]);
    }

    protected onTypeChange(event: Event): void {
        this.addRelationType.set((event.target as HTMLSelectElement).value as TaskRelationType);
    }

    protected async onPickTarget(target: TaskListItem): Promise<void> {
        const existing = this.task();
        if (!existing) {
            return;
        }
        if (target.id === existing.id) {
            return;
        }
        this.addRelationSaving.set(true);
        try {
            await this.taskRelationService.create(existing.id, {
                targetTaskId: target.id,
                type: this.addRelationType(),
            });
            this.alertService.success(await this.translate.instant('app.taskRelations.added') as string);
            this.onCloseAddRelation();
            await this.loadRelations(existing.id);
        } catch (err) {
            const message = this.extractErrorMessage(err)
                ?? await this.translate.instant('app.taskRelations.errors.generic') as string;
            this.alertService.error(message);
        } finally {
            this.addRelationSaving.set(false);
        }
    }

    protected async onRemoveRelation(relation: TaskRelation): Promise<void> {
        const message = await this.translate.instant('app.taskRelations.removeConfirm', {name: relation.otherTaskName}) as string;
        if (!confirm(message)) {
            return;
        }
        const existing = this.task();
        if (!existing) {
            return;
        }
        try {
            await this.taskRelationService.delete(relation.id);
            this.alertService.success(await this.translate.instant('app.taskRelations.removed') as string);
            await this.loadRelations(existing.id);
        } catch {
            // error interceptor
        }
    }

    protected async onOpenRelatedTask(relation: TaskRelation): Promise<void> {
        try {
            const task = await this.taskService.getTask(relation.otherTaskId);
            const item: TaskListItem = {
                id: task.id,
                code: task.code,
                projectId: task.projectId,
                projectName: relation.otherTaskProjectName,
                statusId: task.statusId,
                status: {
                    id: relation.otherTaskStatusId,
                    workflowId: 0,
                    name: relation.otherTaskStatusName,
                    color: relation.otherTaskStatusColor,
                    position: 0,
                    type: 'Normal',
                },
                assigneeId: task.assigneeId,
                name: task.name,
                description: task.description,
                priority: task.priority,
                dueDate: task.dueDate,
                startDate: task.startDate,
                position: task.position,
                sequenceNumber: task.sequenceNumber,
                createdByAgent: task.createdByAgent,
                archivedAt: task.archivedAt,
                createdAt: task.createdAt,
                updatedAt: task.updatedAt,
                tagIds: task.tagIds,
            };
            this.openTask.emit(item);
        } catch {
            // error interceptor
        }
    }

    protected formatFileSize(size: number): string {
        if (size < 1024) {
            return size + ' B';
        }
        if (size < 1024 * 1024) {
            return (size / 1024).toFixed(1) + ' KB';
        }
        return (size / (1024 * 1024)).toFixed(1) + ' MB';
    }

    protected fileTypeChip(filename: string): FileTypeChip {
        const dot = filename.lastIndexOf('.');
        if (dot === -1 || dot === filename.length - 1) {
            return FILE_TYPE_FALLBACK;
        }
        const ext = filename.slice(dot + 1).toLowerCase();
        return FILE_TYPE_MAP[ext] ?? FILE_TYPE_FALLBACK;
    }

    protected totalRelationCount(): number {
        return this.outgoingRelations().length + this.incomingRelations().length;
    }

    private async loadFiles(taskId: number): Promise<void> {
        try {
            const list = await this.taskService.listTaskFiles(taskId);
            this.files.set(list);
        } catch {
            // ignore — task may have just been created
        }
    }

    private async loadRelations(taskId: number): Promise<void> {
        try {
            const list = await this.taskRelationService.list(taskId);
            this.outgoingRelations.set(list.outgoing);
            this.incomingRelations.set(list.incoming);
            this.relationsLoaded.set(true);
        } catch {
            this.outgoingRelations.set([]);
            this.incomingRelations.set([]);
            this.relationsLoaded.set(true);
        }
    }

    private async loadComments(taskId: number): Promise<void> {
        try {
            const list = await this.taskCommentService.list(taskId);
            this.comments.set(list);
        } catch {
            this.comments.set([]);
        } finally {
            this.commentsLoaded.set(true);
        }
    }

    private async loadWatchers(taskId: number): Promise<void> {
        try {
            const result = await this.taskWatcherService.list(taskId);
            this.watchers.set(result.watchers);
            this.watching.set(result.watching);
        } catch {
            this.watchers.set([]);
            this.watching.set(false);
        }
    }

    protected async toggleWatch(): Promise<void> {
        const current = this.task();
        if (current === null || this.watchToggling()) {
            return;
        }
        this.watchToggling.set(true);
        try {
            const result = this.watching()
                ? await this.taskWatcherService.unwatch(current.id)
                : await this.taskWatcherService.watch(current.id);
            this.watchers.set(result.watchers);
            this.watching.set(result.watching);
        } catch {
            // error interceptor surfaces failures
        } finally {
            this.watchToggling.set(false);
        }
    }

    protected canDeleteComment(comment: TaskComment): boolean {
        const user = this.currentUserService.currentUser();
        if (user === null) {
            return false;
        }
        return user.systemRole === 'SystemAdmin' || comment.authorId === user.id;
    }

    protected canEditComment(comment: TaskComment): boolean {
        const user = this.currentUserService.currentUser();
        if (user === null) {
            return false;
        }
        // Editing rewrites someone's words, so only the author (or a system admin) may do it.
        return user.systemRole === 'SystemAdmin' || comment.authorId === user.id;
    }

    protected async onAddComment(): Promise<void> {
        const existing = this.task();
        if (!existing || this.commentForm.invalid) {
            return;
        }
        const body = this.commentForm.controls.body.value.trim();
        if (body === '') {
            return;
        }
        this.postingComment.set(true);
        try {
            const created = await this.taskCommentService.create(existing.id, {
                body,
                parentCommentId: this.replyingTo()?.id ?? null,
            });
            this.comments.update((list) => [...list, created]);
            this.commentForm.reset({body: ''});
            this.replyingTo.set(null);
            this.closeMention();
        } catch {
            // error interceptor
        } finally {
            this.postingComment.set(false);
        }
    }

    protected startReply(comment: TaskComment): void {
        // Clamp to one level — replying to a reply targets its top-level parent.
        const target = comment.parentCommentId !== null
            ? this.comments().find((c) => c.id === comment.parentCommentId) ?? comment
            : comment;
        this.replyingTo.set(target);
        const el = this.commentInputRef()?.nativeElement;
        queueMicrotask(() => el?.focus());
    }

    protected cancelReply(): void {
        this.replyingTo.set(null);
    }

    protected startEdit(comment: TaskComment): void {
        this.editingCommentId.set(comment.id);
        this.editingBody.set(comment.body);
    }

    protected cancelEdit(): void {
        this.editingCommentId.set(null);
        this.editingBody.set('');
    }

    protected onEditBodyInput(event: Event): void {
        this.editingBody.set((event.target as HTMLTextAreaElement).value);
    }

    protected async saveEdit(comment: TaskComment): Promise<void> {
        const body = this.editingBody().trim();
        if (body === '') {
            return;
        }
        if (body === comment.body) {
            this.cancelEdit();
            return;
        }
        this.savingEdit.set(true);
        try {
            const updated = await this.taskCommentService.update(comment.id, {body});
            this.comments.update((list) => list.map((c) => (c.id === updated.id ? updated : c)));
            this.cancelEdit();
        } catch {
            // error interceptor
        } finally {
            this.savingEdit.set(false);
        }
    }

    protected async onDeleteComment(comment: TaskComment): Promise<void> {
        const message = await this.translate.instant('app.taskComments.deleteConfirm') as string;
        if (!confirm(message)) {
            return;
        }
        try {
            await this.taskCommentService.delete(comment.id);
            // Deleting a top-level comment also removes its replies on the backend.
            this.comments.update((list) => list.filter((c) => c.id !== comment.id && c.parentCommentId !== comment.id));
            if (this.replyingTo()?.id === comment.id) {
                this.replyingTo.set(null);
            }
        } catch {
            // error interceptor
        }
    }

    /** Detect an `@…` token immediately before the caret and surface the member picker. */
    protected onCommentInput(event: Event): void {
        const el = event.target as HTMLTextAreaElement;
        const caret = el.selectionStart ?? el.value.length;
        const match = /@([\p{L}\p{N}._-]*)$/u.exec(el.value.slice(0, caret));
        if (match !== null && this.sortedMembers().length > 0) {
            this.mentionAnchor = caret - match[1].length - 1;
            this.mentionQuery.set(match[1].toLowerCase());
            this.mentionActive.set(true);
        } else {
            this.closeMention();
        }
    }

    protected selectMention(member: WorkspaceMember): void {
        const el = this.commentInputRef()?.nativeElement;
        if (el === undefined || this.mentionAnchor < 0) {
            this.closeMention();
            return;
        }
        const caret = el.selectionStart ?? el.value.length;
        const label = member.name.replace(/[[\]()]/g, '').trim();
        const token = `@[${label}](user:${member.userId}) `;
        const next = el.value.slice(0, this.mentionAnchor) + token + el.value.slice(caret);
        this.commentForm.controls.body.setValue(next);
        const pos = this.mentionAnchor + token.length;
        this.closeMention();
        queueMicrotask(() => {
            el.focus();
            el.setSelectionRange(pos, pos);
        });
    }

    private closeMention(): void {
        this.mentionActive.set(false);
        this.mentionQuery.set('');
        this.mentionAnchor = -1;
    }

    /** Turn `@[Name](user:ID)` mention tokens into a styled, inert span the markdown sanitizer keeps. */
    protected renderCommentBody(body: string): string {
        return body.replace(
            /@\[([^\]]+)\]\(user:\d+\)/g,
            (_match, name: string) => `<span class="mention">@${this.escapeHtml(name)}</span>`,
        );
    }

    private escapeHtml(value: string): string {
        return value
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    private async runSearch(term: string): Promise<void> {
        if (!this.addRelationOpen()) {
            return;
        }
        if (term.trim() === '') {
            this.searchResults.set([]);
            return;
        }
        try {
            const result = await this.taskService.getTasks({
                limit: 20,
                offset: 0,
                orderBy: 'name',
                orderDirection: 'ASC',
                search: term.trim(),
            });
            const currentId = this.task()?.id;
            this.searchResults.set(result.tasks.filter((t) => t.id !== currentId));
        } catch {
            this.searchResults.set([]);
        }
    }

    private extractErrorMessage(err: unknown): string | null {
        if (typeof err === 'object' && err !== null && 'error' in err) {
            const inner = (err as {error: unknown}).error;
            if (typeof inner === 'object' && inner !== null && 'message' in inner) {
                const msg = (inner as {message: unknown}).message;
                if (typeof msg === 'string' && msg !== '') {
                    return msg;
                }
            }
        }
        return null;
    }
}
