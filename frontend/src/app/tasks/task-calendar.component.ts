import {CdkDrag, CdkDragDrop, CdkDropList, CdkDropListGroup} from '@angular/cdk/drag-drop';
import {ChangeDetectionStrategy, Component, computed, effect, inject, input, output, signal} from '@angular/core';
import {ArchivedFilter, SubtaskFilter, TaskListItem} from '@app/models/task';
import {TaskService} from '@app/services/task.service';
import {projectColor} from '@app/shared/project-color';
import {TranslatePipe} from '@ngx-translate/core';

/**
 * Shared filter state the Calendar borrows from the Tasks shell. Mirrors the
 * list query params minus pagination + sort (the calendar fetches its own
 * date-scoped window and orders client-side by day).
 */
export interface CalendarTaskFilters {
    search: string | undefined;
    statusIds: number[] | undefined;
    tagIds: number[] | undefined;
    assigneeIds: number[] | undefined;
    onlyActive: boolean | undefined;
    subtaskFilter: SubtaskFilter | undefined;
    archived: ArchivedFilter | undefined;
}

type CalendarView = 'month' | 'week';

interface DayCell {
    iso: string;
    day: number;
    inMonth: boolean;
    isToday: boolean;
    isWeekend: boolean;
    monthLabel: string; // shown on the first row of the grid when the month rolls over
}

// The list endpoint scopes by due-date range server-side, so a single fetch covers the
// whole visible window. This cap is just a safety bound for the rare month with a huge
// number of dated tasks — exceeding it surfaces a hint rather than silently dropping rows.
const FETCH_LIMIT = 200;

// ── UTC date helpers (no tz drift; ISO yyyy-mm-dd in, ISO out) ──
const toUtc = (iso: string): Date => new Date(`${iso}T00:00:00Z`);
const isoOf = (d: Date): string => d.toISOString().slice(0, 10);
const addDays = (iso: string, n: number): string => {
    const d = toUtc(iso);
    d.setUTCDate(d.getUTCDate() + n);
    return isoOf(d);
};
const firstOfMonth = (iso: string): string => `${iso.slice(0, 7)}-01`;
const shiftMonth = (iso: string, delta: number): string => {
    const d = toUtc(iso);
    d.setUTCMonth(d.getUTCMonth() + delta, 1);
    return isoOf(d);
};

const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
const WEEKDAYS = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

@Component({
    selector: 'uk-task-calendar',
    standalone: true,
    imports: [CdkDropListGroup, CdkDropList, CdkDrag, TranslatePipe],
    changeDetection: ChangeDetectionStrategy.OnPush,
    templateUrl: './task-calendar.component.html',
    styleUrl: './task-calendar.component.scss',
})
export class TaskCalendarComponent {
    private readonly taskService = inject(TaskService);

    public readonly filters = input.required<CalendarTaskFilters>();
    // Bumped by the shell after a drawer save/delete so the calendar refetches.
    public readonly refreshKey = input<number>(0);

    public readonly openTask = output<{id: number; projectId: number}>();
    // Emitted after a drag-reschedule so the shell can refresh its own list.
    public readonly changed = output<void>();

    protected readonly weekdays = WEEKDAYS;

    protected readonly view = signal<CalendarView>('month');
    // Anchor is always a real day inside the visible range; month view snaps it to the 1st.
    protected readonly anchor = signal<string>(firstOfMonth(todayIso()));

    protected readonly tasks = signal<TaskListItem[]>([]);
    protected readonly loading = signal<boolean>(false);
    protected readonly truncated = signal<boolean>(false);

    protected readonly cells = computed<DayCell[]>(() => {
        const anchor = this.anchor();
        const today = todayIso();
        if (this.view() === 'week') {
            const weekStart = addDays(anchor, -toUtc(anchor).getUTCDay());
            return Array.from({length: 7}, (_, i) => this.buildCell(addDays(weekStart, i), anchor.slice(0, 7), today, false));
        }
        const monthKey = anchor.slice(0, 7);
        const gridStart = addDays(anchor, -toUtc(anchor).getUTCDay());
        return Array.from({length: 42}, (_, i) => this.buildCell(addDays(gridStart, i), monthKey, today, i < 7));
    });

    // Inclusive [from, to] ISO range covering every visible cell — drives the server-side due-date filter.
    protected readonly range = computed<{from: string; to: string}>(() => {
        const anchor = this.anchor();
        const start = addDays(anchor, -toUtc(anchor).getUTCDay());
        return {from: start, to: addDays(start, this.view() === 'week' ? 6 : 41)};
    });

    protected readonly byDay = computed<Map<string, TaskListItem[]>>(() => {
        const map = new Map<string, TaskListItem[]>();
        for (const task of this.tasks()) {
            if (task.dueDate === null) {
                continue;
            }
            const day = task.dueDate.slice(0, 10);
            const bucket = map.get(day);
            if (bucket) {
                bucket.push(task);
            } else {
                map.set(day, [task]);
            }
        }
        return map;
    });

