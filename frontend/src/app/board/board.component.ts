import {CdkDrag, CdkDragDrop, CdkDropList, CdkDropListGroup, moveItemInArray, transferArrayItem} from '@angular/cdk/drag-drop';
import {ChangeDetectionStrategy, Component, computed, inject, OnInit, signal} from '@angular/core';
import {ActivatedRoute, Router, RouterLink} from '@angular/router';
import {LectureCardComponent} from '@app/board/lecture-card.component';
import {Board} from '@app/models/board';
import {Lecture} from '@app/models/lecture';
import {Status, StatusType} from '@app/models/status';
import {Tag} from '@app/models/tag';
import {BoardService} from '@app/services/board.service';
import {CurrentUserService} from '@app/services/current-user.service';
import {LectureService} from '@app/services/lecture.service';
import {TagService} from '@app/services/tag.service';
import {WorkspaceService} from '@app/services/workspace.service';
import {TranslatePipe} from '@ngx-translate/core';

interface Column {
    status: Status;
    lectures: Lecture[];
}

@Component({
    selector: 'uk-board',
    standalone: true,
    imports: [CdkDropListGroup, CdkDropList, CdkDrag, RouterLink, LectureCardComponent, TranslatePipe],
    changeDetection: ChangeDetectionStrategy.OnPush,
    templateUrl: './board.component.html',
    styleUrl: './board.component.scss',
})
export class BoardComponent implements OnInit {
    private readonly route = inject(ActivatedRoute);
    private readonly router = inject(Router);
    private readonly boardService = inject(BoardService);
    private readonly lectureService = inject(LectureService);
    private readonly tagService = inject(TagService);
    private readonly workspaceService = inject(WorkspaceService);
    private readonly currentUserService = inject(CurrentUserService);

    protected readonly loading = signal(true);
    protected readonly board = signal<Board | null>(null);
    protected readonly courseId = signal<number | null>(null);
    protected readonly workspaceTags = signal<Tag[]>([]);

    protected readonly columns = computed<Column[]>(() => {
        const board = this.board();
        if (!board) {
            return [];
        }
        return [...board.statuses]
            .sort((a, b) => a.position - b.position)
            .map((status) => ({
                status,
                lectures: board.lectures
                    .filter((t) => t.statusId === status.id)
                    .sort((a, b) => a.position - b.position),
            }));
    });

    // ─── Course header stats ────────────────────────────────────
    // Ring geometry (matches the design's 76px ring, r=33, 6px stroke).
    protected readonly ringCircumference = 2 * Math.PI * 33;

    protected readonly totalCount = computed<number>(() => this.board()?.lectures.length ?? 0);

    protected readonly masteredCount = computed<number>(() => this.countByStatusType('Finish'));

    protected readonly learningCount = computed<number>(() => this.countByStatusType('Normal'));

    protected readonly masteredPercent = computed<number>(() => {
        const total = this.totalCount();
        return total === 0 ? 0 : Math.round((this.masteredCount() / total) * 100);
    });

    // stroke-dasharray for the progress arc: "<filled> <remaining>".
    protected readonly ringDash = computed<string>(() => {
        const c = this.ringCircumference;
        const filled = (this.masteredPercent() / 100) * c;
        return `${filled} ${c - filled}`;
    });

    private countByStatusType(type: StatusType): number {
        const board = this.board();
        if (!board) {
            return 0;
        }
        const ids = new Set(board.statuses.filter((s) => s.type === type).map((s) => s.id));
        return board.lectures.filter((l) => ids.has(l.statusId)).length;
    }

    // Status dot color by workflow role, mapped to theme-aware tokens so it
    // flips in dark mode (backend status.color is a static hex).
    protected statusDotColor(status: Status): string {
        switch (status.type) {
            case 'Start':
                return 'var(--color-status-todo)';
            case 'Finish':
                return 'var(--color-status-done)';
            default:
                return 'var(--color-status-doing)';
        }
    }

    public async ngOnInit(): Promise<void> {
        const id = Number(this.route.snapshot.paramMap.get('id'));
        this.courseId.set(id);
        await Promise.all([
            this.loadBoard(),
            this.loadWorkspaceTags(),
        ]);

        const openLectureParam = this.route.snapshot.queryParamMap.get('openLecture');
        if (openLectureParam !== null) {
            const openId = Number(openLectureParam);
            if (Number.isFinite(openId) && openId > 0) {
                // Legacy deep link: redirect to the routed lecture page.
                void this.router.navigate(['courses', id, 'lectures', openId], {replaceUrl: true});
            }
        }
    }

    private async loadBoard(): Promise<void> {
        this.loading.set(true);
        try {
            this.board.set(await this.boardService.getBoard(this.courseId()!));
        } finally {
            this.loading.set(false);
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

    protected async onDrop(event: CdkDragDrop<Lecture[]>, targetStatus: Status): Promise<void> {
        const previousArr = event.previousContainer.data;
        const currentArr = event.container.data;
        let movedLecture: Lecture;

        if (event.previousContainer === event.container) {
            if (event.previousIndex === event.currentIndex) {
                return;
            }
            movedLecture = currentArr[event.previousIndex];
            moveItemInArray(currentArr, event.previousIndex, event.currentIndex);
        } else {
            movedLecture = previousArr[event.previousIndex];
            transferArrayItem(previousArr, currentArr, event.previousIndex, event.currentIndex);
        }

        currentArr.forEach((t, i) => { t.position = i; t.statusId = targetStatus.id; });
        previousArr.forEach((t, i) => { t.position = i; });

        this.board.update((b) => b ? {...b, lectures: [...b.lectures]} : b);

        try {
            await this.lectureService.moveLecture(movedLecture.id, targetStatus.id, event.currentIndex);
        } catch {
            await this.loadBoard();
        }
    }

    protected openCreate(status: Status): void {
        void this.router.navigate(
            ['courses', this.courseId(), 'lectures', 'new'],
            {queryParams: {status: status.id}},
        );
    }

    protected openEdit(lecture: Lecture): void {
        void this.router.navigate(['courses', this.courseId(), 'lectures', lecture.id]);
    }
}
