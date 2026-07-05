import {CdkDrag, CdkDragDrop, CdkDropList, moveItemInArray} from '@angular/cdk/drag-drop';
import {ChangeDetectionStrategy, Component, computed, inject, OnInit, signal} from '@angular/core';
import {FormsModule} from '@angular/forms';
import {ActivatedRoute, RouterLink} from '@angular/router';
import {Board} from '@app/models/board';
import {Status, StatusType} from '@app/models/status';
import {AlertService} from '@app/services/alert.service';
import {BoardService} from '@app/services/board.service';
import {StatusService} from '@app/services/status.service';
import {TranslatePipe, TranslateService} from '@ngx-translate/core';

const DEFAULT_COLORS = ['#94a3b8', '#60a5fa', '#fbbf24', '#f87171', '#a78bfa', '#4ade80', '#34d399'];

@Component({
    selector: 'uk-workflow-editor',
    standalone: true,
    imports: [CdkDropList, CdkDrag, FormsModule, RouterLink, TranslatePipe],
    changeDetection: ChangeDetectionStrategy.OnPush,
    templateUrl: './workflow-editor.component.html',
    styleUrl: './workflow-editor.component.scss',
})
export class WorkflowEditorComponent implements OnInit {
    private readonly route = inject(ActivatedRoute);
    private readonly boardService = inject(BoardService);
    private readonly statusService = inject(StatusService);
    private readonly alertService = inject(AlertService);
    private readonly translate = inject(TranslateService);

    protected readonly board = signal<Board | null>(null);
    protected readonly statuses = computed<Status[]>(() => {
        const currentBoard = this.board();
        if (!currentBoard) return [];
        return [...currentBoard.statuses].sort((a, b) => a.position - b.position);
    });

    public async ngOnInit(): Promise<void> {
        const courseId = Number(this.route.snapshot.paramMap.get('id'));
        this.board.set(await this.boardService.getBoard(courseId));
    }

    protected async save(status: Status): Promise<void> {
        try {
            await this.statusService.updateStatus(status.id, {
                name: status.name,
                color: status.color,
                type: status.type,
            });
        } catch {
            // error interceptor
        }
    }

    protected async onReorder(event: CdkDragDrop<Status[]>): Promise<void> {
        if (event.previousIndex === event.currentIndex) {
            return;
        }
        const arr = this.statuses();
        const movedId = arr[event.previousIndex].id;
        const reordered = [...arr];
        moveItemInArray(reordered, event.previousIndex, event.currentIndex);
        reordered.forEach((s, i) => { s.position = i; });

        this.board.update((b) => b ? {...b, statuses: reordered} : b);

        try {
            await this.statusService.moveStatus(movedId, event.currentIndex);
        } catch {
            const courseId = Number(this.route.snapshot.paramMap.get('id'));
            this.board.set(await this.boardService.getBoard(courseId));
        }
    }

    protected async addStatus(): Promise<void> {
        const b = this.board();
        if (!b) return;
        const promptText = await this.translate.instant('app.workflow.namePrompt') as string;
        const name = prompt(promptText, await this.translate.instant('app.workflow.newStatusDefault') as string);
        if (!name) return;
        const color = DEFAULT_COLORS[b.statuses.length % DEFAULT_COLORS.length];
        const type: StatusType = 'Normal';

        try {
            const created = await this.statusService.createStatus(b.workflow.id, {name, color, type});
            this.board.update((current) => current ? {...current, statuses: [...current.statuses, created]} : current);
            this.alertService.success(await this.translate.instant('app.workflow.statusAdded') as string);
        } catch {
            // error interceptor
        }
    }

    protected async onDelete(status: Status): Promise<void> {
        const confirmMessage = await this.translate.instant('app.workflow.deleteStatusConfirm', {name: status.name}) as string;
        if (!confirm(confirmMessage)) {
            return;
        }
        try {
            await this.statusService.deleteStatus(status.id);
            this.board.update((current) => current ? {...current, statuses: current.statuses.filter((s) => s.id !== status.id)} : current);
            this.alertService.success(await this.translate.instant('app.workflow.statusDeleted') as string);
        } catch {
            // error interceptor
        }
    }
}
