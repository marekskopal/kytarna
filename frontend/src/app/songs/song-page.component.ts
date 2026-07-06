import {ChangeDetectionStrategy, Component, computed, inject, OnDestroy, OnInit, signal} from '@angular/core';
import {toSignal} from '@angular/core/rxjs-interop';
import {FormBuilder, ReactiveFormsModule, Validators} from '@angular/forms';
import {ActivatedRoute, Router} from '@angular/router';
import {Course} from '@app/models/course';
import {Difficulty} from '@app/models/lecture';
import {LectureWatcher} from '@app/models/lecture-watcher';
import {Song} from '@app/models/song';
import {SongFile} from '@app/models/song-file';
import {LEARNING_STATUSES, LearningStatus, statusColorVar} from '@app/models/status';
import {Tag} from '@app/models/tag';
import {AlertService} from '@app/services/alert.service';
import {CourseService} from '@app/services/course.service';
import {CurrentUserService} from '@app/services/current-user.service';
import {LectureWatcherService} from '@app/services/lecture-watcher.service';
import {SongService} from '@app/services/song.service';
import {TagService} from '@app/services/tag.service';
import {WorkspaceService} from '@app/services/workspace.service';
import {pickReadableForeground} from '@app/shared/color-contrast';
import {MarkdownEditorComponent} from '@app/shared/components/markdown-editor/markdown-editor.component';
import {FileTypeChip, fileTypeChip, formatFileSize} from '@app/shared/file-type-chip';
import {StatusLabelPipe} from '@app/shared/pipes/status-label.pipe';
import {TranslatePipe, TranslateService} from '@ngx-translate/core';

import {LectureLinksComponent} from '../board/lecture-links.component';
import {LectureProgressComponent} from '../board/lecture-progress.component';
import {LectureTabsComponent} from '../board/lecture-tabs.component';

type SongPanel = 'details' | 'tabs' | 'progress' | 'links';
const DIFFICULTIES: Difficulty[] = ['Beginner', 'Intermediate', 'Advanced'];

@Component({
    selector: 'uk-song-page',
    standalone: true,
    imports: [
        ReactiveFormsModule,
        MarkdownEditorComponent,
        TranslatePipe,
        StatusLabelPipe,
        LectureTabsComponent,
        LectureProgressComponent,
        LectureLinksComponent,
    ],
    changeDetection: ChangeDetectionStrategy.OnPush,
    templateUrl: './song-page.component.html',
    styleUrl: './song-page.component.scss',
})
export class SongPageComponent implements OnInit, OnDestroy {
    private readonly route = inject(ActivatedRoute);
    private readonly router = inject(Router);
    private readonly fb = inject(FormBuilder);
    private readonly songService = inject(SongService);
    private readonly courseService = inject(CourseService);
    private readonly tagService = inject(TagService);
    private readonly workspaceService = inject(WorkspaceService);
    private readonly currentUserService = inject(CurrentUserService);
    private readonly watcherService = inject(LectureWatcherService);
    private readonly alertService = inject(AlertService);
    private readonly translate = inject(TranslateService);

    protected readonly song = signal<Song | null>(null);
    protected readonly courses = signal<Course[]>([]);
    protected readonly workspaceTags = signal<Tag[]>([]);
    protected readonly loaded = signal(false);
    protected readonly isCreate = signal(false);
    protected readonly saving = signal(false);
    protected readonly archiving = signal(false);
    protected readonly uploadingCover = signal(false);
    protected readonly coverUrl = signal<string | null>(null);

    protected readonly statuses = LEARNING_STATUSES;
    protected readonly difficulties = DIFFICULTIES;

    protected readonly isArchived = computed<boolean>(() => this.song()?.archivedAt != null);
    protected readonly descriptionInitialTab = computed<'edit' | 'preview'>(() =>
        this.song() === null ? 'edit' : 'preview',
    );

    private readonly status = signal<LearningStatus>('ToLearn');
    protected readonly currentStatus = computed<LearningStatus>(() => this.status());
    protected readonly currentStatusColor = computed<string>(() => statusColorVar(this.status()));
    protected readonly currentStatusIsActive = computed<boolean>(() => this.status() === 'Learning');

