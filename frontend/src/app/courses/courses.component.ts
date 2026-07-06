import {ChangeDetectionStrategy, Component, computed, inject, OnInit, signal} from '@angular/core';
import {RouterLink} from '@angular/router';
import {Course} from '@app/models/course';
import {Difficulty, LectureListItem} from '@app/models/lecture';
import {ProgressEntry} from '@app/models/progress';
import {AlertService} from '@app/services/alert.service';
import {CourseService} from '@app/services/course.service';
import {LectureService} from '@app/services/lecture.service';
import {PermissionsService} from '@app/services/permissions.service';
import {ProgressService} from '@app/services/progress.service';
import {WorkspaceService} from '@app/services/workspace.service';
import {TranslatePipe, TranslateService} from '@ngx-translate/core';

// Per-course accent, cycled by position. All theme-aware tokens so the
// dots/rings retint automatically in dark mode.
const DOT_PALETTE = [
    'var(--color-accent)',
    'var(--color-warn)',
    'var(--color-success)',
    'var(--color-info)',
    'var(--color-ai)',
];
const RING_RADIUS = 18;
const RING_CIRCUMFERENCE = 2 * Math.PI * RING_RADIUS;
const MAX_MASTERED_ROWS = 6;
const LECTURE_FETCH_LIMIT = 500;

interface WorkingCard {
    id: number;
    courseId: number;
    name: string;
    courseName: string;
    tuning: string | null;
    difficulty: Difficulty | null;
    agent: boolean;
    current: number | null;
    target: number | null;
    pct: number;
}

interface CourseCard {
    id: number;
    name: string;
    description: string | null;
    dot: string;
    ringPct: number;
    ringDash: string;
    total: number;
}

interface MasteredRow {
    id: number;
    courseId: number;
    name: string;
    courseName: string;
    tuning: string | null;
    bpm: number | null;
}

@Component({
    selector: 'uk-courses',
    standalone: true,
    imports: [RouterLink, TranslatePipe],
    changeDetection: ChangeDetectionStrategy.OnPush,
    templateUrl: './courses.component.html',
    styleUrl: './courses.component.scss',
})
export class CoursesComponent implements OnInit {
    private readonly courseService = inject(CourseService);
    private readonly lectureService = inject(LectureService);
    private readonly progressService = inject(ProgressService);
    private readonly workspaceService = inject(WorkspaceService);
    private readonly permissionsService = inject(PermissionsService);
    private readonly alertService = inject(AlertService);
    private readonly translate = inject(TranslateService);

    protected readonly loading = signal(true);
    protected readonly courses = signal<Course[]>([]);
    protected readonly lectures = signal<LectureListItem[]>([]);
    protected readonly currentBpm = signal<Record<number, number | null>>({});

    protected readonly canManageCourses = computed<boolean>(() =>
        this.permissionsService.canManageCourses(this.workspaceService.currentMembers()),
    );

    protected readonly workspaceName = computed<string>(() => {
        const id = this.workspaceService.currentWorkspaceId();
        return this.workspaceService.workspaces().find((w) => w.id === id)?.name ?? '';
    });

    private readonly workingLectures = computed<LectureListItem[]>(() =>
        this.lectures()
            .filter((l) => l.status === 'Learning')
            .sort((a, b) => b.updatedAt.localeCompare(a.updatedAt)),
    );

    private readonly masteredLectures = computed<LectureListItem[]>(() =>
        this.lectures()
            .filter((l) => l.status === 'Mastered')
            .sort((a, b) => b.updatedAt.localeCompare(a.updatedAt)),
    );

    protected readonly workingCards = computed<WorkingCard[]>(() => {
        const bpm = this.currentBpm();
        return this.workingLectures().map((l) => {
            const current = bpm[l.id] ?? null;
            const target = l.targetTempoBpm;
            return {
                id: l.id,
                courseId: l.courseId,
                name: l.name,
                courseName: l.courseName,
                tuning: l.tuning,
                difficulty: l.difficulty,
                agent: l.createdByAgent,
                current,
                target,
                pct: this.bpmPct(current, target),
            };
        });
    });

