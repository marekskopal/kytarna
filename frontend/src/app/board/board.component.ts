import {CdkDrag, CdkDragDrop, CdkDropList, CdkDropListGroup, moveItemInArray, transferArrayItem} from '@angular/cdk/drag-drop';
import {ChangeDetectionStrategy, Component, computed, inject, OnInit, signal} from '@angular/core';
import {ActivatedRoute, Router, RouterLink} from '@angular/router';
import {LectureCardComponent} from '@app/board/lecture-card.component';
import {SongCardComponent} from '@app/board/song-card.component';
import {Board} from '@app/models/board';
import {Lecture} from '@app/models/lecture';
import {Song} from '@app/models/song';
import {LearningStatus, statusColorVar} from '@app/models/status';
import {Tag} from '@app/models/tag';
import {BoardService} from '@app/services/board.service';
import {CurrentUserService} from '@app/services/current-user.service';
import {LectureService} from '@app/services/lecture.service';
import {SongService} from '@app/services/song.service';
import {TagService} from '@app/services/tag.service';
import {WorkspaceService} from '@app/services/workspace.service';
import {StatusLabelPipe} from '@app/shared/pipes/status-label.pipe';
import {TranslatePipe} from '@ngx-translate/core';

/** A card is either a lecture or a song; `id` is prefixed so cdk tracking never collides. */
type BoardCard =
    | {kind: 'lecture'; key: string; lecture: Lecture}
    | {kind: 'song'; key: string; song: Song};

interface Column {
    status: LearningStatus;
    cards: BoardCard[];
}

@Component({
    selector: 'uk-board',
    standalone: true,
    imports: [CdkDropListGroup, CdkDropList, CdkDrag, RouterLink, LectureCardComponent, SongCardComponent, TranslatePipe, StatusLabelPipe],
    changeDetection: ChangeDetectionStrategy.OnPush,
    templateUrl: './board.component.html',
    styleUrl: './board.component.scss',
})
export class BoardComponent implements OnInit {
    private readonly route = inject(ActivatedRoute);
    private readonly router = inject(Router);
    private readonly boardService = inject(BoardService);
    private readonly lectureService = inject(LectureService);
    private readonly songService = inject(SongService);
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
        return board.statuses.map((status) => ({
            status,
            cards: this.cardsForStatus(board, status),
        }));
    });

    // ─── Course header stats ────────────────────────────────────
    protected readonly ringCircumference = 2 * Math.PI * 33;

    protected readonly totalCount = computed<number>(() => {
        const board = this.board();
        return board ? board.lectures.length + board.songs.length : 0;
    });

    protected readonly masteredCount = computed<number>(() => this.countByStatus('Mastered'));

    protected readonly learningCount = computed<number>(() => this.countByStatus('Learning'));

    protected readonly masteredPercent = computed<number>(() => {
        const total = this.totalCount();
        return total === 0 ? 0 : Math.round((this.masteredCount() / total) * 100);
    });

    protected readonly ringDash = computed<string>(() => {
        const c = this.ringCircumference;
        const filled = (this.masteredPercent() / 100) * c;
        return `${filled} ${c - filled}`;
    });

    private countByStatus(status: LearningStatus): number {
        const board = this.board();
        if (!board) {
            return 0;
        }
        return board.lectures.filter((l) => l.status === status).length
            + board.songs.filter((s) => s.status === status).length;
    }

    private cardsForStatus(board: Board, status: LearningStatus): BoardCard[] {
        const lectures: BoardCard[] = board.lectures
            .filter((l) => l.status === status)
            .map((lecture) => ({kind: 'lecture' as const, key: `l-${lecture.id}`, lecture}));
        const songs: BoardCard[] = board.songs
            .filter((s) => s.status === status)
            .map((song) => ({kind: 'song' as const, key: `s-${song.id}`, song}));
        return [...lectures, ...songs].sort((a, b) => this.cardPosition(a) - this.cardPosition(b));
    }

    private cardPosition(card: BoardCard): number {
        return card.kind === 'lecture' ? card.lecture.position : card.song.position;
    }

    protected statusDotColor(status: LearningStatus): string {
        return statusColorVar(status);
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

    protected async onDrop(event: CdkDragDrop<BoardCard[]>, targetStatus: LearningStatus): Promise<void> {
        const previousArr = event.previousContainer.data;
        const currentArr = event.container.data;
        let moved: BoardCard;

        if (event.previousContainer === event.container) {
            if (event.previousIndex === event.currentIndex) {
                return;
            }
            moved = currentArr[event.previousIndex];
            moveItemInArray(currentArr, event.previousIndex, event.currentIndex);
        } else {
            moved = previousArr[event.previousIndex];
            transferArrayItem(previousArr, currentArr, event.previousIndex, event.currentIndex);
        }

        currentArr.forEach((c, i) => this.applyCardPositionStatus(c, i, targetStatus));
        previousArr.forEach((c, i) => this.applyCardPositionStatus(c, i, c.kind === 'lecture' ? c.lecture.status : c.song.status));

        this.board.update((b) => b ? {...b, lectures: [...b.lectures], songs: [...b.songs]} : b);

        try {
            if (moved.kind === 'lecture') {
                await this.lectureService.moveLecture(moved.lecture.id, targetStatus, event.currentIndex);
            } else {
                await this.songService.moveSong(moved.song.id, targetStatus, event.currentIndex);
            }
        } catch {
            await this.loadBoard();
        }
    }

    private applyCardPositionStatus(card: BoardCard, position: number, status: LearningStatus): void {
        if (card.kind === 'lecture') {
            card.lecture.position = position;
            card.lecture.status = status;
        } else {
            card.song.position = position;
            card.song.status = status;
        }
    }

    protected openCreate(status: LearningStatus): void {
        void this.router.navigate(
            ['courses', this.courseId(), 'lectures', 'new'],
            {queryParams: {status}},
        );
    }

    protected openNewSong(): void {
        void this.router.navigate(['songs', 'new'], {queryParams: {courseId: this.courseId()}});
    }

    protected openCard(card: BoardCard): void {
        if (card.kind === 'lecture') {
            void this.router.navigate(['courses', this.courseId(), 'lectures', card.lecture.id]);
        } else {
            void this.router.navigate(['songs', card.song.id]);
        }
    }
}
