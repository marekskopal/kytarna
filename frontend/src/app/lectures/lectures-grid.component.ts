import {ChangeDetectionStrategy, Component, computed, effect, ElementRef, HostListener, inject, OnInit, signal, viewChild} from '@angular/core';
import {takeUntilDestroyed} from '@angular/core/rxjs-interop';
import {FormControl, ReactiveFormsModule} from '@angular/forms';
import {ActivatedRoute, ParamMap, Router} from '@angular/router';
import {ArchivedFilter, Difficulty, LectureListItem, LectureOrderBy,OrderDirection} from '@app/models/lecture';
import {SavedView, SavedViewFilters} from '@app/models/saved-view';
import {LEARNING_STATUSES, LearningStatus, statusColorVar} from '@app/models/status';
import {Tag} from '@app/models/tag';
import {CurrentUserService} from '@app/services/current-user.service';
import {BulkOp, BulkResult, LectureService} from '@app/services/lecture.service';
import {SavedViewService} from '@app/services/saved-view.service';
import {TagService} from '@app/services/tag.service';
import {WorkspaceService} from '@app/services/workspace.service';
import {pickReadableForeground} from '@app/shared/color-contrast';
import {PaginationComponent} from '@app/shared/components/pagination/pagination.component';
import {StatusLabelPipe} from '@app/shared/pipes/status-label.pipe';
import {TranslatePipe, TranslateService} from '@ngx-translate/core';
import {debounceTime, distinctUntilChanged} from 'rxjs';

interface QueryParams {
    limit: number;
    offset: number;
    orderBy: LectureOrderBy;
    orderDirection: OrderDirection;
    search: string | undefined;
    statuses: LearningStatus[] | undefined;
    tagIds: number[] | undefined;
    onlyActive: boolean | undefined;
    archived: ArchivedFilter | undefined;
}

@Component({
    selector: 'uk-lectures-grid',
    standalone: true,
    imports: [
        ReactiveFormsModule, PaginationComponent, TranslatePipe, StatusLabelPipe,
    ],
    changeDetection: ChangeDetectionStrategy.OnPush,
    templateUrl: './lectures-grid.component.html',
    styleUrl: './lectures-grid.component.scss',
})
export class LecturesGridComponent implements OnInit {
    private readonly lectureService = inject(LectureService);
    private readonly tagService = inject(TagService);
    private readonly workspaceService = inject(WorkspaceService);
    private readonly currentUserService = inject(CurrentUserService);
    private readonly translate = inject(TranslateService);
    private readonly route = inject(ActivatedRoute);
    private readonly router = inject(Router);
    private readonly savedViewService = inject(SavedViewService);

    protected readonly searchControl = new FormControl<string>('', {nonNullable: true});
    protected readonly search = signal<string>('');

    protected readonly statuses = LEARNING_STATUSES;
    protected readonly selectedStatuses = signal<LearningStatus[]>([]);
    protected readonly selectedTagIds = signal<number[]>([]);
    protected readonly workspaceTags = signal<Tag[]>([]);
    protected readonly onlyActive = signal<boolean>(false);
    protected readonly archived = signal<ArchivedFilter>('active');
    protected readonly sortBy = signal<LectureOrderBy>('created_at');
    protected readonly sortDirection = signal<OrderDirection>('DESC');
    protected readonly page = signal<number>(1);
    protected readonly pageSize = signal<number>(50);

    protected readonly lectures = signal<LectureListItem[]>([]);
    protected readonly count = signal<number>(0);
    protected readonly loading = signal<boolean>(false);

    protected readonly views = this.savedViewService.views;
    protected readonly activeViewId = signal<number | null>(null);
    protected readonly savingView = signal<boolean>(false);
    protected readonly newViewName = signal<string>('');
    protected readonly defaultViewId = computed<number | null>(
        () => this.currentUserService.currentUser()?.defaultSavedViewId ?? null,
    );
    protected readonly activeViewName = computed<string | null>(() => {
        const id = this.activeViewId();
        if (id === null) return null;
        return this.views().find((v) => v.id === id)?.name ?? null;
    });

    // Bulk-selection state. Kept as a plain Set for cheap membership tests; signal value is the set ref.
    protected readonly selectedIds = signal<Set<number>>(new Set());
    protected readonly bulkBusy = signal<boolean>(false);
    protected readonly lastBulkResult = signal<BulkResult | null>(null);
    protected readonly bulkDetailsOpen = signal<boolean>(false);

