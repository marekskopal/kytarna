import {ChangeDetectionStrategy, Component, computed, input, output} from '@angular/core';
import {TranslatePipe} from '@ngx-translate/core';

type PageToken = number | 'ellipsis-left' | 'ellipsis-right';

@Component({
    selector: 'uk-pagination',
    standalone: true,
    imports: [TranslatePipe],
    changeDetection: ChangeDetectionStrategy.OnPush,
    templateUrl: './pagination.component.html',
    styleUrl: './pagination.component.scss',
})
export class PaginationComponent {
    public readonly totalItems = input.required<number>();
    public readonly pageSize = input<number>(50);
    public readonly currentPage = input<number>(1);
    public readonly pageSizeOptions = input<number[]>([25, 50, 100, 200]);
    public readonly maxPageButtons = input<number>(7);

    public readonly pageChange = output<number>();
    public readonly pageSizeChange = output<number>();

    protected readonly totalPages = computed<number>(() => Math.max(1, Math.ceil(this.totalItems() / this.pageSize())));

    protected readonly pages = computed<PageToken[]>(() => {
        const total = this.totalPages();
        const current = Math.min(Math.max(1, this.currentPage()), total);
        const max = Math.max(5, this.maxPageButtons());

        if (total <= max) {
            return Array.from({length: total}, (_, i) => i + 1);
        }

        // Reserve slots for first, last and at least one ellipsis.
        const sidePad = Math.max(1, Math.floor((max - 3) / 2));
        let start = Math.max(2, current - sidePad);
        let end = Math.min(total - 1, current + sidePad);

        // Push window outward if it's clipped against either end.
        if (current <= sidePad + 2) {
            end = Math.min(total - 1, max - 2);
        }
        if (current >= total - sidePad - 1) {
            start = Math.max(2, total - (max - 3));
        }

        const tokens: PageToken[] = [1];
        if (start > 2) {
            tokens.push('ellipsis-left');
        }
        for (let p = start; p <= end; p++) {
            tokens.push(p);
        }
        if (end < total - 1) {
            tokens.push('ellipsis-right');
        }
        tokens.push(total);
        return tokens;
    });

    protected readonly hasPrev = computed<boolean>(() => this.currentPage() > 1);
    protected readonly hasNext = computed<boolean>(() => this.currentPage() < this.totalPages());

    protected readonly rangeStart = computed<number>(() => (this.currentPage() - 1) * this.pageSize() + 1);
    protected readonly rangeEnd = computed<number>(() => Math.min(this.totalItems(), this.currentPage() * this.pageSize()));

    protected isNumber(token: PageToken): token is number {
        return typeof token === 'number';
    }

    protected goTo(page: number): void {
        const clamped = Math.min(Math.max(1, page), this.totalPages());
        if (clamped !== this.currentPage()) {
            this.pageChange.emit(clamped);
        }
    }

    protected onPageSize(event: Event): void {
        const next = Number((event.target as HTMLSelectElement).value);
        if (!Number.isNaN(next) && next !== this.pageSize()) {
            this.pageSizeChange.emit(next);
        }
    }
}