    // ─── Tags ───────────────────────────────────────────────────
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

    // ─── Files ──────────────────────────────────────────────────
    protected readonly files = signal<SongFile[]>([]);
    protected readonly uploading = signal(false);

    // ─── Watchers ───────────────────────────────────────────────
    protected readonly watching = signal(false);
    protected readonly watchers = signal<LectureWatcher[]>([]);
    protected readonly watchToggling = signal(false);

    // ─── Panels ─────────────────────────────────────────────────
    protected readonly panel = signal<SongPanel>('details');

    protected setPanel(panel: SongPanel): void {
        this.panel.set(panel);
    }

    protected readonly form = this.fb.nonNullable.group({
        name: ['', Validators.required],
        description: [''],
        status: ['ToLearn' as LearningStatus, Validators.required],
        tuning: [''],
        capo: [null as number | null],
        targetTempoBpm: [null as number | null],
        difficulty: ['' as Difficulty | ''],
        authorName: [''],
        albumName: [''],
    });

    private readonly formValue = toSignal(this.form.valueChanges, {initialValue: this.form.getRawValue()});

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

    protected readonly difficultyColor = computed<string>(() => {
        switch (this.formValue().difficulty) {
            case 'Advanced': return 'var(--color-accent)';
            case 'Intermediate': return 'var(--color-warn)';
            case 'Beginner': return 'var(--color-success)';
            default: return 'var(--color-text-subtle)';
        }
    });

    // Current tempo is tracked in the Progress panel, so the header shows a dash.
    protected readonly currentBpmDisplay = '—';

    /** courseId of the attached course, `null` when the song is standalone. */
    protected readonly courseId = signal<number | null>(null);

    protected readonly attachedCourseName = computed<string | null>(() => {
        const id = this.courseId();
        if (id === null) {
            return null;
        }
        return this.courses().find((c) => c.id === id)?.name ?? this.song()?.courseName ?? null;
    });

    public async ngOnInit(): Promise<void> {
        await Promise.all([
            this.loadCourses(),
            this.loadWorkspaceTags(),
        ]);

        const songIdParam = this.route.snapshot.paramMap.get('id');
        if (songIdParam !== null && songIdParam !== '') {
            try {
                const existing = await this.songService.getSong(Number(songIdParam));
                this.applySong(existing);
            } catch {
                await this.navigateToLibrary();
                return;
            }
        } else {
            this.isCreate.set(true);
            const courseParam = this.route.snapshot.queryParamMap.get('courseId');
            const parsed = courseParam !== null ? Number(courseParam) : NaN;
            if (Number.isFinite(parsed) && parsed > 0) {
                this.courseId.set(parsed);
            }
        }

        this.form.controls.status.valueChanges.subscribe((value) => {
            this.status.set(value);
        });

        this.loaded.set(true);
    }

    public ngOnDestroy(): void {
        this.revokeCover();
    }

    private applySong(existing: Song): void {
        this.song.set(existing);
        this.isCreate.set(false);
        this.courseId.set(existing.courseId);
        this.form.patchValue({
            name: existing.name,
            description: existing.description ?? '',
            status: existing.status,
            tuning: existing.tuning ?? '',
            capo: existing.capo,
            targetTempoBpm: existing.targetTempoBpm,
            difficulty: existing.difficulty ?? '',
            authorName: existing.authorName ?? '',
            albumName: existing.albumName ?? '',
        });
        this.status.set(existing.status);
        this.selectedTagIds.set([...(existing.tagIds ?? [])]);
        void this.loadCover(existing);
        void this.loadFiles(existing.id);
        void this.loadWatchers(existing.id);
    }