    private readonly statusDetails = viewChild<ElementRef<HTMLDetailsElement>>('statusDetails');
    private readonly tagDetails = viewChild<ElementRef<HTMLDetailsElement>>('tagDetails');
    private readonly viewsDetails = viewChild<ElementRef<HTMLDetailsElement>>('viewsDetails');
    private readonly bulkMoveDetails = viewChild<ElementRef<HTMLDetailsElement>>('bulkMoveDetails');
    private readonly bulkAddTagDetails = viewChild<ElementRef<HTMLDetailsElement>>('bulkAddTagDetails');
    private readonly bulkRemoveTagDetails = viewChild<ElementRef<HTMLDetailsElement>>('bulkRemoveTagDetails');

    protected readonly tagById = computed<Map<number, Tag>>(() => {
        return new Map(this.workspaceTags().map((t) => [t.id, t]));
    });

    protected readonly offset = computed<number>(() => (this.page() - 1) * this.pageSize());

    protected readonly selectionCount = computed<number>(() => this.selectedIds().size);

    protected readonly pageIds = computed<number[]>(() => this.lectures().map((t) => t.id));

    protected readonly allOnPageSelected = computed<boolean>(() => {
        const ids = this.pageIds();
        if (ids.length === 0) return false;
        const sel = this.selectedIds();
        return ids.every((id) => sel.has(id));
    });

    protected readonly someOnPageSelected = computed<boolean>(() => {
        const ids = this.pageIds();
        if (ids.length === 0) return false;
        const sel = this.selectedIds();
        return ids.some((id) => sel.has(id)) && !this.allOnPageSelected();
    });

    private readonly queryParams = computed<QueryParams>(() => ({
        limit: this.pageSize(),
        offset: this.offset(),
        orderBy: this.sortBy(),
        orderDirection: this.sortDirection(),
        search: this.search() === '' ? undefined : this.search(),
        statuses: this.selectedStatuses().length > 0 ? this.selectedStatuses() : undefined,
        tagIds: this.selectedTagIds().length > 0 ? this.selectedTagIds() : undefined,
        onlyActive: this.onlyActive() ? true : undefined,
        archived: this.archived() === 'active' ? undefined : this.archived(),
    }));

    public ngOnInit(): void {
        void this.loadWorkspaceTags();
        void this.loadSavedViews();
        void this.openFromQueryParam();
    }

    /** Resolves `?open=CODE` (e.g. from the notification bell) to the lecture's routed page. */
    private async openFromQueryParam(): Promise<void> {
        const code = this.route.snapshot.queryParamMap.get('open');
        if (code === null || code === '') {
            return;
        }
        try {
            const lecture = await this.lectureService.getLecture(code);
            await this.router.navigate(['courses', lecture.courseId, 'lectures', lecture.id]);
        } catch {
            // lecture may have been deleted; ignore
        }
    }

    public constructor() {
        // Hydrate filter / sort / page signals from URL query params before any effects run.
        this.applyQueryParams(this.route.snapshot.queryParamMap);

        this.searchControl.valueChanges
            .pipe(debounceTime(300), distinctUntilChanged(), takeUntilDestroyed())
            .subscribe((value) => {
                this.search.set(value);
                this.page.set(1);
            });

        effect(() => {
            const params = this.queryParams();
            void this.fetchLectures(params);
        });

        // Push the current filter state back to the URL on every change. Skip the first tick to
        // avoid clobbering whatever the user landed on (the values we hydrated come from there).
        let firstUrlPush = true;
        effect(() => {
            const urlParams = this.urlParams();
            if (firstUrlPush) {
                firstUrlPush = false;
                return;
            }
            void this.router.navigate([], {relativeTo: this.route, queryParams: urlParams, replaceUrl: true});

            // Clear active view tag when state diverges from the view's saved config.
            const id = this.activeViewId();
            if (id !== null) {
                const view = this.views().find((v) => v.id === id);
                if (view) {
                    let savedFilters: SavedViewFilters | null;
                    try {
                        savedFilters = JSON.parse(view.filterConfig) as SavedViewFilters;
                    } catch {
                        savedFilters = null;
                    }
                    if (savedFilters === null || !this.currentMatchesFilters(savedFilters)) {
                        this.activeViewId.set(null);
                    }
                }
            }
        });
    }

