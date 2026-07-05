import {ChangeDetectionStrategy, Component, computed, inject, input, OnInit, signal} from '@angular/core';
import {FormBuilder, ReactiveFormsModule, Validators} from '@angular/forms';
import {PracticeSummary, ProgressEntry} from '@app/models/progress';
import {ProgressService} from '@app/services/progress.service';
import {TranslatePipe, TranslateService} from '@ngx-translate/core';

interface SparkPoint {
    x: number;
    y: number;
    last: boolean;
}

interface Sparkline {
    points: string;
    area: string;
    dots: SparkPoint[];
    targetY: number | null;
    min: number;
    max: number;
    width: number;
    height: number;
}

const SPARK_W = 300;
const SPARK_H = 96;
const SPARK_PAD = 12;

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
    /** Target tempo (from the lecture) drawn as a dashed goal line on the sparkline. */
    public readonly targetBpm = input<number | null>(null);

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
        const target = this.targetBpm();
        const min = Math.min(...values);
        const max = Math.max(...values);
        // Include the target in the drawn range so the goal line is always visible.
        const domainMin = target !== null ? Math.min(min, target) : min;
        const domainMax = target !== null ? Math.max(max, target) : max;
        const span = domainMax - domainMin || 1;
        const step = SPARK_W / (trend.length - 1);
        const y = (v: number): number => SPARK_PAD + (1 - (v - domainMin) / span) * (SPARK_H - 2 * SPARK_PAD);
        const dots: SparkPoint[] = trend.map((p, i) => ({
            x: Number((i * step).toFixed(1)),
            y: Number(y(p.tempoBpm).toFixed(1)),
            last: i === trend.length - 1,
        }));
        const points = dots.map((d) => `${d.x},${d.y}`).join(' ');
        const baseline = SPARK_H - SPARK_PAD;
        const area = `M${dots[0].x},${dots[0].y} `
            + dots.slice(1).map((d) => `L${d.x},${d.y}`).join(' ')
            + ` L${dots[dots.length - 1].x},${baseline} L${dots[0].x},${baseline} Z`;
        const targetY = target !== null ? Number(y(target).toFixed(1)) : null;
        return {points, area, dots, targetY, min, max, width: SPARK_W, height: SPARK_H};
    });

    /** Latest logged tempo, or null when nothing has been logged. */
    protected readonly currentBpm = computed<number | null>(() => {
        const trend = this.summary()?.bpmTrend ?? [];
        return trend.length > 0 ? trend[trend.length - 1].tempoBpm : null;
    });

    /** BPM gained from the first to the latest logged session (positive = improving). */
    protected readonly bpmGain = computed<number | null>(() => {
        const trend = this.summary()?.bpmTrend ?? [];
        if (trend.length < 2) {
            return null;
        }
        return trend[trend.length - 1].tempoBpm - trend[0].tempoBpm;
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
