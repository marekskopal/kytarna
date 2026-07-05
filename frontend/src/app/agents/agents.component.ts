import {DatePipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, computed, effect, inject, signal} from '@angular/core';
import {ActorType, AuditEvent, WorkspaceAgentStats} from '@app/models/event';
import {EventService} from '@app/services/event.service';
import {WorkspaceService} from '@app/services/workspace.service';
import {TranslatePipe, TranslateService} from '@ngx-translate/core';

type ChipKey = 'All' | 'Humans' | 'Agents' | 'Comments' | 'StatusChanges';

@Component({
    selector: 'uk-agents',
    standalone: true,
    imports: [DatePipe, TranslatePipe],
    changeDetection: ChangeDetectionStrategy.OnPush,
    styleUrl: './agents.component.scss',
    templateUrl: './agents.component.html',
})
export class AgentsComponent {
    private readonly eventService = inject(EventService);
    private readonly workspaceService = inject(WorkspaceService);
    private readonly translate = inject(TranslateService);

    protected readonly stats = signal<WorkspaceAgentStats | null>(null);
    protected readonly allEvents = signal<AuditEvent[]>([]);
    protected readonly loading = signal(true);
    protected readonly activeChip = signal<ChipKey>('All');

    protected readonly events = computed<AuditEvent[]>(() => {
        const chip = this.activeChip();
        const events = this.allEvents();
        switch (chip) {
            case 'Humans':
                return events.filter((e) => e.actorType === 'Human');
            case 'Agents':
                return events.filter((e) => e.actorType === 'Agent');
            case 'StatusChanges':
                return events.filter((e) => e.type === 'TaskMoved' || e.type === 'StatusUpdated');
            case 'Comments':
                return [];
            case 'All':
            default:
                return events;
        }
    });

    protected readonly chips: {key: ChipKey; label: string}[] = [
        {key: 'All', label: 'app.agents.chips.all'},
        {key: 'Humans', label: 'app.agents.chips.humans'},
        {key: 'Agents', label: 'app.agents.chips.agents'},
        {key: 'Comments', label: 'app.agents.chips.comments'},
        {key: 'StatusChanges', label: 'app.agents.chips.statusChanges'},
    ];

    public constructor() {
        effect(() => {
            const workspaceId = this.workspaceService.currentWorkspaceId();
            if (workspaceId === null) {
                return;
            }
            void this.load(workspaceId);
        });
    }

    protected setChip(chip: ChipKey): void {
        this.activeChip.set(chip);
    }

    protected actorTypeLabel(event: AuditEvent): string {
        const fallback = this.translate.instant('app.agents.deletedAuthor') as string;
        if (event.actorType === 'Agent') {
            return event.mcpClientName ?? event.mcpClientId ?? event.authorName ?? fallback;
        }
        return event.authorName ?? fallback;
    }

    protected eventVerbKey(event: AuditEvent): string {
        return `app.agents.verb.${event.type}`;
    }

    protected eventTarget(event: AuditEvent): string {
        if (event.taskCode !== null) {
            return event.taskCode;
        }
        if (event.taskId !== null) {
            return `T-${event.taskId}`;
        }
        const meta = event.metadata;
        if (typeof meta['name'] === 'string') {
            return meta['name'];
        }
        return '';
    }

    private async load(workspaceId: number): Promise<void> {
        this.loading.set(true);
        try {
            const [stats, events] = await Promise.all([
                this.eventService.getWorkspaceAgentStats(workspaceId),
                this.eventService.getWorkspaceEvents(workspaceId, null, 200, 0),
            ]);
            this.stats.set(stats);
            this.allEvents.set(events);
        } catch {
            // interceptor surfaces errors
        } finally {
            this.loading.set(false);
        }
    }

    protected isAgent(event: AuditEvent): boolean {
        return event.actorType === 'Agent';
    }

    protected agentLabel(actorType: ActorType): string {
        return actorType;
    }
}
