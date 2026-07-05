import {CdkDrag, CdkDragDrop, CdkDropList, CdkDropListGroup, moveItemInArray, transferArrayItem} from '@angular/cdk/drag-drop';
import {ChangeDetectionStrategy, Component, computed, inject, OnInit, signal} from '@angular/core';
import {ActivatedRoute, RouterLink} from '@angular/router';
import {LectureCardComponent} from '@app/board/lecture-card.component';
import {LectureDetailDrawerComponent} from '@app/board/lecture-detail-drawer.component';
import {Board} from '@app/models/board';
import {Lecture} from '@app/models/lecture';
import {Status} from '@app/models/status';
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
    imports: [CdkDropListGroup, CdkDropList, CdkDrag, RouterLink, LectureCardComponent, LectureDetailDrawerComponent, TranslatePipe],
    changeDetection: ChangeDetectionStrategy.OnPush,
    templateUrl: './board.component.html',
    styleUrl: './board.component.scss',
})
export class BoardComponent implements OnInit {
    private readonly route = inject(ActivatedRoute);
    private readonly boardService = inject(BoardService);
    private readonly lectureService = inject(LectureService);
    private readonly tagService = inject(TagService);
    private readonly workspaceService = inject(WorkspaceService);
    private readonly currentUserService = inject(CurrentUserService);

    protected readonly loading = signal(true);
    protected readonly board = signal<Board | null>(null);
    protected readonly courseId = signal<number | null>(null);
    protected readonly workspaceTags = signal<Tag[]>([]);

    protected readonly drawerOpen = signal(false);
    protected readonly editingLecture = signal<Lecture | null>(null);
    protected readonly defaultStatusId = signal<number | null>(null);

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
                try {
                    const lecture = await this.lectureService.getLecture(openId);
                    this.editingLecture.set(lecture);
                    this.defaultStatusId.set(null);
                    this.drawerOpen.set(true);
                } catch {
                    // lecture may have been deleted; ignore
                }
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
        this.editingLecture.set(null);
        this.defaultStatusId.set(status.id);
        this.drawerOpen.set(true);
    }

    protected openEdit(lecture: Lecture): void {
        this.editingLecture.set(lecture);
        this.defaultStatusId.set(null);
        this.drawerOpen.set(true);
    }

    protected closeDrawer(): void {
        this.drawerOpen.set(false);
        this.editingLecture.set(null);
    }

    protected onLectureSaved(_lecture: Lecture): void {
        this.closeDrawer();
        void this.loadBoard();
    }

    protected onLectureDeleted(_id: number): void {
        this.closeDrawer();
        void this.loadBoard();
    }
}
