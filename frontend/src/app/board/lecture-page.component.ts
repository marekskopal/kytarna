import {ChangeDetectionStrategy, Component, computed, inject, OnInit, signal} from '@angular/core';
import {toSignal} from '@angular/core/rxjs-interop';
import {FormBuilder, ReactiveFormsModule, Validators} from '@angular/forms';
import {ActivatedRoute, Router} from '@angular/router';
import {Difficulty, Lecture} from '@app/models/lecture';
import {LectureFile} from '@app/models/lecture-file';
import {LectureWatcher} from '@app/models/lecture-watcher';
import {Status} from '@app/models/status';
import {Tag} from '@app/models/tag';
import {AlertService} from '@app/services/alert.service';
import {BoardService} from '@app/services/board.service';
import {CurrentUserService} from '@app/services/current-user.service';
import {LectureService} from '@app/services/lecture.service';
import {LectureWatcherService} from '@app/services/lecture-watcher.service';
import {TagService} from '@app/services/tag.service';
import {WorkspaceService} from '@app/services/workspace.service';
import {pickReadableForeground} from '@app/shared/color-contrast';
import {MarkdownEditorComponent} from '@app/shared/components/markdown-editor/markdown-editor.component';
import {TranslatePipe, TranslateService} from '@ngx-translate/core';

import {LectureLinksComponent} from './lecture-links.component';
import {LectureProgressComponent} from './lecture-progress.component';
import {LectureTabsComponent} from './lecture-tabs.component';

type LecturePanel = 'details' | 'tabs' | 'progress' | 'links';
const DIFFICULTIES: Difficulty[] = ['Beginner', 'Intermediate', 'Advanced'];

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
    selector: 'uk-lecture-page',
    standalone: true,
    imports: [
        ReactiveFormsModule,
        MarkdownEditorComponent,
        TranslatePipe,
        LectureTabsComponent,
        LectureProgressComponent,
        LectureLinksComponent,
    ],
    changeDetection: ChangeDetectionStrategy.OnPush,
    templateUrl: './lecture-page.component.html',
    styleUrl: './lecture-page.component.scss',
})
export class LecturePageComponent implements OnInit {
    private readonly route = inject(ActivatedRoute);
    private readonly router = inject(Router);
    private readonly fb = inject(FormBuilder);
    private readonly lectureService = inject(LectureService);
    private readonly boardService = inject(BoardService);
    private readonly tagService = inject(TagService);
    private readonly workspaceService = inject(WorkspaceService);
    private readonly currentUserService = inject(CurrentUserService);
    private readonly lectureWatcherService = inject(LectureWatcherService);
    private readonly alertService = inject(AlertService);
    private readonly translate = inject(TranslateService);

    /** The lecture being viewed/edited. `null` while loading or in CREATE mode. */
    protected readonly lecture = signal<Lecture | null>(null);
    protected readonly statuses = signal<Status[]>([]);
    protected readonly courseId = signal<number>(0);
    protected readonly courseName = signal<string>('');
    protected readonly defaultStatusId = signal<number | null>(null);
    protected readonly workspaceTags = signal<Tag[]>([]);

    /** True once route data has resolved; CREATE mode is `true` immediately (no lecture to fetch). */
    protected readonly loaded = signal(false);
    /** CREATE mode = the route has no `:lectureId`. */
    protected readonly isCreate = signal(false);