    private async loadCourses(): Promise<void> {
        try {
            this.courses.set(await this.courseService.getCourses());
        } catch {
            this.courses.set([]);
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

    private async loadCover(song: Song): Promise<void> {
        this.revokeCover();
        if (!song.hasCover) {
            return;
        }
        try {
            const blob = await this.songService.downloadCover(song.id);
            this.coverUrl.set(URL.createObjectURL(blob));
        } catch {
            this.coverUrl.set(null);
        }
    }

    private revokeCover(): void {
        const url = this.coverUrl();
        if (url !== null) {
            URL.revokeObjectURL(url);
            this.coverUrl.set(null);
        }
    }

    private navigateToLibrary(): Promise<boolean> {
        return this.router.navigate(['songs']);
    }

    protected selectStatus(status: LearningStatus): void {
        this.form.controls.status.setValue(status);
    }

    protected statusDotColor(status: LearningStatus): string {
        return statusColorVar(status);
    }

    protected async onSubmit(): Promise<void> {
        if (this.form.invalid) {
            return;
        }
        this.saving.set(true);
        const raw = this.form.getRawValue();
        const payload = {
            name: raw.name,
            status: raw.status,
            description: (raw.description ?? '').trim() === '' ? null : raw.description,
            tuning: raw.tuning.trim() === '' ? null : raw.tuning.trim(),
            capo: raw.capo !== null ? Number(raw.capo) : null,
            targetTempoBpm: raw.targetTempoBpm !== null ? Number(raw.targetTempoBpm) : null,
            difficulty: raw.difficulty === '' ? null : raw.difficulty,
            authorName: raw.authorName.trim() === '' ? null : raw.authorName.trim(),
            albumName: raw.albumName.trim() === '' ? null : raw.albumName.trim(),
            tagIds: this.selectedTagIds(),
        };
        try {
            const existing = this.song();
            if (existing) {
                const saved = await this.songService.updateSong(existing.id, payload);
                this.alertService.success(await this.translate.instant('app.songs.updated') as string);
                this.applySong(saved);
            } else {
                const created = await this.songService.createSong({...payload, courseId: this.courseId()});
                this.alertService.success(await this.translate.instant('app.songs.created') as string);
                await this.router.navigate(['songs', created.id]);
            }
        } catch {
            // error interceptor
        } finally {
            this.saving.set(false);
        }
    }

    protected async onDelete(): Promise<void> {
        const existing = this.song();
        if (!existing) {
            return;
        }
        const confirmMessage = await this.translate.instant('app.songs.deleteConfirm', {name: existing.name}) as string;
        if (!confirm(confirmMessage)) {
            return;
        }
        try {
            await this.songService.deleteSong(existing.id);
            this.alertService.success(await this.translate.instant('app.songs.deleted') as string);
            await this.navigateToLibrary();
        } catch {
            // error interceptor
        }
    }

    protected onCancel(): void {
        void this.navigateToLibrary();
    }

    protected async onArchive(): Promise<void> {
        const existing = this.song();
        if (!existing) {
            return;
        }
        this.archiving.set(true);
        try {
            const updated = await this.songService.archiveSong(existing.id);
            this.song.set(updated);
        } catch {
            // error interceptor
        } finally {
            this.archiving.set(false);
        }
    }

    protected async onUnarchive(): Promise<void> {
        const existing = this.song();
        if (!existing) {
            return;
        }
        this.archiving.set(true);
        try {
            const updated = await this.songService.unarchiveSong(existing.id);
            this.song.set(updated);
        } catch {
            // error interceptor
        } finally {
            this.archiving.set(false);
        }
    }

    // ─── Cover image ────────────────────────────────────────────
    protected async onCoverSelected(event: Event): Promise<void> {
        const target = event.target as HTMLInputElement;
        const file = target.files?.[0];
        target.value = '';
        const existing = this.song();
        if (!file || !existing) {
            return;
        }
        this.uploadingCover.set(true);
        try {
            const updated = await this.songService.uploadCover(existing.id, file);
            this.song.set(updated);
            await this.loadCover(updated);
            this.alertService.success(await this.translate.instant('app.songs.coverUpdated') as string);
        } catch {
            // error interceptor
        } finally {
            this.uploadingCover.set(false);
        }
    }

    protected async onRemoveCover(): Promise<void> {
        const existing = this.song();
        if (!existing) {
            return;
        }
        try {
            const updated = await this.songService.deleteCover(existing.id);
            this.song.set(updated);
            this.revokeCover();
            this.alertService.success(await this.translate.instant('app.songs.coverRemoved') as string);
        } catch {
            // error interceptor
        }
    }

    // ─── Course attach / detach ─────────────────────────────────
    protected async onCourseChange(event: Event): Promise<void> {
        const value = (event.target as HTMLSelectElement).value;
        const nextCourseId = value === '' ? null : Number(value);
        const existing = this.song();
        if (!existing) {
            // CREATE mode — just remember the choice; it rides along on create.
            this.courseId.set(nextCourseId);
            return;
        }
        try {
            const updated = await this.songService.setCourse(existing.id, nextCourseId);
            this.applySong(updated);
            this.alertService.success(await this.translate.instant(
                nextCourseId === null ? 'app.songs.detached' : 'app.songs.attached',
            ) as string);
        } catch {
            // error interceptor
        }
    }

    // ─── Tags ───────────────────────────────────────────────────
    protected toggleTagPicker(): void {
        this.tagPickerOpen.update((v) => !v);
    }

    protected addTagToSong(tag: Tag): void {
        this.selectedTagIds.update((ids) => ids.includes(tag.id) ? ids : [...ids, tag.id]);
        this.tagPickerOpen.set(false);
    }

    protected removeTagFromSong(tag: Tag): void {
        this.selectedTagIds.update((ids) => ids.filter((id) => id !== tag.id));
    }

    protected tagForeground(color: string): string {
        return pickReadableForeground(color);
    }

    // ─── Files ──────────────────────────────────────────────────
    protected async onFileSelected(event: Event): Promise<void> {
        const target = event.target as HTMLInputElement;
        const file = target.files?.[0];
        target.value = '';
        if (!file) {
            return;
        }
        const existing = this.song();
        if (!existing) {
            return;
        }
        this.uploading.set(true);
        try {
            const uploaded = await this.songService.uploadSongFile(existing.id, file);
            this.files.update((current) => [...current, uploaded]);
            this.alertService.success(await this.translate.instant('app.board.drawer.files.uploaded') as string);
        } catch {
            // error interceptor
        } finally {
            this.uploading.set(false);
        }
    }

    protected async onDownloadFile(file: SongFile): Promise<void> {
        const existing = this.song();
        if (!existing) {
            return;
        }
        try {
            const blob = await this.songService.downloadSongFile(existing.id, file.id);
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

    protected async onDeleteFile(file: SongFile): Promise<void> {
        const existing = this.song();
        if (!existing) {
            return;
        }
        const message = await this.translate.instant('app.board.drawer.files.deleteConfirm', {name: file.filename}) as string;
        if (!confirm(message)) {
            return;
        }
        try {
            await this.songService.deleteSongFile(existing.id, file.id);
            this.files.update((current) => current.filter((f) => f.id !== file.id));
        } catch {
            // error interceptor
        }
    }

    protected formatFileSize(size: number): string {
        return formatFileSize(size);
    }

    protected fileTypeChip(filename: string): FileTypeChip {
        return fileTypeChip(filename);
    }

    private async loadFiles(songId: number): Promise<void> {
        try {
            this.files.set(await this.songService.listSongFiles(songId));
        } catch {
            // ignore — song may have just been created
        }
    }

    // ─── Watchers ───────────────────────────────────────────────
    private async loadWatchers(songId: number): Promise<void> {
        try {
            const result = await this.watcherService.list(songId, 'songs');
            this.watchers.set(result.watchers);
            this.watching.set(result.watching);
        } catch {
            this.watchers.set([]);
            this.watching.set(false);
        }
    }

    protected async toggleWatch(): Promise<void> {
        const current = this.song();
        if (current === null || this.watchToggling()) {
            return;
        }
        this.watchToggling.set(true);
        try {
            const result = this.watching()
                ? await this.watcherService.unwatch(current.id, 'songs')
                : await this.watcherService.watch(current.id, 'songs');
            this.watchers.set(result.watchers);
            this.watching.set(result.watching);
        } catch {
            // error interceptor surfaces failures
        } finally {
            this.watchToggling.set(false);
        }
    }
}
