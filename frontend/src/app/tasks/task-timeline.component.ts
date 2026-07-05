import {ChangeDetectionStrategy, Component, computed, effect, ElementRef, inject, input, output, signal, viewChild} from '@angular/core';
import {TaskListItem} from '@app/models/task';
import {TaskService} from '@app/services/task.service';
import {projectColor} from '@app/shared/project-color';
import {CalendarTaskFilters} from '@app/tasks/task-calendar.component';
import {TranslatePipe} from '@ngx-translate/core';

type TimelineZoom = 'week' | 'month';

interface TimelineBar {
    task: TaskListItem;
    leftPct: number;
    widthPct: number;
    overdue: boolean;
    done: boolean;
    color: string;
    // True when the bar is clipped by the visible range edge (no resize handle on a clipped side).
    clippedStart: boolean;
    clippedEnd: boolean;
}

interface TimelineGroup {
    project: string;
    color: string;
    rows: TimelineBar[];
}

interface WeekColumn {
    iso: string;
    label: string;
}

type DragMode = 'move' | 'resizeStart' | 'resizeEnd';

interface DragState {
    taskId: number;
    mode: DragMode;
    startX: number;
    dayWidthPx: number;
    origStart: string;
    origEnd: string;
    moved: boolean;
}

// Safety cap — the timeline scopes to a visible window client-side, so a single fetch covers it.
const FETCH_LIMIT = 200;

const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

// ── UTC date helpers ──
const toUtc = (iso: string): Date => new Date(`${iso}T00:00:00Z`);
const isoOf = (d: Date): string => d.toISOString().slice(0, 10);
const addDays = (iso: string, n: number): string => {
    const d = toUtc(iso);
    d.setUTCDate(d.getUTCDate() + n);
    return isoOf(d);
};
const daysBetween = (a: string, b: string): number => Math.round((toUtc(b).getTime() - toUtc(a).getTime()) / 86400000);
const mondayOf = (iso: string): string => {
    const dow = toUtc(iso).getUTCDay();
    return addDays(iso, dow === 0 ? -6 : 1 - dow);
};
const fmtDay = (iso: string): string => {
    const d = toUtc(iso);
    return `${MONTHS[d.getUTCMonth()]} ${d.getUTCDate()}`;
};

@Component({
    selector: 'uk-task-timeline',
    standalone: true,
    imports: [TranslatePipe],
    changeDetection: ChangeDetectionStrategy.OnPush,
    templateUrl: './task-timeline.component.html',
    styleUrl: './task-timeline.component.scss',
})
export class TaskTimelineComponent {
    private readonly taskService = inject(TaskService);

    public readonly filters = input.required<CalendarTaskFilters>();
    public readonly refreshKey = input<number>(0);

    public readonly openTask = output<{id: number; projectId: number}>();
    public readonly changed = output<void>();

    private readonly track = viewChild<ElementRef<HTMLElement>>('track');

    protected readonly zoom = signal<TimelineZoom>('week');
    protected readonly anchor = signal<string>(todayIso());

    protected readonly tasks = signal<TaskListItem[]>([]);
    protected readonly loading = signal<boolean>(false);
    protected readonly truncated = signal<boolean>(false);

    // Live preview of dates while a bar is being dragged/resized; overrides the persisted value.
    protected readonly drag = signal<DragState | null>(null);
    private readonly preview = signal<{taskId: number; start: string; end: string} | null>(null);

    protected readonly spanDays = computed<number>(() => (this.zoom() === 'week' ? 35 : 70));
    protected readonly rangeStart = computed<string>(() => mondayOf(this.anchor()));
    protected readonly rangeEnd = computed<string>(() => addDays(this.rangeStart(), this.spanDays() - 1));

    protected readonly weeks = computed<WeekColumn[]>(() => {
        const start = this.rangeStart();
        return Array.from({length: this.spanDays() / 7}, (_, i) => {
            const iso = addDays(start, i * 7);
            return {iso, label: fmtDay(iso)};
        });
    });

    // Per-day cells backing the gridline / weekend-shading overlay.
    protected readonly days = computed<{weekend: boolean; monday: boolean}[]>(() => {
        const start = this.rangeStart();
        return Array.from({length: this.spanDays()}, (_, i) => {
            const dow = toUtc(addDays(start, i)).getUTCDay();
            return {weekend: dow === 0 || dow === 6, monday: dow === 1};
        });
    });