    private applyQueryParams(map: ParamMap): void {
        const q = map.get('q') ?? '';
        if (q !== '') {
            this.searchControl.setValue(q, {emitEvent: false});
            this.search.set(q);
        }

        const statuses = parseStatusList(map.get('statuses'));
        if (statuses.length > 0) this.selectedStatuses.set(statuses);

        const tagIds = parseIdList(map.get('tagIds'));
        if (tagIds.length > 0) this.selectedTagIds.set(tagIds);

        if (map.get('onlyActive') === '1') {
            this.onlyActive.set(true);
        }

        const archived = map.get('archived');
        if (archived === 'archived' || archived === 'all') {
            this.archived.set(archived);
        }

        const orderBy = map.get('orderBy');
        if (orderBy === 'created_at' || orderBy === 'name' || orderBy === 'status') {
            this.sortBy.set(orderBy);
        }

        const orderDirection = map.get('orderDirection');
        if (orderDirection === 'ASC' || orderDirection === 'DESC') {
            this.sortDirection.set(orderDirection);
        }

        const page = Number.parseInt(map.get('page') ?? '', 10);
        if (Number.isFinite(page) && page > 0) {
            this.page.set(page);
        }

        const pageSize = Number.parseInt(map.get('pageSize') ?? '', 10);
        if (Number.isFinite(pageSize) && pageSize > 0) {
            this.pageSize.set(pageSize);
        }
    }

    private readonly urlParams = computed<Record<string, string>>(() => {
        const out: Record<string, string> = {};
        const s = this.search();
        if (s !== '') out['q'] = s;
        if (this.selectedStatuses().length > 0) out['statuses'] = this.selectedStatuses().join('|');
        if (this.selectedTagIds().length > 0) out['tagIds'] = this.selectedTagIds().join('|');
        if (this.onlyActive()) out['onlyActive'] = '1';
        if (this.archived() !== 'active') out['archived'] = this.archived();
        if (this.sortBy() !== 'created_at') out['orderBy'] = this.sortBy();
        if (this.sortDirection() !== 'DESC') out['orderDirection'] = this.sortDirection();
        if (this.page() !== 1) out['page'] = String(this.page());
        if (this.pageSize() !== 50) out['pageSize'] = String(this.pageSize());
        return out;
    });

