import {ChangeDetectionStrategy, Component, computed, inject, input, OnInit, output, signal} from '@angular/core';
import {FormBuilder, ReactiveFormsModule, Validators} from '@angular/forms';
import {Status} from '@app/models/status';
import {Tag} from '@app/models/tag';
import {Task} from '@app/models/task';
import {TaskFile} from '@app/models/task-file';
import {TaskWatcher} from '@app/models/task-watcher';
import {AlertService} from '@app/services/alert.service';
import {TaskService} from '@app/services/task.service';
import {TaskWatcherService} from '@app/services/task-watcher.service';
import {pickReadableForeground} from '@app/shared/color-contrast';
import {MarkdownEditorComponent} from '@app/shared/components/markdown-editor/markdown-editor.component';
import {TranslatePipe, TranslateService} from '@ngx-translate/core';

interface FileTypeChip {
    tag: string;
    bg: string;
    fg: string;
}

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
        ReactiveFormsModule,
        MarkdownEditorComponent,
        TranslatePipe,
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
    public readonly workspaceTags = input<Tag[]>([]);

    public readonly saved = output<Task>();
    public readonly deleted = output<number>();
    public readonly cancelled = output<void>();

    private readonly fb = inject(FormBuilder);
    private readonly taskService = inject(TaskService);
    private readonly taskWatcherService = inject(TaskWatcherService);
    private readonly alertService = inject(AlertService);
    private readonly translate = inject(TranslateService);

    protected readonly saving = signal(false);
    protected readonly archiving = signal(false);
    protected readonly isArchived = computed<boolean>(() => this.task()?.archivedAt != null);
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

    protected readonly files = signal<TaskFile[]>([]);
    protected readonly uploading = signal(false);

    protected readonly watching = signal(false);
    protected readonly watchers = signal<TaskWatcher[]>([]);
    protected readonly watchToggling = signal(false);

    protected readonly form = this.fb.nonNullable.group({
        name: ['', Validators.required],
        description: [''],
        statusId: [0, Validators.required],
    });

    private readonly statusId = signal<number>(0);

    protected readonly currentStatusColor = computed<string>(() => {
        const id = this.statusId();
        const match = this.statuses().find((s) => s.id === id);
        return match?.color ?? '#94a3a8';
    });

    public ngOnInit(): void {
        const existing = this.task();
        if (existing) {
            this.form.patchValue({
                name: existing.name,
                description: existing.description ?? '',
                statusId: existing.statusId,
            });
            this.statusId.set(existing.statusId);
            this.selectedTagIds.set([...(existing.tagIds ?? [])]);
            void this.loadFiles(existing.id);
            void this.loadWatchers(existing.id);
        } else {
            const fallbackStatusId = this.defaultStatusId() ?? this.statuses()[0]?.id ?? 0;
            this.form.patchValue({statusId: fallbackStatusId});
            this.statusId.set(fallbackStatusId);
        }

        this.form.controls.statusId.valueChanges.subscribe((value) => {
            this.statusId.set(Number(value));
        });
    }

    protected async onSubmit(): Promise<void> {
        if (this.form.invalid) {
            return;
        }
        this.saving.set(true);
        const payload = {
            statusId: Number(this.form.value.statusId),
            name: this.form.value.name!,
            description: (this.form.value.description ?? '').trim() === '' ? null : this.form.value.description!,
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
        const confirmMessage = await this.translate.instant('app.board.deleteTaskConfirm', {name: existing.name}) as string;
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

    private async loadFiles(taskId: number): Promise<void> {
        try {
            const list = await this.taskService.listTaskFiles(taskId);
            this.files.set(list);
        } catch {
            // ignore — task may have just been created
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
}