    protected readonly masteredRows = computed<MasteredRow[]>(() =>
        this.masteredLectures()
            .slice(0, MAX_MASTERED_ROWS)
            .map((l) => ({
                id: l.id,
                courseId: l.courseId,
                name: l.name,
                courseName: l.courseName,
                tuning: l.tuning,
                // "Mastered" means the target tempo was reached.
                bpm: l.targetTempoBpm,
            })),
    );

    protected readonly courseCards = computed<CourseCard[]>(() => {
        const lectures = this.lectures();
        return this.courses().map((course, index) => {
            const own = lectures.filter((l) => l.courseId === course.id);
            const masteredCount = own.filter((l) => l.status === 'Mastered').length;
            const ringPct = own.length > 0 ? Math.round((masteredCount / own.length) * 100) : 0;
            return {
                id: course.id,
                name: course.name,
                description: course.description,
                dot: DOT_PALETTE[index % DOT_PALETTE.length],
                ringPct,
                ringDash: this.ringDash(ringPct),
                total: own.length,
            };
        });
    });

    protected readonly workingCount = computed<number>(() => this.workingLectures().length);
    protected readonly masteredCount = computed<number>(() => this.masteredLectures().length);

    public async ngOnInit(): Promise<void> {
        await this.reload();
    }

    private async reload(): Promise<void> {
        this.loading.set(true);
        try {
            const [courses, lectureList] = await Promise.all([
                this.courseService.getCourses(),
                this.lectureService.getLectures({
                    limit: LECTURE_FETCH_LIMIT,
                    offset: 0,
                    orderBy: 'created_at',
                    orderDirection: 'DESC',
                    onlyActive: true,
                }),
            ]);
            this.courses.set(courses);
            this.lectures.set(lectureList.lectures);
            await this.loadCurrentTempos();
        } finally {
            this.loading.set(false);
        }
    }

    // Latest recorded tempo per in-progress lecture drives the "current → target"
    // row. Fetched only for the (small) working set; failures fall back to null.
    private async loadCurrentTempos(): Promise<void> {
        const working = this.lectures().filter((l) => l.status === 'Learning');
        const pairs = await Promise.all(
            working.map(async (l): Promise<[number, number | null]> => {
                try {
                    const entries = await this.progressService.listEntries(l.id);
                    return [l.id, this.latestTempo(entries)];
                } catch {
                    return [l.id, null];
                }
            }),
        );
        this.currentBpm.set(Object.fromEntries(pairs));
    }

    private latestTempo(entries: ProgressEntry[]): number | null {
        let latest: ProgressEntry | null = null;
        for (const entry of entries) {
            if (entry.tempoBpm === null) {
                continue;
            }
            if (latest === null || entry.practicedAt.localeCompare(latest.practicedAt) > 0) {
                latest = entry;
            }
        }
        return latest?.tempoBpm ?? null;
    }

    private bpmPct(current: number | null, target: number | null): number {
        if (current === null || target === null || target <= 0) {
            return 0;
        }
        return Math.max(0, Math.min(100, Math.round((current / target) * 100)));
    }

    protected diffColor(difficulty: Difficulty | null): string {
        switch (difficulty) {
            case 'Advanced':
                return 'var(--color-accent)';
            case 'Intermediate':
                return 'var(--color-warn)';
            case 'Beginner':
                return 'var(--color-success)';
            default:
                return 'var(--color-text-subtle)';
        }
    }

    private ringDash(pct: number): string {
        const filled = (RING_CIRCUMFERENCE * pct) / 100;
        return `${filled} ${RING_CIRCUMFERENCE}`;
    }

    protected async onDelete(course: CourseCard): Promise<void> {
        const confirmMessage = await this.translate.instant('app.courses.deleteConfirm', {name: course.name}) as string;
        if (!confirm(confirmMessage)) {
            return;
        }
        try {
            await this.courseService.deleteCourse(course.id);
            this.alertService.success(await this.translate.instant('app.courses.deleted') as string);
            this.courses.update((all) => all.filter((p) => p.id !== course.id));
            this.lectures.update((all) => all.filter((l) => l.courseId !== course.id));
        } catch {
            // error interceptor
        }
    }
}