    protected readonly rangeLabel = computed<string>(() => `${fmtDay(this.rangeStart())} – ${fmtDay(this.rangeEnd())}`);

    protected readonly todayLeftPct = computed<number | null>(() => {
        const today = todayIso();
        if (today < this.rangeStart() || today > this.rangeEnd()) {
            return null;
        }
        return (daysBetween(this.rangeStart(), today) + 0.5) / this.spanDays() * 100;
    });

    protected readonly groups = computed<TimelineGroup[]>(() => {
        const rangeStart = this.rangeStart();
        const rangeEnd = this.rangeEnd();
        const span = this.spanDays();
        const today = todayIso();
        const previewed = this.preview();
        const byProject = new Map<string, TimelineGroup>();

        for (const task of this.tasks()) {
            let start = task.startDate;
            let end = task.dueDate;
            if (previewed !== null && previewed.taskId === task.id) {
                start = previewed.start;
                end = previewed.end;
            }
            // Degrade to a single-day bar when only one date is set; skip undated tasks.
            const effStart = (start ?? end)?.slice(0, 10) ?? null;
            const effEnd = (end ?? start)?.slice(0, 10) ?? null;
            if (effStart === null || effEnd === null) {
                continue;
            }
            if (effStart > rangeEnd || effEnd < rangeStart) {
                continue;
            }

            const barStart = effStart < rangeStart ? rangeStart : effStart;
            const barEnd = effEnd > rangeEnd ? rangeEnd : effEnd;
            const bar: TimelineBar = {
                task,
                leftPct: daysBetween(rangeStart, barStart) / span * 100,
                widthPct: (daysBetween(barStart, barEnd) + 1) / span * 100,
                overdue: task.status.type !== 'Finish' && effEnd < today,
                done: task.status.type === 'Finish',
                color: projectColor(task.projectId),
                clippedStart: effStart < rangeStart,
                clippedEnd: effEnd > rangeEnd,
            };

            const group = byProject.get(task.projectName);
            if (group) {
                group.rows.push(bar);
            } else {
                byProject.set(task.projectName, {project: task.projectName, color: bar.color, rows: [bar]});
            }
        }

        const groups = [...byProject.values()].sort((a, b) => a.project.localeCompare(b.project));
        for (const g of groups) {
            g.rows.sort((a, b) => {
                const sa = (a.task.startDate ?? a.task.dueDate ?? '').slice(0, 10);
                const sb = (b.task.startDate ?? b.task.dueDate ?? '').slice(0, 10);
                return sa.localeCompare(sb);
            });
        }
        return groups;
    });

    public constructor() {
        effect(() => {
            const filters = this.filters();
            this.refreshKey();
            void this.fetchTasks(filters);
        });
    }

    private async fetchTasks(filters: CalendarTaskFilters): Promise<void> {
        this.loading.set(true);
        try {
            const result = await this.taskService.getTasks({
                limit: FETCH_LIMIT,
                offset: 0,
                orderBy: 'created_at',
                orderDirection: 'DESC',
                search: filters.search,
                statusIds: filters.statusIds,
                tagIds: filters.tagIds,
                assigneeIds: filters.assigneeIds,
                onlyActive: filters.onlyActive,
                subtaskFilter: filters.subtaskFilter,
                archived: filters.archived,
            });
            this.tasks.set(result.tasks);
            this.truncated.set(result.count > result.tasks.length);
        } catch {
            this.tasks.set([]);
            this.truncated.set(false);
        } finally {
            this.loading.set(false);
        }
    }

    // ── Navigation ──
    protected prev(): void {
        this.anchor.update((a) => addDays(a, this.zoom() === 'week' ? -7 : -28));
    }

    protected next(): void {
        this.anchor.update((a) => addDays(a, this.zoom() === 'week' ? 7 : 28));
    }

    protected goToday(): void {
        this.anchor.set(todayIso());
    }

    protected setZoom(zoom: TimelineZoom): void {
        this.zoom.set(zoom);
    }