    protected readonly rangeLabel = computed<string>(() => {
        const anchor = this.anchor();
        if (this.view() === 'week') {
            const start = addDays(anchor, -toUtc(anchor).getUTCDay());
            const end = addDays(start, 6);
            const s = toUtc(start);
            const e = toUtc(end);
            const endLabel = s.getUTCMonth() === e.getUTCMonth()
                ? `${e.getUTCDate()}`
                : `${MONTHS[e.getUTCMonth()]} ${e.getUTCDate()}`;
            return `${MONTHS[s.getUTCMonth()]} ${s.getUTCDate()} – ${endLabel}`;
        }
        const d = toUtc(anchor);
        return `${MONTHS[d.getUTCMonth()]} ${d.getUTCFullYear()}`;
    });

    public constructor() {
        effect(() => {
            // React to filter / visible-range / external refresh changes.
            const filters = this.filters();
            const range = this.range();
            this.refreshKey();
            void this.fetchTasks(filters, range);
        });
    }

    private buildCell(iso: string, monthKey: string, today: string, isFirstRow: boolean): DayCell {
        const d = toUtc(iso);
        const dow = d.getUTCDay();
        const inMonth = iso.slice(0, 7) === monthKey;
        return {
            iso,
            day: d.getUTCDate(),
            inMonth,
            isToday: iso === today,
            isWeekend: dow === 0 || dow === 6,
            monthLabel: isFirstRow && d.getUTCDate() <= 7 ? MONTHS[d.getUTCMonth()] : '',
        };
    }

    private async fetchTasks(filters: CalendarTaskFilters, range: {from: string; to: string}): Promise<void> {
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
                dueFrom: range.from,
                dueTo: range.to,
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

    protected tasksFor(iso: string): TaskListItem[] {
        return this.byDay().get(iso) ?? [];
    }

    protected projColor(projectId: number): string {
        return projectColor(projectId);
    }

    protected isDone(task: TaskListItem): boolean {
        return task.status.type === 'Finish';
    }

    protected isOverdue(task: TaskListItem): boolean {
        return !this.isDone(task) && task.dueDate !== null && task.dueDate.slice(0, 10) < todayIso();
    }

    // ── Navigation ──
    protected prev(): void {
        this.anchor.update((a) => (this.view() === 'week' ? addDays(a, -7) : shiftMonth(a, -1)));
    }

    protected next(): void {
        this.anchor.update((a) => (this.view() === 'week' ? addDays(a, 7) : shiftMonth(a, 1)));
    }

    protected goToday(): void {
        this.anchor.set(this.view() === 'week' ? todayIso() : firstOfMonth(todayIso()));
    }

    protected setView(view: CalendarView): void {
        // Keep the user roughly where they were: month → snap to the 1st; week → centre on today if the
        // current month isn't the live one, else keep today.
        if (view === 'month') {
            this.anchor.set(firstOfMonth(this.anchor()));
        } else if (this.view() === 'month') {
            const today = todayIso();
            this.anchor.set(today.slice(0, 7) === this.anchor().slice(0, 7) ? today : this.anchor());
        }
        this.view.set(view);
    }

    protected showDay(iso: string): void {
        this.anchor.set(iso);
        this.view.set('week');
    }

    protected onChipClick(task: TaskListItem): void {
        this.openTask.emit({id: task.id, projectId: task.projectId});
    }

    // ── Drag to reschedule ──
    protected async onDrop(event: CdkDragDrop<string>): Promise<void> {
        const targetIso = event.container.data;
        const task = event.item.data as TaskListItem;
        if (task.dueDate !== null && task.dueDate.slice(0, 10) === targetIso) {
            return;
        }

        // Optimistic: move the chip immediately, revert on failure.
        const previous = task.dueDate;
        this.patchDueDate(task.id, targetIso);
        try {
            const full = await this.taskService.getTask(task.id);
            await this.taskService.updateTask(task.id, {
                statusId: full.statusId,
                name: full.name,
                description: full.description,
                priorityId: full.priority.id,
                dueDate: targetIso,
                // Preserve the existing start date — the update endpoint treats an omitted
                // startDate as a clear, which would silently drop a Timeline-spanning task's start.
                startDate: full.startDate,
                assigneeId: full.assigneeId,
                fieldValues: full.fieldValues,
                tagIds: full.tagIds,
            });
            this.changed.emit();
        } catch {
            this.patchDueDate(task.id, previous);
        }
    }

    private patchDueDate(taskId: number, dueDate: string | null): void {
        this.tasks.update((tasks) => tasks.map((t) => (t.id === taskId ? {...t, dueDate} : t)));
    }
}

function todayIso(): string {
    const now = new Date();
    return `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}`;
}

function pad(n: number): string {
    return String(n).padStart(2, '0');
}
