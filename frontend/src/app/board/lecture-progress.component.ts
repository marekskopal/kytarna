import {ChangeDetectionStrategy, Component, computed, inject, input, OnInit, signal} from '@angular/core';
import {FormBuilder, ReactiveFormsModule, Validators} from '@angular/forms';
import {PracticeSummary, ProgressEntry} from '@app/models/progress';
import {ProgressService} from '@app/services/progress.service';
import {TranslatePipe, TranslateService} from '@ngx-translate/core';

interface Sparkline {
    points: string;
    min: number;
    max: number;
    width: number;
    height: number;
}

const SPARK_W = 240;
const SPARK_H = 48;

/** Practice log for a lecture: timeline (newest first), a log form, and a summary + BPM sparkline. */
@Component({
    selector: 'uk-lecture-progress',
    standalone: true,
    imports: [ReactiveFormsModule, TranslatePipe],
    changeDetection: ChangeDetectionStrategy.OnPush,
    templateUrl: './lecture-progress.component.html',
    styleUrl: './lecture-progress.component.scss',
})
export class LectureProgressComponent implements OnInit {
    public readonly lectureId = input.required<number | string>();

    private readonly fb = inject(FormBuilder);
    private readonly progressService = inject(ProgressService);
    private readonly translate = inject(TranslateService);

    protected readonly entries = signal<ProgressEntry[]>([]);
    protected readonly summary = signal<PracticeSummary | null>(null);
    protected readonly saving = signal(false);
    protected readonly loading = signal(true);

    protected readonly form = this.fb.nonNullable.group({
        practicedAt: [this.today(), Validators.required],
        note: [''],
        tempoBpm: [null as number | null],
        durationMinutes: [null as number | null],
    });

    /** newest-first for the timeline. */
    protected readonly sortedEntries = computed<ProgressEntry[]>(() =>
        [...this.entries()].sort((a, b) => b.practicedAt.localeCompare(a.practicedAt)),
    );

    protected readonly sparkline = computed<Sparkline | null>(() => {
        const trend = this.summary()?.bpmTrend ?? [];
        if (trend.length < 2) {
            return null;
        }
        const values = trend.map((p) => p.tempoBpm);
        const min = Math.min(...values);
        const max = Math.max(...values);
        const span = max - min || 1;
        const step = trend.length > 1 ? SPARK_W / (trend.length - 1) : 0;
        const points = trend
            .map((p, i) => {
                const x = i * step;
                const y = SPARK_H - ((p.tempoBpm - min) / span) * SPARK_H;
                return `${x.toFixed(1)},${y.toFixed(1)}`;
            })
            .join(' ');
        return {points, min, max, width: SPARK_W, height: SPARK_H};
    });

    public ngOnInit(): void {
        void this.reload();
    }

    private async reload(): Promise<void> {
        this.loading.set(true);
        try {
            const [entries, summary] = await Promise.all([
                this.progressService.listEntries(this.lectureId()),
                this.progressService.getLectureSummary(this.lectureId()),
            ]);
            this.entries.set(entries);
            this.summary.set(summary);
        } catch {
            this.entries.set([]);
            this.summary.set(null);
        } finally {
            this.loading.set(false);
        }
    }

    protected async onSubmit(): Promise<void> {
        if (this.form.invalid) {
            return;
        }
        this.saving.set(true);
        const raw = this.form.getRawValue();
        try {
            await this.progressService.createEntry(this.lectureId(), {
                practicedAt: raw.practicedAt,
                note: raw.note.trim() === '' ? null : raw.note,
                tempoBpm: raw.tempoBpm !== null ? Number(raw.tempoBpm) : null,
                durationMinutes: raw.durationMinutes !== null ? Number(raw.durationMinutes) : null,
            });
            this.form.reset({practicedAt: this.today(), note: '', tempoBpm: null, durationMinutes: null});
            await this.reload();
        } catch {
            // error interceptor
        } finally {
            this.saving.set(false);
        }
    }

    protected async onDelete(entry: ProgressEntry): Promise<void> {
        const message = await this.translate.instant('app.progress.deleteConfirm') as string;
        if (!confirm(message)) {
            return;
        }
        try {
            await this.progressService.deleteEntry(entry.id);
            await this.reload();
        } catch {
            // error interceptor
        }
    }

    private today(): string {
        return new Date().toISOString().slice(0, 10);
    }
}