    private async loadWorkspaceTags(): Promise<void> {
        const workspaceId = await this.resolveCurrentWorkspaceId();
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

    private async loadSavedViews(): Promise<void> {
        const workspaceId = await this.resolveCurrentWorkspaceId();
        if (workspaceId === null) {
            this.savedViewService.clearCache();
            return;
        }
        try {
            const views = await this.savedViewService.loadForWorkspace(workspaceId);
            // If the URL came in empty and the user has a default for this workspace, apply it.
            if (this.isEmptyFilterState() && this.route.snapshot.queryParamMap.keys.length === 0) {
                const defaultId = this.currentUserService.currentUser()?.defaultSavedViewId ?? null;
                if (defaultId !== null) {
                    const view = views.find((v) => v.id === defaultId);
                    if (view) {
                        this.applyView(view);
                    }
                }
            }
        } catch {
            // ignore — picker just shows an empty state
        }
    }

    private isEmptyFilterState(): boolean {
        return this.search() === ''
            && this.selectedStatuses().length === 0
            && this.selectedTagIds().length === 0
            && !this.onlyActive()
            && this.archived() === 'active'
            && this.page() === 1
            && this.sortBy() === 'created_at'
            && this.sortDirection() === 'DESC';
    }

    // ─── Saved views ──────────────────────────────────────────

    protected applyView(view: SavedView): void {
        let filters: SavedViewFilters;
        try {
            filters = JSON.parse(view.filterConfig) as SavedViewFilters;
        } catch {
            return;
        }
        this.clearFilters();
        if (filters.q !== undefined && filters.q !== '') {
            this.searchControl.setValue(filters.q, {emitEvent: false});
            this.search.set(filters.q);
        }
        if (filters.statuses && filters.statuses.length > 0) {
            this.selectedStatuses.set([...filters.statuses]);
        }
        if (filters.tagIds && filters.tagIds.length > 0) {
            this.selectedTagIds.set([...filters.tagIds]);
        }
        if (filters.onlyActive) {
            this.onlyActive.set(true);
        }
        if (filters.archived === 'archived' || filters.archived === 'all') {
            this.archived.set(filters.archived);
        }
        if (filters.orderBy) {
            this.sortBy.set(filters.orderBy);
        }
        if (filters.orderDirection) {
            this.sortDirection.set(filters.orderDirection);
        }
        if (filters.pageSize) {
            this.pageSize.set(filters.pageSize);
        }
        this.activeViewId.set(view.id);
        this.closeViewsDetails();
    }

    protected startSaveView(): void {
        this.newViewName.set('');
        this.savingView.set(true);
    }

    protected cancelSaveView(): void {
        this.savingView.set(false);
        this.newViewName.set('');
    }

    protected onNewViewNameInput(event: Event): void {
        this.newViewName.set((event.target as HTMLInputElement).value);
    }

    protected async confirmSaveView(): Promise<void> {
        const name = this.newViewName().trim();
        if (name === '') return;
        const workspaceId = await this.resolveCurrentWorkspaceId();
        if (workspaceId === null) return;

        const filters: SavedViewFilters = {};
        if (this.search() !== '') filters.q = this.search();
        if (this.selectedStatuses().length > 0) filters.statuses = [...this.selectedStatuses()];
        if (this.selectedTagIds().length > 0) filters.tagIds = [...this.selectedTagIds()];
        if (this.onlyActive()) filters.onlyActive = true;
        if (this.archived() !== 'active') filters.archived = this.archived();
        if (this.sortBy() !== 'created_at') filters.orderBy = this.sortBy();
        if (this.sortDirection() !== 'DESC') filters.orderDirection = this.sortDirection();
        if (this.pageSize() !== 50) filters.pageSize = this.pageSize();

        try {
            const view = await this.savedViewService.create(workspaceId, {
                name,
                filterConfig: JSON.stringify(filters),
            });
            this.activeViewId.set(view.id);
            this.savingView.set(false);
            this.newViewName.set('');
            this.closeViewsDetails();
        } catch {
            // error interceptor surfaces the failure
        }
    }

    protected async setAsDefault(view: SavedView): Promise<void> {
        try {
            await this.currentUserService.update({defaultSavedViewId: view.id});
        } catch {
            // ignore
        }
    }

    protected async deleteView(view: SavedView): Promise<void> {
        const prompt = await this.translate.get('app.savedViews.confirmDelete', {name: view.name}).toPromise();
        if (!confirm(typeof prompt === 'string' ? prompt : `Delete view "${view.name}"?`)) {
            return;
        }
        try {
            await this.savedViewService.delete(view.id);
            if (this.activeViewId() === view.id) {
                this.activeViewId.set(null);
            }
            // If we just deleted the local default, also reflect that locally — backend cleared it server-side.
            if (this.currentUserService.currentUser()?.defaultSavedViewId === view.id) {
                const u = this.currentUserService.currentUser();
                if (u) {
                    this.currentUserService.currentUser.set({...u, defaultSavedViewId: null});
                }
            }
        } catch {
            // ignore
        }
    }

    private closeViewsDetails(): void {
        const el = this.viewsDetails()?.nativeElement;
        if (el) el.open = false;
    }

    private currentMatchesFilters(saved: SavedViewFilters): boolean {
        const sameArray = <T>(a: T[], b: T[] | undefined): boolean => {
            const other = b ?? [];
            if (a.length !== other.length) return false;
            const sortedA = [...a].sort();
            const sortedB = [...other].sort();
            return sortedA.every((v, i) => v === sortedB[i]);
        };
        return this.search() === (saved.q ?? '')
            && sameArray(this.selectedStatuses(), saved.statuses)
            && sameArray(this.selectedTagIds(), saved.tagIds)
            && this.onlyActive() === (saved.onlyActive ?? false)
            && this.archived() === (saved.archived ?? 'active')
            && this.sortBy() === (saved.orderBy ?? 'created_at')
            && this.sortDirection() === (saved.orderDirection ?? 'DESC')
            && this.pageSize() === (saved.pageSize ?? 50);
    }

    private async resolveCurrentWorkspaceId(): Promise<number | null> {
        let workspaceId = this.workspaceService.currentWorkspaceId();
        if (workspaceId === null) {
            try {
                workspaceId = (await this.currentUserService.load()).currentWorkspaceId;
            } catch {
                workspaceId = null;
            }
        }
        return workspaceId;
    }

    private async fetchLectures(params: QueryParams): Promise<void> {
        this.loading.set(true);
        try {
            const result = await this.lectureService.getLectures({
                limit: params.limit,
                offset: params.offset,
                orderBy: params.orderBy,
                orderDirection: params.orderDirection,
                search: params.search,
                statuses: params.statuses,
                tagIds: params.tagIds,
                onlyActive: params.onlyActive,
                archived: params.archived,
            });
            this.lectures.set(result.lectures);
            this.count.set(result.count);
            this.pruneSelection();
        } catch {
            this.lectures.set([]);
            this.count.set(0);
        } finally {
            this.loading.set(false);
        }
    }

    private pruneSelection(): void {
        const sel = this.selectedIds();
        if (sel.size === 0) return;
        const visible = new Set(this.lectures().map((t) => t.id));
        let changed = false;
        const next = new Set<number>();
        for (const id of sel) {
            if (visible.has(id)) {
                next.add(id);
            } else {
                changed = true;
            }
        }
        if (changed) {
            this.selectedIds.set(next);
        }
    }

    @HostListener('document:click', ['$event.target'])
    protected onDocumentClick(target: EventTarget | null): void {
        if (!(target instanceof Node)) {
            return;
        }
        const refs = [
            this.statusDetails(), this.tagDetails(), this.viewsDetails(),
            this.bulkMoveDetails(), this.bulkAddTagDetails(), this.bulkRemoveTagDetails(),
        ];
        for (const ref of refs) {
            const el = ref?.nativeElement;
            if (el?.open && !el.contains(target)) {
                el.open = false;
            }
        }
    }

    protected onSortClick(column: LectureOrderBy): void {
        if (this.sortBy() === column) {
            this.sortDirection.set(this.sortDirection() === 'ASC' ? 'DESC' : 'ASC');
        } else {
            this.sortBy.set(column);
            this.sortDirection.set(column === 'created_at' ? 'DESC' : 'ASC');
        }
        this.page.set(1);
    }

    protected sortArrow(column: LectureOrderBy): string {
        if (this.sortBy() !== column) {
            return '';
        }
        return this.sortDirection() === 'ASC' ? '↑' : '↓';
    }

    protected onStatusToggle(status: LearningStatus, event: Event): void {
        const checked = (event.target as HTMLInputElement).checked;
        const current = this.selectedStatuses();
        if (checked && !current.includes(status)) {
            this.selectedStatuses.set([...current, status]);
        } else if (!checked) {
            this.selectedStatuses.set(current.filter((s) => s !== status));
        }
        this.page.set(1);
    }

    protected isStatusSelected(status: LearningStatus): boolean {
        return this.selectedStatuses().includes(status);
    }

    protected onTagToggle(tagId: number, event: Event): void {
        const checked = (event.target as HTMLInputElement).checked;
        const current = this.selectedTagIds();
        if (checked && !current.includes(tagId)) {
            this.selectedTagIds.set([...current, tagId]);
        } else if (!checked) {
            this.selectedTagIds.set(current.filter((id) => id !== tagId));
        }
        this.page.set(1);
    }

    protected isTagSelected(tagId: number): boolean {
        return this.selectedTagIds().includes(tagId);
    }

    protected onOnlyActiveToggle(event: Event): void {
        this.onlyActive.set((event.target as HTMLInputElement).checked);
        this.page.set(1);
    }

    protected onArchivedFilterChange(event: Event): void {
        const value = (event.target as HTMLSelectElement).value;
        this.archived.set(value === 'archived' || value === 'all' ? value : 'active');
        this.page.set(1);
    }

    protected clearFilters(): void {
        this.searchControl.setValue('');
        this.selectedStatuses.set([]);
        this.selectedTagIds.set([]);
        this.onlyActive.set(false);
        this.archived.set('active');
        this.page.set(1);
    }

    protected tagsForLecture(lectureTagIds: number[] | undefined): Tag[] {
        if (!lectureTagIds || lectureTagIds.length === 0) {
            return [];
        }
        const byId = this.tagById();
        const tags: Tag[] = [];
        for (const id of lectureTagIds) {
            const t = byId.get(id);
            if (t) tags.push(t);
        }
        return tags;
    }

    protected tagForeground(color: string): string {
        return pickReadableForeground(color);
    }

    // Difficulty hue rule (theme-aware tokens), shared with the lecture card.
    protected difficultyColor(difficulty: Difficulty): string {
        switch (difficulty) {
            case 'Advanced':
                return 'var(--color-accent)';
            case 'Intermediate':
                return 'var(--color-warn)';
            default:
                return 'var(--color-success)';
        }
    }

    // Status dot color (theme-aware; flips in dark mode).
    protected statusDotColor(status: LearningStatus): string {
        return statusColorVar(status);
    }

    protected onPageChange(page: number): void {
        this.page.set(page);
    }

    protected onPageSizeChange(size: number): void {
        this.pageSize.set(size);
        this.page.set(1);
    }

    // ─── Bulk selection ────────────────────────────────────────

    protected isRowSelected(id: number): boolean {
        return this.selectedIds().has(id);
    }

    protected toggleRow(id: number, event: Event): void {
        event.stopPropagation();
        const next = new Set(this.selectedIds());
        if (next.has(id)) {
            next.delete(id);
        } else {
            next.add(id);
        }
        this.selectedIds.set(next);
    }

    protected togglePage(event: Event): void {
        const checked = (event.target as HTMLInputElement).checked;
        const ids = this.pageIds();
        const next = new Set(this.selectedIds());
        if (checked) {
            for (const id of ids) {
                next.add(id);
            }
        } else {
            for (const id of ids) {
                next.delete(id);
            }
        }
        this.selectedIds.set(next);
    }

    protected clearSelection(): void {
        this.selectedIds.set(new Set());
    }

    private get selectedIdsArray(): number[] {
        return Array.from(this.selectedIds());
    }

    protected async onBulkMove(status: LearningStatus): Promise<void> {
        this.closeBulkPopovers();
        await this.runBulk('move', {status});
    }

    protected async onBulkAddTag(tagId: number): Promise<void> {
        this.closeBulkPopovers();
        await this.runBulk('tag', {tagIds: [tagId]});
    }

    protected async onBulkRemoveTag(tagId: number): Promise<void> {
        this.closeBulkPopovers();
        await this.runBulk('untag', {tagIds: [tagId]});
    }

    protected async onBulkDelete(): Promise<void> {
        this.closeBulkPopovers();
        const count = this.selectionCount();
        const msg = await this.translate
            .get('app.lectures.bulk.confirmDelete', {count})
            .toPromise();
        if (!confirm(typeof msg === 'string' ? msg : `Delete ${count} lecture(s)?`)) {
            return;
        }
        await this.runBulk('delete');
    }

    private async runBulk(op: BulkOp, payload?: Record<string, unknown>): Promise<void> {
        const ids = this.selectedIdsArray;
        if (ids.length === 0) {
            return;
        }
        this.bulkBusy.set(true);
        try {
            const result = await this.lectureService.bulkUpdate(ids, op, payload);
            this.lastBulkResult.set(result);

            // Remove succeeded-and-now-gone ids from selection optimistically; fetchLectures will prune the rest.
            if (op === 'delete') {
                const next = new Set(this.selectedIds());
                for (const id of result.succeeded) {
                    next.delete(id);
                }
                this.selectedIds.set(next);
            }

            await this.fetchLectures(this.queryParams());
        } catch {
            // error interceptor will surface failure
        } finally {
            this.bulkBusy.set(false);
        }
    }

    protected dismissBulkResult(): void {
        this.lastBulkResult.set(null);
        this.bulkDetailsOpen.set(false);
    }

    protected toggleBulkDetails(): void {
        this.bulkDetailsOpen.update((v) => !v);
    }

    private closeBulkPopovers(): void {
        const refs = [
            this.bulkMoveDetails(),
            this.bulkAddTagDetails(),
            this.bulkRemoveTagDetails(),
        ];
        for (const ref of refs) {
            const el = ref?.nativeElement;
            if (el) el.open = false;
        }
    }

    // ─── Rows ──────────────────────────────────────────────────

    protected onRowClick(row: LectureListItem): void {
        void this.router.navigate(['courses', row.courseId, 'lectures', row.id]);
    }

    protected formatCreated(iso: string): string {
        const date = new Date(iso);
        if (Number.isNaN(date.getTime())) {
            return iso;
        }
        return date.toLocaleDateString(undefined, {year: 'numeric', month: 'short', day: '2-digit'});
    }
}

function parseIdList(raw: string | null): number[] {
    if (raw === null || raw === '') {
        return [];
    }
    const out: number[] = [];
    for (const part of raw.split('|')) {
        const n = Number.parseInt(part, 10);
        if (Number.isFinite(n) && n > 0) {
            out.push(n);
        }
    }
    return out;
}

function parseStatusList(raw: string | null): LearningStatus[] {
    if (raw === null || raw === '') {
        return [];
    }
    const out: LearningStatus[] = [];
    for (const part of raw.split('|')) {
        if (LEARNING_STATUSES.includes(part as LearningStatus)) {
            out.push(part as LearningStatus);
        }
    }
    return out;
}