    // ── Drag to move / resize ──
    protected onBarPointerDown(event: PointerEvent, bar: TimelineBar): void {
        // Left mouse / touch only.
        if (event.button !== 0) {
            return;
        }
        const trackEl = this.track()?.nativeElement;
        if (!trackEl) {
            return;
        }
        const dayWidthPx = trackEl.getBoundingClientRect().width / this.spanDays();
        if (dayWidthPx <= 0) {
            return;
        }
        // Mode from where on the bar the pointer landed: near an (unclipped) edge → resize, else move.
        const barEl = event.currentTarget as HTMLElement;
        const rect = barEl.getBoundingClientRect();
        const offsetX = event.clientX - rect.left;
        const edge = Math.min(8, rect.width / 3);
        let mode: DragMode = 'move';
        if (offsetX <= edge && !bar.clippedStart) {
            mode = 'resizeStart';
        } else if (offsetX >= rect.width - edge && !bar.clippedEnd) {
            mode = 'resizeEnd';
        }
        event.preventDefault();
        event.stopPropagation();
        barEl.setPointerCapture(event.pointerId);

        const start = (bar.task.startDate ?? bar.task.dueDate ?? '').slice(0, 10);
        const end = (bar.task.dueDate ?? bar.task.startDate ?? '').slice(0, 10);
        this.drag.set({taskId: bar.task.id, mode, startX: event.clientX, dayWidthPx, origStart: start, origEnd: end, moved: false});
    }

    protected onBarPointerMove(event: PointerEvent): void {
        const drag = this.drag();
        if (drag === null) {
            return;
        }
        const deltaDays = Math.round((event.clientX - drag.startX) / drag.dayWidthPx);
        if (deltaDays === 0 && !drag.moved) {
            return;
        }
        if (!drag.moved) {
            this.drag.set({...drag, moved: true});
        }

        let start = drag.origStart;
        let end = drag.origEnd;
        if (drag.mode === 'move') {
            start = addDays(drag.origStart, deltaDays);
            end = addDays(drag.origEnd, deltaDays);
        } else if (drag.mode === 'resizeStart') {
            start = addDays(drag.origStart, deltaDays);
            if (start > end) start = end;
        } else {
            end = addDays(drag.origEnd, deltaDays);
            if (end < start) end = start;
        }
        this.preview.set({taskId: drag.taskId, start, end});
    }

    protected async onBarPointerUp(event: PointerEvent, bar: TimelineBar): Promise<void> {
        const drag = this.drag();
        if (drag === null) {
            return;
        }
        (event.target as HTMLElement).releasePointerCapture?.(event.pointerId);
        const preview = this.preview();
        this.drag.set(null);

        // A press with no real movement is a click → open the task.
        if (!drag.moved || preview === null) {
            this.preview.set(null);
            this.openTask.emit({id: bar.task.id, projectId: bar.task.projectId});
            return;
        }
        if (preview.start === drag.origStart && preview.end === drag.origEnd) {
            this.preview.set(null);
            return;
        }
        await this.persist(bar.task, preview.start, preview.end);
        this.preview.set(null);
    }

    private async persist(task: TaskListItem, startDate: string, dueDate: string): Promise<void> {
        try {
            const full = await this.taskService.getTask(task.id);
            await this.taskService.updateTask(task.id, {
                statusId: full.statusId,
                name: full.name,
                description: full.description,
                priorityId: full.priority.id,
                dueDate,
                startDate,
                assigneeId: full.assigneeId,
                fieldValues: full.fieldValues,
                tagIds: full.tagIds,
            });
            // Reflect locally so the bar stays put before the next fetch.
            this.tasks.update((tasks) => tasks.map((t) => (t.id === task.id ? {...t, startDate, dueDate} : t)));
            this.changed.emit();
        } catch {
            // error interceptor surfaces it; bar snaps back on the next fetch.
        }
    }

    protected openTaskFor(bar: TimelineBar): void {
        this.openTask.emit({id: bar.task.id, projectId: bar.task.projectId});
    }

    protected priorityInitial(name: string): string {
        return name.slice(0, 1).toUpperCase();
    }
}

function todayIso(): string {
    const now = new Date();
    return `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}`;
}

function pad(n: number): string {
    return String(n).padStart(2, '0');
}