    protected readonly saving = signal(false);
    protected readonly archiving = signal(false);
    protected readonly isArchived = computed<boolean>(() => this.lecture()?.archivedAt != null);
    protected readonly descriptionInitialTab = computed<'edit' | 'preview'>(() =>
        this.lecture() === null ? 'edit' : 'preview',
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

    protected readonly files = signal<LectureFile[]>([]);
    protected readonly uploading = signal(false);

    protected readonly watching = signal(false);
    protected readonly watchers = signal<LectureWatcher[]>([]);
    protected readonly watchToggling = signal(false);

    protected readonly form = this.fb.nonNullable.group({
        name: ['', Validators.required],
        description: [''],
        statusId: [0, Validators.required],
        tuning: [''],
        capo: [null as number | null],
        targetTempoBpm: [null as number | null],
        difficulty: ['' as Difficulty | ''],
    });

    protected readonly difficulties = DIFFICULTIES;
    protected readonly panel = signal<LecturePanel>('details');

    protected setPanel(panel: LecturePanel): void {
        this.panel.set(panel);
    }

    private readonly statusId = signal<number>(0);

    /** Live view of the form so the header band (chips + BPM) reflects unsaved edits. */
    private readonly formValue = toSignal(this.form.valueChanges, {initialValue: this.form.getRawValue()});

    protected readonly currentStatusColor = computed<string>(() => {
        const id = this.statusId();
        const match = this.statuses().find((s) => s.id === id);
        return match?.color ?? '#94a3a8';
    });

    protected readonly currentStatus = computed(() => {
        const id = this.statusId();
        return this.statuses().find((s) => s.id === id) ?? null;
    });

    /** The "Learning"-equivalent status (a mid-workflow / Normal step) gets the soft glow dot. */
    protected readonly currentStatusIsActive = computed<boolean>(() => this.currentStatus()?.type === 'Normal');

    protected readonly tuningDisplay = computed<string>(() => {
        const v = (this.formValue().tuning ?? '').trim();
        return v === '' ? '—' : v;
    });

    protected readonly capoDisplay = computed<string>(() => {
        const c = this.formValue().capo;
        return c === null || c === undefined ? '—' : String(c);
    });

    protected readonly targetDisplay = computed<string>(() => {
        const t = this.formValue().targetTempoBpm;
        return t === null || t === undefined ? '—' : String(t);
    });

    protected readonly difficultyDisplay = computed<string>(() => {
        const d = this.formValue().difficulty;
        return d === null || d === undefined || d === '' ? '—' : d;
    });

    /** Difficulty value colour, matching the design (Advanced=accent, Intermediate=gold, Beginner=olive). */
    protected readonly difficultyColor = computed<string>(() => {
        switch (this.formValue().difficulty) {
            case 'Advanced': return 'var(--color-accent)';
            case 'Intermediate': return 'var(--color-warn)';
            case 'Beginner': return 'var(--color-success)';
            default: return 'var(--color-text-subtle)';
        }
    });

    // Current tempo is tracked in the Progress panel, not here, so the header shows a dash.
    protected readonly currentBpmDisplay = '—';

    protected selectStatus(id: number): void {
        this.form.controls.statusId.setValue(id);
    }

    public async ngOnInit(): Promise<void> {
        const courseId = Number(this.route.snapshot.paramMap.get('id'));
        this.courseId.set(courseId);

        await Promise.all([
            this.loadStatuses(courseId),
            this.loadWorkspaceTags(),
        ]);

        const lectureIdParam = this.route.snapshot.paramMap.get('lectureId');
        if (lectureIdParam !== null && lectureIdParam !== '') {
            try {
                const existing = await this.lectureService.getLecture(Number(lectureIdParam));
                this.applyLecture(existing);
            } catch {
                // lecture may have been deleted — bounce back to the board
                await this.navigateToBoard();
                return;
            }
        } else {
            this.isCreate.set(true);
            const statusParam = this.route.snapshot.queryParamMap.get('status');
            const parsed = statusParam !== null ? Number(statusParam) : NaN;
            const fallbackStatusId = Number.isFinite(parsed) && parsed > 0
                ? parsed
                : this.statuses()[0]?.id ?? 0;
            this.defaultStatusId.set(Number.isFinite(parsed) && parsed > 0 ? parsed : null);
            this.form.patchValue({statusId: fallbackStatusId});
            this.statusId.set(fallbackStatusId);
        }

        this.form.controls.statusId.valueChanges.subscribe((value) => {
            this.statusId.set(Number(value));
        });

        this.loaded.set(true);
    }

    /** Hydrate the form + related state from a fetched/saved lecture. */
    private applyLecture(existing: Lecture): void {
        this.lecture.set(existing);
        this.isCreate.set(false);
        this.form.patchValue({
            name: existing.name,
            description: existing.description ?? '',
            statusId: existing.statusId,
            tuning: existing.tuning ?? '',
            capo: existing.capo,
            targetTempoBpm: existing.targetTempoBpm,
            difficulty: existing.difficulty ?? '',
        });
        this.statusId.set(existing.statusId);
        this.selectedTagIds.set([...(existing.tagIds ?? [])]);
        void this.loadFiles(existing.id);
        void this.loadWatchers(existing.id);
    }

    private async loadStatuses(courseId: number): Promise<void> {
        try {
            const board = await this.boardService.getBoard(courseId);
            this.statuses.set(board.statuses);
            this.courseName.set(board.course.name);
        } catch {
            this.statuses.set([]);
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

    private navigateToBoard(): Promise<boolean> {
        return this.router.navigate(['courses', this.courseId(), 'board']);
    }

    protected async onSubmit(): Promise<void> {
        if (this.form.invalid) {
            return;
        }
        this.saving.set(true);
        const raw = this.form.getRawValue();
        const payload = {
            statusId: Number(raw.statusId),
            name: raw.name,
            description: (raw.description ?? '').trim() === '' ? null : raw.description,
            tuning: raw.tuning.trim() === '' ? null : raw.tuning.trim(),
            capo: raw.capo !== null ? Number(raw.capo) : null,
            targetTempoBpm: raw.targetTempoBpm !== null ? Number(raw.targetTempoBpm) : null,
            difficulty: raw.difficulty === '' ? null : raw.difficulty,
            tagIds: this.selectedTagIds(),
        };
        try {
            const existing = this.lecture();
            if (existing) {
                const saved = await this.lectureService.updateLecture(existing.id, payload);
                this.alertService.success(await this.translate.instant('app.board.lectureUpdated') as string);
                this.applyLecture(saved);
            } else {
                const created = await this.lectureService.createLecture(this.courseId(), payload);
                this.alertService.success(await this.translate.instant('app.board.lectureCreated') as string);
                // Land on the freshly-created lecture's full page.
                await this.router.navigate(['courses', this.courseId(), 'lectures', created.id]);
            }
        } catch {
            // error interceptor
        } finally {
            this.saving.set(false);
        }
    }

    protected async onDelete(): Promise<void> {
        const existing = this.lecture();
        if (!existing) {
            return;
        }
        const confirmMessage = await this.translate.instant('app.board.deleteLectureConfirm', {name: existing.name}) as string;
        if (!confirm(confirmMessage)) {
            return;
        }
        try {
            await this.lectureService.deleteLecture(existing.id);
            this.alertService.success(await this.translate.instant('app.board.lectureDeleted') as string);
            await this.navigateToBoard();
        } catch {
            // error interceptor
        }
    }

    protected onCancel(): void {
        void this.navigateToBoard();
    }

    protected async onArchive(): Promise<void> {
        const existing = this.lecture();
        if (!existing) {
            return;
        }
        this.archiving.set(true);
        try {
            const updated = await this.lectureService.archiveLecture(existing.id);
            this.alertService.success(await this.translate.instant('app.board.drawer.archived') as string);
            this.lecture.set(updated);
        } catch {
            // error interceptor
        } finally {
            this.archiving.set(false);
        }
    }

    protected async onUnarchive(): Promise<void> {
        const existing = this.lecture();
        if (!existing) {
            return;
        }
        this.archiving.set(true);
        try {
            const updated = await this.lectureService.unarchiveLecture(existing.id);
            this.alertService.success(await this.translate.instant('app.board.drawer.unarchived') as string);
            this.lecture.set(updated);
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

    protected addTagToLecture(tag: Tag): void {
        this.selectedTagIds.update((ids) => ids.includes(tag.id) ? ids : [...ids, tag.id]);
        this.tagPickerOpen.set(false);
    }

    protected removeTagFromLecture(tag: Tag): void {
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
        const existing = this.lecture();
        if (!existing) {
            return;
        }
        this.uploading.set(true);
        try {
            const uploaded = await this.lectureService.uploadLectureFile(existing.id, file);
            this.files.update((current) => [...current, uploaded]);
            this.alertService.success(await this.translate.instant('app.board.drawer.files.uploaded') as string);
        } catch {
            // error interceptor
        } finally {
            this.uploading.set(false);
        }
    }

    protected async onDownloadFile(file: LectureFile): Promise<void> {
        const existing = this.lecture();
        if (!existing) {
            return;
        }
        try {
            const blob = await this.lectureService.downloadLectureFile(existing.id, file.id);
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

    protected async onDeleteFile(file: LectureFile): Promise<void> {
        const existing = this.lecture();
        if (!existing) {
            return;
        }
        const message = await this.translate.instant('app.board.drawer.files.deleteConfirm', {name: file.filename}) as string;
        if (!confirm(message)) {
            return;
        }
        try {
            await this.lectureService.deleteLectureFile(existing.id, file.id);
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

    private async loadFiles(lectureId: number): Promise<void> {
        try {
            const list = await this.lectureService.listLectureFiles(lectureId);
            this.files.set(list);
        } catch {
            // ignore — lecture may have just been created
        }
    }

    private async loadWatchers(lectureId: number): Promise<void> {
        try {
            const result = await this.lectureWatcherService.list(lectureId);
            this.watchers.set(result.watchers);
            this.watching.set(result.watching);
        } catch {
            this.watchers.set([]);
            this.watching.set(false);
        }
    }

    protected async toggleWatch(): Promise<void> {
        const current = this.lecture();
        if (current === null || this.watchToggling()) {
            return;
        }
        this.watchToggling.set(true);
        try {
            const result = this.watching()
                ? await this.lectureWatcherService.unwatch(current.id)
                : await this.lectureWatcherService.watch(current.id);
            this.watchers.set(result.watchers);
            this.watching.set(result.watching);
        } catch {
            // error interceptor surfaces failures
        } finally {
            this.watchToggling.set(false);
        }
    }
}
