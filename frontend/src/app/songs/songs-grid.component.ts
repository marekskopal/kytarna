import {ChangeDetectionStrategy, Component, computed, effect, ElementRef, HostListener, inject, signal, viewChild} from '@angular/core';
import {takeUntilDestroyed} from '@angular/core/rxjs-interop';
import {FormControl, ReactiveFormsModule} from '@angular/forms';
import {Router} from '@angular/router';
import {ArchivedFilter, Difficulty, LectureOrderBy, OrderDirection} from '@app/models/lecture';
import {Song} from '@app/models/song';
import {LEARNING_STATUSES, LearningStatus, statusColorVar} from '@app/models/status';
import {SongService} from '@app/services/song.service';
import {PaginationComponent} from '@app/shared/components/pagination/pagination.component';
import {StatusLabelPipe} from '@app/shared/pipes/status-label.pipe';
import {TranslatePipe} from '@ngx-translate/core';
import {debounceTime, distinctUntilChanged} from 'rxjs';

interface QueryParams {
    limit: number;
    offset: number;
    orderBy: LectureOrderBy;
    orderDirection: OrderDirection;
    search: string | undefined;
    statuses: LearningStatus[] | undefined;
    onlyActive: boolean | undefined;
    archived: ArchivedFilter | undefined;
}

@Component({
    selector: 'uk-songs-grid',
    standalone: true,
    imports: [ReactiveFormsModule, PaginationComponent, TranslatePipe, StatusLabelPipe],
    changeDetection: ChangeDetectionStrategy.OnPush,
    templateUrl: './songs-grid.component.html',
    styleUrl: './songs-grid.component.scss',
})
export class SongsGridComponent {
    private readonly songService = inject(SongService);
    private readonly router = inject(Router);

    protected readonly searchControl = new FormControl<string>('', {nonNullable: true});
    protected readonly search = signal<string>('');

    protected readonly statuses = LEARNING_STATUSES;
    protected readonly selectedStatuses = signal<LearningStatus[]>([]);
    protected readonly onlyActive = signal<boolean>(false);
    protected readonly archived = signal<ArchivedFilter>('active');
    protected readonly sortBy = signal<LectureOrderBy>('created_at');
    protected readonly sortDirection = signal<OrderDirection>('DESC');
    protected readonly page = signal<number>(1);
    protected readonly pageSize = signal<number>(50);

    protected readonly songs = signal<Song[]>([]);
    protected readonly count = signal<number>(0);
    protected readonly loading = signal<boolean>(false);

    private readonly statusDetails = viewChild<ElementRef<HTMLDetailsElement>>('statusDetails');

    protected readonly offset = computed<number>(() => (this.page() - 1) * this.pageSize());

    private readonly queryParams = computed<QueryParams>(() => ({
        limit: this.pageSize(),
        offset: this.offset(),
        orderBy: this.sortBy(),
        orderDirection: this.sortDirection(),
        search: this.search() === '' ? undefined : this.search(),
        statuses: this.selectedStatuses().length > 0 ? this.selectedStatuses() : undefined,
        onlyActive: this.onlyActive() ? true : undefined,
        archived: this.archived() === 'active' ? undefined : this.archived(),
    }));

    public constructor() {
        this.searchControl.valueChanges
            .pipe(debounceTime(300), distinctUntilChanged(), takeUntilDestroyed())
            .subscribe((value) => {
                this.search.set(value);
                this.page.set(1);
            });

        effect(() => {
            const params = this.queryParams();
            void this.fetchSongs(params);
        });
    }

    private async fetchSongs(params: QueryParams): Promise<void> {
        this.loading.set(true);
        try {
            const result = await this.songService.getSongs({
                limit: params.limit,
                offset: params.offset,
                orderBy: params.orderBy,
                orderDirection: params.orderDirection,
                search: params.search,
                statuses: params.statuses,
                onlyActive: params.onlyActive,
                archived: params.archived,
            });
            this.songs.set(result.songs);
            this.count.set(result.count);
        } catch {
            this.songs.set([]);
            this.count.set(0);
        } finally {
            this.loading.set(false);
        }
    }

    @HostListener('document:click', ['$event.target'])
    protected onDocumentClick(target: EventTarget | null): void {
        if (!(target instanceof Node)) {
            return;
        }
        const el = this.statusDetails()?.nativeElement;
        if (el?.open && !el.contains(target)) {
            el.open = false;
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
        this.onlyActive.set(false);
        this.archived.set('active');
        this.page.set(1);
    }

    protected statusDotColor(status: LearningStatus): string {
        return statusColorVar(status);
    }

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

    protected byline(song: Song): string {
        return [song.authorName, song.albumName].filter((v) => v !== null && v !== '').join(' · ');
    }

    protected onPageChange(page: number): void {
        this.page.set(page);
    }

    protected onPageSizeChange(size: number): void {
        this.pageSize.set(size);
        this.page.set(1);
    }

    protected onNew(): void {
        void this.router.navigate(['songs', 'new']);
    }

    protected onRowClick(song: Song): void {
        void this.router.navigate(['songs', song.id]);
    }

    protected formatCreated(iso: string): string {
        const date = new Date(iso);
        if (Number.isNaN(date.getTime())) {
            return iso;
        }
        return date.toLocaleDateString(undefined, {year: 'numeric', month: 'short', day: '2-digit'});
    }
}
