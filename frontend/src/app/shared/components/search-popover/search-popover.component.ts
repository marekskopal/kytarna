import {ChangeDetectionStrategy, Component, computed, effect, ElementRef, inject, input, OnDestroy, output, signal, ViewChild} from '@angular/core';
import {SearchHit} from '@app/models/search';
import {SearchService} from '@app/services/search.service';
import {TranslatePipe} from '@ngx-translate/core';

const DebounceMs = 200;
const MinQueryLength = 2;

@Component({
    selector: 'uk-search-popover',
    standalone: true,
    imports: [TranslatePipe],
    changeDetection: ChangeDetectionStrategy.OnPush,
    templateUrl: './search-popover.component.html',
    styleUrl: './search-popover.component.scss',
})
export class SearchPopoverComponent implements OnDestroy {
    private readonly searchService = inject(SearchService);

    public readonly open = input.required<boolean>();

    public readonly selected = output<SearchHit>();
    public readonly closed = output<void>();

    @ViewChild('queryInput') protected queryInput?: ElementRef<HTMLInputElement>;

    protected readonly query = signal('');
    protected readonly hits = signal<SearchHit[]>([]);
    protected readonly loading = signal(false);
    protected readonly errored = signal(false);
    protected readonly totalHits = signal(0);
    protected readonly activeIndex = signal(0);
    protected readonly hasQuery = computed(() => this.query().trim().length >= MinQueryLength);

    private debounceHandle: ReturnType<typeof setTimeout> | null = null;
    private requestSeq = 0;

    public constructor() {
        effect(() => {
            if (this.open()) {
                queueMicrotask(() => this.queryInput?.nativeElement.focus());
            } else {
                this.reset();
            }
        });
    }

    public ngOnDestroy(): void {
        if (this.debounceHandle !== null) {
            clearTimeout(this.debounceHandle);
        }
    }

    protected onInput(value: string): void {
        this.query.set(value);
        if (this.debounceHandle !== null) {
            clearTimeout(this.debounceHandle);
        }
        if (!this.hasQuery()) {
            this.hits.set([]);
            this.totalHits.set(0);
            this.errored.set(false);
            return;
        }
        this.debounceHandle = setTimeout(() => this.runSearch(), DebounceMs);
    }

    protected onKeydown(event: KeyboardEvent): void {
        switch (event.key) {
            case 'ArrowDown':
                event.preventDefault();
                this.moveActive(1);
                break;
            case 'ArrowUp':
                event.preventDefault();
                this.moveActive(-1);
                break;
            case 'Enter': {
                const hit = this.hits()[this.activeIndex()];
                if (hit) {
                    event.preventDefault();
                    this.choose(hit);
                }
                break;
            }
            case 'Escape':
                event.preventDefault();
                this.closed.emit();
                break;
            default:
                break;
        }
    }

    protected choose(hit: SearchHit): void {
        this.selected.emit(hit);
    }

    protected hover(index: number): void {
        this.activeIndex.set(index);
    }

    protected renderSnippet(snippet: string | null): string | null {
        // The snippet is Meilisearch highlight output: raw, unescaped task/comment/field
        // content with <mark> markers around matches. Returning it as a plain string lets
        // Angular's [innerHTML] sanitizer strip any scripts/event handlers from the
        // user-controlled content while preserving the <mark> highlights. Never wrap this
        // in bypassSecurityTrustHtml — that would reintroduce stored XSS.
        if (snippet === null || snippet === '') {
            return null;
        }
        return snippet;
    }

    private moveActive(delta: number): void {
        const max = this.hits().length;
        if (max === 0) {
            return;
        }
        const next = (this.activeIndex() + delta + max) % max;
        this.activeIndex.set(next);
    }

    private async runSearch(): Promise<void> {
        const q = this.query().trim();
        if (q.length < MinQueryLength) {
            return;
        }
        const seq = ++this.requestSeq;
        this.loading.set(true);
        this.errored.set(false);
        try {
            const result = await this.searchService.search({q, limit: 12});
            if (seq !== this.requestSeq) {
                return;
            }
            this.hits.set(result.hits);
            this.totalHits.set(result.estimatedTotalHits);
            this.activeIndex.set(0);
        } catch {
            if (seq !== this.requestSeq) {
                return;
            }
            this.errored.set(true);
            this.hits.set([]);
            this.totalHits.set(0);
        } finally {
            if (seq === this.requestSeq) {
                this.loading.set(false);
            }
        }
    }

    private reset(): void {
        if (this.debounceHandle !== null) {
            clearTimeout(this.debounceHandle);
            this.debounceHandle = null;
        }
        this.requestSeq++;
        this.query.set('');
        this.hits.set([]);
        this.totalHits.set(0);
        this.loading.set(false);
        this.errored.set(false);
        this.activeIndex.set(0);
    }
}
